<?php

use App\Models\Friendship;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUsers(): array
{
    return [
        User::factory()->create(['name' => 'Asha Verma']),
        User::factory()->create(['name' => 'Ravi Kumar']),
    ];
}

test('friend request can be sent and accepted', function () {
    [$asha, $ravi] = makeUsers();

    $this->actingAs($asha)->post(route('friends.request', $ravi->id));
    expect(Friendship::where('user_id', $asha->id)->where('friend_id', $ravi->id)->where('status', 'pending')->exists())->toBeTrue();
    expect($asha->isFriendWith($ravi->id))->toBeFalse();

    $this->actingAs($ravi)->post(route('friends.accept', $asha->id));
    expect($asha->isFriendWith($ravi->id))->toBeTrue();
    expect($ravi->isFriendWith($asha->id))->toBeTrue();
    expect($asha->friendsCount())->toBe(1);
});

test('duplicate friend request is rejected', function () {
    [$asha, $ravi] = makeUsers();
    SocialService::sendFriendRequest($asha, $ravi);

    $res = SocialService::sendFriendRequest($asha, $ravi);
    expect($res['ok'])->toBeFalse();
});

test('reverse request auto-accepts', function () {
    [$asha, $ravi] = makeUsers();
    SocialService::sendFriendRequest($asha, $ravi);

    $res = SocialService::sendFriendRequest($ravi, $asha);
    expect($res['ok'])->toBeTrue();
    expect($asha->isFriendWith($ravi->id))->toBeTrue();
});

test('friend request can be declined and cancelled', function () {
    [$asha, $ravi] = makeUsers();
    SocialService::sendFriendRequest($asha, $ravi);

    $this->actingAs($ravi)->post(route('friends.decline', $asha->id));
    expect(Friendship::count())->toBe(0);

    SocialService::sendFriendRequest($asha, $ravi);
    $this->actingAs($asha)->post(route('friends.cancel', $ravi->id));
    expect(Friendship::count())->toBe(0);
});

test('unfriend removes friendship', function () {
    [$asha, $ravi] = makeUsers();
    SocialService::sendFriendRequest($asha, $ravi);
    SocialService::acceptFriendRequest($ravi, $asha->id);

    $this->actingAs($asha)->post(route('friends.unfriend', $ravi->id));
    expect($asha->isFriendWith($ravi->id))->toBeFalse();
});

test('follow can be toggled and notifies', function () {
    [$asha, $ravi] = makeUsers();

    $this->actingAs($asha)->post(route('users.follow', $ravi->id));
    expect(\App\Models\Follow::where('follower_id', $asha->id)->where('followee_id', $ravi->id)->exists())->toBeTrue();
    expect(\App\Models\SiteNotification::where('user_id', $ravi->id)->where('type', 'follow')->exists())->toBeTrue();

    $this->actingAs($asha)->post(route('users.follow', $ravi->id));
    expect(\App\Models\Follow::count())->toBe(0);
});

test('block removes friendship and follow both ways', function () {
    [$asha, $ravi] = makeUsers();
    SocialService::sendFriendRequest($asha, $ravi);
    SocialService::acceptFriendRequest($ravi, $asha->id);
    SocialService::toggleFollow($asha, $ravi);

    $this->actingAs($asha)->post(route('users.block', $ravi->id));
    expect($asha->isFriendWith($ravi->id))->toBeFalse();
    expect(\App\Models\Follow::count())->toBe(0);
    expect(\App\Models\Block::count())->toBe(1);

    // blocked pair cannot send requests
    $res = SocialService::sendFriendRequest($ravi, $asha);
    expect($res['ok'])->toBeFalse();
});

test('friend request privacy is enforced', function () {
    [$asha, $ravi] = makeUsers();
    $ravi->update(['friend_request_privacy' => 'none']);

    $res = SocialService::sendFriendRequest($asha, $ravi);
    expect($res['ok'])->toBeFalse();

    $ravi->update(['friend_request_privacy' => 'friends_of_friends']);
    // No mutual friend yet → blocked
    $res = SocialService::sendFriendRequest($asha, $ravi);
    expect($res['ok'])->toBeFalse();

    // With a mutual friend it succeeds
    $mutual = User::factory()->create();
    SocialService::sendFriendRequest($asha, $mutual);
    SocialService::acceptFriendRequest($mutual, $asha->id);
    SocialService::sendFriendRequest($ravi, $mutual);
    SocialService::acceptFriendRequest($mutual, $ravi->id);

    expect(count(SocialService::mutualFriendIds($asha, $ravi)))->toBe(1);

    $res = SocialService::sendFriendRequest($asha, $ravi);
    expect($res['ok'])->toBeTrue();
});

test('mutual friends are computed correctly', function () {
    [$asha, $ravi] = makeUsers();
    $third = User::factory()->create();

    foreach ([[$asha, $third], [$ravi, $third]] as [$a, $b]) {
        SocialService::sendFriendRequest($a, $b);
        SocialService::acceptFriendRequest($b, $a->id);
    }

    expect(count(SocialService::mutualFriendIds($asha, $ravi)))->toBe(1);
    expect(SocialService::haveMutualFriend($asha, $ravi))->toBeTrue();
});

test('friends page renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('friends.index'))->assertOk();
});

test('profile page renders for self', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.show', ['user' => 'me']))->assertOk();
});

test('private profile is restricted for strangers', function () {
    [$asha, $ravi] = makeUsers();
    $asha->update(['profile_visibility' => 'friends']);

    $response = $this->actingAs($ravi)->get(route('profile.show', $asha->id));
    $response->assertOk();
    $view = $response->getOriginalContent();
    $page = $view instanceof \Illuminate\View\View ? $view->getData()['page'] : [];
    $props = is_array($page) ? ($page['props'] ?? []) : ($page->props ?? []);
    expect($props)->toHaveKey('profileUser');
    expect($props)->not->toHaveKey('posts');
    expect($props)->not->toHaveKey('friends');
});

test('blocked users page renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('blocked.index'))->assertOk();
});
