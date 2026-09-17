<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_returns_token_and_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'flutter-test',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'is_admin'], 'token']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_mobile_login_rejects_bad_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_mobile_register_creates_user_and_token(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mobile User',
            'email' => 'mobile@test.dev',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $res->assertCreated()->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'mobile@test.dev']);
    }

    public function test_sanctum_protects_api_endpoints(): void
    {
        $this->getJson('/api/v1/feed')->assertStatus(401);
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_token_can_access_me_feed_and_logout(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret123',
        ])->json('token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/v1/feed', $headers)->assertOk();

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_token_can_access_data_endpoints(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret123',
        ])->json('token');

        $headers = ['Authorization' => "Bearer {$token}"];

        foreach ([
            '/api/v1/stories/tray',
            '/api/v1/reels',
            '/api/v1/friends',
            '/api/v1/groups',
            '/api/v1/pages',
            '/api/v1/notifications',
            '/api/v1/notifications/unread',
            '/api/v1/messenger',
            '/api/v1/search?q=',
            '/api/v1/saved',
            '/api/v1/memories',
            '/api/v1/profile/me',
            '/api/v1/recommendations',
        ] as $url) {
            $this->getJson($url, $headers)->assertOk();
        }
    }

    public function test_admin_endpoints_reject_non_admin(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret123',
        ])->json('token');

        $this->getJson('/api/v1/admin', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403);
    }

    public function test_admin_endpoints_allow_admin(): void
    {
        $admin = User::factory()->create([
            'password' => bcrypt('secret123'),
            'is_admin' => true,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email, 'password' => 'secret123',
        ])->json('token');

        $this->getJson('/api/v1/admin', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('tab', 'overview');
    }
}
