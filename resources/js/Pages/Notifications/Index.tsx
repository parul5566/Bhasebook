import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { CheckIcon } from '@/Components/Icons';

type Item = {
    id: number;
    type: string;
    category: string;
    actor: { id: number; name: string; avatar_url: string | null; hue?: number } | null;
    text: string;
    href: string | null;
    read: boolean;
    created_at: string;
};

type Props = {
    items: Item[];
    tab: string;
    unread: { all: number; mentions: number };
    prefs: Record<string, boolean>;
};

const TABS = [
    ['all', 'All'],
    ['mentions', 'Mentions'],
    ['friend', 'Friends'],
    ['reaction', 'Reactions'],
    ['comment', 'Comments'],
    ['group', 'Groups'],
    ['page', 'Pages'],
] as const;

const CATEGORIES = ['friend', 'reaction', 'comment', 'follow', 'group', 'page', 'message', 'general'] as const;

export default function Index({ items, tab, unread, prefs }: Props) {
    const [localPrefs, setLocalPrefs] = useState(prefs);
    const [savedMsg, setSavedMsg] = useState('');

    const markAll = async () => {
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        await fetch(route('notifications.readAll'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        }).catch(() => {});
        router.reload();
    };

    const savePrefs = async (next: Record<string, boolean>) => {
        setLocalPrefs(next);
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        await fetch(route('notifications.settings'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ prefs: next }),
        }).catch(() => {});
        setSavedMsg('Saved ✓');
        setTimeout(() => setSavedMsg(''), 1500);
    };

    return (
        <AuthenticatedLayout title="Notifications">
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">Notifications</h1>
                    {unread.all > 0 && (
                        <button type="button" className="bhas-btn-ghost text-xs" onClick={markAll}>
                            <CheckIcon className="h-4 w-4" /> Mark all read
                        </button>
                    )}
                </div>

                <div className="bhas-scroll-x">
                    {TABS.map(([key, label]) => (
                        <Link
                            key={key}
                            href={route('notifications.index', { tab: key })}
                            preserveState
                            className={`shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold transition ${tab === key ? 'bg-bhas-600 text-white' : 'bg-bhas-100 text-bhas-700 hover:bg-bhas-200 dark:bg-bhas-800 dark:text-bhas-100'}`}
                        >
                            {label}
                            {key === 'all' && unread.all > 0 && <span className="ml-1.5 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">{unread.all}</span>}
                            {key === 'mentions' && unread.mentions > 0 && <span className="ml-1.5 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">{unread.mentions}</span>}
                        </Link>
                    ))}
                </div>

                {items.length === 0 && (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">
                        No notifications here yet. Interact with people and this fills up!
                    </div>
                )}

                <div className="space-y-1">
                    {items.map((n) => (
                        <Link
                            key={n.id}
                            href={n.href ?? '#'}
                            preserveScroll
                            className={`flex items-start gap-3 rounded-xl p-3 transition hover:bg-bhas-100 dark:hover:bg-bhas-800/60 ${!n.read ? 'bg-bhas-50 dark:bg-bhas-800/40' : ''}`}
                        >
                            {n.actor ? <UserAvatar user={n.actor} size={40} /> : <div className="flex h-10 w-10 items-center justify-center rounded-full bg-bhas-100 text-lg dark:bg-bhas-800">🔔</div>}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm">
                                    {n.actor && <span className="font-bold">{n.actor.name} </span>}
                                    <span className="text-slate-600 dark:text-slate-300">{n.text}</span>
                                </p>
                                <p className="text-xs text-slate-400">{n.created_at}</p>
                            </div>
                            {!n.read && <span className="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-bhas-500" />}
                        </Link>
                    ))}
                </div>

                <details className="bhas-card p-4">
                    <summary className="cursor-pointer text-sm font-bold">Notification settings</summary>
                    <div className="mt-3 space-y-2">
                        {CATEGORIES.map((cat) => (
                            <label key={cat} className="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-bhas-50 dark:hover:bg-bhas-800/50">
                                <span className="text-sm capitalize">{cat === 'friend' ? 'Friend activity' : cat === 'general' ? 'General' : cat + 's'}</span>
                                <input
                                    type="checkbox"
                                    checked={localPrefs[cat] ?? true}
                                    onChange={(e) => savePrefs({ ...localPrefs, [cat]: e.target.checked })}
                                    className="h-4 w-8 appearance-none rounded-full bg-slate-300 transition checked:bg-bhas-500 dark:bg-bhas-700"
                                />
                            </label>
                        ))}
                        {savedMsg && <p className="text-xs font-bold text-emerald-600">{savedMsg}</p>}
                    </div>
                </details>
            </div>
        </AuthenticatedLayout>
    );
}
