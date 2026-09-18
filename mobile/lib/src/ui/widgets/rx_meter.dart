import 'package:flutter/material.dart';

import '../../theme/status.dart';
import '../../theme/tokens.dart';

/// Nilai RX dalam mono ("−27,8 dBm") berwarna status, dengan meter kecil
/// skala tetap −40 s.d. −10 dBm di bawahnya. RX null / ≤ ambang down tampil
/// sebagai garis datar, bukan angka merah.
class RxValue extends StatelessWidget {
  const RxValue({
    super.key,
    required this.rx,
    this.operStatus,
    this.thresholds,
    this.showMeter = true,
    this.size = 14,
    this.align = CrossAxisAlignment.end,
  });

  final double? rx;
  final int? operStatus;
  final RxThresholds? thresholds;
  final bool showMeter;
  final double size;
  final CrossAxisAlignment align;

  static String format(double? rx, RxThresholds t) {
    if (rx == null || rx <= t.down) return '—';
    return rx.toStringAsFixed(1).replaceFirst('-', '−').replaceFirst('.', ',');
  }

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = thresholds ?? RxThresholds.current;
    final status = t.statusOf(rx: rx, operStatus: operStatus);
    final c = k.status(status);
    final valueColor = switch (status) {
      NetStatus.up => k.ink,
      NetStatus.none => k.ink3,
      _ => c.text,
    };

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: align,
      children: [
        Text.rich(
          TextSpan(
            text: format(rx, t),
            style: NpText.mono(size: size, color: valueColor),
            children: [
              TextSpan(
                text: ' dBm',
                style: TextStyle(fontFamily: NpFont.body, fontSize: 10.5, fontWeight: FontWeight.w500, color: k.ink3),
              ),
            ],
          ),
        ),
        if (showMeter) ...[
          const SizedBox(height: NpSpace.xs),
          RxMeter(rx: rx, status: status, thresholds: t),
        ],
      ],
    );
  }
}

/// Meter 72×4 px: isi sebanding posisi RX di skala −40..−10, garis tipis di
/// tengah sebagai patokan −25 dBm.
class RxMeter extends StatelessWidget {
  const RxMeter({super.key, required this.rx, required this.status, required this.thresholds, this.width = 72});

  final double? rx;
  final NetStatus status;
  final RxThresholds thresholds;
  final double width;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final frac = (rx == null || status == NetStatus.none || status == NetStatus.down) ? 0.0 : thresholds.meterFraction(rx!);
    final markFrac = thresholds.meterFraction(thresholds.warnLow);

    return SizedBox(
      width: width,
      height: 4,
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: k.track, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          if (frac > 0)
            Positioned(
              left: 0,
              top: 0,
              bottom: 0,
              width: width * frac,
              child: DecoratedBox(
                decoration: BoxDecoration(color: k.status(status).mark, borderRadius: BorderRadius.circular(2)),
              ),
            ),
          Positioned(
            left: width * markFrac,
            top: -1,
            bottom: -1,
            width: 1,
            child: ColoredBox(color: k.ink3.withValues(alpha: 0.6)),
          ),
        ],
      ),
    );
  }
}
