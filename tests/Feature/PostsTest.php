<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);


test('user can create a text post with visibility', function () {
    $me = acting();

    $response = $this->postJson(route('posts.store'), [
        'content' => 'Hello Bhasebook! #first #post',
        'type' => 'text',
        'visibility' => 'public',
    ]);

    $response->assertCreated();
    expect(Post::where('user_id', $me->id)->count())->toBe(1);
    $post = Post::first();
    expect($post->hashtags()->count())->toBe(2);
    expect(\App\Models\Hashtag::where('tag', 'first')->exists())->toBeTrue();
});

test('friend-only post hidden from strangers in feed', function () {
    $me = acting();
    $friend = \App\Models\User::factory()->create();
    $stranger = \App\Models\User::factory()->create();
    SocialService::sendFriendRequest($me, $friend);
    SocialService::acceptFriendRequest($friend, $me->id);

    $this->postJson(route('posts.store'), ['content' => 'secret for friends', 'type' => 'text', 'visibility' => 'friends']);

    $friendFeed = $this->actingAs($friend)->getJson(route('feed'))->json('posts');
    expect(count($friendFeed))->toBe(1);

    $strangerFeed = $this->actingAs($stranger)->getJson(route('feed'))->json('posts');
    expect(count($strangerFeed))->toBe(0);
});

test('post with image upload creates media', function () {
    Storage::fake('public');
    acting();

    $response = $this->post(route('posts.store'), [
        'content' => 'my photo',
        'type' => 'photo',
        'visibility' => 'public',
        'media' => [UploadedFile::fake()->image('pic.jpg', 900, 700)],
    ]);

    $response->assertStatus(201);
    $media = Post::first()->media;
    expect($media)->toHaveCount(1);
    expect($media->first()->kind)->toBe('image');
});

test('invalid mime upload is rejected', function () {
    Storage::fake('public');
    acting();

    $response = $this->post(route('posts.store'), [
        'content' => 'bad file',
        'type' => 'photo',
        'visibility' => 'public',
        'media' => [UploadedFile::fake()->create('script.php', 100, 'application/x-php')],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
});

test('poll can be created and voted once', function () {
    $me = acting();
    $other = \App\Models\User::factory()->create();

    $res = $this->postJson(route('posts.store'), [
        'content' => 'Best framework?',
        'type' => 'poll',
        'visibility' => 'public',
        'poll_options' => ['Laravel', 'Symfony'],
    ]);
    $res->assertCreated();

    $post = Post::first();
    $optionId = $post->pollOptions->first()->id;

    $vote = $this->actingAs($other)->postJson(route('posts.vote', $post->id), ['option_id' => $optionId]);
    $vote->assertOk()->assertJsonPath('poll.total_votes', 1);

    $again = $this->postJson(route('posts.vote', $post->id), ['option_id' => $optionId]);
    $again->assertStatus(422);
});

test('reactions can be cycled and toggled off', function () {
    $me = acting();
    $this->postJson(route('posts.store'), ['content' => 'react to me', 'type' => 'text', 'visibility' => 'public']);
    $post = Post::first();

    $this->postJson(route('posts.react', $post->id), ['type' => 'love'])->assertOk()->assertJsonPath('my_reaction', 'love');
    $this->postJson(route('posts.react', $post->id), ['type' => 'haha'])->assertOk()->assertJsonPath('my_reaction', 'haha');
    $this->postJson(route('posts.react', $post->id), ['type' => 'haha'])->assertOk()->assertJsonPath('my_reaction', null);
    expect($post->reactions()->count())->toBe(0);
});

test('comments nest and can be liked and deleted', function () {
    $me = acting();
    $this->postJson(route('posts.store'), ['content' => 'comment here', 'type' => 'text', 'visibility' => 'public']);
    $post = Post::first();

    $c = $this->postJson(route('posts.comments.store', $post->id), ['content' => 'first!'])->assertCreated()->json('comment');
    $reply = $this->postJson(route('posts.comments.store', $post->id), ['content' => 'reply', 'parent_id' => $c['id']])->assertCreated()->json('comment');
    expect($reply['parent_id'])->toBe($c['id']);

    $this->postJson(route('comments.react', $c['id']), ['type' => 'like'])->assertOk()->assertJsonPath('reaction_total', 1);

    $this->deleteJson(route('comments.destroy', $c['id']))->assertOk();
    expect(Comment::whereNull('parent_id')->count())->toBe(0);
});

test('post save and pin toggles', function () {
    $me = acting();
    $this->postJson(route('posts.store'), ['content' => 'pin me', 'type' => 'text', 'visibility' => 'public']);
    $post = Post::first();

    $this->postJson(route('posts.save', $post->id))->assertOk()->assertJsonPath('saved', true);
    $this->postJson(route('posts.save', $post->id))->assertOk()->assertJsonPath('saved', false);

    $this->postJson(route('posts.pin', $post->id))->assertOk()->assertJsonPath('pinned', true);
    $this->postJson(route('posts.pin', $post->id))->assertOk()->assertJsonPath('pinned', false);
});

test('share creates nested post and notifies original author', function () {
    $me = acting();
    $orig = Post::create(['user_id' => $me->id, 'content' => 'original', 'type' => 'text', 'visibility' => 'public']);

    $other = \App\Models\User::factory()->create();
    $res = $this->actingAs($other)->postJson(route('posts.store'), [
        'content' => 'great one',
        'type' => 'share',
        'visibility' => 'public',
        'shared_post_id' => $orig->id,
    ]);
    $res->assertCreated();

    expect(Post::where('shared_post_id', $orig->id)->count())->toBe(1);
    expect(\App\Models\SiteNotification::where('user_id', $me->id)->where('type', 'share')->exists())->toBeTrue();
});

test('post edit and delete by owner only', function () {
    $me = acting();
    $this->postJson(route('posts.store'), ['content' => 'editable', 'type' => 'text', 'visibility' => 'public']);
    $post = Post::first();

    $this->patchJson(route('posts.update', $post->id), ['content' => 'edited text'])->assertOk();
    expect($post->fresh()->content)->toBe('edited text');
    expect($post->fresh()->edited_at)->not->toBeNull();

    $other = \App\Models\User::factory()->create();
    $this->actingAs($other)->patchJson(route('posts.update', $post->id), ['content' => 'hacked'])->assertStatus(403);

    $this->actingAs($me)->deleteJson(route('posts.destroy', $post->id))->assertOk();
    expect(Post::count())->toBe(0);
});

test('hashtags are extracted and hashtag page renders', function () {
    acting();
    $this->postJson(route('posts.store'), ['content' => 'loving #laravel and #tailwind', 'type' => 'text', 'visibility' => 'public']);

    $response = $this->get(route('hashtag.show', 'laravel'));
    $response->assertOk();
});

test('feed pagination uses cursor', function () {
    acting();
    for ($i = 0; $i < 12; $i++) {
        Post::create(['user_id' => auth()->id(), 'content' => "post $i", 'type' => 'text', 'visibility' => 'public']);
    }

    $page1 = $this->getJson(route('feed'))->json();
    expect(count($page1['posts']))->toBe(8);
    expect($page1['next_cursor'])->not->toBeNull();

    $page2 = $this->getJson(route('feed', ['cursor' => $page1['next_cursor']]))->json();
    expect(count($page2['posts']))->toBe(4);
    expect($page2['next_cursor'])->toBeNull();
});

test('blocked user posts hidden from feed', function () {
    $me = acting();
    $enemy = \App\Models\User::factory()->create();
    Post::create(['user_id' => $enemy->id, 'content' => 'enemy public post', 'type' => 'text', 'visibility' => 'public']);

    // before block: visible
    expect(count($this->getJson(route('feed'))->json('posts')))->toBe(1);

    SocialService::toggleBlock($me, $enemy);
    expect(count($this->getJson(route('feed'))->json('posts')))->toBe(0);
});

test('custom visibility post only shows to included users', function () {
    $me = acting();
    $allowed = \App\Models\User::factory()->create();
    SocialService::sendFriendRequest($me, $allowed);
    SocialService::acceptFriendRequest($allowed, $me->id);

    $this->postJson(route('posts.store'), [
        'content' => 'custom secret',
        'type' => 'text',
        'visibility' => 'custom',
        'visibility_user_ids' => [$allowed->id],
    ]);

    $otherFriend = \App\Models\User::factory()->create();
    SocialService::sendFriendRequest($me, $otherFriend);
    SocialService::acceptFriendRequest($otherFriend, $me->id);

    expect(count($this->actingAs($allowed)->getJson(route('feed'))->json('posts')))->toBe(1);
    expect(count($this->actingAs($otherFriend)->getJson(route('feed'))->json('posts')))->toBe(0);
});
