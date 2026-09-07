import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

ThemeData buildNetpulseTheme() {
  const ink = Color(0xFF0F172A);
  const cloud = Color(0xFFF3F8F7);
  const primary = Color(0xFF0F766E);
  const secondary = Color(0xFFF97316);
  const accent = Color(0xFF0369A1);

  final baseScheme = ColorScheme.fromSeed(
    seedColor: primary,
    brightness: Brightness.light,
  );
  final colorScheme = baseScheme.copyWith(
    primary: primary,
    secondary: secondary,
    tertiary: accent,
    surface: Colors.white,
    onSurface: ink,
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: cloud,
    dividerColor: ink.withValues(alpha: 0.08),
    textTheme: GoogleFonts.soraTextTheme().copyWith(
      headlineLarge: GoogleFonts.sora(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.5,
      ),
      titleLarge: GoogleFonts.sora(fontWeight: FontWeight.w700),
      bodyMedium: GoogleFonts.inter(fontWeight: FontWeight.w500),
    ),
    appBarTheme: const AppBarTheme(
      centerTitle: false,
      elevation: 0,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      backgroundColor: Colors.transparent,
      foregroundColor: ink,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: ink.withValues(alpha: 0.08)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: primary, width: 1.2),
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    ),
    chipTheme: ChipThemeData(
      side: BorderSide(color: ink.withValues(alpha: 0.08)),
      selectedColor: primary.withValues(alpha: 0.14),
      disabledColor: Colors.white,
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      labelStyle: GoogleFonts.inter(fontWeight: FontWeight.w600),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: primary.withValues(alpha: 0.14),
      labelTextStyle: WidgetStatePropertyAll(
        GoogleFonts.inter(fontSize: 11.5, fontWeight: FontWeight.w700),
      ),
    ),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.selected) ? primary : Colors.grey,
      ),
      trackColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.selected)
            ? primary.withValues(alpha: 0.35)
            : Colors.grey.withValues(alpha: 0.35),
      ),
    ),
  );
}

ThemeData buildNetpulseDarkTheme() {
  const bgDark = Color(0xFF0B1120);
  const cardDark = Color(0xFF1E293B);
  const textDark = Color(0xFFF1F5F9);
  const textSoftDark = Color(0xFF94A3B8);
  const primary = Color(0xFF14B8A6);
  const secondary = Color(0xFFF97316);
  const accent = Color(0xFF38BDF8);

  final baseScheme = ColorScheme.fromSeed(
    seedColor: primary,
    brightness: Brightness.dark,
  );
  final colorScheme = baseScheme.copyWith(
    primary: primary,
    secondary: secondary,
    tertiary: accent,
    surface: cardDark,
    onSurface: textDark,
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: bgDark,
    dividerColor: Colors.white.withValues(alpha: 0.10),
    textTheme: GoogleFonts.soraTextTheme(ThemeData.dark().textTheme).copyWith(
      headlineLarge: GoogleFonts.sora(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.5,
        color: textDark,
      ),
      titleLarge: GoogleFonts.sora(fontWeight: FontWeight.w700, color: textDark),
      titleSmall: GoogleFonts.sora(fontWeight: FontWeight.w700, color: textDark),
      bodyLarge: GoogleFonts.inter(color: textDark),
      bodyMedium: GoogleFonts.inter(fontWeight: FontWeight.w500, color: textSoftDark),
      bodySmall: GoogleFonts.inter(color: textSoftDark),
      labelSmall: GoogleFonts.inter(color: textSoftDark),
      labelMedium: GoogleFonts.inter(color: textSoftDark),
    ),
    appBarTheme: const AppBarTheme(
      centerTitle: false,
      elevation: 0,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      backgroundColor: Colors.transparent,
      foregroundColor: textDark,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: cardDark,
      hintStyle: GoogleFonts.inter(color: textSoftDark.withValues(alpha: 0.7)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.12)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: primary, width: 1.2),
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: cardDark,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    ),
    chipTheme: ChipThemeData(
      side: BorderSide(color: Colors.white.withValues(alpha: 0.12)),
      selectedColor: primary.withValues(alpha: 0.25),
      disabledColor: cardDark,
      backgroundColor: cardDark,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      labelStyle: GoogleFonts.inter(fontWeight: FontWeight.w600, color: textDark),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: const Color(0xFF0F172A),
      indicatorColor: primary.withValues(alpha: 0.25),
      labelTextStyle: WidgetStatePropertyAll(
        GoogleFonts.inter(fontSize: 11.5, fontWeight: FontWeight.w700, color: textDark),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (s) => IconThemeData(
          color: s.contains(WidgetState.selected) ? primary : textSoftDark,
        ),
      ),
    ),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Color(0xFF1E293B),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: cardDark,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.selected) ? primary : Colors.grey,
      ),
      trackColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.selected)
            ? primary.withValues(alpha: 0.4)
            : Colors.grey.withValues(alpha: 0.4),
      ),
    ),
    dropdownMenuTheme: DropdownMenuThemeData(
      menuStyle: MenuStyle(
        backgroundColor: const WidgetStatePropertyAll(Color(0xFF1E293B)),
      ),
    ),
  );
}
