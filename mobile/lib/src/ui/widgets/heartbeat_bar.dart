import 'package:flutter/material.dart';

import '../../theme/status.dart';
import '../../theme/tokens.dart';

/// Bar riwayat status per jam (ala Uptime Kuma): satu batang per bucket,
/// terlama di kiri. Input string huruf dari API: u/w/d/n.
class HeartbeatBar extends StatelessWidget {
  const HeartbeatBar(this.history, {super.key, this.height = 14, this.gap = 2});

  final String history;
  final double height;
  final double gap;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final chars = history.isEmpty ? 'n' * 24 : history;

    return Semantics(
      label: 'Riwayat 24 jam: ${_summary(chars)}',
      child: SizedBox(
        height: height,
        child: Row(
          children: [
            for (var i = 0; i < chars.length; i++) ...[
              if (i > 0) SizedBox(width: gap),
              Expanded(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: k.status(NetStatusX.fromHistoryChar(chars[i])).mark,
                    borderRadius: BorderRadius.circular(2),
                  ),
                  child: const SizedBox.expand(),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static String _summary(String s) {
    final down = s.split('').where((c) => c == 'd').length;
    final warn = s.split('').where((c) => c == 'w').length;
    if (down == 0 && warn == 0) return 'up sepanjang hari';
    return '$down jam down, $warn jam marjinal';
  }
}
