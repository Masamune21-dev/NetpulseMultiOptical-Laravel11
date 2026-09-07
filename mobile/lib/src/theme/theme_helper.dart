import 'package:flutter/material.dart';

/// Helper warna adaptif terang/gelap agar mode gelap merata di semua layar.
extension NetpulseThemeX on BuildContext {
  bool get isDark => Theme.of(this).brightness == Brightness.dark;

  Color get cardBg =>
      isDark ? const Color(0xFF1E293B) : Colors.white;

  Color get cardBorder =>
      isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0);

  Color get subtleBg =>
      isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);

  Color get textPrimary =>
      isDark ? const Color(0xFFF1F5F9) : const Color(0xFF0F172A);

  Color get textMuted =>
      isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B);

  Color get textFaint =>
      isDark ? const Color(0xFF94A3B8) : const Color(0xFF94A3B8);

  Color get chartGrid =>
      isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0);
}
