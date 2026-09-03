import { Link, usePage } from '@inertiajs/react';
import { GroupIcon, HomeIcon, MenuIcon, MessengerIcon, StoreIcon, VideoIcon, GameIcon, GridIcon, BellIcon } from '@/Components/Icons';

export default function LeftSidebar() {
    const item =
        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-700 transition hover:bg-bhas-100 dark:text-slate-200 dark:hover:bg-bhas-800';
    return (
        <nav className="bhas-card p-2">
            <Link href={route('dashboard')} className={item}>
                <HomeIcon className="h-6 w-6 text-bhas-600" /> Home
            </Link>
            <Link href={route('friends.index')} className={item}>
                <GroupIcon className="h-6 w-6 text-emerald-600" /> Friends
            </Link>
            <Link href={route('watch.index')} className={item}>
                <VideoIcon className="h-6 w-6 text-rose-500" /> Watch
            </Link>
            <Link href={route('reels.index')} className={item}>
                <ReelGlyph /> Reels
            </Link>
            <Link href={route('groups.index')} className={item}>
                <GroupIcon className="h-6 w-6 text-sky-600" /> Groups
            </Link>
            <Link href={route('pages.index')} className={item}>
                <StoreIcon className="h-6 w-6 text-indigo-500" /> Pages
            </Link>
            <Link href={route('saved.index')} className={item}>
                <GridIcon className="h-6 w-6 text-amber-500" /> Saved
            </Link>
            <Link href={route('memories.index')} className={item}>
                <GameIcon className="h-6 w-6 text-fuchsia-500" /> Memories
            </Link>
            <Link href={route('messenger.index')} className={item}>
                <MessengerIcon className="h-6 w-6 text-cyan-600" /> Messenger
            </Link>
        </nav>
    );
}

function ReelGlyph() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-6 w-6 text-purple-600" aria-hidden="true">
            <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" strokeWidth="1.8" />
            <path d="M6 8.5h12M6 15.5h12M9.5 8.5 14.5 15.5M14.5 8.5 9.5 15.5" stroke="currentColor" strokeWidth="1.2" />
        </svg>
    );
}
