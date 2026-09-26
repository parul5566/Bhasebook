import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app_router.dart';
import 'services/push_service.dart';
import 'state/auth_state.dart';
import 'theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final auth = AuthState();
  runApp(BhasebookApp(auth: auth));
}

class BhasebookApp extends StatelessWidget {
  final AuthState auth;
  const BhasebookApp({super.key, required this.auth});

  @override
  Widget build(BuildContext context) {
    final router = buildRouter(auth);
    // Push (FCM) — degrades silently when Firebase isn't configured.
    PushService.init(context);
    return ChangeNotifierProvider.value(
      value: auth,
      child: MaterialApp.router(
        title: 'Bhasebook',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: ThemeMode.system,
        routerConfig: router,
      ),
    );
  }
}
