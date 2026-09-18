import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'tokens.dart';

/// ThemeData Netpulse untuk kedua mode, dibangun dari token [Np].
/// Nama fungsi dipertahankan supaya `app.dart` tidak berubah.
ThemeData buildNetpulseTheme() => _NetpulseTheme.build(Np.light, Brightness.light);
ThemeData buildNetpulseDarkTheme() => _NetpulseTheme.build(Np.dark, Brightness.dark);

class _NetpulseTheme {
  static ThemeData build(Np k, Brightness brightness) {
    final scheme = ColorScheme(
      brightness: brightness,
      primary: k.accent,
      onPrimary: k.onAccent,
      secondary: k.accent,
      onSecondary: k.onAccent,
      tertiary: k.info,
      onTertiary: k.onAccent,
      error: k.bad,
      onError: Colors.white,
      surface: k.surface,
      onSurface: k.ink,
      onSurfaceVariant: k.ink2,
      surfaceContainerHighest: k.surface2,
      surfaceContainerLow: k.bg,
      outline: k.border,
      outlineVariant: k.border,
    );

    final text = _textTheme(k);
    final control = RoundedRectangleBorder(borderRadius: BorderRadius.circular(NpRadius.control));
    final pill = RoundedRectangleBorder(borderRadius: BorderRadius.circular(NpRadius.pill));
    OutlineInputBorder input(Color c, [double w = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(NpRadius.control),
          borderSide: BorderSide(color: c, width: w),
        );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      extensions: [k],
      scaffoldBackgroundColor: k.bg,
      canvasColor: k.surface,
      fontFamily: NpFont.body,
      textTheme: text,
      splashFactory: InkSparkle.splashFactory,
      dividerColor: k.border,
      dividerTheme: DividerThemeData(color: k.border, thickness: 1, space: 1),
      appBarTheme: AppBarTheme(
        backgroundColor: k.bg,
        foregroundColor: k.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        titleTextStyle: text.titleLarge,
        iconTheme: IconThemeData(color: k.ink2, size: 22),
        systemOverlayStyle: brightness == Brightness.dark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      ),
      // Layar lama (Monitoring, Akun, Alert, Tentang) menumpuk `Card` tanpa
      // SizedBox di antaranya dan mengandalkan margin tema; jarak vertikal 12
      // diberikan di sini, jarak samping dari padding ListView (16).
      cardTheme: CardThemeData(
        color: k.surface,
        elevation: 0,
        margin: const EdgeInsets.only(bottom: NpSpace.md),
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(NpRadius.card),
          side: BorderSide(color: k.border),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: k.surface,
        modalBackgroundColor: k.surface,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: k.border,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(NpRadius.sheet)),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: k.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(NpRadius.card)),
        titleTextStyle: text.titleLarge,
        contentTextStyle: text.bodyMedium,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: k.accent,
          foregroundColor: k.onAccent,
          disabledBackgroundColor: k.surface2,
          disabledForegroundColor: k.ink3,
          minimumSize: const Size.fromHeight(48),
          textStyle: text.labelLarge,
          shape: control,
          elevation: 0,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: k.accent,
          foregroundColor: k.onAccent,
          minimumSize: const Size.fromHeight(48),
          textStyle: text.labelLarge,
          shape: control,
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: k.ink,
          backgroundColor: k.surface,
          minimumSize: const Size.fromHeight(48),
          side: BorderSide(color: k.border),
          textStyle: text.labelLarge,
          shape: control,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: k.accent, textStyle: text.labelMedium, shape: control),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(foregroundColor: k.ink2),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: k.surface,
        hintStyle: text.bodyMedium?.copyWith(color: k.ink3),
        labelStyle: text.bodyMedium?.copyWith(color: k.ink2),
        prefixIconColor: k.ink3,
        suffixIconColor: k.ink3,
        contentPadding: const EdgeInsets.symmetric(horizontal: NpSpace.lg, vertical: NpSpace.md),
        border: input(k.border),
        enabledBorder: input(k.border),
        focusedBorder: input(k.accent, 1.5),
        errorBorder: input(k.bad),
        focusedErrorBorder: input(k.bad, 1.5),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: k.surface,
        selectedColor: k.accent,
        disabledColor: k.surface2,
        side: BorderSide(color: k.border),
        shape: pill,
        labelStyle: text.labelMedium?.copyWith(color: k.ink2),
        secondaryLabelStyle: text.labelMedium?.copyWith(color: k.onAccent),
        checkmarkColor: k.onAccent,
        showCheckmark: false,
        padding: const EdgeInsets.symmetric(horizontal: NpSpace.md, vertical: NpSpace.sm),
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: SegmentedButton.styleFrom(
          backgroundColor: k.surface2,
          selectedBackgroundColor: k.surface,
          selectedForegroundColor: k.ink,
          foregroundColor: k.ink2,
          side: BorderSide(color: k.border),
          textStyle: text.labelMedium,
          shape: control,
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: k.accent,
        unselectedLabelColor: k.ink3,
        indicatorColor: k.accent,
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: k.border,
        labelStyle: text.labelLarge,
        unselectedLabelStyle: text.labelLarge,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: k.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: k.accentSoft,
        elevation: 0,
        height: 64,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (s) => text.labelSmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: s.contains(WidgetState.selected) ? k.accent : k.ink3,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (s) => IconThemeData(size: 22, color: s.contains(WidgetState.selected) ? k.accent : k.ink3),
        ),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: k.ink2,
        textColor: k.ink,
        titleTextStyle: text.titleSmall,
        subtitleTextStyle: text.bodySmall?.copyWith(color: k.ink2),
        contentPadding: const EdgeInsets.symmetric(horizontal: NpSpace.lg, vertical: NpSpace.xs),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: k.accent, linearTrackColor: k.track, circularTrackColor: k.track),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? k.onAccent : k.ink3),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? k.accent : k.surface2),
        trackOutlineColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? k.accent : k.border),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: k.ink,
        contentTextStyle: text.bodyMedium?.copyWith(color: k.bg),
        behavior: SnackBarBehavior.floating,
        shape: control,
      ),
      dropdownMenuTheme: DropdownMenuThemeData(
        menuStyle: MenuStyle(
          backgroundColor: WidgetStatePropertyAll(k.surface),
          surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
          shape: WidgetStatePropertyAll(control),
        ),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: k.surface,
        surfaceTintColor: Colors.transparent,
        shape: control,
        textStyle: text.bodyMedium,
      ),
    );
  }

  /// Skala tipe tujuh langkah (mockup 18 Sep 2026):
  /// angka KPI Sora 24/800 · judul layar Sora 18/700 · judul kartu Sora 14/700 ·
  /// nama baris Inter 13.5/600 · keterangan Inter 12/500 · badge Inter 11/700 ·
  /// data teknis JetBrainsMono 14/600 (lewat [NpText.mono]).
  static TextTheme _textTheme(Np k) {
    TextStyle sora(double size, FontWeight w, double tracking, {Color? color}) => TextStyle(
          fontFamily: NpFont.display,
          fontSize: size,
          fontWeight: w,
          letterSpacing: tracking,
          height: 1.1,
          color: color ?? k.ink,
          fontFeatures: const [FontFeature.tabularFigures()],
        );
    TextStyle inter(double size, FontWeight w, {double h = 1.4, Color? color}) => TextStyle(
          fontFamily: NpFont.body,
          fontSize: size,
          fontWeight: w,
          height: h,
          color: color ?? k.ink,
        );

    return TextTheme(
      displayLarge: sora(36, FontWeight.w800, -1.0),
      displayMedium: sora(30, FontWeight.w800, -0.8),
      displaySmall: sora(24, FontWeight.w800, -0.7), // angka KPI
      headlineLarge: sora(24, FontWeight.w700, -0.5),
      headlineMedium: sora(20, FontWeight.w700, -0.4),
      headlineSmall: sora(18, FontWeight.w700, -0.3), // judul layar
      titleLarge: sora(18, FontWeight.w700, -0.3),
      titleMedium: sora(14, FontWeight.w700, -0.1), // judul kartu
      titleSmall: inter(13.5, FontWeight.w600, h: 1.3), // nama baris
      bodyLarge: inter(15, FontWeight.w400, h: 1.5),
      bodyMedium: inter(14, FontWeight.w400, h: 1.5),
      bodySmall: inter(12, FontWeight.w500, h: 1.4, color: k.ink2), // keterangan
      labelLarge: inter(14, FontWeight.w600, h: 1.2),
      labelMedium: inter(12.5, FontWeight.w600, h: 1.2),
      labelSmall: inter(11, FontWeight.w700, h: 1.2), // badge
    );
  }
}
