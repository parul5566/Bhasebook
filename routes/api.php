<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\AiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1) — token auth via Laravel Sanctum
|--------------------------------------------------------------------------
| Reuses the same controllers the web app uses so behavior stays identical.
| Inertia-rendering routes (page shells) are NOT proxied; the Flutter app
| renders its own screens and consumes the JSON/data endpoints only.
*/

Route::prefix('v1')->group(function () {
    // Auth (no token required)
    Route::post('auth/login', [ApiAuthController::class, 'login']);
    Route::post('auth/register', [ApiAuthController::class, 'register']);
    Route::post('auth/forgot-password', [ApiAuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [ApiAuthController::class, 'me']);
        Route::post('auth/logout', [ApiAuthController::class, 'logout']);

        // Feed & posts
        Route::get('feed', [PostController::class, 'feed']);
        Route::post('posts', [PostController::class, 'store']);
        Route::get('posts/{post}', [PostController::class, 'show']);
        Route::patch('posts/{post}', [PostController::class, 'update']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);
        Route::post('posts/{post}/react', [PostController::class, 'react']);
        Route::get('posts/{post}/comments', [PostController::class, 'comments']);
        Route::post('posts/{post}/comments', [PostController::class, 'storeComment']);
        Route::delete('comments/{comment}', [PostController::class, 'destroyComment']);
        Route::post('comments/{comment}/react', [PostController::class, 'reactComment']);
        Route::post('posts/{post}/vote', [PostController::class, 'vote']);
        Route::post('posts/{post}/save', [PostController::class, 'toggleSave']);
        Route::post('posts/{post}/pin', [PostController::class, 'togglePin']);
        Route::get('hashtag/{tag}', [PostController::class, 'hashtag']);
        Route::get('memories', [PostController::class, 'memories']);
        Route::get('saved', [SavedController::class, 'index']);

        // Stories
        Route::get('stories/tray', [StoryController::class, 'tray']);
        Route::post('stories', [StoryController::class, 'store']);
        Route::post('stories/{story}/view', [StoryController::class, 'view']);
        Route::get('stories/{story}/viewers', [StoryController::class, 'viewers']);
        Route::delete('stories/{story}', [StoryController::class, 'destroy']);

        // Reels
        Route::get('reels', [ReelController::class, 'reels']);
        Route::post('reels', [ReelController::class, 'storeReel']);

        // Profiles
        Route::get('profile/{user}', [ProfileController::class, 'showData']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/deactivate', [ProfileController::class, 'deactivate']);

        // Friends
        Route::get('friends', [FriendController::class, 'indexData']);
        Route::post('friends/{user}/request', [FriendController::class, 'request']);
        Route::post('friends/{user}/accept', [FriendController::class, 'accept']);
        Route::post('friends/{user}/decline', [FriendController::class, 'decline']);
        Route::post('friends/{user}/cancel', [FriendController::class, 'cancel']);
        Route::post('friends/{user}/unfriend', [FriendController::class, 'unfriend']);
        Route::post('users/{user}/follow', [FriendController::class, 'toggleFollow']);
        Route::post('users/{user}/block', [FriendController::class, 'toggleBlock']);
        Route::get('settings/blocked', [FriendController::class, 'blockedIndex']);

        // Search
        Route::get('search/suggest', [SearchController::class, 'suggestions']);
        Route::get('search', [SearchController::class, 'showData']);
        Route::post('search/recents/clear', [SearchController::class, 'clearRecents']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'indexData']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::get('notifications/unread', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/settings', [NotificationController::class, 'settings']);

        // Groups
        Route::get('groups', [GroupController::class, 'indexData']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::get('groups/{group}', [GroupController::class, 'showData']);
        Route::post('groups/{group}/join', [GroupController::class, 'join']);
        Route::post('groups/{group}/leave', [GroupController::class, 'leave']);
        Route::post('groups/{group}/members/{user}/approve', [GroupController::class, 'approve']);
        Route::post('groups/{group}/members/{user}/remove', [GroupController::class, 'removeMember']);
        Route::post('groups/{group}/members/{user}/role', [GroupController::class, 'changeRole']);
        Route::post('groups/{group}/invite', [GroupController::class, 'invite']);
        Route::post('group-invites/{invite}/respond', [GroupController::class, 'respondInvite']);
        Route::patch('groups/{group}', [GroupController::class, 'update']);

        // Pages
        Route::get('pages', [PageController::class, 'indexData']);
        Route::post('pages', [PageController::class, 'store']);
        Route::get('pages/{page}', [PageController::class, 'showData']);
        Route::post('pages/{page}/follow', [PageController::class, 'toggleFollow']);
        Route::post('pages/{page}/roles', [PageController::class, 'addRole']);
        Route::patch('pages/{page}', [PageController::class, 'update']);

        // Messenger
        Route::get('messenger', [MessengerController::class, 'indexData']);
        Route::get('messenger/{conversation}/messages', [MessengerController::class, 'messages']);
        Route::post('messenger/start', [MessengerController::class, 'start']);
        Route::post('messenger/{conversation}/send', [MessengerController::class, 'send']);
        Route::post('messages/{message}/react', [MessengerController::class, 'react']);
        Route::patch('messages/{message}', [MessengerController::class, 'edit']);
        Route::delete('messages/{message}', [MessengerController::class, 'destroy']);
        Route::get('messages/search', [MessengerController::class, 'search']);

        // Reports & AI
        Route::post('reports', [ReportController::class, 'store']);
        Route::post('ai/assist', [AiController::class, 'assist']);
        Route::get('recommendations', [AiController::class, 'recommendations']);
        Route::post('recommendations/hide', [AiController::class, 'hideRecommendation']);

        // Admin
        Route::middleware('can:admin')->group(function () {
            Route::get('admin', [AdminController::class, 'dashboardData']);
            Route::post('admin/users/{user}/action', [AdminController::class, 'userAction']);
            Route::post('admin/reports/{report}/action', [AdminController::class, 'reportAction']);
            Route::post('admin/ai-flags/{flag}/action', [AdminController::class, 'aiFlagAction']);
            Route::post('admin/settings', [AdminController::class, 'saveSettings']);
        });
    });
});
