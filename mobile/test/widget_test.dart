import 'package:flutter_test/flutter_test.dart';
import 'package:bhasebook_mobile/main.dart';
import 'package:bhasebook_mobile/state/auth_state.dart';

void main() {
  testWidgets('app builds and shows login when unauthenticated', (tester) async {
    final auth = AuthState();
    auth.initialized = true;
    await tester.pumpWidget(BhasebookApp(auth: auth));
    await tester.pumpAndSettle();
    expect(find.text('Bhasebook'), findsOneWidget);
    expect(find.text('Log in'), findsOneWidget);
  });
}
