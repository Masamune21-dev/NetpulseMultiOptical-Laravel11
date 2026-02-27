import 'package:flutter/material.dart';

import '../auth/session_store.dart';
import 'home_shell.dart';
import 'login_screen.dart';

class Gate extends StatelessWidget {
  const Gate({super.key});

  @override
  Widget build(BuildContext context) {
    final hasToken = (SessionStore.instance.accessToken ?? '').isNotEmpty;
    return hasToken ? const HomeShell() : const LoginScreen();
  }
}

