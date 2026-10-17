import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../design/app_colors.dart';
import '../design/app_spacing.dart';

/// v4.0.53 LITE - سبک، سریع، 60fps در گوشی ضعیف
/// فقط 1 انیمیشن pulse + scale، بدون confetti/dust/flag/plasma
/// برای فیکس مشکل دکمه‌ها که عمل نمی‌کنه (TransactionTooLarge + hitTest)

enum LiteConnectState { disconnected, connecting, connected }

class ConnectButtonLite extends StatefulWidget {
  final LiteConnectState state;
  final VoidCallback? onTap;
  final double size;
  final String? countryFlag;
  final String? serverName;
  final bool enableHaptic;

  const ConnectButtonLite({
    Key? key,
    required this.state,
    this.onTap,
    this.size = 140,
    this.countryFlag,
    this.serverName,
    this.enableHaptic = true,
  }) : super(key: key);

  @override
  State<ConnectButtonLite> createState() => _ConnectButtonLiteState();
}

class _ConnectButtonLiteState extends State<ConnectButtonLite>
    with SingleTickerProviderStateMixin {
  late AnimationController _pulseController;
  late AnimationController _scaleController;
  late Animation<double> _scaleAnim;
  late Animation<double> _pulseAnim;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat();
    _pulseAnim = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
    _scaleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 120),
    );
    _scaleAnim = Tween<double>(begin: 1.0, end: 0.94).animate(
      CurvedAnimation(parent: _scaleController, curve: Curves.easeOut),
    );
    _updateForState(widget.state);
  }

  @override
  void didUpdateWidget(ConnectButtonLite oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.state != widget.state) {
      _updateForState(widget.state);
      if (widget.state == LiteConnectState.connected) _triggerHaptic();
    }
  }

  void _updateForState(LiteConnectState state) {
    switch (state) {
      case LiteConnectState.disconnected:
        _pulseController.duration = const Duration(seconds: 2);
        break;
      case LiteConnectState.connecting:
        _pulseController.duration = const Duration(milliseconds: 600);
        break;
      case LiteConnectState.connected:
        _pulseController.duration = const Duration(milliseconds: 1200);
        break;
    }
    _pulseController.repeat();
  }

  void _triggerHaptic() {
    if (!widget.enableHaptic) return;
    try {
      HapticFeedback.mediumImpact();
    } catch (_) {}
  }

  void _handleTap() {
    if (widget.enableHaptic) {
      try {
        HapticFeedback.lightImpact();
      } catch (_) {}
    }
    _scaleController.forward().then((_) => _scaleController.reverse());
    // اطمینان از اینکه onTap حتی اگر انیمیشن در حال اجراست صدا زده شود
    WidgetsBinding.instance.addPostFrameCallback((_) {
      widget.onTap?.call();
    });
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _scaleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool isConnected = widget.state == LiteConnectState.connected;
    final bool isConnecting = widget.state == LiteConnectState.connecting;

    Color outerColor;
    Color innerColor;
    IconData icon;
    if (isConnected) {
      outerColor = AppColors.success;
      innerColor = AppColors.successDark;
      icon = Icons.power_settings_new_rounded;
    } else if (isConnecting) {
      outerColor = AppColors.warning;
      innerColor = AppColors.warningDark;
      icon = Icons.sync_rounded;
    } else {
      outerColor = AppColors.primary;
      innerColor = AppColors.primaryDark;
      icon = Icons.power_settings_new_rounded;
    }

    return GestureDetector(
      onTap: _handleTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedBuilder(
        animation: Listenable.merge([_pulseController, _scaleController]),
        builder: (context, child) {
          return Transform.scale(
            scale: _scaleAnim.value,
            child: SizedBox(
              width: widget.size,
              height: widget.size,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // Pulse outer - فقط 1 لایه
                  if (!isConnected)
                    Container(
                      width: widget.size * (0.9 + _pulseAnim.value * 0.2),
                      height: widget.size * (0.9 + _pulseAnim.value * 0.2),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: outerColor.withOpacity(0.15 * (1 - _pulseAnim.value)),
                        border: Border.all(
                          color: outerColor.withOpacity(0.3 * (1 - _pulseAnim.value)),
                          width: 1,
                        ),
                      ),
                    ),
                  // Inner glow
                  Container(
                    width: widget.size * 0.85,
                    height: widget.size * 0.85,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        colors: [
                          innerColor.withOpacity(0.3),
                          innerColor.withOpacity(0.05),
                        ],
                      ),
                    ),
                  ),
                  // Main button
                  Container(
                    width: widget.size * 0.72,
                    height: widget.size * 0.72,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [outerColor, innerColor],
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: outerColor.withOpacity(0.4),
                          blurRadius: isConnected ? 20 : 12,
                          spreadRadius: isConnected ? 2 : 0,
                        ),
                      ],
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        if (widget.countryFlag != null && widget.countryFlag!.isNotEmpty)
                          Text(
                            widget.countryFlag!,
                            style: const TextStyle(fontSize: 28),
                          ),
                        Icon(
                          icon,
                          size: 36,
                          color: Colors.white,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          isConnected
                              ? 'متصل'
                              : isConnecting
                                  ? 'در حال اتصال'
                                  : 'اتصال',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Connecting spinner
                  if (isConnecting)
                    SizedBox(
                      width: widget.size * 0.78,
                      height: widget.size * 0.78,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        valueColor: AlwaysStoppedAnimation(outerColor),
                        backgroundColor: Colors.transparent,
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
