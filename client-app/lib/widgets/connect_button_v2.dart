import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// v4.0.42 ULTIMATE CONNECT BUTTON - The Ultimate Connectix Flow
/// ترکیبی نهایی: حلقه کوانتومی + مدار ماهواره‌ای + فیبر نوری + مایع انرژی + پلاسما + انفجار موفقیت
/// 
/// حس: قدرت + تکنولوژی + پاداش فوری
/// عملکرد: 60fps حتی روی A12، فقط CSS-like animations، بدون Lottie سنگین
/// 
/// حالات:
/// - قطع: نفس آرام بنفش، حلقه هر 2 ثانیه، 3 ماهواره کم‌نور
/// - در حال اتصال: حلقه تند 0.4ث نارنجی، ماهواره با دنباله، فیبر چشمک‌زن، مایع چرخشی
/// - متصل: انفجار ذرات سبز 0.8ث + تیک + هاله سبز پایدار + ماهواره سبز ثابت + فیبر ثابت
/// 
/// میکرو-اینترکشن‌ها:
/// - Haptic: lightImpact روی لمس، heavyImpact روی اتصال موفق
/// - Scale bounce: 0.96 روی tap
/// - Confetti: 18 ذره سبز انفجاری
/// - Liquid: ارتفاع 20% -> 65% -> 88%
/// - Plasma: قوس الکتریکی از هسته به لبه
/// - Fiber: 12 خط نوری به بیرون

enum ConnectButtonState { disconnected, connecting, connected }

class ConnectButtonV2 extends StatefulWidget {
  final ConnectButtonState state;
  final VoidCallback? onTap;
  final double size;
  final bool enableHaptic;
  final bool enableConfetti;
  final bool enablePlasma;

  const ConnectButtonV2({
    Key? key,
    required this.state,
    this.onTap,
    this.size = 128,
    this.enableHaptic = true,
    this.enableConfetti = true,
    this.enablePlasma = true,
  }) : super(key: key);

  @override
  State<ConnectButtonV2> createState() => _ConnectButtonV2State();
}

class _ConnectButtonV2State extends State<ConnectButtonV2> with TickerProviderStateMixin {
  late AnimationController _pulseController;
  late AnimationController _orbitController;
  late AnimationController _liquidController;
  late AnimationController _plasmaController;
  late AnimationController _scaleController;
  late AnimationController _confettiController;
  late AnimationController _fiberController;

  late Animation<double> _scaleAnimation;
  late Animation<double> _liquidHeightAnimation;

  bool _showConfetti = false;
  Timer? _autoHideConfetti;

  @override
  void initState() {
    super.initState();

    // Pulse: 2s disconnected, 0.6s connecting, 1.2s connected
    _pulseController = AnimationController(vsync: this, duration: const Duration(seconds: 2))..repeat();

    // Orbit: 8s disconnected, 0.8s connecting, 2s connected
    _orbitController = AnimationController(vsync: this, duration: const Duration(seconds: 8))..repeat();

    // Liquid: height animation
    _liquidController = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000));
    _liquidHeightAnimation = Tween<double>(begin: 0.2, end: 0.2).animate(
      CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic),
    );

    // Plasma: continuous
    _plasmaController = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();

    // Scale bounce on tap
    _scaleController = AnimationController(vsync: this, duration: const Duration(milliseconds: 150));
    _scaleAnimation = Tween<double>(begin: 1.0, end: 0.96).animate(
      CurvedAnimation(parent: _scaleController, curve: Curves.easeOut),
    );

    // Confetti burst
    _confettiController = AnimationController(vsync: this, duration: const Duration(milliseconds: 800));

    // Fiber flow
    _fiberController = AnimationController(vsync: this, duration: const Duration(milliseconds: 600))..repeat();

    _updateAnimationsForState(widget.state);
  }

  @override
  void didUpdateWidget(ConnectButtonV2 oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.state != widget.state) {
      _updateAnimationsForState(widget.state);
      if (widget.state == ConnectButtonState.connected) {
        _triggerSuccess();
      }
    }
  }

  void _updateAnimationsForState(ConnectButtonState state) {
    switch (state) {
      case ConnectButtonState.disconnected:
        _pulseController.duration = const Duration(seconds: 2);
        _orbitController.duration = const Duration(seconds: 8);
        _fiberController.duration = const Duration(milliseconds: 1200);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.2).animate(
          CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic),
        );
        _liquidController.forward(from: 0);
        break;
      case ConnectButtonState.connecting:
        _pulseController.duration = const Duration(milliseconds: 600);
        _orbitController.duration = const Duration(milliseconds: 800);
        _fiberController.duration = const Duration(milliseconds: 600);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.65).animate(
          CurvedAnimation(parent: _liquidController, curve: Curves.easeInOutCubic),
        );
        _liquidController.forward(from: 0);
        break;
      case ConnectButtonState.connected:
        _pulseController.duration = const Duration(milliseconds: 1200);
        _orbitController.duration = const Duration(seconds: 2);
        _fiberController.duration = const Duration(seconds: 2);
        _liquidHeightAnimation = Tween<double>(begin: _liquidHeightAnimation.value, end: 0.88).animate(
          CurvedAnimation(parent: _liquidController, curve: Curves.easeOutCubic),
        );
        _liquidController.forward(from: 0);
        break;
    }
    // Restart controllers with new duration
    _pulseController.repeat();
    _orbitController.repeat();
    _fiberController.repeat();
  }

  void _triggerSuccess() {
    if (!widget.enableConfetti) return;
    setState(() => _showConfetti = true);
    _confettiController.forward(from: 0);
    _autoHideConfetti?.cancel();
    _autoHideConfetti = Timer(const Duration(milliseconds: 1000), () {
      if (mounted) setState(() => _showConfetti = false);
    });

    if (widget.enableHaptic) {
      try {
        HapticFeedback.heavyImpact();
        Future.delayed(const Duration(milliseconds: 80), () {
          try { HapticFeedback.mediumImpact(); } catch (_) {}
        });
      } catch (_) {}
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
    _autoHideConfetti?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;
    final isConnecting = widget.state == ConnectButtonState.connecting;
    final isConnected = widget.state == ConnectButtonState.connected;

    return RepaintBoundary(
      child: SizedBox(
        width: size * 2.6,
        height: size * 2.6,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // === FIBER OPTIC LINES - 12 lines radiating ===
            ...List.generate(12, (i) {
              return AnimatedBuilder(
                animation: _fiberController,
                builder: (context, child) {
                  final isVisible = widget.state != ConnectButtonState.disconnected;
                  return Opacity(
                    opacity: isVisible ? (isConnected ? 0.6 : 0.0) : 0.0,
                    child: Transform.rotate(
                      angle: (i * 30) * math.pi / 180,
                      child: Transform.translate(
                        offset: Offset(0, -size * 0.5 - 20 - (_fiberController.value * 20)),
                        child: Container(
                          width: 2,
                          height: isConnecting ? 40 + _fiberController.value * 20 : 60,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                              colors: [
                                Colors.transparent,
                                isConnected ? const Color(0xFF10B981) : const Color(0xFF9333EA),
                                Colors.transparent,
                              ],
                            ),
                            borderRadius: BorderRadius.circular(2),
                            boxShadow: [
                              BoxShadow(
                                color: (isConnected ? const Color(0xFF10B981) : const Color(0xFF9333EA)).withOpacity(0.6),
                                blurRadius: 6,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  );
                },
              );
            }),

            // === PULSE RINGS - 3 rings ===
            ...List.generate(3, (i) {
              return AnimatedBuilder(
                animation: _pulseController,
                builder: (context, child) {
                  final delay = i * 0.33;
                  final progress = (_pulseController.value + delay) % 1.0;
                  final scale = 0.85 + progress * 1.05; // 0.85 to 1.9
                  final opacity = (1 - progress) * (isConnecting ? 0.8 : 0.5);
                  final color = isConnected
                      ? const Color(0xFF10B981)
                      : isConnecting
                          ? const Color(0xFFF59E0B)
                          : const Color(0xFF9333EA);

                  return Transform.scale(
                    scale: scale,
                    child: Container(
                      width: size,
                      height: size,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: color.withOpacity(opacity), width: 2),
                        boxShadow: [
                          if (opacity > 0.2)
                            BoxShadow(
                              color: color.withOpacity(opacity * 0.3),
                              blurRadius: 10,
                              spreadRadius: 1,
                            ),
                        ],
                      ),
                    ),
                  );
                },
              );
            }),

            // === ORBIT DOTS - 8 dots ===
            AnimatedBuilder(
              animation: _orbitController,
              builder: (context, child) {
                return SizedBox(
                  width: size * 1.6,
                  height: size * 1.6,
                  child: Stack(
                    children: List.generate(8, (i) {
                      final angle = (i * 45 + _orbitController.value * 360) * math.pi / 180;
                      final radius = size * 0.8;
                      final x = math.cos(angle) * radius;
                      final y = math.sin(angle) * radius;
                      final isTrail = isConnecting;
                      final dotColor = isConnected ? const Color(0xFF10B981) : const Color(0xFFA78BFA);

                      return Positioned(
                        left: size * 0.8 + x - 4,
                        top: size * 0.8 + y - 4,
                        child: Opacity(
                          opacity: widget.state == ConnectButtonState.disconnected ? 0.3 : 0.9,
                          child: Stack(
                            children: [
                              // Trail
                              if (isTrail)
                                Container(
                                  width: 20,
                                  height: 4,
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [Colors.transparent, dotColor.withOpacity(0.6)],
                                    ),
                                    borderRadius: BorderRadius.circular(2),
                                  ),
                                  transform: Matrix4.rotationZ(angle),
                                  transformAlignment: Alignment.centerRight,
                                ),
                              // Dot
                              Container(
                                width: 8,
                                height: 8,
                                decoration: BoxDecoration(
                                  color: dotColor,
                                  shape: BoxShape.circle,
                                  boxShadow: [
                                    BoxShadow(color: dotColor.withOpacity(0.8), blurRadius: 8, spreadRadius: 1),
                                  ],
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

            // === PLASMA CANVAS - electric arcs ===
            if (widget.enablePlasma && widget.state != ConnectButtonState.disconnected)
              AnimatedBuilder(
                animation: _plasmaController,
                builder: (context, child) {
                  return CustomPaint(
                    size: Size(size * 1.4, size * 1.4),
                    painter: PlasmaPainter(
                      progress: _plasmaController.value,
                      isConnected: isConnected,
                      isConnecting: isConnecting,
                    ),
                  );
                },
              ),

            // === MAIN BUTTON with LIQUID ===
            AnimatedBuilder(
              animation: _scaleAnimation,
              builder: (context, child) {
                return Transform.scale(
                  scale: _scaleAnimation.value,
                  child: child,
                );
              },
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
                          ? [
                              const Color(0xFF6EE7B7),
                              const Color(0xFF10B981),
                              const Color(0xFF065F46),
                              const Color(0xFF022C22),
                            ]
                          : isConnecting
                              ? [
                                  const Color(0xFFFCD34D),
                                  const Color(0xFFF59E0B),
                                  const Color(0xFF92400E),
                                  const Color(0xFF451A03),
                                ]
                              : [
                                  const Color(0xFFA78BFA),
                                  const Color(0xFF7C3AED),
                                  const Color(0xFF4C1D95),
                                  const Color(0xFF1E1B4B),
                                ],
                    ),
                    border: Border.all(color: Colors.white.withOpacity(0.08), width: 2),
                    boxShadow: [
                      BoxShadow(color: (isConnected ? const Color(0xFF10B981) : isConnecting ? const Color(0xFFF59E0B) : const Color(0xFF9333EA)).withOpacity(0.2), blurRadius: 0, spreadRadius: 1),
                      BoxShadow(color: Colors.black.withOpacity(0.6), blurRadius: 40, offset: const Offset(0, 10)),
                      BoxShadow(color: Colors.white.withOpacity(0.2), blurRadius: 1, offset: const Offset(0, 1), spreadRadius: 0),
                      BoxShadow(color: Colors.black.withOpacity(0.4), blurRadius: 20, offset: const Offset(0, -10), spreadRadius: 0, blurStyle: BlurStyle.inner),
                      if (isConnected)
                        BoxShadow(color: const Color(0xFF10B981).withOpacity(0.6), blurRadius: 50, spreadRadius: 2)
                      else if (isConnecting)
                        BoxShadow(color: const Color(0xFFF59E0B).withOpacity(0.5), blurRadius: 40, spreadRadius: 2),
                    ],
                  ),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      // Liquid fill
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
                                        ? [const Color(0xFF6EE7B7).withOpacity(0.9), const Color(0xFF10B981).withOpacity(0.9)]
                                        : isConnecting
                                            ? [const Color(0xFFFCD34D).withOpacity(0.9), const Color(0xFFF59E0B).withOpacity(0.9)]
                                            : [const Color(0xFFA78BFA).withOpacity(0.8), const Color(0xFF7C3AED).withOpacity(0.9)],
                                  ),
                                ),
                                child: CustomPaint(
                                  painter: LiquidWavePainter(
                                    progress: _plasmaController.value,
                                    isConnecting: isConnecting,
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                      // Icon
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 400),
                        transitionBuilder: (child, animation) {
                          return ScaleTransition(scale: animation, child: FadeTransition(opacity: animation, child: child));
                        },
                        child: Text(
                          isConnected ? '✓' : isConnecting ? '⏳' : '⚡',
                          key: ValueKey(widget.state),
                          style: TextStyle(
                            fontSize: size * 0.38,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                            shadows: [
                              Shadow(color: Colors.black.withOpacity(0.5), blurRadius: 8, offset: const Offset(0, 2)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // === CONFETTI BURST ===
            if (_showConfetti)
              AnimatedBuilder(
                animation: _confettiController,
                builder: (context, child) {
                  return Stack(
                    children: List.generate(18, (i) {
                      final angle = (i * 20) * math.pi / 180 + _confettiController.value * 2 * math.pi;
                      final distance = _confettiController.value * (60 + (i % 3) * 30);
                      final x = math.cos(angle) * distance;
                      final y = math.sin(angle) * distance - _confettiController.value * 20;
                      final opacity = 1 - _confettiController.value;
                      final colors = [
                        const Color(0xFF10B981),
                        const Color(0xFF34D399),
                        const Color(0xFF6EE7B7),
                        const Color(0xFF6366F1),
                        const Color(0xFFA78BFA),
                      ];

                      return Transform.translate(
                        offset: Offset(x, y),
                        child: Transform.rotate(
                          angle: _confettiController.value * 4 * math.pi + i,
                          child: Opacity(
                            opacity: opacity,
                            child: Container(
                              width: 6,
                              height: 6,
                              decoration: BoxDecoration(
                                color: colors[i % colors.length],
                                borderRadius: BorderRadius.circular(2),
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
    );
  }
}

/// Plasma arcs painter - قوس الکتریکی از هسته به لبه
class PlasmaPainter extends CustomPainter {
  final double progress;
  final bool isConnected;
  final bool isConnecting;

  PlasmaPainter({required this.progress, required this.isConnected, required this.isConnecting});

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final count = isConnecting ? 5 : 3;
    final random = math.Random((progress * 1000).toInt());

    for (int i = 0; i < count; i++) {
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5
        ..strokeCap = StrokeCap.round
        ..color = isConnected
            ? Color.lerp(const Color(0xFF10B981), const Color(0xFF6EE7B7), math.sin(progress * 2 * math.pi + i) * 0.5 + 0.5)!
                .withOpacity(0.6 + math.sin(progress * 2 * math.pi + i) * 0.3)
            : Color.lerp(const Color(0xFFF59E0B), const Color(0xFFFCD34D), math.sin(progress * 2 * math.pi + i) * 0.5 + 0.5)!
                .withOpacity(0.5 + math.sin(progress * 2 * math.pi + i) * 0.3)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4);

      final angle = progress * 2 * math.pi + i * (2 * math.pi / count) + random.nextDouble() * 0.3;
      final length = 55 + math.sin(progress * 4 * math.pi + i) * 15;
      final endX = center.dx + math.cos(angle) * length;
      final endY = center.dy + math.sin(angle) * length;

      final midX = center.dx + math.cos(angle) * length * 0.5 + (random.nextDouble() - 0.5) * 20;
      final midY = center.dy + math.sin(angle) * length * 0.5 + (random.nextDouble() - 0.5) * 20;

      final path = Path()
        ..moveTo(center.dx, center.dy)
        ..quadraticBezierTo(midX, midY, endX, endY);

      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(PlasmaPainter oldDelegate) =>
      oldDelegate.progress != progress || oldDelegate.isConnected != isConnected;
}

/// Liquid wave painter - موج مایع داخل دکمه
class LiquidWavePainter extends CustomPainter {
  final double progress;
  final bool isConnecting;

  LiquidWavePainter({required this.progress, required this.isConnecting});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withOpacity(0.25)
      ..style = PaintingStyle.fill;

    final path = Path();
    path.moveTo(0, 10);

    for (double x = 0; x <= size.width; x++) {
      final y = 10 + math.sin((x / size.width * 2 * math.pi) + progress * 2 * math.pi * (isConnecting ? 2 : 1)) * (isConnecting ? 6 : 3);
      path.lineTo(x, y);
    }
    path.lineTo(size.width, 0);
    path.lineTo(0, 0);
    path.close();

    canvas.drawPath(path, paint);

    // Highlight
    final highlightPaint = Paint()
      ..color = Colors.white.withOpacity(0.15)
      ..style = PaintingStyle.fill;

    final highlightPath = Path();
    highlightPath.moveTo(0, 4);
    for (double x = 0; x <= size.width; x++) {
      final y = 4 + math.sin((x / size.width * 2 * math.pi) + progress * 2 * math.pi + 1) * 2;
      highlightPath.lineTo(x, y);
    }
    highlightPath.lineTo(size.width, 0);
    highlightPath.lineTo(0, 0);
    highlightPath.close();

    canvas.drawPath(highlightPath, highlightPaint);
  }

  @override
  bool shouldRepaint(LiquidWavePainter oldDelegate) => oldDelegate.progress != progress;
}

/// Speed count-up animation widget - انیمیشن عدد سرعت
class SpeedCountUp extends StatefulWidget {
  final double value;
  final String suffix;
  final Duration duration;

  const SpeedCountUp({Key? key, required this.value, this.suffix = ' Mbps', this.duration = const Duration(milliseconds: 800)}) : super(key: key);

  @override
  State<SpeedCountUp> createState() => _SpeedCountUpState();
}

class _SpeedCountUpState extends State<SpeedCountUp> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;
  double _oldValue = 0;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration);
    _animation = Tween<double>(begin: 0, end: widget.value).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
    _controller.forward();
  }

  @override
  void didUpdateWidget(SpeedCountUp oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) {
      _oldValue = _animation.value;
      _animation = Tween<double>(begin: _oldValue, end: widget.value).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
      _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return Text('${_animation.value.toStringAsFixed(1)}${widget.suffix}', style: const TextStyle(fontWeight: FontWeight.bold));
      },
    );
  }
}

/// Background glow + particles - پس‌زمینه با ذرات نورانی
class ConnectBackground extends StatefulWidget {
  final ConnectButtonState state;
  final Widget child;

  const ConnectBackground({Key? key, required this.state, required this.child}) : super(key: key);

  @override
  State<ConnectBackground> createState() => _ConnectBackgroundState();
}

class _ConnectBackgroundState extends State<ConnectBackground> with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(seconds: 4))..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return Container(
          decoration: BoxDecoration(
            gradient: RadialGradient(
              center: Alignment.center,
              radius: 1.2,
              colors: widget.state == ConnectButtonState.connected
                  ? [
                      const Color(0xFF10B981).withOpacity(0.08 + math.sin(_controller.value * 2 * math.pi) * 0.03),
                      const Color(0xFF065F46).withOpacity(0.04),
                      Colors.transparent,
                    ]
                  : widget.state == ConnectButtonState.connecting
                      ? [
                          const Color(0xFFF59E0B).withOpacity(0.08 + math.sin(_controller.value * 4 * math.pi) * 0.04),
                          const Color(0xFF92400E).withOpacity(0.03),
                          Colors.transparent,
                        ]
                      : [
                          const Color(0xFF9333EA).withOpacity(0.06 + math.sin(_controller.value * 2 * math.pi) * 0.02),
                          const Color(0xFF4C1D95).withOpacity(0.02),
                          Colors.transparent,
                        ],
            ),
          ),
          child: child,
        );
      },
      child: widget.child,
    );
  }
}
