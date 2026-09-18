import 'package:flutter/material.dart';

import '../../theme/status.dart';
import '../../theme/tokens.dart';

/// Badge status: pil 22 px, titik 6 px + kata. Warna selalu ditemani kata.
class StatusBadge extends StatelessWidget {
  const StatusBadge(this.status, {super.key, this.label}) : _severity = null;

  /// Badge dengan warna severity alert (`critical` / `warning` / lainnya).
  const StatusBadge.severity(String severity, {super.key, this.label})
      : status = null,
        _severity = severity;

  final NetStatus? status;
  final String? label;
  final String? _severity;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final c = status != null ? k.status(status!) : k.severity(_severity ?? 'info');
    final text = label ?? status?.label ?? _severity ?? '';

    return Container(
      height: 22,
      padding: const EdgeInsets.symmetric(horizontal: NpSpace.sm),
      decoration: BoxDecoration(color: c.bg, borderRadius: BorderRadius.circular(NpRadius.pill)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 6, height: 6, decoration: BoxDecoration(shape: BoxShape.circle, color: c.text)),
          const SizedBox(width: 5),
          Text(
            text,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(color: c.text, letterSpacing: 0.2),
          ),
        ],
      ),
    );
  }
}

/// Titik status tunggal (untuk daftar padat / legenda peta). Selalu ditemani
/// teks di dekatnya oleh pemanggil.
class StatusDot extends StatelessWidget {
  const StatusDot(this.status, {super.key, this.size = 8});

  final NetStatus status;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(shape: BoxShape.circle, color: context.np.status(status).mark),
      );
}
