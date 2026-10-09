import 'package:flutter/material.dart';
import 'connect_button_v2.dart';

/// Demo page برای تست دکمه جدید v4.0.42
/// مسیر: lib/widgets/connect_button_demo.dart
/// برای تست: Navigator.push(context, MaterialPageRoute(builder: (_) => ConnectButtonDemo()))

class ConnectButtonDemo extends StatefulWidget {
  const ConnectButtonDemo({Key? key}) : super(key: key);

  @override
  State<ConnectButtonDemo> createState() => _ConnectButtonDemoState();
}

class _ConnectButtonDemoState extends State<ConnectButtonDemo> {
  ConnectButtonState _state = ConnectButtonState.disconnected;
  double _downSpeed = 0;
  double _upSpeed = 0;
  int _ping = 0;

  void _cycleState() {
    setState(() {
      if (_state == ConnectButtonState.disconnected) {
        _state = ConnectButtonState.connecting;
        // شبیه‌سازی اتصال 2.5 ثانیه‌ای
        Future.delayed(const Duration(milliseconds: 2500), () {
          if (mounted) {
            setState(() {
              _state = ConnectButtonState.connected;
              _downSpeed = 48.2;
              _upSpeed = 12.4;
              _ping = 42;
            });
            _startSpeedSimulation();
          }
        });
      } else if (_state == ConnectButtonState.connected) {
        _state = ConnectButtonState.disconnected;
        _downSpeed = 0;
        _upSpeed = 0;
        _ping = 0;
      } else {
        _state = ConnectButtonState.connected;
      }
    });
  }

  void _startSpeedSimulation() {
    // شبیه‌سازی تغییر سرعت هر 800ms
    Future.doWhile(() async {
      await Future.delayed(const Duration(milliseconds: 800));
      if (!mounted || _state != ConnectButtonState.connected) return false;
      setState(() {
        _downSpeed = 40 + (DateTime.now().millisecond % 150) / 10;
        _upSpeed = 10 + (DateTime.now().millisecond % 50) / 10;
        _ping = 35 + (DateTime.now().millisecond % 15);
      });
      return true;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        title: const Text('دمو دکمه اتصال v4.0.42', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
        centerTitle: true,
      ),
      body: ConnectBackground(
        state: _state,
        child: Column(
          children: [
            // افکت انتخاب
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _buildStateChip('قطع', ConnectButtonState.disconnected),
                  const SizedBox(width: 8),
                  _buildStateChip('در حال اتصال', ConnectButtonState.connecting),
                  const SizedBox(width: 8),
                  _buildStateChip('متصل', ConnectButtonState.connected),
                ],
              ),
            ),

            const Spacer(),

            // دکمه اصلی
            ConnectButtonV2(
              state: _state,
              size: 140,
              enableHaptic: true,
              enableConfetti: true,
              enablePlasma: true,
              onTap: _cycleState,
            ),

            const SizedBox(height: 24),

            // وضعیت
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 400),
              child: Column(
                key: ValueKey(_state),
                children: [
                  Text(
                    _state == ConnectButtonState.disconnected
                        ? 'آماده اتصال'
                        : _state == ConnectButtonState.connecting
                            ? 'در حال اتصال...'
                            : 'متصل شد!',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: _state == ConnectButtonState.connected
                          ? const Color(0xFF10B981)
                          : _state == ConnectButtonState.connecting
                              ? const Color(0xFFF59E0B)
                              : const Color(0xFF94A3B8),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _state == ConnectButtonState.disconnected
                        ? 'برای اتصال ضربه بزنید'
                        : _state == ConnectButtonState.connecting
                            ? 'برقراری تونل امن'
                            : 'تونل امن برقرار است',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // سرعت - فقط وقتی متصل
            AnimatedOpacity(
              opacity: _state == ConnectButtonState.connected ? 1 : 0,
              duration: const Duration(milliseconds: 400),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _buildSpeedChip('↓', _downSpeed, ' Mbps', const Color(0xFF10B981)),
                  const SizedBox(width: 8),
                  _buildSpeedChip('↑', _upSpeed, ' Mbps', const Color(0xFF38BDF8)),
                  const SizedBox(width: 8),
                  _buildSpeedChip('⏱', _ping.toDouble(), ' ms', const Color(0xFFF59E0B), isInt: true),
                ],
              ),
            ),

            const Spacer(),

            // توضیحات
            Container(
              margin: const EdgeInsets.all(16),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF1E1B4B), Color(0xFF312E81)]),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFF6366F1).withOpacity(0.3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.auto_awesome, color: Color(0xFFA5B4FC), size: 16),
                      SizedBox(width: 8),
                      Text('میکرو-اینترکشن‌های فعال', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.white)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  _buildFeature('✓ حلقه‌های کوانتومی تپنده (3 حلقه با تاخیر)'),
                  _buildFeature('✓ مدار ماهواره‌ای 8 نقطه با دنباله نورانی'),
                  _buildFeature('✓ فیبر نوری 12 خط به بیرون'),
                  _buildFeature('✓ مایع انرژی با موج سینوسی'),
                  _buildFeature('✓ پلاسما با قوس الکتریکی'),
                  _buildFeature('✓ انفجار confetti سبز هنگام اتصال'),
                  _buildFeature('✓ Haptic: لرزش لمس + موفقیت'),
                  _buildFeature('✓ Speed count-up انیمیشن'),
                  _buildFeature('✓ پس‌زمینه با گرادیان پویا'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStateChip(String label, ConnectButtonState state) {
    final isActive = _state == state;
    Color bgColor;
    if (state == ConnectButtonState.disconnected) bgColor = isActive ? const Color(0xFF334155) : const Color(0xFF0F172A);
    if (state == ConnectButtonState.connecting) bgColor = isActive ? const Color(0xFFF59E0B) : const Color(0xFF0F172A);
    if (state == ConnectButtonState.connected) bgColor = isActive ? const Color(0xFF10B981) : const Color(0xFF0F172A);

    return GestureDetector(
      onTap: () => setState(() => _state = state),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: isActive ? Colors.transparent : const Color(0xFF1E293B)),
          boxShadow: isActive ? [BoxShadow(color: bgColor.withOpacity(0.4), blurRadius: 12)] : null,
        ),
        child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: isActive ? (state == ConnectButtonState.connecting ? Colors.black : Colors.white) : const Color(0xFF94A3B8))),
      ),
    );
  }

  Widget _buildSpeedChip(String icon, double value, String suffix, Color color, {bool isInt = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFF1E293B)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(icon, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
          const SizedBox(width: 4),
          isInt
              ? Text('${value.toInt()}$suffix', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: color))
              : SpeedCountUp(value: value, suffix: suffix),
        ],
      ),
    );
  }

  Widget _buildFeature(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Text(text, style: const TextStyle(fontSize: 10, color: Color(0xFFCBD5E1), height: 1.4)),
    );
  }
}
