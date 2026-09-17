<?php

use App\Models\Report;
use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non-admin cannot access admin dashboard', function () {
    acting();
    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('admin dashboard renders with stats', function () {
    admin();
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stats.total_users')
            ->has('stats.total_posts')
            ->has('stats.open_reports'));
});

test('admin users tab lists users', function () {
    admin();
    User::factory()->count(3)->create(['email_verified_at' => now()]);

    $this->get(route('admin.dashboard', ['tab' => 'users']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('users'));
});

test('admin can warn a user', function () {
    admin();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->post(route('admin.users.action', ['user' => $user]), ['action' => 'warn', 'reason' => 'be nice'])
        ->assertRedirect();
    expect(UserWarning::count())->toBe(1)
        ->and($user->fresh()->status)->toBe('active');
});

test('admin can suspend and unban a user', function () {
    admin();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->post(route('admin.users.action', ['user' => $user]), ['action' => 'suspend', 'days' => 7])
        ->assertRedirect();
    expect($user->fresh()->status)->toBe('suspended');

    $this->post(route('admin.users.action', ['user' => $user]), ['action' => 'unban'])
        ->assertRedirect();
    expect($user->fresh()->status)->toBe('active')
        ->and($user->fresh()->suspended_until)->toBeNull();
});

test('admin cannot moderate themselves', function () {
    $a = admin();
    $this->postJson(route('admin.users.action', ['user' => $a]), ['action' => 'warn', 'reason' => 'nope'])->assertForbidden();
});

test('regular user cannot take admin actions', function () {
    acting();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->postJson(route('admin.users.action', ['user' => $target]), ['action' => 'warn', 'reason' => 'nope'])->assertForbidden();
});

test('admin can dismiss a report', function () {
    $a = admin();
    $post = \App\Models\Post::create(['user_id' => $a->id, 'content' => 'x', 'type' => 'text', 'visibility' => 'public']);
    $report = Report::create(['reporter_id' => $a->id, 'reportable_type' => \App\Models\Post::class, 'reportable_id' => $post->id, 'reason' => 'spam']);

    $this->post(route('admin.reports.action', ['report' => $report]), ['action' => 'dismiss'])
        ->assertRedirect();
    expect($report->fresh()->status)->toBe('dismissed')
        ->and($report->fresh()->handled_by)->toBe($a->id);
});

test('admin can resolve a report by removing content', function () {
    $a = admin();
    $poster = User::factory()->create(['email_verified_at' => now()]);
    $post = \App\Models\Post::create(['user_id' => $poster->id, 'content' => 'bad', 'type' => 'text', 'visibility' => 'public']);
    $report = Report::create(['reporter_id' => $a->id, 'reportable_type' => \App\Models\Post::class, 'reportable_id' => $post->id, 'reason' => 'spam']);

    $this->post(route('admin.reports.action', ['report' => $report]), ['action' => 'resolve_remove'])
        ->assertRedirect();
    expect($report->fresh()->status)->toBe('resolved')
        ->and(\App\Models\Post::withTrashed()->find($post->id)->deleted_at)->not->toBeNull();
});

test('admin can warn the author via a report', function () {
    $a = admin();
    $poster = User::factory()->create(['email_verified_at' => now()]);
    $post = \App\Models\Post::create(['user_id' => $poster->id, 'content' => 'rude', 'type' => 'text', 'visibility' => 'public']);
    $report = Report::create(['reporter_id' => $a->id, 'reportable_type' => \App\Models\Post::class, 'reportable_id' => $post->id, 'reason' => 'harassment']);

    $this->post(route('admin.reports.action', ['report' => $report]), ['action' => 'warn'])
        ->assertRedirect();
    expect($report->fresh()->status)->toBe('resolved')
        ->and(UserWarning::where('user_id', $poster->id)->count())->toBe(1);
});

test('admin settings save and reload', function () {
    admin();
    $this->get(route('admin.dashboard', ['tab' => 'settings']))->assertOk();

    $this->post(route('admin.settings'), ['settings' => ['registration_open' => '0', 'ai_enabled' => '1']])
        ->assertRedirect();
    expect(\App\Models\Setting::get('registration_open'))->toBe('0')
        ->and(\App\Models\Setting::get('ai_enabled'))->toBe('1');
});

test('admin AI flags tab lists unreviewed flags', function () {
    $a = admin();
    $post = \App\Models\Post::create(['user_id' => $a->id, 'content' => 'kill yourself', 'type' => 'text', 'visibility' => 'public']);
    \App\Jobs\ModerateContent::dispatchSync(\App\Models\Post::class, $post->id, 'kill yourself');

    $this->get(route('admin.dashboard', ['tab' => 'moderation']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('aiFlags'));
    expect(\App\Models\AiFlag::count())->toBe(1);
});

test('admin can dismiss an AI flag', function () {
    $a = admin();
    $post = \App\Models\Post::create(['user_id' => $a->id, 'content' => 'idiot spam', 'type' => 'text', 'visibility' => 'public']);
    \App\Jobs\ModerateContent::dispatchSync(\App\Models\Post::class, $post->id, 'idiot spam');
    $flag = \App\Models\AiFlag::first();
    expect($flag)->not->toBeNull();

    $this->post(route('admin.flags.action', ['flag' => $flag]), ['action' => 'dismiss'])->assertRedirect();
    expect($flag->fresh()->reviewed_at)->not->toBeNull();
});
