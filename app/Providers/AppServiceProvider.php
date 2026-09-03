<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        \Inertia\Inertia::share('auth.user', fn () => auth()->check()
            ? [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'avatar_url' => auth()->user()->avatar_url ?? null,
                'is_admin' => (bool) auth()->user()->is_admin,
            ]
            : null);

        \Inertia\Inertia::share('unread_notifications', fn () => auth()->check()
            ? auth()->user()->unreadNotificationsCount()
            : 0);

        \Inertia\Inertia::share('unread_messages', fn () => auth()->check()
            ? auth()->user()->unreadMessagesCount()
            : 0);
    }
}
