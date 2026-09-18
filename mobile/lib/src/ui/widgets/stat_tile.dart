import 'package:flutter/material.dart';

import '../../theme/status.dart';
import '../../theme/tokens.dart';

/// Ubin KPI: label kecil · angka Sora 24/800 (+ sufiks) · catatan kaki dengan
/// titik status. Dipakai berpasangan dalam grid 2 kolom.
class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.label,
    required this.value,
    this.suffix,
    this.foot,
    this.footStatus,
    this.onTap,
  });

  final String label;
  final String value;

  /// Teks kecil setelah angka, mis. "/ 316".
  final String? suffix;
  final String? foot;

  /// Warna titik di depan [foot]; null = tanpa titik.
  final NetStatus? footStatus;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;

    final tile = Container(
      padding: const EdgeInsets.fromLTRB(NpSpace.lg, 14, NpSpace.lg, 14),
      decoration: BoxDecoration(
        color: k.surface,
        borderRadius: BorderRadius.circular(NpRadius.card),
        border: Border.all(color: k.border),
        boxShadow: k.shadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: t.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 6),
          Text.rich(
            TextSpan(
              text: value,
              style: t.displaySmall,
              children: [
                if (suffix != null)
                  TextSpan(
                    text: ' $suffix',
                    style: t.labelMedium?.copyWith(color: k.ink3, fontWeight: FontWeight.w600),
                  ),
              ],
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          if (foot != null) ...[
            const SizedBox(height: 6),
            Row(
              children: [
                if (footStatus != null) ...[
                  Container(
                    width: 6,
                    height: 6,
                    decoration: BoxDecoration(shape: BoxShape.circle, color: k.status(footStatus!).mark),
                  ),
                  const SizedBox(width: 6),
                ],
                Expanded(
                  child: Text(
                    foot!,
                    style: t.bodySmall?.copyWith(fontSize: 11.5),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );

    if (onTap == null) return tile;
    return Material(
      color: Colors.transparent,
      child: InkWell(borderRadius: BorderRadius.circular(NpRadius.card), onTap: onTap, child: tile),
    );
  }
}
