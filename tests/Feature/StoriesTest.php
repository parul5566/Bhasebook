<?php

use App\Models\Story;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('story tray shows own, friends and public stories but not friends-only of strangers', function () {
    $me = acting();
    $friend = User::factory()->create(['email_verified_at' => now()]);
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    SocialService::sendFriendRequest($me, $friend);
    SocialService::acceptFriendRequest($friend, $me->id);

    Story::create(['user_id' => $me->id, 'kind' => 'text', 'text_content' => 'mine', 'background' => 'sunset', 'visibility' => 'friends', 'expires_at' => now()->addHours(20)]);
    Story::create(['user_id' => $friend->id, 'kind' => 'text', 'text_content' => 'friends only', 'background' => 'ocean', 'visibility' => 'friends', 'expires_at' => now()->addHours(20)]);
    Story::create(['user_id' => $stranger->id, 'kind' => 'text', 'text_content' => 'stranger friends', 'background' => 'berry', 'visibility' => 'friends', 'expires_at' => now()->addHours(20)]);
    Story::create(['user_id' => $stranger->id, 'kind' => 'text', 'text_content' => 'stranger public', 'background' => 'bhas', 'visibility' => 'public', 'expires_at' => now()->addHours(20)]);

    $response = $this->getJson(route('stories.tray'))->assertOk();
    $texts = collect($response->json('tray'))->flatMap(fn ($t) => collect($t['stories'])->pluck('text'));
    expect($texts)->toContain('mine')
        ->and($texts)->toContain('friends only')
        ->and($texts)->toContain('stranger public')
        ->and($texts)->not->toContain('stranger friends');
});

test('expired stories are excluded from tray', function () {
    $me = acting();
    Story::create(['user_id' => $me->id, 'kind' => 'text', 'text_content' => 'old', 'visibility' => 'public', 'expires_at' => now()->subHour()]);

    $response = $this->getJson(route('stories.tray'))->assertOk();
    expect(collect($response->json('tray')))->toBeEmpty();
});

test('user can create a text story', function () {
    acting();

    $this->postJson(route('stories.store'), [
        'kind' => 'text', 'text' => 'hello story world', 'background' => 'forest', 'visibility' => 'public',
    ])->assertCreated();

    $story = Story::first();
    expect($story->text_content)->toBe('hello story world')
        ->and($story->background)->toBe('forest')
        ->and($story->expires_at->isFuture())->toBeTrue();
});

test('viewing a story records a view and only owner sees viewers', function () {
    $me = acting();
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $story = Story::create(['user_id' => $me->id, 'kind' => 'text', 'text_content' => 'watch me', 'visibility' => 'public', 'expires_at' => now()->addHours(23)]);

    $this->actingAs($viewer)->postJson(route('stories.view', ['story' => $story]))->assertOk();
    expect(\App\Models\StoryView::count())->toBe(1);

    // duplicate view does not double record
    $this->postJson(route('stories.view', ['story' => $story]))->assertOk();
    expect(\App\Models\StoryView::count())->toBe(1);

    // only owner sees viewers
    $this->getJson(route('stories.viewers', ['story' => $story]))->assertForbidden();
    $this->actingAs($me)->getJson(route('stories.viewers', ['story' => $story]))
        ->assertOk()
        ->assertJsonCount(1, 'viewers');
});

test('owner can delete their story', function () {
    $me = acting();
    $story = Story::create(['user_id' => $me->id, 'kind' => 'text', 'text_content' => 'bye', 'visibility' => 'public', 'expires_at' => now()->addHours(23)]);

    $this->deleteJson(route('stories.destroy', ['story' => $story]))->assertOk();
    expect(Story::count())->toBe(0);
});

test('blocked users stories are hidden', function () {
    $me = acting();
    $enemy = User::factory()->create(['email_verified_at' => now()]);
    SocialService::toggleBlock($me, $enemy);
    Story::create(['user_id' => $enemy->id, 'kind' => 'text', 'text_content' => 'blocked story', 'visibility' => 'public', 'expires_at' => now()->addHours(20)]);

    $response = $this->getJson(route('stories.tray'))->assertOk();
    expect(collect($response->json('tray')))->toBeEmpty();
});
