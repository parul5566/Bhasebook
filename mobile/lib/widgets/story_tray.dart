import 'package:flutter/material.dart';
import '../api/api_client.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Stories tray — mirrors GET /api/v1/stories/tray.
class StoryTrayData {
  final int userId;
  final String userName;
  final String? avatarUrl;
  final bool seen;
  final List<StoryItem> stories;

  StoryTrayData({
    required this.userId,
    required this.userName,
    this.avatarUrl,
    required this.seen,
    required this.stories,
  });

  factory StoryTrayData.fromJson(Map<String, dynamic> j) => StoryTrayData(
        userId: j['user']['id'],
        userName: j['user']['name'] ?? '',
        avatarUrl: j['user']['avatar_url'],
        seen: j['seen'] == true,
        stories: ((j['stories'] as List?) ?? [])
            .map((s) => StoryItem.fromJson(s.cast<String, dynamic>()))
            .toList(),
      );

  static List<StoryTrayData> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((t) => StoryTrayData.fromJson(t.cast<String, dynamic>()))
      .toList();
}

class StoryItem {
  final int id;
  final String kind; // text | image | video
  final String? mediaUrl;
  final String? text;
  final String? background;
  final String createdAt;
  final bool seenByMe;

  StoryItem({
    required this.id,
    required this.kind,
    this.mediaUrl,
    this.text,
    this.background,
    required this.createdAt,
    required this.seenByMe,
  });

  factory StoryItem.fromJson(Map<String, dynamic> j) => StoryItem(
        id: j['id'],
        kind: j['kind'] ?? 'text',
        mediaUrl: j['media_url'],
        text: j['text'],
        background: j['background'],
        createdAt: j['created_at'] ?? '',
        seenByMe: j['seen_by_me'] == true,
      );
}

/// Horizontal story tray shown on top of the feed.
class StoryTray extends StatelessWidget {
  final VoidCallback onCreateStory;
  final ValueChanged<StoryTrayData> onOpen;

  const StoryTray({super.key, required this.onCreateStory, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 110,
      child: FutureBuilder<dynamic>(
        future: ApiClient.instance.get('/stories/tray'),
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(
                child: SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2)));
          }
          if (snap.hasError) {
            return const SizedBox.shrink();
          }
          final tray = StoryTrayData.listFrom(snap.data?['tray']);
          return ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            children: [
              // Create-story tile
              GestureDetector(
                onTap: onCreateStory,
                child: Column(
                  children: [
                    Stack(
                      children: [
                        CircleAvatar(
                          radius: 32,
                          backgroundColor: Bhas.primary.shade100,
                          child: Icon(Icons.add_rounded,
                              color: Bhas.primary.shade700, size: 30),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    const Text('Your story',
                        style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              for (final t in tray)
                GestureDetector(
                  onTap: () => onOpen(t),
                  child: Padding(
                    padding: const EdgeInsets.only(right: 4),
                    child: Column(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(3),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: t.seen
                                  ? Colors.grey.shade400
                                  : Bhas.primary.shade500,
                              width: 2.5,
                            ),
                          ),
                          child: UserAvatar(
                              avatarPath: null, name: t.userName, radius: 27),
                        ),
                        const SizedBox(height: 6),
                        SizedBox(
                          width: 64,
                          child: Text(t.userName.split(' ').first,
                              overflow: TextOverflow.ellipsis,
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 12)),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
