import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({
    canLogin,
    canRegister,
}: {
    canLogin: boolean;
    canRegister: boolean;
    laravelVersion: string;
    phpVersion: string;
}) {
    return (
        <>
            <Head title="Bhasebook — connect, share & discover" />
            <div className="min-h-screen bg-gradient-to-b from-white via-bhas-50 to-bhas-100 dark:from-bhas-950 dark:via-bhas-950 dark:to-bhas-900">
                <header className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    <ApplicationLogo className="h-9 w-auto text-bhas-700 dark:text-bhas-200" />
                    <div className="flex gap-2">
                        {canLogin && (
                            <Link href={route('login')} className="bhas-btn-primary">
                                Log in
                            </Link>
                        )}
                        {canRegister && (
                            <Link href={route('register')} className="bhas-btn-ghost">
                                Create account
                            </Link>
                        )}
                    </div>
                </header>

                <main className="mx-auto grid max-w-7xl gap-10 px-4 py-16 md:grid-cols-2 md:items-center">
                    <div>
                        <h1 className="text-4xl font-extrabold leading-tight tracking-tight md:text-5xl">
                            Connect, share & discover with{' '}
                            <span className="text-bhas-600 dark:text-bhas-300">Bhasebook</span>
                        </h1>
                        <p className="mt-4 max-w-lg text-lg text-slate-600 dark:text-slate-300">
                            A fresh take on social networking — posts, stories, reels, real-time
                            chat, groups and pages, all in one place.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            {canRegister && (
                                <Link href={route('register')} className="bhas-btn-primary px-6 py-3">
                                    Get started — it's free
                                </Link>
                            )}
                            {canLogin && (
                                <Link href={route('login')} className="bhas-btn-ghost px-6 py-3">
                                    I already have an account
                                </Link>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-4">
                        {[
                            ['News feed', 'Posts with photos, videos, polls, feelings & more.'],
                            ['Stories & Reels', '24-hour stories and a vertical reel feed.'],
                            ['Messenger', 'Real-time chats with reactions & receipts.'],
                            ['Groups & Pages', 'Communities and brands you love.'],
                        ].map(([t, d]) => (
                            <div key={t} className="bhas-card p-4">
                                <h3 className="font-bold text-bhas-700 dark:text-bhas-300">{t}</h3>
                                <p className="text-sm text-slate-500 dark:text-slate-400">{d}</p>
                            </div>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
