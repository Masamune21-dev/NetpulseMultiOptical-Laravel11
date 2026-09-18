import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// Kartu section: permukaan + garis + radius kartu + bayangan token.
/// Header opsional (judul Sora 14/700 di kiri, tautan aksi di kanan).
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    this.title,
    this.actionLabel,
    this.onAction,
    this.padding,
    this.clip = false,
    required this.child,
  });

  final String? title;
  final String? actionLabel;
  final VoidCallback? onAction;

  /// Padding isi. Default tanpa padding (daftar baris memasang paddingnya
  /// sendiri); pakai [NpSpace.card] untuk konten bebas.
  final EdgeInsetsGeometry? padding;

  /// Potong isi mengikuti radius (untuk InkWell / daftar yang menyentuh tepi).
  final bool clip;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final hasHeader = title != null;

    Widget body = child;
    if (padding != null) body = Padding(padding: padding!, child: body);

    return Container(
      clipBehavior: clip ? Clip.antiAlias : Clip.none,
      decoration: BoxDecoration(
        color: k.surface,
        borderRadius: BorderRadius.circular(NpRadius.card),
        border: Border.all(color: k.border),
        boxShadow: k.shadow,
      ),
      child: hasHeader
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(NpSpace.lg, 14, NpSpace.lg, 0),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: Text(title!, style: Theme.of(context).textTheme.titleMedium, overflow: TextOverflow.ellipsis),
                      ),
                      if (actionLabel != null)
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: onAction,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: NpSpace.xs),
                            child: Text(
                              actionLabel!,
                              style: Theme.of(context).textTheme.labelMedium?.copyWith(color: k.accent),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                body,
              ],
            )
          : body,
    );
  }
}

/// Pemisah baris di dalam [SectionCard]: garis 1 px warna border.
class RowDivider extends StatelessWidget {
  const RowDivider({super.key});

  @override
  Widget build(BuildContext context) => Divider(height: 1, thickness: 1, color: context.np.border);
}
