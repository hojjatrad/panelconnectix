import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'models/client_model.dart';
import 'models/server_model.dart';
import 'screens/dashboard_screen.dart';
import 'screens/login_screen.dart';
import 'services/api_service.dart';
import 'services/v2ray_compat.dart';

// ---------------- Global crash capture ----------------
// 3.3.3: crashes used to be swallowed by debugPrint (invisible in release)
// and the app would just "close" with no trace. Now:
//   1. Any Dart-level error shows a crash screen with the message and a
//      one-tap «send technical report» button.
//   2. Every cold start writes a session marker; if the previous session
//      died without a clean-exit marker (native crash / process kill), the
//      next launch auto-sends a diagnostic report to support.
class CrashInfo {
  final String kind;
  final String error;
  final String stack;
  final String at;
  CrashInfo(this.kind, this.error, this.stack)
      : at = DateTime.now().toIso8601String();
  String get summary {
    final lines = stack.split('\n').take(10).join('\n');
    return 'نوع: $kind\nزمان: $at\nخطا: $error\n\n$lines';
  }
}

final StreamController<CrashInfo> _crashController =
    StreamController<CrashInfo>.broadcast();

void _captureCrash(String kind, Object error, StackTrace stack) {
  final info = CrashInfo(kind, '$error', stack.toString());
  try {
    debugPrint('=== CRASH CAPTURED ===\n$info.summary');
    _crashController.add(info);
  } catch (_) {}
  try {
    unawaited(SharedPreferences.getInstance().then((p) async {
      await p.setString('last_crash_info', info.summary);
      await p.setString('last_crash_at', info.at);
    }).catchError((_) {}));
  } catch (_) {}
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    _captureCrash(
        'FlutterError', details.exceptionAsString(), details.stack ?? StackTrace.empty);
  };
  PlatformDispatcher.instance.onError = (error, stack) {
    _captureCrash('UncaughtError', error, stack);
    return true; // handled — the crash screen takes over instead of dying
  };

  runZonedGuarded(() async {
    try {
      await ApiService.initBaseUrl();
    } catch (_) {}
    runApp(const ConnectixApp());
  }, (error, stack) {
    _captureCrash('ZoneError', error, stack);
  });
}

/// 3.3.5: reports a NATIVE (pre-Flutter) crash captured by
/// ConnectixApplication's uncaught-exception handler. The report file
/// survives process death; MainActivity exposes it over the updater channel.
/// Shows the crash screen (so the user can re-send manually) and auto-sends
/// each distinct crash once.
Future<void> _autoReportNativeCrash() async {
  try {
    final channel = MethodChannel('com.connectix.vpn/updater');
    final report = await channel.invokeMethod('getLastCrashReport');
    final text = (report ?? '').toString().trim();
    if (text.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    // Distinct-crash marker: the "Time: ..." line of the report.
    final timeLine = text.split('\n').firstWhere(
        (l) => l.startsWith('Time:'),
        orElse: () => text.substring(0, text.length.clamp(0, 60)));
    final reported = prefs.getString('last_native_crash_reported') ?? '';
    if (timeLine == reported) return;

    final os = Platform.operatingSystem.toUpperCase() + ' ' + Platform.version;
    final ok = await ApiService.sendFeedback(
      subject: 'کرش بومی (Native Crash — قبل از شروع Flutter)',
      message: '$text\n\nدستگاه: $os',
      version: DashboardScreen.currentAppVersion,
    );
    if (ok) {
      await prefs.setString('last_native_crash_reported', timeLine);
    }
    // Surface the crash so the user sees details + a manual send button.
    _crashController.add(CrashInfo(
      'NativeCrash',
      'خطا در لایه اندروید (قبل از آماده‌شدن برنامه)',
      text,
    ));
  } catch (_) {}
}

/// Auto-reports a previous abnormal exit (native crash / killed process)
/// once per 24h, fire-and-forget, never throws.
Future<void> _autoReportPreviousCrash() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final startedAt = prefs.getString('session_started_at') ?? '';
    final cleanExitAt = prefs.getString('session_clean_exit_at') ?? '';
    final reportedAt = prefs.getString('last_crash_reported_at') ?? '';
    if (startedAt.isEmpty) return;
    // Abnormal exit: a session started but never marked a clean exit.
    if (startedAt.compareTo(cleanExitAt) <= 0) return;
    // Report at most once per 24h for the same incident.
    if (reportedAt.compareTo(startedAt) >= 0) return;
    final ageOk = DateTime.now().difference(DateTime.parse(startedAt)) <=
        const Duration(hours: 24);
    if (!ageOk) return;

    final saved = prefs.getString('last_crash_info') ?? '';
    final os = Platform.operatingSystem.toUpperCase() + ' ' + Platform.version;
    final ok = await ApiService.sendFeedback(
      subject: 'بازکردن برنامه پس از کرش احتمالی',
      message: 'برنامه در بارگذاری قبل (${startedAt}) به‌طور ناگهانی بسته شد.'
          ' در این بازگشت، خطا در Dart مشاهده نشد — احتمالاً کرش سطح native است.'
          '\n\nدستگاه: $os'
          '${saved.isEmpty ? '' : '\n\nآخرین خطای ثبت‌شده:\n$saved'}',
      version: DashboardScreen.currentAppVersion,
    );
    if (ok) {
      await prefs.setString('last_crash_reported_at', startedAt);
    }
  } catch (_) {}
}

/// Session lifecycle markers (detect "app died without clean exit").
class _SessionMarker extends StatefulWidget {
  final Widget child;
  const _SessionMarker({required this.child});

  @override
  State<_SessionMarker> createState() => _SessionMarkerState();
}

class _SessionMarkerState extends State<_SessionMarker>
    with WidgetsBindingObserver {
  bool _started = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _start();
  }

  Future<void> _start() async {
    if (_started) return;
    _started = true;
    try {
    final prefs = await SharedPreferences.getInstance();
    await _autoReportNativeCrash();
    await _autoReportPreviousCrash();
    await prefs.setString('session_started_at', DateTime.now().toIso8601String());
    } catch (_) {}
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.detached) {
      try {
        unawaited(SharedPreferences.getInstance().then((p) async {
          await p.setString(
              'session_clean_exit_at', DateTime.now().toIso8601String());
        }).catchError((_) {}));
        // Windows: restore the system proxy + stop the Xray core on exit.
        unawaited(V2RayCompat().shutdown().catchError((_) {}));
      } catch (_) {}
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class ConnectixApp extends StatefulWidget {
  const ConnectixApp({Key? key}) : super(key: key);

  @override
  State<ConnectixApp> createState() => _ConnectixAppState();
}

/// Brand palette (shared by every screen).
class C {
  static const Color seed = Color(0xFF9333EA); // purple
  static const Color accent = Color(0xFF6366F1); // indigo
  static const Color ok = Color(0xFF10B981); // green
  static const Color warn = Color(0xFFF59E0B); // amber
  static const Color danger = Color(0xFFF87171); // red
  static const Color bg = Color(0xFF090D16);
  static const Color surface = Color(0xFF111827);
  static const Color surface2 = Color(0xFF0F172A);
  static const Color border = Color(0xFF1E293B);
  static const Color textMain = Color(0xFFF1F5F9);
  static const Color textDim = Color(0xFF94A3B8);
}

/// App-wide Material 3 dark theme with the Vazirmatn Persian font.
/// NOTE: only uses theme APIs that exist in BOTH Flutter 3.22 (Android CI)
/// and Flutter 3.47 (Windows CI).
ThemeData buildConnectixTheme() {
  final base = ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: C.bg,
    fontFamily: 'Vazirmatn',
    colorScheme: const ColorScheme.dark(
      primary: C.seed,
      onPrimary: Colors.white,
      secondary: C.accent,
      onSecondary: Colors.white,
      surface: C.surface,
      onSurface: C.textMain,
      onSurfaceVariant: C.textDim,
      outline: C.border,
      error: C.danger,
    ),
  );
  return base.copyWith(
    appBarTheme: const AppBarTheme(
      backgroundColor: C.bg,
      elevation: 0,
      centerTitle: true,
    ),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: const Color(0xFF1E293B),
      contentTextStyle: const TextStyle(color: C.textMain, fontSize: 13),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: C.seed,
        foregroundColor: Colors.white,
        disabledBackgroundColor: const Color(0xFF334155),
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: C.textMain,
        side: const BorderSide(color: C.border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: C.textDim),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: C.surface2,
      hintStyle: const TextStyle(color: C.textDim, fontSize: 13),
      contentPadding:
          const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: C.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: C.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: C.seed, width: 1.4),
      ),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: C.seed),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? Colors.white
              : C.textDim),
      trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? C.seed
              : const Color(0xFF334155)),
    ),
    textTheme: base.textTheme.apply(
      bodyColor: C.textMain,
      displayColor: C.textMain,
    ),
    iconTheme: const IconThemeData(color: C.textDim),
    dividerColor: C.border,
  );
}

class _ConnectixAppState extends State<ConnectixApp> {
  /// Timestamp of the last crash the user dismissed/sent — the StreamBuilder
  /// keeps its last snapshot, so we compare by timestamp to dismiss it.
  String? _dismissedCrashAt;

  void _dismiss(CrashInfo info) {
    if (mounted) setState(() => _dismissedCrashAt = info.at);
  }

  @override
  Widget build(BuildContext context) {
    return _SessionMarker(
      child: StreamBuilder<CrashInfo>(
        stream: _crashController.stream,
        builder: (ctx, snap) {
          final crash = snap.data;
          if (crash != null && crash.at != _dismissedCrashAt) {
            return MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: buildConnectixTheme(),
              home: _CrashScreen(
                info: crash,
                onDismiss: () => _dismiss(crash),
              ),
            );
          }
          return MaterialApp(
            title: 'Connectix VPN',
            debugShowCheckedModeBanner: false,
            theme: buildConnectixTheme(),
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: const [
              Locale('fa', 'IR'),
            ],
            locale: const Locale('fa', 'IR'),
            home: const SplashScreen(),
          );
        },
      ),
    );
  }
}

class _CrashScreen extends StatefulWidget {
  final CrashInfo info;
  final VoidCallback onDismiss;
  const _CrashScreen({required this.info, required this.onDismiss});

  @override
  State<_CrashScreen> createState() => _CrashScreenState();
}

class _CrashScreenState extends State<_CrashScreen> {
  bool _sending = false;
  bool _sent = false;
  bool _sendFailed = false;

  Future<void> _send() async {
    if (_sending || _sent) return;
    setState(() {
      _sending = true;
      _sendFailed = false;
    });
    final ok = await ApiService.sendFeedback(
      subject: 'گزارش کرش برنامه (Crash Report)',
      message: widget.info.summary,
      version: DashboardScreen.currentAppVersion,
    );
    if (!mounted) return;
    setState(() {
      _sending = false;
      _sent = ok;
      _sendFailed = !ok;
    });
  }

  @override
  Widget build(BuildContext context) {
    final info = widget.info;
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                _sent ? Icons.check_circle_rounded : Icons.warning_amber_rounded,
                color: _sent ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                size: 64,
              ),
              const SizedBox(height: 16),
              Text(
                _sent
                    ? 'گزارش فنی ارسال شد'
                    : 'متأسفانه برنامه با خطایی روبرو شد',
                style: const TextStyle(
                    color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                _sent
                    ? 'با تشکر! تیم پشتیبانی گزارش شما را بررسی و مشکل را رفع می‌کند. می‌توانید به برنامه برگردید.'
                    : 'خطا ثبت شد و می‌توانید آن را برای پشتیبانی بفرستید تا سریع رفع شود.',
                textAlign: TextAlign.center,
                style: TextStyle(
                    color: _sent ? const Color(0xFF10B981) : Color(0xFF94A3B8),
                    fontSize: 13, height: 1.7),
              ),
              if (_sendFailed)
                const Text(
                  'ارسال گزارش ممکن نشد (اتصال؟). دوباره تلاش کنید.',
                  style: TextStyle(color: Color(0xFFF87171), fontSize: 12),
                ),
              const SizedBox(height: 20),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF111827),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF334155)),
                  ),
                  child: SingleChildScrollView(
                    child: SelectableText(
                      info.summary,
                      style: const TextStyle(
                          color: Color(0xFFE2E8F0), fontSize: 11, height: 1.6),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              if (!_sent)
                ElevatedButton.icon(
                  onPressed: _sending ? null : _send,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF6366F1),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  icon: _sending
                      ? const SizedBox(
                          width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.report_problem_rounded, size: 18),
                  label: Text(
                    _sending ? 'در حال ارسال...' : 'ارسال گزارش فنی به پشتیبانی',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => widget.onDismiss(),
                child: const Text('بازگشت به برنامه',
                    style: TextStyle(color: Color(0xFF64748B))),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class SplashScreen extends StatefulWidget {
  const SplashScreen({Key? key}) : super(key: key);

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkSavedSessionAndNavigate();
  }

  void _checkSavedSessionAndNavigate() async {
    // Brief animation delay for visual smoothness
    await Future.delayed(const Duration(milliseconds: 350));

    final session = await ApiService.checkSavedSession();
    if (!mounted) return;

    if (session != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => DashboardScreen(
            client: session['client'],
            branding: session['branding'],
          ),
        ),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF9333EA), Color(0xFF6366F1)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(26),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF9333EA).withOpacity(0.4),
                    blurRadius: 30,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: const Icon(Icons.shield_rounded, color: Colors.white, size: 48),
            ),
            const SizedBox(height: 24),
            const Text(
              'Connectix VPN',
              style: TextStyle(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'در حال بارگذاری نشست امن...',
              style: TextStyle(
                color: Color(0xFF94A3B8),
                fontSize: 12,
              ),
            ),
            const SizedBox(height: 36),
            const SizedBox(
              width: 26,
              height: 26,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: Color(0xFF9333EA),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
