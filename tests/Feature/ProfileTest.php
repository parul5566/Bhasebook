<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('social profile fields can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => 'Hello world',
            'work' => 'Designer at Studio',
            'location' => 'Mumbai',
            'profile_visibility' => 'public',
            'default_post_visibility' => 'public',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    $this->assertSame('Hello world', $user->bio);
    $this->assertSame('Mumbai', $user->location);
    $this->assertSame('public', $user->profile_visibility);
});

test('avatar can be uploaded', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 600, 600),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    $this->assertNotNull($user->avatar);
    Storage::disk('public')->assertExists($user->avatar);
});

test('user can deactivate their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.deactivate'), [
            'password' => 'password',
        ]);

    $response->assertRedirect('/');
    $this->assertSame('deactivated', $user->fresh()->status);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertValid();
    $this->assertNull($user->fresh());
});
