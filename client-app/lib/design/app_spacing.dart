import 'package:flutter/material.dart';

/// v4.0.53 DESIGN SYSTEM - فاصله‌ها و شعاع‌ها
class AppSpacing {
  // Spacing
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;
  static const xxxl = 32.0;

  // Radius
  static const radiusXs = 6.0;
  static const radiusSm = 10.0;
  static const radiusMd = 14.0;
  static const radiusLg = 18.0;
  static const radiusXl = 24.0;
  static const radiusFull = 999.0;

  // Padding presets
  static const paddingCard = EdgeInsets.all(16);
  static const paddingScreen = EdgeInsets.symmetric(horizontal: 20, vertical: 8);
  static const paddingButton = EdgeInsets.symmetric(horizontal: 20, vertical: 12);
  static const paddingChip = EdgeInsets.symmetric(horizontal: 10, vertical: 5);

  // SizedBox helpers
  static const gapXs = SizedBox(height: xs, width: xs);
  static const gapSm = SizedBox(height: sm, width: sm);
  static const gapMd = SizedBox(height: md, width: md);
  static const gapLg = SizedBox(height: lg, width: lg);
  static const gapXl = SizedBox(height: xl, width: xl);
  static const gapXxl = SizedBox(height: xxl, width: xxl);

  // Durations
  static const animFast = Duration(milliseconds: 150);
  static const animNormal = Duration(milliseconds: 250);
  static const animSlow = Duration(milliseconds: 400);
  static const animPulse = Duration(seconds: 2);
}
