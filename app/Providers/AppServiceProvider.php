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
        $this->app->singleton(\App\Services\AiService::class, function () {
            return new \App\Services\AiService(
                (string) config('services.openai.key', ''),
                (string) config('services.openai.base_url', 'https://api.openai.com/v1'),
                (string) config('services.openai.model', 'gpt-4o-mini'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Admin gate — used by the /api/v1/admin routes (mobile app).
        \Illuminate\Support\Facades\Gate::define('admin', fn ($user) => (bool) $user->is_admin);

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
