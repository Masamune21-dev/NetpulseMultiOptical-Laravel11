import 'package:flutter/material.dart';

import 'tokens.dart';

/// Helper warna adaptif lama — kini hanya pemetaan ke token [Np] supaya layar
/// yang belum dimigrasi ikut memakai palet yang sama. Untuk kode baru, pakai
/// `context.np` langsung.
extension NetpulseThemeX on BuildContext {
  bool get isDark => Theme.of(this).brightness == Brightness.dark;

  Color get cardBg => np.surface;
  Color get cardBorder => np.border;
  Color get subtleBg => np.surface2;
  Color get textPrimary => np.ink;
  Color get textMuted => np.ink2;
  Color get textFaint => np.ink3;
  Color get chartGrid => np.border;
}
