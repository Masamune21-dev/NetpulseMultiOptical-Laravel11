import 'package:flutter/material.dart';

import 'auth/session_store.dart';
import 'navigation/app_navigator.dart';
import 'push/fcm_service.dart';
import 'theme/app_theme.dart';
import 'ui/gate.dart';

class NetpulseApp extends StatelessWidget {
  const NetpulseApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Netpulse',
      navigatorKey: AppNavigator.navigatorKey,
      theme: buildNetpulseTheme(),
      home: FutureBuilder(
        future: SessionStore.instance.load().then((_) {
          return FcmService.instance.syncToken();
        }),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const _Splash();
          }
          return const Gate();
        },
      ),
    );
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: SizedBox(
          width: 24,
          height: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }
}
