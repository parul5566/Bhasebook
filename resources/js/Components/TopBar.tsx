import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeToggle from '@/Components/ThemeToggle';
import SearchBar from '@/Components/SearchBar';
import { BellIcon, MenuIcon, MessengerIcon } from '@/Components/Icons';

export default function TopBar({
    unreadNotifications = 0,
    unreadMessages = 0,
}: {
    unreadNotifications?: number;
    unreadMessages?: number;
}) {
    const user = usePage().props.auth.user as unknown as { name: string; avatar_url: string | null } | undefined;
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-bhas-100 bg-white/95 px-3 backdrop-blur dark:border-bhas-800 dark:bg-bhas-950/95">
            <Link href={route('dashboard')} className="shrink-0">
                <ApplicationLogo className="h-8 w-auto text-bhas-700 dark:text-bhas-200" />
            </Link>
            <div className="hidden flex-1 md:flex md:justify-center">
                <SearchBar />
            </div>
            <div className="ml-auto flex items-center gap-1">
                <Link href={route('notifications.index')} className="bhas-icon-btn relative" aria-label="Notifications">
                    <BellIcon className="h-5 w-5" />
                    {unreadNotifications > 0 && (
                        <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                            {unreadNotifications > 9 ? '9+' : unreadNotifications}
                        </span>
                    )}
                </Link>
                <Link href={route('messenger.index')} className="bhas-icon-btn relative" aria-label="Messenger">
                    <MessengerIcon className="h-5 w-5" />
                    {unreadMessages > 0 && (
                        <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                            {unreadMessages > 9 ? '9+' : unreadMessages}
                        </span>
                    )}
                </Link>
                <ThemeToggle />
                {user && (
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setMenuOpen((v) => !v)}
                            className="flex h-10 w-10 items-center justify-center rounded-full transition hover:brightness-95"
                            aria-label="Account menu"
                        >
                            <Avatar user={user} />
                        </button>
                        {menuOpen && (
                            <>
                                <button
                                    type="button"
                                    aria-label="Close menu"
                                    className="fixed inset-0 z-10 cursor-default"
                                    onClick={() => setMenuOpen(false)}
                                />
                                <div className="bhas-card absolute right-0 top-12 z-20 w-64 p-2 shadow-pop">
                                    <MenuBody onClose={() => setMenuOpen(false)} name={user.name} />
                                </div>
                            </>
                        )}
                    </div>
                )}
            </div>
        </header>
    );
}

function Avatar({ user }: { user: { name: string; avatar_url: string | null } }) {
    if (user.avatar_url) {
        return (
            <img
                src={user.avatar_url}
                alt={user.name}
                className="h-9 w-9 rounded-full object-cover ring-2 ring-white dark:ring-bhas-900"
            />
        );
    }
    const initials = user.name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
    const hue = [...user.name].reduce((a, c) => a + c.charCodeAt(0), 0) % 360;
    return (
        <span
            className="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-white"
            style={{ backgroundColor: `hsl(${hue} 55% 45%)` }}
        >
            {initials}
        </span>
    );
}

function MenuBody({ onClose, name }: { onClose: () => void; name: string }) {
    const item =
        'flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-medium hover:bg-bhas-50 dark:hover:bg-bhas-800';
    return (
        <div>
            <div className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                {name}
            </div>
            <Link href={route('profile.show', { user: 'me' })} className={item} onClick={onClose}>
                My Profile
            </Link>
            <Link href={route('friends.index')} className={item} onClick={onClose}>
                Friends
            </Link>
            <Link href={route('groups.index')} className={item} onClick={onClose}>
                Groups
            </Link>
            <Link href={route('pages.index')} className={item} onClick={onClose}>
                Pages
            </Link>
            <Link href={route('saved.index')} className={item} onClick={onClose}>
                Saved
            </Link>
            <Link href={route('profile.edit')} className={item} onClick={onClose}>
                Settings
            </Link>
            <Link
                href={route('logout')}
                method="post"
                as="button"
                className={item}
                onClick={onClose}
            >
                Log out
            </Link>
        </div>
    );
}
