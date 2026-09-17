import 'package:flutter_test/flutter_test.dart';
import 'package:bhasebook_mobile/models/post.dart';

void main() {
  test('parses a serialized post', () {
    final p = Post.fromJson({
      'id': 14,
      'author': {'id': 5, 'name': 'Sara Khan', 'avatar_url': null, 'hue': 89, 'type': 'user'},
      'group': null,
      'content': 'Biryani #food',
      'type': 'video',
      'visibility': 'public',
      'media': [
        {'url': 'https://x/y.webm', 'mime': 'video/webm', 'kind': 'video'}
      ],
      'poll': null,
      'tags': [
        {'id': 1, 'name': 'food'}
      ],
      'shared_post': null,
      'reaction_counts': {'love': 1, 'like': 1},
      'reaction_total': 2,
      'my_reaction': 'love',
      'comment_count': 3,
      'saved': false,
      'pinned': false,
      'created_at': '2 hours ago',
      'edited': false,
      'can_edit': false,
    });
    expect(p.id, 14);
    expect(p.author.name, 'Sara Khan');
    expect(p.hasVideo, isTrue);
    expect(p.firstVideo!.url, 'https://x/y.webm');
    expect(p.reactionCounts['love'], 1);
    expect(p.myReaction, 'love');
  });

  test('parses a poll post', () {
    final p = Post.fromJson({
      'id': 1,
      'author': {'id': 1, 'name': 'A', 'type': 'user'},
      'content': '',
      'type': 'poll',
      'visibility': 'public',
      'media': [],
      'tags': [],
      'reaction_counts': {},
      'reaction_total': 0,
      'comment_count': 0,
      'saved': false,
      'pinned': false,
      'created_at': '',
      'edited': false,
      'can_edit': true,
      'poll': {
        'options': [
          {'id': 1, 'text': 'Gym', 'votes': 1, 'percent': 33, 'voted': false},
          {'id': 2, 'text': 'Rest', 'votes': 2, 'percent': 67, 'voted': true},
        ],
        'total_votes': 3,
        'my_vote': 2,
      },
    });
    expect(p.poll, isNotNull);
    expect(p.poll!.options.length, 2);
    expect(p.poll!.myVote, 2);
    expect(p.poll!.options[1].voted, isTrue);
  });

  test('listFrom handles null/empty', () {
    expect(Post.listFrom(null), isEmpty);
    expect(Post.listFrom([]), isEmpty);
  });
}
