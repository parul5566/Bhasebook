<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /** POST /api/v1/devices — register the calling user's FCM token. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
            'platform' => 'nullable|string|in:android,ios,web',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/v1/devices — remove a token (logout). */
    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:255']);
        DeviceToken::where('token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
