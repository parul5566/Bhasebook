<?php

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\Post;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('search finds people, posts, groups, pages and hashtags', function () {
    $me = acting();
    $other = User::factory()->create(['name' => 'Zoey Zephyr', 'email_verified_at' => now()]);

    $post = Post::create(['user_id' => $other->id, 'content' => 'I love matcha lattes', 'type' => 'text', 'visibility' => 'public']);
    $group = Group::create(['name' => 'Matcha Lovers', 'privacy' => 'public', 'created_by' => $other->id]);
    $page = \App\Models\Page::create(['name' => 'Matcha World', 'category' => 'Brand', 'created_by' => $other->id]);
    $post->hashtags()->sync([\App\Models\Hashtag::firstOrCreate(['tag' => 'matcha'])->id]);

    $response = $this->get(route('search.show', ['q' => 'matcha']));

    $response->assertOk();
    $response->assertInertia(fn ($page_) => $page_
        ->where('results.posts.0.id', $post->id)
        ->where('results.groups.0.id', $group->id)
        ->where('results.pages.0.id', $page->id)
        ->where('results.hashtags.0.tag', 'matcha'));

    // People search hits name
    $response = $this->get(route('search.show', ['q' => 'Zoey']));
    $response->assertInertia(fn ($page_) => $page_->where('results.people.0.id', $other->id));
});

test('blocked users never appear in search results', function () {
    $me = acting();
    $bad = User::factory()->create(['name' => 'Blocked Barney', 'email_verified_at' => now()]);
    SocialService::toggleBlock($me, $bad);

    $response = $this->get(route('search.show', ['q' => 'Barney']));
    $response->assertOk();
    $response->assertInertia(fn ($page_) => $page_->missing('results.people.0'));
});

test('friend-only posts are hidden from search for strangers', function () {
    $me = acting();
    $author = User::factory()->create(['email_verified_at' => now()]);
    Post::create(['user_id' => $author->id, 'content' => 'secret garden party', 'type' => 'text', 'visibility' => 'friends']);

    $response = $this->get(route('search.show', ['q' => 'garden', 'tab' => 'posts']));
    $response->assertOk();
    $response->assertInertia(fn ($page_) => $page_->missing('results.posts.0'));
});

test('search suggestions endpoint returns grouped results', function () {
    $me = acting();
    User::factory()->create(['name' => 'Maya Matcha', 'email_verified_at' => now()]);

    $response = $this->getJson(route('search.suggest', ['q' => 'maya']));
    $response->assertOk()->assertJsonCount(1, 'suggestions');
    expect($response->json('suggestions.0.type'))->toBe('person');
});

test('recent searches are recorded and clearable', function () {
    $me = acting();
    $this->get(route('search.show', ['q' => 'puppies']));
    $this->assertDatabaseHas('search_histories', ['user_id' => $me->id, 'term' => 'puppies']);

    $this->postJson(route('search.recents.clear'))->assertOk();
    $this->assertDatabaseMissing('search_histories', ['user_id' => $me->id]);
});
