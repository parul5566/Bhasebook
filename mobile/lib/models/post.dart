import 'dart:convert';

/// Post as serialized by PostController::serializePosts (Laravel backend).
class Post {
  final int id;
  final PostAuthor author;
  final Map<String, dynamic>? group;
  final String content;
  final String type; // text | image | video | reel | poll | link
  final String visibility;
  final String? feeling;
  final String? location;
  final List<PostMedia> media;
  final Poll? poll;
  final List<PostTag> tags;
  final SharedPost? sharedPost;
  final Map<String, int> reactionCounts;
  final int reactionTotal;
  final String? myReaction;
  final int commentCount;
  final bool saved;
  final bool pinned;
  final String createdAt;
  final bool edited;
  final bool canEdit;

  Post({
    required this.id,
    required this.author,
    this.group,
    required this.content,
    required this.type,
    required this.visibility,
    this.feeling,
    this.location,
    required this.media,
    this.poll,
    required this.tags,
    this.sharedPost,
    required this.reactionCounts,
    required this.reactionTotal,
    this.myReaction,
    required this.commentCount,
    required this.saved,
    required this.pinned,
    required this.createdAt,
    required this.edited,
    required this.canEdit,
  });

  factory Post.fromJson(Map<String, dynamic> j) => Post(
        id: j['id'],
        author: PostAuthor.fromJson((j['author'] ?? {}).cast<String, dynamic>()),
        group: (j['group'] as Map?)?.cast<String, dynamic>(),
        content: j['content'] ?? '',
        type: j['type'] ?? 'text',
        visibility: j['visibility'] ?? 'public',
        feeling: j['feeling'],
        location: j['location'],
        media: ((j['media'] as List?) ?? [])
            .map((m) => PostMedia.fromJson(m.cast<String, dynamic>()))
            .toList(),
        poll: j['poll'] == null ? null : Poll.fromJson((j['poll'] as Map).cast<String, dynamic>()),
        tags: ((j['tags'] as List?) ?? [])
            .map((t) => PostTag.fromJson(t.cast<String, dynamic>()))
            .toList(),
        sharedPost: j['shared_post'] == null
            ? null
            : SharedPost.fromJson((j['shared_post'] as Map).cast<String, dynamic>()),
        reactionCounts:
            ((j['reaction_counts'] as Map?) ?? {}).map((k, v) => MapEntry(k as String, v as int)),
        reactionTotal: j['reaction_total'] ?? 0,
        myReaction: j['my_reaction'],
        commentCount: j['comment_count'] ?? 0,
        saved: j['saved'] == true,
        pinned: j['pinned'] == true,
        createdAt: j['created_at'] ?? '',
        edited: j['edited'] == true,
        canEdit: j['can_edit'] == true,
      );

  static List<Post> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((p) => Post.fromJson(p.cast<String, dynamic>()))
      .toList();

  bool get hasVideo => media.any((m) => m.kind == 'video');
  PostMedia? get firstVideo {
    for (final m in media) {
      if (m.kind == 'video') return m;
    }
    return null;
  }

  PostMedia? get firstImage {
    for (final m in media) {
      if (m.kind == 'image') return m;
    }
    return null;
  }
}

class PostAuthor {
  final int id;
  final String name;
  final String? avatarUrl;
  final int hue;
  final String type; // user | page

  PostAuthor({
    required this.id,
    required this.name,
    this.avatarUrl,
    this.hue = 210,
    required this.type,
  });

  factory PostAuthor.fromJson(Map<String, dynamic> j) => PostAuthor(
        id: j['id'],
        name: j['name'] ?? '',
        avatarUrl: j['avatar_url'],
        hue: j['hue'] ?? 210,
        type: j['type'] ?? 'user',
      );
}

class PostMedia {
  final String url;
  final String mime;
  final String kind; // image | video

  PostMedia({required this.url, required this.mime, required this.kind});

  factory PostMedia.fromJson(Map<String, dynamic> j) => PostMedia(
        url: j['url'] ?? '',
        mime: j['mime'] ?? '',
        kind: j['kind'] ?? 'image',
      );
}

class PollOption {
  final int id;
  final String text;
  final int votes;
  final int percent;
  final bool voted;

  PollOption({
    required this.id,
    required this.text,
    required this.votes,
    required this.percent,
    required this.voted,
  });

  factory PollOption.fromJson(Map<String, dynamic> j) => PollOption(
        id: j['id'],
        text: j['text'] ?? '',
        votes: j['votes'] ?? 0,
        percent: j['percent'] ?? 0,
        voted: j['voted'] == true,
      );
}

class Poll {
  final List<PollOption> options;
  final int totalVotes;
  final int? myVote;

  Poll({required this.options, required this.totalVotes, this.myVote});

  factory Poll.fromJson(Map<String, dynamic> j) => Poll(
        options: ((j['options'] as List?) ?? [])
            .map((o) => PollOption.fromJson(o.cast<String, dynamic>()))
            .toList(),
        totalVotes: j['total_votes'] ?? 0,
        myVote: j['my_vote'],
      );
}

class PostTag {
  final int id;
  final String name;

  PostTag({required this.id, required this.name});

  factory PostTag.fromJson(Map<String, dynamic> j) =>
      PostTag(id: j['id'], name: j['name'] ?? '');
}

class SharedPost {
  final int id;
  final String content;
  final String? authorName;

  SharedPost({required this.id, required this.content, this.authorName});

  factory SharedPost.fromJson(Map<String, dynamic> j) => SharedPost(
        id: j['id'],
        content: j['content'] ?? '',
        authorName: j['author']?['name'],
      );
}

/// Comment as returned by PostController::comments.
class Comment {
  final int id;
  final int? userId;
  final String authorName;
  final String? authorAvatarUrl;
  final String text;
  final Map<String, int> reactionCounts;
  final int reactionTotal;
  final String? myReaction;
  final String createdAt;

  Comment({
    required this.id,
    this.userId,
    required this.authorName,
    this.authorAvatarUrl,
    required this.text,
    required this.reactionCounts,
    required this.reactionTotal,
    this.myReaction,
    required this.createdAt,
  });

  factory Comment.fromJson(Map<String, dynamic> j) => Comment(
        id: j['id'],
        userId: j['user_id'],
        authorName: j['author']?['name'] ?? j['author_name'] ?? 'User',
        authorAvatarUrl: j['author']?['avatar_url'],
        text: j['text'] ?? j['content'] ?? '',
        reactionCounts: ((j['reaction_counts'] as Map?) ?? {})
            .map((k, v) => MapEntry(k as String, v as int)),
        reactionTotal: j['reaction_total'] ?? 0,
        myReaction: j['my_reaction'],
        createdAt: j['created_at'] ?? '',
      );

  static List<Comment> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((c) => Comment.fromJson(c.cast<String, dynamic>()))
      .toList();
}

/// Shared JSON decode helper.
dynamic decodeJson(String body) => jsonDecode(body);
