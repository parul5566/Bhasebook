import ApplicationLogo from '@/Components/ApplicationLogo';
import PageTransition from '@/Components/PageTransition';
import { Link, Head } from '@inertiajs/react';

export default function GuestLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    return (
        <div className="relative min-h-screen bg-gradient-to-b from-bhas-100 via-bhas-50 to-bhas-100 dark:from-bhas-950 dark:via-bhas-950 dark:to-bhas-900">
            <div aria-hidden className="pointer-events-none fixed -top-32 left-1/2 z-0 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-r from-bhas-400/40 via-purple-500/30 to-fuchsia-500/40 blur-3xl motion-safe:animate-[fade-up_1s_ease-out]" />
            {title && (
                <Head title={title} />
            )}
            <div className="relative z-10 mx-auto flex min-h-screen max-w-6xl flex-col px-4">
                <header className="flex items-center justify-between py-4">
                    <Link href="/">
                        <ApplicationLogo className="h-9 w-auto text-bhas-700 dark:text-bhas-200" />
                    </Link>
                    <div className="flex items-center gap-2">
                        <Link href={route('login')} className="bhas-btn-primary !py-1.5">
                            Log in
                        </Link>
                    </div>
                </header>
                <main className="flex flex-1 items-center justify-center py-6"><PageTransition>{children}</PageTransition></main>
            </div>
        </div>
    );
}
