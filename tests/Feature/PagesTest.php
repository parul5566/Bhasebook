<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('user can create a page and becomes admin + follower', function () {
    $me = acting();

    $this->post(route('pages.store'), [
        'name' => 'Bhasebook Official',
        'category' => 'Community',
        'about' => 'The official page',
    ], ['Accept' => 'application/json']);

    $page = Page::first();
    expect($page)->not->toBeNull()
        ->and($page->name)->toBe('Bhasebook Official')
        ->and($page->category)->toBe('Community')
        ->and($page->roles()->count())->toBe(1)
        ->and($page->roles()->first()->pivot->role)->toBe('admin')
        ->and($page->followers()->count())->toBe(1); // creator auto-follows
});

test('user can follow and unfollow a page', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $page = Page::create(['name' => 'Fan Base', 'category' => 'Entertainment', 'created_by' => $owner->id]);
    $page->roles()->attach($owner->id, ['role' => 'admin']);

    $this->post(route('pages.follow', ['page' => $page]))->assertRedirect();
    expect($page->fresh()->followers()->count())->toBe(1);

    $this->post(route('pages.follow', ['page' => $page]))->assertRedirect();
    expect($page->fresh()->followers()->count())->toBe(0);
});

test('only page staff can post as the page', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $page = Page::create(['name' => 'Brand', 'category' => 'Brand', 'created_by' => $owner->id]);
    $page->roles()->attach($owner->id, ['role' => 'admin']);

    $this->postJson(route('posts.store'), [
        'type' => 'text', 'content' => 'as page', 'visibility' => 'public', 'page_id' => $page->id,
    ])->assertForbidden();

    $page->roles()->attach($me->id, ['role' => 'editor']);
    $this->postJson(route('posts.store'), [
        'type' => 'text', 'content' => 'as page', 'visibility' => 'public', 'page_id' => $page->id,
    ])->assertCreated();

    $post = \App\Models\Post::first();
    expect($post->page_id)->toBe($page->id)
        ->and($post->user_id)->toBeNull(); // posted as the page, not as the user
});

test('page insights are only shown to page admins', function () {
    $me = acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $page = Page::create(['name' => 'Insightful', 'category' => 'Brand', 'created_by' => $owner->id]);
    $page->roles()->attach($owner->id, ['role' => 'admin']);
    $page->followers()->attach($me->id);

    // follower sees no insights
    $this->get(route('pages.show', ['page' => $page]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('insights', null));

    // admin sees insights
    $this->actingAs($owner)->get(route('pages.show', ['page' => $page]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('insights.followers', 1)
            ->where('insights.posts', 0));
});

test('page show page loads with inertia for any visitor', function () {
    acting();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $page = Page::create(['name' => 'Show Me', 'category' => 'Brand', 'created_by' => $owner->id]);
    $page->roles()->attach($owner->id, ['role' => 'admin']);

    $this->get(route('pages.show', ['page' => $page]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.name', 'Show Me'));
});
