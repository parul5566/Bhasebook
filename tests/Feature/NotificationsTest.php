<?php

use App\Models\SiteNotification;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('notification page renders items and tabs', function () {
    $me = acting();
    $actor = User::factory()->create(['name' => 'Nora Notifier', 'email_verified_at' => now()]);
    SocialService::sendFriendRequest($actor, $me);

    $response = $this->get(route('notifications.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items', 1)
        ->where('unread.all', 1)
        ->where('items.0.type', 'friend_request'));
});

test('tab filter shows only matching category', function () {
    $me = acting();
    $actor = User::factory()->create(['email_verified_at' => now()]);
    SocialService::toggleFollow($actor, $me); // follow category
    SiteNotification::create([
        'user_id' => $me->id, 'actor_id' => $actor->id, 'type' => 'reaction',
        'category' => 'reaction', 'data' => ['text' => 'reacted to your post'],
    ]);

    $response = $this->get(route('notifications.index', ['tab' => 'follow']));

    $response->assertInertia(fn ($page) => $page
        ->has('items', 1)
        ->where('items.0.category', 'follow'));
});

test('mark one notification as read', function () {
    $me = acting();
    $actor = User::factory()->create(['email_verified_at' => now()]);
    $n = SiteNotification::create([
        'user_id' => $me->id, 'actor_id' => $actor->id, 'type' => 'follow',
        'category' => 'follow', 'data' => ['text' => 'started following you'],
    ]);

    $this->post(route('notifications.read', ['notification' => $n->id]))->assertRedirect();

    expect($n->fresh()->read_at)->not->toBeNull();
    expect($me->fresh()->unreadNotificationsCount())->toBe(0);
});

test('mark all notifications as read', function () {
    $me = acting();
    $actor = User::factory()->create(['email_verified_at' => now()]);
    SiteNotification::create(['user_id' => $me->id, 'type' => 'follow', 'category' => 'follow', 'data' => ['text' => 'a']]);
    SiteNotification::create(['user_id' => $me->id, 'type' => 'reaction', 'category' => 'reaction', 'data' => ['text' => 'b']]);

    $this->post(route('notifications.readAll'))->assertRedirect();

    expect($me->fresh()->unreadNotificationsCount())->toBe(0);
});

test('cannot read someone elses notification', function () {
    $me = acting();
    $other = User::factory()->create();
    $n = SiteNotification::create(['user_id' => $other->id, 'type' => 'follow', 'category' => 'follow', 'data' => ['text' => 'x']]);

    $this->post(route('notifications.read', ['notification' => $n->id]))->assertForbidden();
});

test('notification preferences can be updated', function () {
    $me = acting();

    $this->post(route('notifications.settings'), [
        'prefs' => ['reaction' => '0', 'comment' => '1'],
    ])->assertRedirect();

    $prefs = $me->fresh()->notification_prefs;
    expect($prefs['reaction'])->toBe('0')
        ->and($prefs['comment'])->toBe('1');
});

test('unread count endpoint returns json', function () {
    $me = acting();
    SiteNotification::create(['user_id' => $me->id, 'type' => 'follow', 'category' => 'follow', 'data' => ['text' => 'x']]);

    $this->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJson(['count' => 1]);
});
