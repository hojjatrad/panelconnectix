import 'package:flutter/material.dart';

/// v4.0.53 DESIGN SYSTEM - رنگ‌های یکپارچه
class AppColors {
  // Primary
  static const primary = Color(0xFF6366F1);
  static const primaryDark = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFF818CF8);

  // Success
  static const success = Color(0xFF10B981);
  static const successDark = Color(0xFF059669);
  static const successLight = Color(0xFF34D399);

  // Warning
  static const warning = Color(0xFFF59E0B);
  static const warningDark = Color(0xFFD97706);

  // Error
  static const error = Color(0xFFEF4444);
  static const errorDark = Color(0xFFDC2626);

  // Backgrounds
  static const bgPrimary = Color(0xFF0F172A);
  static const bgSecondary = Color(0xFF1E293B);
  static const bgCard = Color(0xFF1E293B);
  static const bgInput = Color(0xFF0F172A);

  // Text
  static const textPrimary = Colors.white;
  static const textSecondary = Color(0xFF94A3B8);
  static const textTertiary = Color(0xFF64748B);

  // Borders
  static const border = Color(0xFF334155);
  static const borderLight = Color(0xFF1E293B);

  // Gradients
  static const gradientPrimary = LinearGradient(
    colors: [primary, primaryDark],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  static const gradientSuccess = LinearGradient(
    colors: [success, successDark],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  static const gradientWarning = LinearGradient(
    colors: [warning, warningDark],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  // Server status
  static Color pingColor(int? ms) {
    if (ms == null) return textTertiary;
    if (ms == 0) return success; // آماده
    if (ms < 100) return success;
    if (ms < 250) return warning;
    return error;
  }

  static String pingLabel(int? ms) {
    if (ms == null) return 'نامشخص';
    if (ms == 0) return 'آماده';
    if (ms < 100) return 'عالی';
    if (ms < 250) return 'خوب';
    if (ms < 500) return 'متوسط';
    return 'ضعیف';
  }
}
