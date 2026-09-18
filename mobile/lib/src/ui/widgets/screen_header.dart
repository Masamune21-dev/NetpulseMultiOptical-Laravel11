import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// Kepala layar tab utama (tanpa AppBar): judul Sora 18/700 + keterangan
/// kecil di kiri, tombol ikon 36 px di kanan.
class ScreenHeader extends StatelessWidget {
  const ScreenHeader({super.key, required this.title, this.subtitle, this.leading, this.actions = const []});

  final String title;
  final String? subtitle;
  final Widget? leading;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.only(top: 6, bottom: 2),
      child: Row(
        children: [
          if (leading != null) ...[leading!, const SizedBox(width: 10)],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: t.headlineSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(subtitle!, style: t.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                ],
              ],
            ),
          ),
          for (var i = 0; i < actions.length; i++) ...[
            if (i > 0) const SizedBox(width: NpSpace.sm),
            actions[i],
          ],
        ],
      ),
    );
  }
}

/// Tombol ikon 36 px berlatar permukaan, dengan titik merah opsional.
class HeaderIconButton extends StatelessWidget {
  const HeaderIconButton({super.key, required this.icon, this.onTap, this.tooltip, this.dot = false, this.busy = false});

  final IconData icon;
  final VoidCallback? onTap;
  final String? tooltip;
  final bool dot;

  /// Tampilkan spinner kecil menggantikan ikon.
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final child = Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        color: k.surface,
        borderRadius: BorderRadius.circular(NpRadius.control),
        border: Border.all(color: k.border),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          if (busy)
            SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: k.ink2))
          else
            Icon(icon, size: 18, color: k.ink2),
          if (dot)
            Positioned(
              top: 7,
              right: 7,
              child: Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(shape: BoxShape.circle, color: k.bad, border: Border.all(color: k.surface, width: 2)),
              ),
            ),
        ],
      ),
    );

    final button = Material(
      color: Colors.transparent,
      child: InkWell(borderRadius: BorderRadius.circular(NpRadius.control), onTap: busy ? null : onTap, child: child),
    );
    return tooltip == null ? button : Tooltip(message: tooltip!, child: button);
  }
}

/// Logo kecil Netpulse untuk kepala layar beranda.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 34});

  final double size;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: k.accent, borderRadius: BorderRadius.circular(10)),
      child: Icon(Icons.show_chart_rounded, size: size * 0.55, color: k.onAccent),
    );
  }
}
