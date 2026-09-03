<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request, string $user)
    {
        $me = $request->user();
        $profileUser = $user === 'me'
            ? $me
            : User::where('id', $user)->firstOrFail();

        abort_if($profileUser->status === 'deactivated' || $profileUser->status === 'banned', 404);

        $isMe = $me->id === $profileUser->id;
        $friendIds = $profileUser->friendIds();

        // Profile visibility enforcement
        $canViewFull = $isMe || $profileUser->profile_visibility === 'public'
            || ($profileUser->profile_visibility === 'friends' && $me->isFriendWith($profileUser->id))
            || ($profileUser->profile_visibility === 'friends_of_friends' && SocialService::haveMutualFriend($me, $profileUser))
            || $me->is_admin;

        if (! $canViewFull) {
            return Inertia::render('Profile/Restricted', [
                'profileUser' => $this->basicUser($profileUser),
                'relation' => $this->relation($me, $profileUser),
            ])->withViewData(['status' => 200]);
        }

        $friends = User::whereIn('id', $friendIds)->limit(9)->get()->map(fn ($u) => $this->basicUser($u));

        $posts = \App\Models\Post::where('user_id', $profileUser->id)
            ->where(function ($q) use ($me, $isMe, $profileUser) {
                if (! $isMe) {
                    $q->where('visibility', '!=', 'private');
                    if (! $me->isFriendWith($profileUser->id)) {
                        $q->where('visibility', 'public')
                            ->orWhere(function ($q2) use ($me) {
                                $q2->where('visibility', 'custom')
                                    ->whereHas('visibilityUsers', fn ($q3) => $q3->where('user_id', $me->id));
                            });
                    }
                }
            })
            ->with(['media', 'pollOptions.votes', 'comments', 'reactions', 'tags:id,name', 'sharedPost.media', 'sharedPost.user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Profile/Show', [
            'profileUser' => array_merge($this->basicUser($profileUser), [
                'cover_url' => $profileUser->cover_url,
                'bio' => $profileUser->bio,
                'work' => $profileUser->work,
                'education' => $profileUser->education,
                'location' => $profileUser->location,
                'birthday' => $profileUser->birthday?->format('M j, Y'),
                'gender' => $profileUser->gender,
                'friends_count' => count($friendIds),
                'followers_count' => \App\Models\Follow::where('followee_id', $profileUser->id)->count(),
                'is_admin' => $profileUser->is_admin,
            ]),
            'relation' => $this->relation($me, $profileUser),
            'friends' => $friends,
            'posts' => \App\Http\Controllers\PostController::serializePosts($posts, $me),
        ]);
    }

    public function editSettings(Request $request)
    {
        $me = $request->user();
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $me instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $me->hasVerifiedEmail(),
            'status' => session('status'),
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        auth()->logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function update(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email,'.$me->id,
            'bio' => 'nullable|string|max:500',
            'work' => 'nullable|string|max:150',
            'education' => 'nullable|string|max:150',
            'location' => 'nullable|string|max:150',
            'birthday' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'default_post_visibility' => 'in:public,friends,private,custom',
            'profile_visibility' => 'in:public,friends,friends_of_friends',
            'friend_request_privacy' => 'in:everyone,friends_of_friends,none',
        ]);

        if (($data['email'] ?? null) !== $me->email) {
            $me->forceFill(['email' => $data['email'], 'email_verified_at' => null]);
            unset($data['email']);
        }

        $me->update($data);

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpg,jpeg,png,webp,gif|max:4096']);
            $path = $request->file('avatar')->store('avatars', 'public');
            $this->downscale($path);
            $me->avatar = $path;
            $me->save();
        }
        if ($request->hasFile('cover')) {
            $request->validate(['cover' => 'image|mimes:jpg,jpeg,png,webp|max:8192']);
            $path = $request->file('cover')->store('covers', 'public');
            $this->downscaleCover($path);
            $me->cover = $path;
            $me->save();
        }

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }

    public function deactivate(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        $request->user()->update([
            'status' => 'deactivated',
            'deactivated_at' => now(),
        ]);
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function reactivate(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! \Hash::check($credentials['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }
        if ($user->status === 'deactivated') {
            $user->update(['status' => 'active', 'deactivated_at' => null]);
        }
        auth()->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    private function relation(User $me, User $other): array
    {
        $pending = \App\Models\Friendship::where(function ($q) use ($me, $other) {
            $q->where('user_id', $me->id)->where('friend_id', $other->id)
                ->orWhere('user_id', $other->id)->where('friend_id', $me->id);
        })->first();

        return [
            'is_me' => $me->id === $other->id,
            'is_friend' => $me->isFriendWith($other->id),
            'request_sent_by_me' => $pending && $pending->status === 'pending' && (int) $pending->user_id === (int) $me->id,
            'request_received' => $pending && $pending->status === 'pending' && (int) $pending->friend_id === (int) $me->id,
            'following' => \App\Models\Follow::where('follower_id', $me->id)->where('followee_id', $other->id)->exists(),
            'blocked_by_me' => \App\Models\Block::where('user_id', $me->id)->where('blocked_id', $other->id)->exists(),
            'mutual_count' => count(SocialService::mutualFriendIds($me, $other)),
        ];
    }

    public static function basicUser(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'avatar_url' => $u->avatar_url,
            'hue' => $u->initialsHue(),
        ];
    }

    private function downscale(string $path): void
    {
        $abs = Storage::disk('public')->path($path);
        $info = @getimagesize($abs);
        if (! $info) {
            return;
        }
        [$w, $h] = $info;
        $max = 512;
        if ($w > $max || $h > $max) {
            $ratio = min($max / $w, $max / $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $src = match ($info[2]) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($abs),
                IMAGETYPE_PNG => imagecreatefrompng($abs),
                IMAGETYPE_WEBP => imagecreatefromwebp($abs),
                IMAGETYPE_GIF => imagecreatefromgif($abs),
                default => null,
            };
            if ($src) {
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                match ($info[2]) {
                    IMAGETYPE_JPEG => imagejpeg($dst, $abs, 85),
                    IMAGETYPE_PNG => imagepng($dst, $abs, 6),
                    IMAGETYPE_WEBP => imagewebp($dst, $abs, 85),
                    IMAGETYPE_GIF => imagegif($dst, $abs),
                    default => null,
                };
                imagedestroy($src);
                imagedestroy($dst);
            }
        }
    }

    private function downscaleCover(string $path): void
    {
        // same as avatar but wider max
        $abs = Storage::disk('public')->path($path);
        $info = @getimagesize($abs);
        if (! $info) {
            return;
        }
        [$w, $h] = $info;
        $max = 1600;
        if ($w > $max) {
            $nw = $max;
            $nh = (int) round($h * $max / $w);
            $src = match ($info[2]) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($abs),
                IMAGETYPE_PNG => imagecreatefrompng($abs),
                IMAGETYPE_WEBP => imagecreatefromwebp($abs),
                default => null,
            };
            if ($src) {
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                match ($info[2]) {
                    IMAGETYPE_JPEG => imagejpeg($dst, $abs, 85),
                    IMAGETYPE_PNG => imagepng($dst, $abs, 6),
                    IMAGETYPE_WEBP => imagewebp($dst, $abs, 85),
                    default => null,
                };
                imagedestroy($src);
                imagedestroy($dst);
            }
        }
    }
}
