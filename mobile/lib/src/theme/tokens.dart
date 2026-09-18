import 'package:flutter/material.dart';

/// Token desain Netpulse Mobile (18 Sep 2026) — arah «slate netral, satu aksen
/// teal, tiga warna status». Permukaan mengikuti bahasa Ubiquiti: terang
/// sebagai default untuk teknisi di lapangan, gelap slate (bukan inversi)
/// untuk NOC. Diakses lewat `context.np`.
///
/// Aturan: layar tidak boleh memegang nilai warna/jarak/radius sendiri —
/// semuanya dari kelas di berkas ini.
@immutable
class Np extends ThemeExtension<Np> {
  const Np({
    required this.bg,
    required this.surface,
    required this.surface2,
    required this.border,
    required this.ink,
    required this.ink2,
    required this.ink3,
    required this.accent,
    required this.accentSoft,
    required this.onAccent,
    required this.ok,
    required this.okText,
    required this.okBg,
    required this.warn,
    required this.warnText,
    required this.warnBg,
    required this.bad,
    required this.badText,
    required this.badBg,
    required this.info,
    required this.infoText,
    required this.infoBg,
    required this.track,
    required this.shadow,
  });

  // A. Permukaan & teks
  final Color bg;
  final Color surface;
  final Color surface2;
  final Color border;
  final Color ink;
  final Color ink2;
  final Color ink3;

  // B. Aksi — satu-satunya warna aksen
  final Color accent;
  final Color accentSoft;
  final Color onAccent;

  // C. Status — `x` untuk tanda (titik, bar, ring), `xText`/`xBg` untuk badge
  final Color ok;
  final Color okText;
  final Color okBg;
  final Color warn;
  final Color warnText;
  final Color warnBg;
  final Color bad;
  final Color badText;
  final Color badBg;
  final Color info;
  final Color infoText;
  final Color infoBg;

  /// Latar meter / bar riwayat tanpa data.
  final Color track;

  /// Bayangan kartu (terang: lembut; gelap: kosong — kedalaman dari garis).
  final List<BoxShadow> shadow;

  static const light = Np(
    bg: Color(0xFFF3F6F8),
    surface: Color(0xFFFFFFFF),
    surface2: Color(0xFFEDF2F5),
    border: Color(0xFFE1E8EE),
    ink: Color(0xFF0F172A),
    ink2: Color(0xFF5B6B80),
    ink3: Color(0xFF93A1B3),
    accent: Color(0xFF0F766E),
    accentSoft: Color(0xFFE3F3F0),
    onAccent: Color(0xFFFFFFFF),
    ok: Color(0xFF16A34A),
    okText: Color(0xFF15803D),
    okBg: Color(0xFFDCFCE7),
    warn: Color(0xFFF59E0B),
    warnText: Color(0xFFB45309),
    warnBg: Color(0xFFFEF3C7),
    bad: Color(0xFFDC2626),
    badText: Color(0xFFB91C1C),
    badBg: Color(0xFFFEE2E2),
    info: Color(0xFF0284C7),
    infoText: Color(0xFF0369A1),
    infoBg: Color(0xFFE0F2FE),
    track: Color(0xFFE1E8EE),
    shadow: [
      BoxShadow(color: Color(0x0F0F172A), blurRadius: 18, offset: Offset(0, 6)),
      BoxShadow(color: Color(0x0A0F172A), blurRadius: 2, offset: Offset(0, 1)),
    ],
  );

  static const dark = Np(
    bg: Color(0xFF0B1220),
    surface: Color(0xFF131C2B),
    surface2: Color(0xFF1A2536),
    border: Color(0xFF26324A),
    ink: Color(0xFFEEF2F7),
    ink2: Color(0xFF98A6B8),
    ink3: Color(0xFF66748A),
    accent: Color(0xFF2DD4BF),
    accentSoft: Color(0xFF12312F),
    onAccent: Color(0xFF03201C),
    ok: Color(0xFF34D399),
    okText: Color(0xFF4ADE80),
    okBg: Color(0xFF12301F),
    warn: Color(0xFFFBBF24),
    warnText: Color(0xFFFCD34D),
    warnBg: Color(0xFF3A2A0A),
    bad: Color(0xFFF87171),
    badText: Color(0xFFFCA5A5),
    badBg: Color(0xFF3B1516),
    info: Color(0xFF38BDF8),
    infoText: Color(0xFF7DD3FC),
    infoBg: Color(0xFF0F2A3F),
    track: Color(0xFF26324A),
    shadow: [],
  );

  @override
  Np copyWith() => this;

  @override
  Np lerp(ThemeExtension<Np>? other, double t) {
    if (other is! Np) return this;
    Color c(Color a, Color b) => Color.lerp(a, b, t)!;
    return Np(
      bg: c(bg, other.bg),
      surface: c(surface, other.surface),
      surface2: c(surface2, other.surface2),
      border: c(border, other.border),
      ink: c(ink, other.ink),
      ink2: c(ink2, other.ink2),
      ink3: c(ink3, other.ink3),
      accent: c(accent, other.accent),
      accentSoft: c(accentSoft, other.accentSoft),
      onAccent: c(onAccent, other.onAccent),
      ok: c(ok, other.ok),
      okText: c(okText, other.okText),
      okBg: c(okBg, other.okBg),
      warn: c(warn, other.warn),
      warnText: c(warnText, other.warnText),
      warnBg: c(warnBg, other.warnBg),
      bad: c(bad, other.bad),
      badText: c(badText, other.badText),
      badBg: c(badBg, other.badBg),
      info: c(info, other.info),
      infoText: c(infoText, other.infoText),
      infoBg: c(infoBg, other.infoBg),
      track: c(track, other.track),
      shadow: t < 0.5 ? shadow : other.shadow,
    );
  }
}

extension NpContext on BuildContext {
  Np get np => Theme.of(this).extension<Np>() ?? Np.light;
}

/// Radius, empat nilai: badge/chip pil · kontrol 12 · kartu 16 · sheet 24.
class NpRadius {
  static const pill = 999.0;
  static const control = 12.0;
  static const card = 16.0;
  static const sheet = 24.0;
}

/// Jarak, skala 4 pt. Tepi layar & antar-kartu 16 · isi kartu 16 ·
/// antar-baris 12 · antar-chip 8 · label ke nilai 4.
class NpSpace {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;

  static const screen = EdgeInsets.fromLTRB(lg, sm, lg, xxl);
  static const card = EdgeInsets.all(lg);
  static const row = EdgeInsets.symmetric(horizontal: lg, vertical: md);
}

/// Keluarga font (aset di `assets/fonts/`).
/// - Sora: judul dan angka besar. Inter: isi. JetBrainsMono: dBm, port, IP.
class NpFont {
  static const display = 'Sora';
  static const body = 'Inter';
  static const mono = 'JetBrainsMono';
}

/// Gaya teks monospace untuk data teknis (rata kolom, tabular).
class NpText {
  static TextStyle mono({
    double size = 14,
    FontWeight weight = FontWeight.w600,
    Color? color,
    double? height,
  }) =>
      TextStyle(
        fontFamily: NpFont.mono,
        fontFeatures: const [FontFeature.tabularFigures()],
        fontSize: size,
        fontWeight: weight,
        color: color,
        height: height,
      );
}

/// Gerak seragam.
class NpMotion {
  static const fast = Duration(milliseconds: 150);
  static const base = Duration(milliseconds: 240);
  static const curve = Curves.easeOutCubic;
}
