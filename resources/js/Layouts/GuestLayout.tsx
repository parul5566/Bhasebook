import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, Head } from '@inertiajs/react';

export default function GuestLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    return (
        <div className="min-h-screen bg-gradient-to-b from-bhas-100 via-bhas-50 to-bhas-100 dark:from-bhas-950 dark:via-bhas-950 dark:to-bhas-900">
            {title && (
                <Head title={title} />
            )}
            <div className="mx-auto flex min-h-screen max-w-6xl flex-col px-4">
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
                <main className="flex flex-1 items-center justify-center py-6">{children}</main>
            </div>
        </div>
    );
}
