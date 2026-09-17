<?php

use App\Http\Controllers\Auth\VerifiedUserController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'))->middleware('guest')->name('welcome');
Route::get('/welcome', fn () => Inertia::render('Welcome'))->name('landing');

Route::post('/reactivate', [ProfileController::class, 'reactivate'])
    ->middleware('guest')->name('reactivate');

Route::middleware(['auth', 'verified'])->group(function () {
    // Feed
    Route::get('/dashboard', fn (Request $r) => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/api/feed', [PostController::class, 'feed'])->name('feed');

    // Posts
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/react', [PostController::class, 'react'])->name('posts.react');
    Route::get('/posts/{post}/comments', [PostController::class, 'comments'])->name('posts.comments');
    Route::post('/posts/{post}/comments', [PostController::class, 'storeComment'])->name('posts.comments.store');
    Route::delete('/comments/{comment}', [PostController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('/comments/{comment}/react', [PostController::class, 'reactComment'])->name('comments.react');
    Route::post('/posts/{post}/vote', [PostController::class, 'vote'])->name('posts.vote');
    Route::post('/posts/{post}/save', [PostController::class, 'toggleSave'])->name('posts.save');
    Route::post('/posts/{post}/pin', [PostController::class, 'togglePin'])->name('posts.pin');
    Route::get('/hashtag/{tag}', [PostController::class, 'hashtag'])->name('hashtag.show');
    Route::get('/memories', [PostController::class, 'memories'])->name('memories.index');
    Route::get('/saved', [\App\Http\Controllers\SavedController::class, 'index'])->name('saved.index');

    // Stories
    Route::get('/stories', [\App\Http\Controllers\StoryController::class, 'index'])->name('stories.index');
    Route::get('/api/stories/tray', [\App\Http\Controllers\StoryController::class, 'tray'])->name('stories.tray');
    Route::post('/stories', [\App\Http\Controllers\StoryController::class, 'store'])->name('stories.store');
    Route::post('/stories/{story}/view', [\App\Http\Controllers\StoryController::class, 'view'])->name('stories.view');
    Route::get('/stories/{story}/viewers', [\App\Http\Controllers\StoryController::class, 'viewers'])->name('stories.viewers');
    Route::delete('/stories/{story}', [\App\Http\Controllers\StoryController::class, 'destroy'])->name('stories.destroy');

    // Reels & Watch
    Route::get('/reels', [\App\Http\Controllers\ReelController::class, 'reelsPage'])->name('reels.index');
    Route::get('/api/reels', [\App\Http\Controllers\ReelController::class, 'reels'])->name('reels.feed');
    Route::post('/reels', [\App\Http\Controllers\ReelController::class, 'storeReel'])->name('reels.store');
    Route::get('/watch', [\App\Http\Controllers\ReelController::class, 'watch'])->name('watch.index');

    // Profiles
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile', [ProfileController::class, 'editSettings'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/deactivate', [ProfileController::class, 'deactivate'])->name('profile.deactivate');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Friends
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{user}/request', [FriendController::class, 'request'])->name('friends.request');
    Route::post('/friends/{user}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::post('/friends/{user}/decline', [FriendController::class, 'decline'])->name('friends.decline');
    Route::post('/friends/{user}/cancel', [FriendController::class, 'cancel'])->name('friends.cancel');
    Route::post('/friends/{user}/unfriend', [FriendController::class, 'unfriend'])->name('friends.unfriend');
    Route::post('/users/{user}/follow', [FriendController::class, 'toggleFollow'])->name('users.follow');
    Route::post('/users/{user}/block', [FriendController::class, 'toggleBlock'])->name('users.block');
    Route::get('/settings/blocked', [FriendController::class, 'blockedIndex'])->name('blocked.index');

    // Search
    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'show'])->name('search.show');
    Route::get('/api/search/suggest', [\App\Http\Controllers\SearchController::class, 'suggestions'])->name('search.suggest');
    Route::post('/search/recents/clear', [\App\Http\Controllers\SearchController::class, 'clearRecents'])->name('search.recents.clear');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/api/notifications/unread', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('/notifications/settings', [\App\Http\Controllers\NotificationController::class, 'settings'])->name('notifications.settings');

    // Groups
    Route::get('/groups', [\App\Http\Controllers\GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [\App\Http\Controllers\GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [\App\Http\Controllers\GroupController::class, 'show'])->name('groups.show');
    Route::post('/groups/{group}/join', [\App\Http\Controllers\GroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/{group}/leave', [\App\Http\Controllers\GroupController::class, 'leave'])->name('groups.leave');
    Route::post('/groups/{group}/members/{user}/approve', [\App\Http\Controllers\GroupController::class, 'approve'])->name('groups.approve');
    Route::post('/groups/{group}/members/{user}/remove', [\App\Http\Controllers\GroupController::class, 'removeMember'])->name('groups.members.remove');
    Route::post('/groups/{group}/members/{user}/role', [\App\Http\Controllers\GroupController::class, 'changeRole'])->name('groups.members.role');
    Route::post('/groups/{group}/invite', [\App\Http\Controllers\GroupController::class, 'invite'])->name('groups.invite');
    Route::post('/group-invites/{invite}/respond', [\App\Http\Controllers\GroupController::class, 'respondInvite'])->name('groups.invites.respond');
    Route::patch('/groups/{group}', [\App\Http\Controllers\GroupController::class, 'update'])->name('groups.update');

    // Pages
    Route::get('/pages', [\App\Http\Controllers\PageController::class, 'index'])->name('pages.index');
    Route::post('/pages', [\App\Http\Controllers\PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}', [\App\Http\Controllers\PageController::class, 'show'])->name('pages.show');
    Route::post('/pages/{page}/follow', [\App\Http\Controllers\PageController::class, 'toggleFollow'])->name('pages.follow');
    Route::post('/pages/{page}/roles', [\App\Http\Controllers\PageController::class, 'addRole'])->name('pages.roles.add');
    Route::patch('/pages/{page}', [\App\Http\Controllers\PageController::class, 'update'])->name('pages.update');

    // Messenger
    Route::get('/messenger', [\App\Http\Controllers\MessengerController::class, 'index'])->name('messenger.index');
    Route::get('/messenger/{conversation}/messages', [\App\Http\Controllers\MessengerController::class, 'messages'])->name('messenger.messages');
    Route::post('/messenger/start', [\App\Http\Controllers\MessengerController::class, 'start'])->name('messenger.start');
    Route::post('/messenger/{conversation}/send', [\App\Http\Controllers\MessengerController::class, 'send'])->name('messenger.send');
    Route::post('/messages/{message}/react', [\App\Http\Controllers\MessengerController::class, 'react'])->name('messages.react');
    Route::patch('/messages/{message}', [\App\Http\Controllers\MessengerController::class, 'edit'])->name('messages.edit');
    Route::delete('/messages/{message}', [\App\Http\Controllers\MessengerController::class, 'destroy'])->name('messages.destroy');
    Route::get('/api/messages/search', [\App\Http\Controllers\MessengerController::class, 'search'])->name('messages.search');

    // Reports
    Route::post('/reports', [\App\Http\Controllers\ReportController::class, 'store'])->name('reports.store');

    // AI
    Route::post('/api/ai/assist', [\App\Http\Controllers\AiController::class, 'assist'])->name('ai.assist');
    Route::get('/api/recommendations', [\App\Http\Controllers\AiController::class, 'recommendations'])->name('ai.recommendations');
    Route::post('/api/recommendations/hide', [\App\Http\Controllers\AiController::class, 'hideRecommendation'])->name('ai.recommendations.hide');

    // Admin
    Route::get('/admin', [\App\Http\Controllers\AdminController::class, '__invoke'])->name('admin.dashboard');
    Route::post('/admin/users/{user}/action', [\App\Http\Controllers\AdminController::class, 'userAction'])->name('admin.users.action');
    Route::post('/admin/reports/{report}/action', [\App\Http\Controllers\AdminController::class, 'reportAction'])->name('admin.reports.action');
    Route::post('/admin/ai-flags/{flag}/action', [\App\Http\Controllers\AdminController::class, 'aiFlagAction'])->name('admin.flags.action');
    Route::post('/admin/settings', [\App\Http\Controllers\AdminController::class, 'saveSettings'])->name('admin.settings');
});

require __DIR__.'/auth.php';
