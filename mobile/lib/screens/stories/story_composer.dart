import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;

import '../../api/api_client.dart';
import '../../theme/app_theme.dart';

/// Story composer — pick photo/video from camera or gallery, add a caption
/// or a text-only story with a gradient background, then upload to
/// POST /api/v1/stories.
class StoryComposer extends StatefulWidget {
  const StoryComposer({super.key});

  @override
  State<StoryComposer> createState() => _StoryComposerState();
}

class _StoryComposerState extends State<StoryComposer> {
  final _text = TextEditingController();
  File? _media;
  bool _isVideo = false;
  String _background = 'bhas';
  bool _busy = false;
  String? _error;

  static const _backgrounds = {
    'bhas': LinearGradient(
        colors: [Color(0xFF3387FB), Color(0xFF1E3A8A)],
        begin: Alignment.topLeft, end: Alignment.bottomRight),
    'sunset': LinearGradient(
        colors: [Color(0xFFff9966), Color(0xFFff5e62)],
        begin: Alignment.topLeft, end: Alignment.bottomRight),
    'forest': LinearGradient(
        colors: [Color(0xFF134E5E), Color(0xFF71B280)],
        begin: Alignment.topLeft, end: Alignment.bottomRight),
    'ocean': LinearGradient(
        colors: [Color(0xFF2E3192), Color(0xFF1BFFFF)],
        begin: Alignment.topLeft, end: Alignment.bottomRight),
    'night': LinearGradient(
        colors: [Color(0xFF141E30), Color(0xFF243B55)],
        begin: Alignment.topLeft, end: Alignment.bottomRight),
  };

  Future<void> _pick(ImageSource source, {bool video = false}) async {
    try {
      final picked = video
          ? await ImagePicker().pickVideo(source: source, maxDuration: const Duration(seconds: 30))
          : await ImagePicker().pickImage(source: source, maxWidth: 1440, imageQuality: 85);
      if (picked == null) return;
      setState(() {
        _media = File(picked.path);
        _isVideo = video;
      });
    } catch (_) {
      setState(() => _error = 'Could not open ${video ? 'camera/video' : 'photos'}. Check permissions.');
    }
  }

  Future<void> _post() async {
    final kind = _media != null ? (_isVideo ? 'video' : 'photo') : 'text';
    if (kind == 'text' && _text.text.trim().isEmpty) {
      setState(() => _error = 'Write something or pick a photo/video.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      http.MultipartFile? file;
      if (_media != null) {
        file = await http.MultipartFile.fromPath('media', _media!.path);
      }
      await ApiClient.instance.upload('/stories', [
        if (file != null) file,
      ], fields: {
        'kind': kind,
        if (kind == 'text') 'text': _text.text.trim(),
        if (kind == 'text') 'background': _background,
      });
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() {
        _busy = false;
        _error = e.message;
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = 'Upload failed — check your connection and try again.';
      });
    }
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasMedia = _media != null;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create story'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: FilledButton(
              onPressed: _busy ? null : _post,
              style: FilledButton.styleFrom(
                backgroundColor: Bhas.primary.shade600,
              ),
              child: _busy
                  ? const SizedBox(width: 18, height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Share'),
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Preview
            AspectRatio(
              aspectRatio: 3 / 4,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: hasMedia
                    ? (_isVideo
                        ? const Center(child: Icon(Icons.play_circle_fill_rounded, size: 64))
                        : Image.file(_media!, fit: BoxFit.cover))
                    : DecoratedBox(
                        decoration: BoxDecoration(gradient: _backgrounds[_background]),
                        child: Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(
                              _text.text.isEmpty ? 'Your text story…' : _text.text,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                  color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700, height: 1.3),
                            ),
                          ),
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 16),
            if (!hasMedia) ...[
              TextField(
                controller: _text,
                maxLines: 2,
                maxLength: 200,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                    labelText: 'Text', hintText: 'What is on your mind?'),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 10,
                children: [
                  for (final entry in _backgrounds.entries)
                    GestureDetector(
                      onTap: () => setState(() => _background = entry.key),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          gradient: entry.value,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: _background == entry.key
                                ? Theme.of(context).colorScheme.primary
                                : Colors.transparent,
                            width: 3,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ],
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.photo_library_rounded),
                    label: const Text('Gallery'),
                    onPressed: _busy ? null : () => _pick(ImageSource.gallery),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.photo_camera_rounded),
                    label: const Text('Camera'),
                    onPressed: _busy ? null : () => _pick(ImageSource.camera),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.videocam_rounded),
                    label: const Text('Video'),
                    onPressed: _busy ? null : () => _pick(ImageSource.gallery, video: true),
                  ),
                ),
              ],
            ),
            if (hasMedia)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: TextButton.icon(
                  icon: const Icon(Icons.close_rounded, size: 18),
                  label: const Text('Remove media (make a text story)'),
                  onPressed: _busy ? null : () => setState(() => _media = null),
                ),
              ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(_error!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
          ],
        ),
      ),
    );
  }
}
