import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// Keadaan memuat / gagal / kosong yang seragam. Semua bisa di-scroll supaya
/// RefreshIndicator tetap bekerja di atasnya.
class AsyncState extends StatelessWidget {
  const AsyncState.loading({super.key, this.message = 'Memuat…'})
      : _kind = _Kind.loading,
        onRetry = null,
        icon = null;

  const AsyncState.error({super.key, required this.message, this.onRetry})
      : _kind = _Kind.error,
        icon = null;

  const AsyncState.empty({super.key, required this.message, this.icon, this.onRetry})
      : _kind = _Kind.empty;

  final _Kind _kind;
  final String message;
  final VoidCallback? onRetry;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;

    final Widget glyph = switch (_kind) {
      _Kind.loading => SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5, color: k.accent)),
      _Kind.error => Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(color: k.badBg, borderRadius: BorderRadius.circular(NpRadius.card)),
          child: Icon(Icons.error_outline_rounded, color: k.badText, size: 24),
        ),
      _Kind.empty => Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(color: k.surface2, borderRadius: BorderRadius.circular(NpRadius.card)),
          child: Icon(icon ?? Icons.inbox_outlined, color: k.ink3, size: 24),
        ),
    };

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(NpSpace.xxl, 72, NpSpace.xxl, NpSpace.xxl),
      children: [
        Center(
          child: Column(
            children: [
              glyph,
              const SizedBox(height: NpSpace.md),
              Text(
                message,
                textAlign: TextAlign.center,
                style: t.bodyMedium?.copyWith(color: _kind == _Kind.loading ? k.ink2 : k.ink),
              ),
              if (onRetry != null) ...[
                const SizedBox(height: NpSpace.lg),
                SizedBox(
                  width: 160,
                  child: OutlinedButton(onPressed: onRetry, child: const Text('Coba lagi')),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

enum _Kind { loading, error, empty }
