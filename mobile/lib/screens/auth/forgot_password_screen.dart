import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../widgets/common.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _email = TextEditingController();
  bool _loading = false;

  Future<void> _submit() async {
    if (!_email.text.contains('@')) {
      showSnackMsg(context, 'Enter a valid email');
      return;
    }
    setState(() => _loading = true);
    try {
      final res = await ApiClient.instance
          .post('/auth/forgot-password', {'email': _email.text.trim()});
      if (mounted) {
        showSnackMsg(context, res['message'] ?? 'Check your email.');
      }
    } catch (e) {
      if (mounted) showSnack(context, e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reset password')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Enter your account email and we will send a reset link.',
                style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 16),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                  hintText: 'Email', prefixIcon: Icon(Icons.mail_outline)),
            ),
            const SizedBox(height: 16),
            PrimaryButton(
                label: 'Send reset link', onPressed: _submit, loading: _loading),
          ],
        ),
      ),
    );
  }
}
