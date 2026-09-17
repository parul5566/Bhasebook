import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../api/api_client.dart';
import '../../state/auth_state.dart';
import '../../widgets/common.dart';

/// Profile & settings editor — mirrors the web Profile/Edit form
/// (PATCH /api/v1/profile).
class ProfileEditScreen extends StatefulWidget {
  const ProfileEditScreen({super.key});

  @override
  State<ProfileEditScreen> createState() => _ProfileEditScreenState();
}

class _ProfileEditScreenState extends State<ProfileEditScreen> {
  final _name = TextEditingController();
  final _bio = TextEditingController();
  final _work = TextEditingController();
  final _education = TextEditingController();
  final _location = TextEditingController();
  String? _gender;
  String _profileVisibility = 'public';
  String _postVisibility = 'public';
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ApiClient.instance.get('/profile/me');
      final u = (res['profileUser'] as Map).cast<String, dynamic>();
      _name.text = u['name'] ?? '';
      _bio.text = u['bio'] ?? '';
      _work.text = u['work'] ?? '';
      _education.text = u['education'] ?? '';
      _location.text = u['location'] ?? '';
      _gender = u['gender'];
      setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        showSnack(context, e);
      }
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ApiClient.instance.patch('/profile', {
        'name': _name.text.trim(),
        'bio': _bio.text.trim(),
        'work': _work.text.trim(),
        'education': _education.text.trim(),
        'location': _location.text.trim(),
        if (_gender != null) 'gender': _gender,
        'profile_visibility': _profileVisibility,
        'default_post_visibility': _postVisibility,
      });
      if (mounted) {
        showSnackMsg(context, 'Profile updated.');
        context.pop();
      }
    } catch (e) {
      if (mounted) showSnack(context, e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit profile')),
      body: _loading
          ? const LoadingView()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                    controller: _name,
                    decoration: const InputDecoration(labelText: 'Name')),
                const SizedBox(height: 12),
                TextField(
                    controller: _bio,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Bio')),
                const SizedBox(height: 12),
                TextField(
                    controller: _work,
                    decoration:
                        const InputDecoration(labelText: 'Work')),
                const SizedBox(height: 12),
                TextField(
                    controller: _education,
                    decoration:
                        const InputDecoration(labelText: 'Education')),
                const SizedBox(height: 12),
                TextField(
                    controller: _location,
                    decoration:
                        const InputDecoration(labelText: 'Location')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: _gender,
                  decoration: const InputDecoration(labelText: 'Gender'),
                  items: const [
                    DropdownMenuItem(value: 'male', child: Text('Male')),
                    DropdownMenuItem(value: 'female', child: Text('Female')),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                    DropdownMenuItem(
                        value: 'prefer_not_to_say',
                        child: Text('Prefer not to say')),
                  ],
                  onChanged: (v) => setState(() => _gender = v),
                ),
                const SizedBox(height: 12),
                Text('Who can see my profile',
                    style: Theme.of(context).textTheme.titleSmall),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'public', label: Text('🌍')),
                    ButtonSegment(value: 'friends', label: Text('👥')),
                    ButtonSegment(value: 'friends_of_friends', label: Text('🌐')),
                  ],
                  selected: {_profileVisibility},
                  onSelectionChanged: (s) =>
                      setState(() => _profileVisibility = s.first),
                ),
                const SizedBox(height: 16),
                Text('Default post visibility',
                    style: Theme.of(context).textTheme.titleSmall),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'public', label: Text('🌍')),
                    ButtonSegment(value: 'friends', label: Text('👥')),
                    ButtonSegment(value: 'private', label: Text('🔒')),
                  ],
                  selected: {_postVisibility},
                  onSelectionChanged: (s) =>
                      setState(() => _postVisibility = s.first),
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _saving ? null : _save,
                    child: _saving
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Save changes'),
                  ),
                ),
                const SizedBox(height: 24),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.logout_rounded, color: Colors.redAccent),
                  title: const Text('Log out'),
                  onTap: () async {
                    await context.read<AuthState>().logout();
                    if (context.mounted) context.go('/login');
                  },
                ),
              ],
            ),
    );
  }
}
