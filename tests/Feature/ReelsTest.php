<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);


test('reels feed returns only reels with cursor pagination', function () {
    $me = acting();
    for ($i = 1; $i <= 6; $i++) {
        Post::create(['user_id' => $me->id, 'content' => 'reel '.$i, 'type' => 'reel', 'visibility' => 'public']);
    }
    Post::create(['user_id' => $me->id, 'content' => 'just a post', 'type' => 'text', 'visibility' => 'public']);

    $page1 = $this->getJson(route('reels.feed'))->assertOk()->json();
    expect(count($page1['reels']))->toBe(5)
        ->and($page1['reels'][0]['type'])->toBe('reel')
        ->and($page1['reels'][0]['content'])->toBe('reel 6')
        ->and($page1['next_cursor'])->not->toBeNull();

    $page2 = $this->getJson(route('reels.feed').'?cursor='.$page1['next_cursor'])->assertOk()->json();
    expect(count($page2['reels']))->toBe(1)
        ->and($page2['reels'][0]['content'])->toBe('reel 1')
        ->and($page2['next_cursor'])->toBeNull();
});

test('user can upload a reel video', function () {
    Storage::fake('public');
    acting();

    // minimal valid mp4 header so finfo reports video/mp4
    $mp4 = base64_decode('AAAAHGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDE=');
    $file = UploadedFile::fake()->createWithContent('reel.mp4', $mp4);

    $this->post(route('reels.store'), ['video' => $file, 'content' => 'my first reel'], ['Accept' => 'application/json'])
        ->assertCreated();

    $post = Post::where('type', 'reel')->first();
    expect($post)->not->toBeNull()
        ->and($post->media()->count())->toBe(1)
        ->and($post->media->first()->kind)->toBe('video');
    Storage::disk('public')->assertExists($post->media->first()->path);
});

test('reel upload rejects non-video files', function () {
    Storage::fake('public');
    acting();

    $this->post(route('reels.store'), ['video' => UploadedFile::fake()->image('fake.png')], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

test('reels page renders inertia', function () {
    acting();
    $this->get(route('reels.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Reels/Index'));
});

test('watch page lists video posts only', function () {
    $me = acting();
    Post::create(['user_id' => $me->id, 'content' => 'watch this', 'type' => 'video', 'visibility' => 'public']);
    Post::create(['user_id' => $me->id, 'content' => 'also a reel', 'type' => 'reel', 'visibility' => 'public']);
    Post::create(['user_id' => $me->id, 'content' => 'not a video', 'type' => 'text', 'visibility' => 'public']);

    $this->get(route('watch.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('videos', 1)->where('videos.0.content', 'watch this'));
});
