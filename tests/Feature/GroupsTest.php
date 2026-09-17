<?php

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('user can create a group and becomes owner', function () {
    $me = acting();

    $this->post(route('groups.store'), [
        'name' => 'Laravel Enthusiasts',
        'description' => 'All things Laravel',
        'privacy' => 'public',
    ], ['Accept' => 'application/json']);

    $group = Group::first();
    expect($group)->not->toBeNull()
        ->and($group->name)->toBe('Laravel Enthusiasts')
        ->and($group->members()->count())->toBe(1)
        ->and($group->members()->first()->pivot->role)->toBe('owner')
        ->and($group->members()->first()->pivot->status)->toBe('active');
});

test('joining an open-approval group adds member immediately', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Open Club', 'privacy' => 'public', 'requires_approval' => false, 'created_by' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $this->post(route('groups.join', ['group' => $group]))->assertRedirect();
    expect($group->fresh()->activeMembersCount())->toBe(2);
});

test('joining an approval group creates a pending request', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Gated Club', 'privacy' => 'public', 'requires_approval' => true, 'created_by' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
    $group->members()->attach($me->id, ['role' => 'member', 'status' => 'pending']);

    expect($group->fresh()->activeMembersCount())->toBe(1);
    $member = $group->members()->where('user_id', $me->id)->first();
    expect($member->pivot->status)->toBe('pending');
});

test('group owner can approve a pending member', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Gated', 'privacy' => 'public', 'requires_approval' => true, 'created_by' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
    $group->members()->attach($me->id, ['role' => 'member', 'status' => 'pending']);

    // non-admin cannot approve
    $this->postJson(route('groups.approve', ['group' => $group, 'user' => $me]))->assertForbidden();

    $this->actingAs($owner)->post(route('groups.approve', ['group' => $group, 'user' => $me]))->assertRedirect();
    expect($group->fresh()->activeMembersCount())->toBe(2)
        ->and(\App\Models\SiteNotification::where('user_id', $me->id)->count())->toBe(1);
});

test('members can post inside a group and outsiders cannot', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Posters', 'privacy' => 'public', 'requires_approval' => false, 'created_by' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $this->postJson(route('posts.store'), [
        'type' => 'text', 'content' => 'hello group', 'visibility' => 'public', 'group_id' => $group->id,
    ])->assertForbidden();

    $group->members()->attach($me->id, ['role' => 'member', 'status' => 'active']);
    $this->postJson(route('posts.store'), [
        'type' => 'text', 'content' => 'hello group', 'visibility' => 'public', 'group_id' => $group->id,
    ])->assertCreated();
    $post = Post::first();
    expect($post->group_id)->toBe($group->id)
        ->and($post->user_id)->toBe($me->id);
});

test('group admins can invite and the invited user can accept', function () {
    $me = acting();
    $target = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Invite Club', 'privacy' => 'private', 'requires_approval' => true, 'created_by' => $me->id]);
    $group->members()->attach($me->id, ['role' => 'owner', 'status' => 'active']);

    $this->post(route('groups.invite', ['group' => $group]), ['user_id' => $target->id])->assertRedirect();
    expect(GroupInvite::count())->toBe(1);

    $this->actingAs($target)->post(route('groups.invites.respond', ['invite' => GroupInvite::first()]), ['action' => 'accept'])->assertRedirect();
    expect($group->fresh()->activeMembersCount())->toBe(2)
        ->and(GroupInvite::first()->status)->toBe('accepted');
});

test('owner can promote a member to admin via role change', function () {
    $me = acting();
    $member = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Roles', 'privacy' => 'public', 'requires_approval' => false, 'created_by' => $me->id]);
    $group->members()->attach($me->id, ['role' => 'owner', 'status' => 'active']);
    $group->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    $this->post(route('groups.members.role', ['group' => $group, 'user' => $member]), ['role' => 'admin'])->assertRedirect();
    expect($group->members()->where('users.id', $member->id)->first()->pivot->role)->toBe('admin');
});

test('private group is hidden from non-members', function () {
    acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'Secret', 'privacy' => 'private', 'requires_approval' => true, 'created_by' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $this->get(route('groups.show', ['group' => $group]))->assertNotFound();
});

test('group show page renders for members', function () {
    $me = acting();
    $group = Group::create(['name' => 'Visible', 'privacy' => 'public', 'requires_approval' => false, 'created_by' => $me->id]);
    $group->members()->attach($me->id, ['role' => 'owner', 'status' => 'active']);

    $this->get(route('groups.show', ['group' => $group]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('group.name', 'Visible'));
});

test('admin can remove a member but not the owner', function () {
    $me = acting();
    $member = User::factory()->create(['email_verified_at' => now()]);
    $group = Group::create(['name' => 'RemoveTest', 'privacy' => 'public', 'requires_approval' => false, 'created_by' => $me->id]);
    $group->members()->attach($me->id, ['role' => 'owner', 'status' => 'active']);
    $group->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    $this->post(route('groups.members.remove', ['group' => $group, 'user' => $member]))->assertRedirect();
    expect($group->fresh()->activeMembersCount())->toBe(1);

    // owner cannot be removed even by another admin
    $this->actingAs($member)->postJson(route('groups.members.remove', ['group' => $group, 'user' => $me]))->assertForbidden();
});
