<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);


function makeConversation(User $a, User $b): Conversation
{
    $c = Conversation::create(['is_group' => false, 'created_by' => $a->id, 'last_activity_at' => now()]);
    $c->participants()->attach([$a->id, $b->id]);
    return $c;
}

test('user can start a conversation with another user', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->postJson(route('messenger.start'), ['user_id' => $other->id]);
    $response->assertOk();
    expect(Conversation::count())->toBe(1)
        ->and($response->json('conversation_id'))->toBe(Conversation::first()->id);
});

test('starting a direct conversation with the same user reuses it', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    makeConversation($me, $other);

    $this->postJson(route('messenger.start'), ['user_id' => $other->id])->assertOk();
    expect(Conversation::count())->toBe(1);
});

test('group conversation can be started with multiple participants', function () {
    $me = acting();
    $a = User::factory()->create(['email_verified_at' => now()]);
    $b = User::factory()->create(['email_verified_at' => now()]);

    $this->postJson(route('messenger.start'), ['participants' => [$a->id, $b->id], 'title' => 'gang'])->assertOk();
    $conv = Conversation::first();
    expect($conv->is_group)->toBeTrue()
        ->and($conv->title)->toBe('gang')
        ->and($conv->participants()->count())->toBe(3);
});

test('participants can send and list messages', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);

    $this->postJson(route('messenger.send', ['conversation' => $c]), ['body' => 'hello there'])->assertCreated();
    $this->postJson(route('messenger.send', ['conversation' => $c]), ['body' => 'second message'])->assertCreated();

    $response = $this->getJson(route('messenger.messages', ['conversation' => $c]));
    $response->assertOk()->assertJsonCount(2, 'messages');
    expect(collect($response->json('messages'))->pluck('body'))->toContain('hello there');
});

test('non participants cannot read a conversation', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);

    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger)->getJson(route('messenger.messages', ['conversation' => $c]))
        ->assertForbidden();
});

test('sending an empty message is rejected', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);

    $this->postJson(route('messenger.send', ['conversation' => $c]), [])->assertStatus(422);
});

test('user can send a message with an attachment', function () {
    Storage::fake('public');
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);

    $this->post(route('messenger.send', ['conversation' => $c]), [
        'body' => 'check this out',
        'attachment' => UploadedFile::fake()->image('photo.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $message = Message::first();
    expect($message->attachment_path)->not->toBeNull()
        ->and($message->attachment_mime)->toStartWith('image/');
    Storage::disk('public')->assertExists($message->attachment_path);
});

test('sender can edit and delete their message', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);
    $message = Message::create(['conversation_id' => $c->id, 'sender_id' => $me->id, 'body' => 'typo']);

    $this->patchJson(route('messages.edit', ['message' => $message]), ['body' => 'fixed'])->assertOk();
    expect($message->fresh()->body)->toBe('fixed');

    $this->actingAs($other)->deleteJson(route('messages.destroy', ['message' => $message]))->assertForbidden();
    $this->actingAs($me)->deleteJson(route('messages.destroy', ['message' => $message]))->assertOk();
    expect($message->fresh()->deleted_at)->not->toBeNull();
});

test('user can react to a message and toggle it off', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);
    $message = Message::create(['conversation_id' => $c->id, 'sender_id' => $me->id, 'body' => 'react to me']);

    $this->postJson(route('messages.react', ['message' => $message]), ['type' => 'love'])->assertOk();
    expect($message->reactions()->count())->toBe(1);

    $this->postJson(route('messages.react', ['message' => $message]), ['type' => 'love'])->assertOk();
    expect($message->fresh()->reactions()->count())->toBe(0);
});

test('recipient gets notified of a new message', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);

    $this->postJson(route('messenger.send', ['conversation' => $c]), ['body' => 'ping'])->assertCreated();
    expect(\App\Models\SiteNotification::where('user_id', $other->id)->where('type', 'message')->count())->toBe(1);
});

test('messenger index page renders conversations with unread count', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);
    Message::create(['conversation_id' => $c->id, 'sender_id' => $other->id, 'body' => 'ping']);
    Message::create(['conversation_id' => $c->id, 'sender_id' => $other->id, 'body' => 'pong']);

    $response = $this->get(route('messenger.index', ['c' => $c->id]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('conversations', 1)
            ->where('conversations.0.unread', 2)
            ->where('active.id', $c->id));
});

test('opening a conversation marks its messages as read', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);
    Message::create(['conversation_id' => $c->id, 'sender_id' => $other->id, 'body' => 'one']);

    $this->get(route('messenger.index', ['c' => $c->id]))->assertOk();
    $pivot = \DB::table('conversation_participants')->where('conversation_id', $c->id)->where('user_id', $me->id)->first();
    expect($pivot->last_read_message_id)->not->toBeNull();
});

test('messages can be searched across conversations', function () {
    $me = acting();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $c = makeConversation($me, $other);
    Message::create(['conversation_id' => $c->id, 'sender_id' => $other->id, 'body' => 'the banana bread recipe']);
    Message::create(['conversation_id' => $c->id, 'sender_id' => $other->id, 'body' => 'random chatter']);

    $response = $this->getJson(route('messages.search', ['q' => 'banana']))->assertOk();
    expect(collect($response->json('results'))->pluck('body'))->toContain('the banana bread recipe');
});
