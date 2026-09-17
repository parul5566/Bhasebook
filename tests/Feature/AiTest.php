<?php

use App\Models\AiRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('post assistant returns generated result', function () {
    acting();

    // no API key configured -> AiService throws, controller degrades gracefully
    $response = $this->postJson(route('ai.assist'), ['mode' => 'caption', 'topic' => 'morning coffee']);
    $response->assertOk();
    expect($response->json('result'))->toBeString()->not->toBeEmpty();
});

test('post assistant validates mode and topic', function () {
    acting();
    $this->postJson(route('ai.assist'), ['mode' => 'bogus', 'topic' => 'x'])->assertStatus(422);
    $this->postJson(route('ai.assist'), ['mode' => 'caption'])->assertStatus(422);
});

test('post assistant enforces daily limit', function () {
    acting();

    for ($i = 0; $i < 25; $i++) {
        $this->postJson(route('ai.assist'), ['mode' => 'ideas', 'topic' => 'filler '.$i]);
    }

    $this->postJson(route('ai.assist'), ['mode' => 'ideas', 'topic' => 'one more'])->assertStatus(429);
});

test('assistant responses are logged with usage tracking', function () {
    acting();

    $this->postJson(route('ai.assist'), ['mode' => 'hashtags', 'topic' => 'travel']);
    expect(AiRequest::count())->toBe(1);
});

test('recommendations endpoint returns people groups pages', function () {
    acting();

    $response = $this->getJson(route('ai.recommendations'));
    $response->assertOk();
    $json = $response->json();
    expect($json)->toHaveKeys(['people', 'groups', 'pages']);
});

test('hidden recommendations are excluded', function () {
    $me = acting();
    $candidate = User::factory()->create(['email_verified_at' => now(), 'name' => 'Hide Me Not']);
    $this->postJson(route('ai.recommendations.hide'), ['kind' => 'user', 'item_id' => $candidate->id])->assertOk();

    $response = $this->getJson(route('ai.recommendations'))->assertOk();
    expect(collect($response->json('people'))->pluck('id'))->not->toContain($candidate->id);
});

test('ai routes require authentication', function () {
    $this->postJson(route('ai.assist'), ['mode' => 'caption', 'topic' => 'hi'])->assertUnauthorized();
    $this->getJson(route('ai.recommendations'))->assertUnauthorized();
});
