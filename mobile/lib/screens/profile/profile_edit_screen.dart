import 'dart:io';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;

import '../../api/api_client.dart';
import '../../state/auth_state.dart';
import '../../widgets/common.dart';

/// Profile & settings editor — mirrors the web Profile/Edit form
/// (PATCH /api/v1/profile), including avatar/cover photo upload.
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
  String _email = '';
  String _profileVisibility = 'public';
  String _postVisibility = 'public';
  String? _avatarUrl;
  String? _coverUrl;
  File? _newAvatar;
  File? _newCover;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _bio.dispose();
    _work.dispose();
    _education.dispose();
    _location.dispose();
    super.dispose();
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
      _email = u['email'] ?? '';
      _avatarUrl = u['avatar_url'];
      _coverUrl = u['cover_url'];
      setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        showSnack(context, e);
      }
    }
  }

  Future<void> _pickPhoto({required bool avatar}) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_rounded),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    try {
      final picked = avatar
          ? await ImagePicker()
              .pickImage(source: source, maxWidth: 1024, imageQuality: 85)
          : await ImagePicker()
              .pickImage(source: source, maxWidth: 1920, imageQuality: 85);
      if (picked == null) return;
      final f = File(picked.path);
      if (!mounted) return;
      setState(() {
        _error = null;
        if (avatar) _newAvatar = f;
        _newCover = avatar ? _newCover : f;
      });
    } catch (_) {
      if (mounted) {
        setState(() => _error =
            'Could not open photos. Check that Bhasebook has photo permissions.');
      }
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      if (_newAvatar != null || _newCover != null) {
        // Multipart PATCH — text fields + any new photos in one request.
        final fields = <String, String>{
          'name': _name.text.trim(),
          'email': _email,
          'bio': _bio.text.trim(),
          'work': _work.text.trim(),
          'education': _education.text.trim(),
          'location': _location.text.trim(),
          'profile_visibility': _profileVisibility,
          'default_post_visibility': _postVisibility,
          if (_gender != null) 'gender': _gender!,
        };
        final files = <http.MultipartFile>[
          if (_newAvatar != null)
            await http.MultipartFile.fromPath('avatar', _newAvatar!.path),
          if (_newCover != null)
            await http.MultipartFile.fromPath('cover', _newCover!.path),
        ];
        await ApiClient.instance.uploadPatch('/profile', files, fields: fields);
      } else {
        await ApiClient.instance.patch('/profile', {
          'name': _name.text.trim(),
          'email': _email,
          'bio': _bio.text.trim(),
          'work': _work.text.trim(),
          'education': _education.text.trim(),
          'location': _location.text.trim(),
          if (_gender != null) 'gender': _gender,
          'profile_visibility': _profileVisibility,
          'default_post_visibility': _postVisibility,
        });
      }
      if (mounted) {
        showSnackMsg(context, 'Profile updated.');
        context.pop(true);
      }
    } catch (e) {
      if (mounted) {
        setState(() =>
            _error = e is ApiException ? e.message : 'Could not save profile.');
      }
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
                // Cover preview + picker
                GestureDetector(
                  onTap: _saving ? null : () => _pickPhoto(avatar: false),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: Stack(
                      alignment: Alignment.bottomRight,
                      children: [
                        AspectRatio(
                          aspectRatio: 3 / 1,
                          child: _newCover != null
                              ? Image.file(_newCover!, fit: BoxFit.cover)
                              : (_coverUrl != null
                                  ? Image.network(_coverUrl!, fit: BoxFit.cover,
                                      errorBuilder: (_, __, ___) =>
                                          Container(color: Theme.of(context).colorScheme.primaryContainer))
                                  : Container(color: Theme.of(context).colorScheme.primaryContainer)),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(8),
                          child: Chip(
                            avatar: const Icon(Icons.edit_rounded, size: 16),
                            label: Text(
                                _coverUrl == null && _newCover == null
                                    ? 'Add cover'
                                    : _newCover != null
                                        ? 'Cover selected ✓'
                                        : 'Change cover'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                // Avatar preview + picker
                Center(
                  child: GestureDetector(
                    onTap: _saving ? null : () => _pickPhoto(avatar: true),
                    child: Stack(
                      children: [
                        CircleAvatar(
                          radius: 44,
                          backgroundColor:
                              Theme.of(context).colorScheme.primaryContainer,
                          backgroundImage: _newAvatar != null
                              ? FileImage(_newAvatar!)
                              : (_avatarUrl != null
                                  ? NetworkImage(_avatarUrl!)
                                  : null) as ImageProvider?,
                          child: (_newAvatar == null && _avatarUrl == null)
                              ? Text(
                                  _name.text.isNotEmpty
                                      ? _name.text
                                          .split(' ')
                                          .map((p) => p[0])
                                          .take(2)
                                          .join()
                                          .toUpperCase()
                                      : '?',
                                  style: const TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold),
                                )
                              : null,
                        ),
                        Positioned(
                          right: 0,
                          bottom: 0,
                          child: CircleAvatar(
                            radius: 15,
                            backgroundColor:
                                Theme.of(context).colorScheme.primary,
                            child: const Icon(Icons.photo_camera_rounded,
                                size: 16, color: Colors.white),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                Center(
                  child: TextButton(
                    onPressed: _saving ? null : () => _pickPhoto(avatar: true),
                    child: Text(_newAvatar != null
                        ? 'Photo selected ✓ — tap Save to apply'
                        : 'Change profile photo'),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                    controller: _name,
                    decoration: const InputDecoration(labelText: 'Name')),
                const SizedBox(height: 12),
                TextField(
                    controller: _bio,
                    maxLines: 3,
                    maxLength: 500,
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
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                  ),
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
