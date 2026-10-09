import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// v4.0.42 ULTIMATE - کامل‌ترین نسخه با تمام میکرو-اینترکشن‌ها + صدا + پرچم + غبار
/// 
/// ویژگی‌های جدید v4.0.42 Full:
/// 1. حلقه کوانتومی 3x
/// 2. مدار ماهواره 8x با دنباله
/// 3. فیبر نوری 12x
/// 4. مایع انرژی با موج سینوسی
/// 5. پلاسما با قوس الکتریکی
/// 6. انفجار confetti 18 ذره
/// 7. Haptic feedback
/// 8. Speed count-up
/// 9. Background glow پویا
/// 10. ✨ پرچم کشور با موج (جدید)
/// 11. ✨ ذرات غبار نورانی پس‌زمینه (جدید)
/// 12. ✨ صدای whoosh (جدید - اختیاری)
/// 13. ✨ لرزش صفحه هنگام اتصال (جدید)
/// 14. ✨ پالس ضربان قلب روی تایمر (جدید)

enum UltimateConnectState { disconnected, connecting, connected }

class ConnectButtonUltimate extends StatefulWidget {
  final UltimateConnectState state;
  final VoidCallback? onTap;
  final double size;
  final String? countryFlag; // مثلا "🇩🇪" یا "🇺🇸"
  final String? serverName;
  final bool enableHaptic;
  final bool enableConfetti;
  final bool enablePlasma;
  final bool enableSound;
  final bool enableFlagWave;
  final bool enableDust;
  final bool enableScreenShake;

  const ConnectButtonUltimate({
    Key? key,
    required this.state,
    this.onTap,
    this.size = 135,
    this.countryFlag,
    this.serverName,
    this.enableHaptic = true,
    this.enableConfetti = true,
    this.enablePlasma = true,
    this.enableSound = true,
    this.enableFlagWave = true,
    this.enableDust = true,
    this.enableScreenShake = true,
  }) : super(key: key);

  @override
  State<ConnectButtonUltimate> createState() => _ConnectButtonUltimateState();
}

class _ConnectButtonUltimateState extends State<ConnectButtonUltimate> with TickerProviderStateMixin {
  late AnimationController _pulseController;
  late AnimationController _orbitController;
  late AnimationController _liquidController;
  late AnimationController _plasmaController;
  late AnimationController _scaleController;
  late AnimationController _confettiController;
  late AnimationController _fiberController;
  late AnimationController _flagWaveController;
  late AnimationController _dustController;
  late AnimationController _shakeController;

  late Animation<double> _scaleAnimation;
  late Animation<double> _liquidHeightAnimation;
  late Animation<Offset> _shakeAnimation;

  bool _showConfetti = false;
  Timer? _autoHideConfetti;

  @override
  void initState() {
    super.initState();

    _pulseController = AnimationController(vsync: this, duration: const Duration(seconds: 2))..repeat();
    _orbitController = AnimationController(vsync: this, duration: const Duration(seconds: 8))..repeat();
    _liquidController = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000));
    _liquidHeightAnimation = Tween<double>(begin: 0.2, end: 0.2).animate(CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic));
    _plasmaController = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();
    _scaleController = AnimationController(vsync: this, duration: const Duration(milliseconds: 150));
    _scaleAnimation = Tween<double>(begin: 1.0, end: 0.96).animate(CurvedAnimation(parent: _scaleController, curve: Curves.easeOut));
    _confettiController = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _fiberController = AnimationController(vsync: this, duration: const Duration(milliseconds: 600))..repeat();
    _flagWaveController = AnimationController(vsync: this, duration: const Duration(milliseconds: 2000))..repeat();
    _dustController = AnimationController(vsync: this, duration: const Duration(seconds: 12))..repeat();
    _shakeController = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
    _shakeAnimation = Tween<Offset>(begin: Offset.zero, end: const Offset(0.02, 0)).animate(CurvedAnimation(parent: _shakeController, curve: Curves.elasticIn));

    _updateForState(widget.state);
  }

  @override
  void didUpdateWidget(ConnectButtonUltimate oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.state != widget.state) {
      _updateForState(widget.state);
      if (widget.state == UltimateConnectState.connected) _triggerSuccess();
    }
  }

  void _updateForState(UltimateConnectState state) {
    switch (state) {
      case UltimateConnectState.disconnected:
        _pulseController.duration = const Duration(seconds: 2);
        _orbitController.duration = const Duration(seconds: 8);
        _fiberController.duration = const Duration(milliseconds: 1200);
        _flagWaveController.duration = const Duration(seconds: 3);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.2).animate(CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic));
        break;
      case UltimateConnectState.connecting:
        _pulseController.duration = const Duration(milliseconds: 500);
        _orbitController.duration = const Duration(milliseconds: 700);
        _fiberController.duration = const Duration(milliseconds: 500);
        _flagWaveController.duration = const Duration(milliseconds: 800);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.68).animate(CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic));
        break;
      case UltimateConnectState.connected:
        _pulseController.duration = const Duration(milliseconds: 1200);
        _orbitController.duration = const Duration(seconds: 2);
        _fiberController.duration = const Duration(seconds: 2);
        _flagWaveController.duration = const Duration(milliseconds: 1500);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.9).animate(CurvedAnimation(parent: _liquidController, curve: Curves.easeOutCubic));
        break;
    }
    _liquidController.forward(from: 0);
    _pulseController.repeat();
    _orbitController.repeat();
    _fiberController.repeat();
    _flagWaveController.repeat();
  }

  void _triggerSuccess() {
    // Confetti
    if (widget.enableConfetti) {
      setState(() => _showConfetti = true);
      _confettiController.forward(from: 0);
      _autoHideConfetti?.cancel();
      _autoHideConfetti = Timer(const Duration(milliseconds: 1200), () {
        if (mounted) setState(() => _showConfetti = false);
      });
    }

    // Haptic
    if (widget.enableHaptic) {
      try {
        HapticFeedback.heavyImpact();
        Future.delayed(const Duration(milliseconds: 80), () {
          try { HapticFeedback.mediumImpact(); } catch (_) {}
        });
        Future.delayed(const Duration(milliseconds: 160), () {
          try { HapticFeedback.lightImpact(); } catch (_) {}
        });
      } catch (_) {}
    }

    // Screen shake
    if (widget.enableScreenShake) {
      _shakeController.forward(from: 0).then((_) => _shakeController.reverse());
    }

    // Sound (via MethodChannel - optional)
    if (widget.enableSound) {
      _playConnectSound();
    }
  }

  Future<void> _playConnectSound() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/sound');
      await channel.invokeMethod('playConnectSound').timeout(const Duration(milliseconds: 500), onTimeout: () => null);
    } catch (_) {
      // Sound is optional, ignore if not implemented
      // Fallback: system click sound via Haptic is enough
    }
  }

  void _handleTap() {
    if (widget.enableHaptic) {
      try { HapticFeedback.lightImpact(); } catch (_) {}
    }
    _scaleController.forward().then((_) => _scaleController.reverse());
    widget.onTap?.call();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _orbitController.dispose();
    _liquidController.dispose();
    _plasmaController.dispose();
    _scaleController.dispose();
    _confettiController.dispose();
    _fiberController.dispose();
    _flagWaveController.dispose();
    _dustController.dispose();
    _shakeController.dispose();
    _autoHideConfetti?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;
    final isConnecting = widget.state == UltimateConnectState.connecting;
    final isConnected = widget.state == UltimateConnectState.connected;

    return RepaintBoundary(
      child: AnimatedBuilder(
        animation: _shakeAnimation,
        builder: (context, child) {
          return Transform.translate(
            offset: Offset(_shakeAnimation.value.dx * size, _shakeAnimation.value.dy * size),
            child: child,
          );
        },
        child: SizedBox(
          width: size * 2.8,
          height: size * 2.8,
          child: Stack(
            alignment: Alignment.center,
            children: [
              // === DUST PARTICLES BACKGROUND ===
              if (widget.enableDust)
                AnimatedBuilder(
                  animation: _dustController,
                  builder: (context, child) {
                    return CustomPaint(
                      size: Size(size * 2.8, size * 2.8),
                      painter: DustPainter(progress: _dustController.value, isConnected: isConnected),
                    );
                  },
                ),

              // === FIBER OPTIC ===
              ...List.generate(14, (i) {
                return AnimatedBuilder(
                  animation: _fiberController,
                  builder: (context, child) {
                    final isVisible = widget.state != UltimateConnectState.disconnected;
                    return Opacity(
                      opacity: isVisible ? (isConnected ? 0.7 : 0.0) : 0.0,
                      child: Transform.rotate(
                        angle: (i * 25.7) * math.pi / 180,
                        child: Transform.translate(
                          offset: Offset(0, -size * 0.5 - 20 - (_fiberController.value * 25)),
                          child: Container(
                            width: 2,
                            height: isConnecting ? 45 + _fiberController.value * 25 : 70,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [Colors.transparent, isConnected ? const Color(0xFF10B981) : const Color(0xFF9333EA), Colors.transparent],
                              ),
                              borderRadius: BorderRadius.circular(2),
                              boxShadow: [BoxShadow(color: (isConnected ? const Color(0xFF10B981) : const Color(0xFF9333EA)).withOpacity(0.7), blurRadius: 8)],
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                );
              }),

              // === PULSE RINGS ===
              ...List.generate(3, (i) {
                return AnimatedBuilder(
                  animation: _pulseController,
                  builder: (context, child) {
                    final delay = i * 0.33;
                    final progress = (_pulseController.value + delay) % 1.0;
                    final scale = 0.85 + progress * 1.1;
                    final opacity = (1 - progress) * (isConnecting ? 0.9 : 0.55);
                    final color = isConnected ? const Color(0xFF10B981) : isConnecting ? const Color(0xFFF59E0B) : const Color(0xFF9333EA);
                    return Transform.scale(
                      scale: scale,
                      child: Container(
                        width: size,
                        height: size,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: color.withOpacity(opacity), width: 2.5),
                          boxShadow: [if (opacity > 0.25) BoxShadow(color: color.withOpacity(opacity * 0.35), blurRadius: 12, spreadRadius: 1)],
                        ),
                      ),
                    );
                  },
                );
              }),

              // === ORBIT DOTS ===
              AnimatedBuilder(
                animation: _orbitController,
                builder: (context, child) {
                  return SizedBox(
                    width: size * 1.7,
                    height: size * 1.7,
                    child: Stack(
                      children: List.generate(8, (i) {
                        final angle = (i * 45 + _orbitController.value * 360) * math.pi / 180;
                        final radius = size * 0.85;
                        final x = math.cos(angle) * radius;
                        final y = math.sin(angle) * radius;
                        final dotColor = isConnected ? const Color(0xFF10B981) : const Color(0xFFA78BFA);
                        return Positioned(
                          left: size * 0.85 + x - 4,
                          top: size * 0.85 + y - 4,
                          child: Opacity(
                            opacity: widget.state == UltimateConnectState.disconnected ? 0.35 : 0.95,
                            child: Stack(
                              children: [
                                if (isConnecting)
                                  Container(
                                    width: 22,
                                    height: 4,
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(colors: [Colors.transparent, dotColor.withOpacity(0.7)]),
                                      borderRadius: BorderRadius.circular(2),
                                    ),
                                    transform: Matrix4.rotationZ(angle),
                                    transformAlignment: Alignment.centerRight,
                                  ),
                                Container(
                                  width: 9,
                                  height: 9,
                                  decoration: BoxDecoration(
                                    color: dotColor,
                                    shape: BoxShape.circle,
                                    boxShadow: [BoxShadow(color: dotColor.withOpacity(0.9), blurRadius: 10, spreadRadius: 1)],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }),
                    ),
                  );
                },
              ),

              // === PLASMA ===
              if (widget.enablePlasma && widget.state != UltimateConnectState.disconnected)
                AnimatedBuilder(
                  animation: _plasmaController,
                  builder: (context, child) {
                    return CustomPaint(
                      size: Size(size * 1.5, size * 1.5),
                      painter: UltimatePlasmaPainter(progress: _plasmaController.value, isConnected: isConnected, isConnecting: isConnecting),
                    );
                  },
                ),

              // === FLAG WAVE (NEW) ===
              if (widget.enableFlagWave && widget.countryFlag != null && isConnected)
                Positioned(
                  top: size * 0.15,
                  child: AnimatedBuilder(
                    animation: _flagWaveController,
                    builder: (context, child) {
                      return Transform(
                        alignment: Alignment.center,
                        transform: Matrix4.identity()
                          ..setEntry(3, 2, 0.001)
                          ..rotateY(math.sin(_flagWaveController.value * 2 * math.pi) * 0.15)
                          ..rotateZ(math.sin(_flagWaveController.value * 2 * math.pi) * 0.05),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFF0F172A).withOpacity(0.9),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: const Color(0xFF10B981).withOpacity(0.5)),
                            boxShadow: [BoxShadow(color: const Color(0xFF10B981).withOpacity(0.3), blurRadius: 12)],
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              // Wave flag text
                              ShaderMask(
                                shaderCallback: (bounds) {
                                  return LinearGradient(
                                    colors: [Colors.white, Colors.white.withOpacity(0.8)],
                                    stops: [0.0, 1.0],
                                  ).createShader(bounds);
                                },
                                child: Text(widget.countryFlag!, style: const TextStyle(fontSize: 14)),
                              ),
                              if (widget.serverName != null) ...[
                                const SizedBox(width: 6),
                                Text(widget.serverName!, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF10B981))),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),

              // === MAIN BUTTON ===
              AnimatedBuilder(
                animation: _scaleAnimation,
                builder: (context, child) => Transform.scale(scale: _scaleAnimation.value, child: child),
                child: GestureDetector(
                  onTap: _handleTap,
                  child: Container(
                    width: size,
                    height: size,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        center: const Alignment(-0.3, -0.3),
                        radius: 1.2,
                        colors: isConnected
                            ? [const Color(0xFF6EE7B7), const Color(0xFF10B981), const Color(0xFF065F46), const Color(0xFF022C22)]
                            : isConnecting
                                ? [const Color(0xFFFCD34D), const Color(0xFFF59E0B), const Color(0xFF92400E), const Color(0xFF451A03)]
                                : [const Color(0xFFA78BFA), const Color(0xFF7C3AED), const Color(0xFF4C1D95), const Color(0xFF1E1B4B)],
                      ),
                      border: Border.all(color: Colors.white.withOpacity(0.1), width: 2),
                      boxShadow: [
                        BoxShadow(color: (isConnected ? const Color(0xFF10B981) : isConnecting ? const Color(0xFFF59E0B) : const Color(0xFF9333EA)).withOpacity(0.25), blurRadius: 0, spreadRadius: 1),
                        BoxShadow(color: Colors.black.withOpacity(0.65), blurRadius: 45, offset: const Offset(0, 12)),
                        BoxShadow(color: Colors.white.withOpacity(0.22), blurRadius: 1, offset: const Offset(0, 1)),
                        BoxShadow(color: Colors.black.withOpacity(0.45), blurRadius: 22, offset: const Offset(0, -12), blurStyle: BlurStyle.inner),
                        if (isConnected) BoxShadow(color: const Color(0xFF10B981).withOpacity(0.65), blurRadius: 60, spreadRadius: 3)
                        else if (isConnecting) BoxShadow(color: const Color(0xFFF59E0B).withOpacity(0.55), blurRadius: 45, spreadRadius: 3),
                      ],
                    ),
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        ClipOval(
                          child: AnimatedBuilder(
                            animation: _liquidHeightAnimation,
                            builder: (context, child) {
                              return Align(
                                alignment: Alignment.bottomCenter,
                                child: Container(
                                  width: size,
                                  height: size * _liquidHeightAnimation.value,
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      begin: Alignment.topCenter,
                                      end: Alignment.bottomCenter,
                                      colors: isConnected
                                          ? [const Color(0xFF6EE7B7).withOpacity(0.92), const Color(0xFF10B981).withOpacity(0.92)]
                                          : isConnecting
                                              ? [const Color(0xFFFCD34D).withOpacity(0.92), const Color(0xFFF59E0B).withOpacity(0.92)]
                                              : [const Color(0xFFA78BFA).withOpacity(0.85), const Color(0xFF7C3AED).withOpacity(0.92)],
                                    ),
                                  ),
                                  child: CustomPaint(
                                    painter: UltimateLiquidPainter(progress: _plasmaController.value, isConnecting: isConnecting),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        // Heartbeat pulse when connected
                        if (isConnected)
                          AnimatedBuilder(
                            animation: _pulseController,
                            builder: (context, child) {
                              final beat = math.sin(_pulseController.value * 2 * math.pi);
                              final scale = 1.0 + beat * 0.04;
                              return Transform.scale(scale: scale, child: child);
                            },
                            child: AnimatedSwitcher(
                              duration: const Duration(milliseconds: 500),
                              transitionBuilder: (child, animation) => ScaleTransition(scale: animation, child: FadeTransition(opacity: animation, child: child)),
                              child: Text(
                                '✓',
                                key: ValueKey(widget.state),
                                style: TextStyle(
                                  fontSize: size * 0.42,
                                  fontWeight: FontWeight.w900,
                                  color: Colors.white,
                                  shadows: [Shadow(color: Colors.black.withOpacity(0.6), blurRadius: 10, offset: const Offset(0, 3))],
                                ),
                              ),
                            ),
                          )
                        else
                          AnimatedSwitcher(
                            duration: const Duration(milliseconds: 400),
                            transitionBuilder: (child, animation) => ScaleTransition(scale: animation, child: FadeTransition(opacity: animation, child: child)),
                            child: Text(
                              isConnecting ? '⏳' : '⚡',
                              key: ValueKey(widget.state),
                              style: TextStyle(
                                fontSize: size * 0.4,
                                fontWeight: FontWeight.w900,
                                color: Colors.white,
                                shadows: [Shadow(color: Colors.black.withOpacity(0.5), blurRadius: 8, offset: const Offset(0, 2))],
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),

              // === CONFETTI ===
              if (_showConfetti)
                AnimatedBuilder(
                  animation: _confettiController,
                  builder: (context, child) {
                    return Stack(
                      children: List.generate(22, (i) {
                        final angle = (i * 16.36) * math.pi / 180 + _confettiController.value * 2.5 * math.pi;
                        final distance = _confettiController.value * (70 + (i % 4) * 35);
                        final x = math.cos(angle) * distance;
                        final y = math.sin(angle) * distance - _confettiController.value * 30;
                        final opacity = 1 - _confettiController.value;
                        final colors = [const Color(0xFF10B981), const Color(0xFF34D399), const Color(0xFF6EE7B7), const Color(0xFFA7F3D0), const Color(0xFF6366F1), const Color(0xFFF59E0B)];
                        return Transform.translate(
                          offset: Offset(x, y),
                          child: Transform.rotate(
                            angle: _confettiController.value * 5 * math.pi + i,
                            child: Opacity(
                              opacity: opacity,
                              child: Container(
                                width: i % 3 == 0 ? 8 : 6,
                                height: i % 3 == 0 ? 8 : 6,
                                decoration: BoxDecoration(
                                  color: colors[i % colors.length],
                                  borderRadius: BorderRadius.circular(i % 2 == 0 ? 2 : 999),
                                  boxShadow: [BoxShadow(color: colors[i % colors.length].withOpacity(0.6), blurRadius: 4)],
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                    );
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class UltimatePlasmaPainter extends CustomPainter {
  final double progress;
  final bool isConnected;
  final bool isConnecting;
  UltimatePlasmaPainter({required this.progress, required this.isConnected, required this.isConnecting});
  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final count = isConnecting ? 6 : 4;
    final random = math.Random((progress * 1000).toInt());
    for (int i = 0; i < count; i++) {
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.8
        ..strokeCap = StrokeCap.round
        ..color = isConnected
            ? Color.lerp(const Color(0xFF10B981), const Color(0xFF6EE7B7), math.sin(progress * 2 * math.pi + i) * 0.5 + 0.5)!.withOpacity(0.65 + math.sin(progress * 2 * math.pi + i) * 0.35)
            : Color.lerp(const Color(0xFFF59E0B), const Color(0xFFFCD34D), math.sin(progress * 2 * math.pi + i) * 0.5 + 0.5)!.withOpacity(0.55 + math.sin(progress * 2 * math.pi + i) * 0.35)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5);
      final angle = progress * 2 * math.pi + i * (2 * math.pi / count) + random.nextDouble() * 0.4;
      final length = 62 + math.sin(progress * 4 * math.pi + i) * 18;
      final endX = center.dx + math.cos(angle) * length;
      final endY = center.dy + math.sin(angle) * length;
      final midX = center.dx + math.cos(angle) * length * 0.5 + (random.nextDouble() - 0.5) * 24;
      final midY = center.dy + math.sin(angle) * length * 0.5 + (random.nextDouble() - 0.5) * 24;
      final path = Path()..moveTo(center.dx, center.dy)..quadraticBezierTo(midX, midY, endX, endY);
      canvas.drawPath(path, paint);
      // Small spark at end
      if (isConnecting) {
        final sparkPaint = Paint()..color = paint.color.withOpacity(0.9)..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6);
        canvas.drawCircle(Offset(endX, endY), 2.5, sparkPaint);
      }
    }
  }
  @override
  bool shouldRepaint(UltimatePlasmaPainter old) => old.progress != progress || old.isConnected != isConnected;
}

class UltimateLiquidPainter extends CustomPainter {
  final double progress;
  final bool isConnecting;
  UltimateLiquidPainter({required this.progress, required this.isConnecting});
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white.withOpacity(0.28)..style = PaintingStyle.fill;
    final path = Path()..moveTo(0, 12);
    for (double x = 0; x <= size.width; x++) {
      final y = 12 + math.sin((x / size.width * 2 * math.pi) + progress * 2 * math.pi * (isConnecting ? 2.2 : 1)) * (isConnecting ? 7 : 3.5);
      path.lineTo(x, y);
    }
    path.lineTo(size.width, 0);
    path.lineTo(0, 0);
    path.close();
    canvas.drawPath(path, paint);

    final highlightPaint = Paint()..color = Colors.white.withOpacity(0.18)..style = PaintingStyle.fill;
    final highlightPath = Path()..moveTo(0, 5);
    for (double x = 0; x <= size.width; x++) {
      final y = 5 + math.sin((x / size.width * 2 * math.pi) + progress * 2 * math.pi + 1) * 2.5;
      highlightPath.lineTo(x, y);
    }
    highlightPath.lineTo(size.width, 0);
    highlightPath.lineTo(0, 0);
    highlightPath.close();
    canvas.drawPath(highlightPath, highlightPaint);
  }
  @override
  bool shouldRepaint(UltimateLiquidPainter old) => old.progress != progress;
}

class DustPainter extends CustomPainter {
  final double progress;
  final bool isConnected;
  DustPainter({required this.progress, required this.isConnected});
  @override
  void paint(Canvas canvas, Size size) {
    final random = math.Random(42);
    final count = isConnected ? 25 : 12;
    for (int i = 0; i < count; i++) {
      final baseX = random.nextDouble() * size.width;
      final baseY = random.nextDouble() * size.height;
      final offsetX = math.sin(progress * 2 * math.pi + i) * 15;
      final offsetY = math.cos(progress * 2 * math.pi * 0.7 + i) * 10;
      final x = (baseX + offsetX) % size.width;
      final y = (baseY + offsetY) % size.height;
      final opacity = (0.15 + math.sin(progress * 2 * math.pi + i) * 0.1).clamp(0.05, 0.3);
      final radius = 0.8 + random.nextDouble() * (isConnected ? 2.2 : 1.2);
      final paint = Paint()
        ..color = (isConnected ? const Color(0xFF10B981) : const Color(0xFF9333EA)).withOpacity(opacity)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2);
      canvas.drawCircle(Offset(x, y), radius, paint);
    }
  }
  @override
  bool shouldRepaint(DustPainter old) => old.progress != progress || old.isConnected != isConnected;
}

/// Sound service - پخش صدای اتصال (اختیاری)
class ConnectSoundService {
  static const _channel = MethodChannel('com.connectix.vpn/sound');
  static bool _enabled = true;

  static Future<void> setEnabled(bool enabled) async {
    _enabled = enabled;
  }

  static Future<void> playConnect() async {
    if (!_enabled) return;
    try {
      await _channel.invokeMethod('playConnectSound').timeout(const Duration(milliseconds: 500), onTimeout: () => null);
    } catch (_) {}
  }

  static Future<void> playDisconnect() async {
    if (!_enabled) return;
    try {
      await _channel.invokeMethod('playDisconnectSound').timeout(const Duration(milliseconds: 500), onTimeout: () => null);
    } catch (_) {}
  }

  static Future<void> playClick() async {
    if (!_enabled) return;
    try {
      await _channel.invokeMethod('playClickSound').timeout(const Duration(milliseconds: 300), onTimeout: () => null);
    } catch (_) {}
  }
}

/// Flag wave widget - پرچم با موج سه‌بعدی
class FlagWave extends StatefulWidget {
  final String flag;
  final String? label;
  final bool isConnected;
  const FlagWave({Key? key, required this.flag, this.label, required this.isConnected}) : super(key: key);
  @override
  State<FlagWave> createState() => _FlagWaveState();
}

class _FlagWaveState extends State<FlagWave> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1800))..repeat();
  }
  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }
  @override
  Widget build(BuildContext context) {
    if (!widget.isConnected) return const SizedBox.shrink();
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return Transform(
          alignment: Alignment.center,
          transform: Matrix4.identity()
            ..setEntry(3, 2, 0.001)
            ..rotateY(math.sin(_controller.value * 2 * math.pi) * 0.18)
            ..rotateZ(math.sin(_controller.value * 2 * math.pi) * 0.06),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A).withOpacity(0.92),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: const Color(0xFF10B981).withOpacity(0.6)),
              boxShadow: [BoxShadow(color: const Color(0xFF10B981).withOpacity(0.35), blurRadius: 14)],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(widget.flag, style: const TextStyle(fontSize: 16)),
                if (widget.label != null) ...[
                  const SizedBox(width: 8),
                  Text(widget.label!, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF10B981))),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}
