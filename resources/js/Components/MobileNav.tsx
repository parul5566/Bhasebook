import { Link, usePage } from '@inertiajs/react';
import { GroupIcon, HomeIcon, MessengerIcon, SearchIcon, StoreIcon, VideoIcon } from '@/Components/Icons';

export default function MobileNav() {
    const { url } = usePage();
    const tab = (href: string, label: string, icon: React.ReactNode) => {
        const active = url.startsWith(href);
        return (
            <Link
                href={href}
                className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-semibold ${
                    active ? 'text-bhas-600 dark:text-bhas-300' : 'text-slate-500 dark:text-slate-400'
                }`}
                aria-label={label}
            >
                {icon}
                {label}
            </Link>
        );
    };
    return (
        <nav className="fixed bottom-0 left-0 right-0 z-30 flex border-t border-bhas-100 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden dark:border-bhas-800 dark:bg-bhas-950/95">
            {tab(route('dashboard'), 'Home', <HomeIcon className="h-6 w-6" />)}
            {tab(route('watch.index'), 'Watch', <VideoIcon className="h-6 w-6" />)}
            {tab(route('groups.index'), 'Groups', <GroupIcon className="h-6 w-6" />)}
            {tab(route('pages.index'), 'Pages', <StoreIcon className="h-6 w-6" />)}
            {tab(route('messenger.index'), 'Chats', <MessengerIcon className="h-6 w-6" />)}
        </nav>
    );
}
