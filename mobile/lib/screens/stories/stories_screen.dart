import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';
import '../../widgets/story_tray.dart';

/// Full-screen story viewer with tap-to-advance and progress bars.
class StoryViewer extends StatefulWidget {
  final List<StoryTrayData> tray;
  final int initialIndex;

  const StoryViewer({super.key, required this.tray, required this.initialIndex});

  @override
  State<StoryViewer> createState() => _StoryViewerState();
}

class _StoryViewerState extends State<StoryViewer>
    with SingleTickerProviderStateMixin {
  late int _userIndex;
  int _storyIndex = 0;
  late final AnimationController _progress =
      AnimationController(vsync: this, duration: const Duration(seconds: 5))
        ..addStatusListener((status) {
          if (status == AnimationStatus.completed) _next();
        });

  @override
  void initState() {
    super.initState();
    _userIndex = widget.initialIndex;
    _markSeen();
    _progress.forward();
  }

  StoryTrayData get _current => widget.tray[_userIndex];
  StoryItem get _story => _current.stories[_storyIndex];

  void _next() {
    if (_storyIndex < _current.stories.length - 1) {
      setState(() => _storyIndex++);
      _markSeen();
      _progress.forward(from: 0);
    } else if (_userIndex < widget.tray.length - 1) {
      setState(() {
        _userIndex++;
        _storyIndex = 0;
      });
      _markSeen();
      _progress.forward(from: 0);
    } else {
      Navigator.of(context).pop();
    }
  }

  void _prev() {
    if (_storyIndex > 0) {
      setState(() => _storyIndex--);
      _progress.forward(from: 0);
    } else if (_userIndex > 0) {
      setState(() {
        _userIndex--;
        _storyIndex = 0;
      });
      _progress.forward(from: 0);
    } else {
      _progress.forward(from: 0);
    }
  }

  Future<void> _markSeen() async {
    try {
      await ApiClient.instance.post('/stories/${_story.id}/view', {});
    } catch (_) {}
  }

  @override
  void dispose() {
    _progress.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bg = _backgroundFor(_story.background);
    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTapDown: (d) =>
            d.localPosition.dx > MediaQuery.of(context).size.width / 2
                ? _next()
                : _prev(),
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Media or gradient background
            if (_story.kind == 'image' && _story.mediaUrl != null)
              Image.network(_story.mediaUrl!, fit: BoxFit.cover)
            else if (_story.kind == 'video' && _story.mediaUrl != null)
              const Center(
                child: Icon(Icons.play_circle_fill_rounded,
                    size: 72, color: Colors.white70),
              )
            else
              Container(decoration: BoxDecoration(gradient: bg)),
            SafeArea(
              child: Column(
                children: [
                  // Progress bars
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
                    child: Row(
                      children: [
                        for (var i = 0; i < _current.stories.length; i++)
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 2),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: SizedBox(
                                  height: 3,
                                  child: Stack(
                                    children: [
                                      Container(color: Colors.white24),
                                      if (i < _storyIndex)
                                        Container(color: Colors.white)
                                      else if (i == _storyIndex)
                                        AnimatedBuilder(
                                          animation: _progress,
                                          builder: (_, __) => FractionallySizedBox(
                                            widthFactor: _progress.value,
                                            child: Container(color: Colors.white),
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  // Header
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        UserAvatar(avatarPath: null, name: _current.userName, radius: 16),
                        const SizedBox(width: 8),
                        Text(_current.userName,
                            style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w700)),
                        const SizedBox(width: 8),
                        Text(_story.createdAt,
                            style:
                                const TextStyle(color: Colors.white70, fontSize: 12)),
                        const Spacer(),
                        IconButton(
                          icon: const Icon(Icons.close_rounded,
                              color: Colors.white),
                          onPressed: () => Navigator.of(context).pop(),
                        ),
                      ],
                    ),
                  ),
                  // Text content
                  Expanded(
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Text(
                          _story.text ?? '',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 26,
                              fontWeight: FontWeight.w700,
                              height: 1.3),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  LinearGradient _backgroundFor(String? name) {
    switch (name) {
      case 'sunset':
        return const LinearGradient(
            colors: [Color(0xFFff9966), Color(0xFFff5e62)],
            begin: Alignment.topLeft, end: Alignment.bottomRight);
      case 'forest':
        return const LinearGradient(
            colors: [Color(0xFF134E5E), Color(0xFF71B280)],
            begin: Alignment.topLeft, end: Alignment.bottomRight);
      case 'ocean':
        return const LinearGradient(
            colors: [Color(0xFF2E3192), Color(0xFF1BFFFF)],
            begin: Alignment.topLeft, end: Alignment.bottomRight);
      case 'night':
        return const LinearGradient(
            colors: [Color(0xFF141E30), Color(0xFF243B55)],
            begin: Alignment.topLeft, end: Alignment.bottomRight);
      default:
        return LinearGradient(
            colors: [Bhas.primary.shade600, Bhas.primary.shade900],
            begin: Alignment.topLeft, end: Alignment.bottomRight);
    }
  }
}

/// Stories index screen — tray + create button.
class StoriesScreen extends StatelessWidget {
  const StoriesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Stories')),
      body: FutureBuilder<dynamic>(
        future: ApiClient.instance.get('/stories/tray'),
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const LoadingView();
          }
          if (snap.hasError) {
            return ErrorView(
                message: 'Could not load stories.', onRetry: () => (context as Element).markNeedsBuild());
          }
          final tray = StoryTrayData.listFrom(snap.data?['tray']);
          if (tray.isEmpty) {
            return const EmptyView(
                icon: Icons.auto_awesome_rounded,
                message: 'No stories yet.\nBe the first to share one!');
          }
          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: tray.length,
            itemBuilder: (context, i) => ListTile(
              leading: UserAvatar(avatarPath: null, name: tray[i].userName, radius: 22),
              title: Text(tray[i].userName,
                  style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text('${tray[i].stories.length} stories'),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => _open(context, tray, i),
            ),
          );
        },
      ),
    );
  }
}

void _open(BuildContext context, List<StoryTrayData> tray, int index) {
  Navigator.of(context).push(MaterialPageRoute(
    builder: (_) => StoryViewer(tray: tray, initialIndex: index),
  ));
}

/// Re-exported for feed integration.
typedef StoryTrayWidget = StoryTray;
typedef PostModel = Post;
typedef PostHost = PostCardHost;
