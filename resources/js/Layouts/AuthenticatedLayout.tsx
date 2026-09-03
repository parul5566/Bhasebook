import TopBar from '@/Components/TopBar';
import MobileNav from '@/Components/MobileNav';
import { usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

type Common = {
    unread_notifications?: number;
    unread_messages?: number;
};

export default function AuthenticatedLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const props = usePage().props as unknown as Common;
    return (
        <div className="min-h-screen pb-16 md:pb-0">
            <Head title={title} />
            <TopBar
                unreadNotifications={props.unread_notifications ?? 0}
                unreadMessages={props.unread_messages ?? 0}
            />
            <main className="mx-auto max-w-7xl px-2 py-4 md:px-4">{children}</main>
            <MobileNav />
        </div>
    );
}
