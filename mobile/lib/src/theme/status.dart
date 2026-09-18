import 'package:flutter/material.dart';

import 'tokens.dart';

/// Status semantik yang dipakai seluruh layar. Empat nilai, selalu tampil
/// bersama kata (lihat [NetStatusX.label]) — tidak pernah warna saja.
enum NetStatus { up, warn, down, none }

extension NetStatusX on NetStatus {
  String get label => switch (this) {
        NetStatus.up => 'Up',
        NetStatus.warn => 'Marjinal',
        NetStatus.down => 'Down',
        NetStatus.none => 'Tanpa DDM',
      };

  /// Huruf bar riwayat dari API (`history_24h`).
  static NetStatus fromHistoryChar(String ch) => switch (ch) {
        'u' => NetStatus.up,
        'w' => NetStatus.warn,
        'd' => NetStatus.down,
        _ => NetStatus.none,
      };
}

/// Ambang RX (dBm). Satu sumber: nilai dari server lewat `thresholds` di
/// respons `/api/v1/dashboard` dan `/api/v1/interfaces`. Sebelumnya tiap layar
/// memegang angka lokal yang berbeda (−25, −35, −40).
@immutable
class RxThresholds {
  const RxThresholds({required this.warnLow, required this.down});

  final double warnLow;
  final double down;

  static const defaults = RxThresholds(warnLow: -25.0, down: -40.0);

  /// Nilai terakhir yang diterima dari server; dipakai layar yang API-nya
  /// belum mengirim ambang sendiri (peta, monitoring).
  static RxThresholds current = defaults;

  static RxThresholds fromJson(Map<String, dynamic>? json) {
    if (json == null) return defaults;
    double d(String k, double fb) {
      final v = json[k];
      if (v is num) return v.toDouble();
      return double.tryParse(v?.toString() ?? '') ?? fb;
    }

    return RxThresholds(
      warnLow: d('rx_warn_low', defaults.warnLow),
      down: d('rx_down_threshold', defaults.down),
    );
  }

  /// Terapkan sebagai nilai berjalan dan kembalikan dirinya.
  RxThresholds adopt() {
    current = this;
    return this;
  }

  /// Status sebuah port dari RX dan oper_status.
  /// - oper_status ≠ 1 → down.
  /// - RX null → tanpa DDM.
  /// - RX ≤ ambang down (−40 = tanpa pembacaan) → down.
  /// - RX < ambang peringatan → marjinal.
  NetStatus statusOf({double? rx, int? operStatus}) {
    if (operStatus != null && operStatus != 1) return NetStatus.down;
    if (rx == null) return NetStatus.none;
    if (rx <= down) return NetStatus.down;
    if (rx < warnLow) return NetStatus.warn;
    return NetStatus.up;
  }

  /// Posisi RX pada meter tetap −40 s.d. −10 dBm (0..1).
  double meterFraction(double rx) => ((rx - down) / (-10.0 - down)).clamp(0.0, 1.0);
}

/// Pasangan warna untuk sebuah status: `mark` untuk titik/bar/ring,
/// `text` + `bg` untuk badge.
extension NpStatusColors on Np {
  ({Color mark, Color text, Color bg}) status(NetStatus s) => switch (s) {
        NetStatus.up => (mark: ok, text: okText, bg: okBg),
        NetStatus.warn => (mark: warn, text: warnText, bg: warnBg),
        NetStatus.down => (mark: bad, text: badText, bg: badBg),
        NetStatus.none => (mark: track, text: ink2, bg: surface2),
      };

  /// Warna severity alert dari `alert_logs.severity`.
  ({Color mark, Color text, Color bg}) severity(String sev) => switch (sev.toLowerCase()) {
        'critical' => (mark: bad, text: badText, bg: badBg),
        'warning' => (mark: warn, text: warnText, bg: warnBg),
        _ => (mark: info, text: infoText, bg: infoBg),
      };
}
