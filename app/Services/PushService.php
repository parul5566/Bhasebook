<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\SiteNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 push sender.
 *
 * Uses the Firebase Admin SDK when credentials are configured
 * (FIREBASE_CREDENTIALS env). Fails silently otherwise so the app
 * keeps working without push configured.
 */
class PushService
{
    /** Notification categories the user can mute (mirror of NotificationController::CATEGORIES). */
    public const MUTABLE = ['friend', 'follow', 'message'];

    public static function enabled(): bool
    {
        return !empty(config('services.firebase.credentials'));
    }

    /**
     * Send a push for a SiteNotification to all of the user's devices.
     * Respects the user's per-category mute prefs.
     */
    public static function pushNotification(SiteNotification $n): void
    {
        if (!self::enabled()) {
            return;
        }

        $user = User::find($n->user_id);
        if (!$user) {
            return;
        }

        // Respect per-category prefs (default on).
        $prefs = $user->notification_prefs ?? [];
        if (($prefs[$n->category] ?? '1') !== '1') {
            return;
        }

        $actorName = $n->actor?->name ?? 'Bhasebook';
        $text = $n->data['text'] ?? '';

        self::sendToUser(
            $user->id,
            title: $actorName,
            body: $text ?: 'You have a new notification',
            data: [
                'type' => (string) $n->type,
                'category' => (string) $n->category,
                'notification_id' => (string) $n->id,
                'notifiable_type' => (string) ($n->notifiable_type ?? ''),
                'notifiable_id' => (string) ($n->notifiable_id ?? ''),
            ],
        );
    }

    /** Send a data/message push to every device of a user, pruning dead tokens. */
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        if (!self::enabled()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)->pluck('token');
        foreach ($tokens as $token) {
            try {
                self::send($token, $title, $body, $data);
            } catch (\Throwable $e) {
                // UNREGISTERED / INVALID_ARGUMENT → token is dead, prune it.
                if (str_contains($e->getMessage(), 'UNREGISTERED') || str_contains($e->getMessage(), 'INVALID_ARGUMENT')) {
                    DeviceToken::where('token', $token)->delete();
                } else {
                    Log::warning('FCM send failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** Low-level FCM HTTP v1 send via the kreait Firebase SDK. */
    protected static function send(string $token, string $title, string $body, array $data): void
    {
        $messaging = app('firebase.messaging');

        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ])
            ->withData($data)
            ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray([
                'priority' => 'high',
            ]));

        $messaging->send($message);
    }
}
