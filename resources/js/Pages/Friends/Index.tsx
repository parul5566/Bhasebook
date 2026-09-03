import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';
import { PlusIcon, CheckIcon, SendIcon, XIcon } from '@/Components/Icons';

type Suggestion = BasicUser & { mutual: number };

export default function FriendsIndex({
    friends,
    received,
    sent,
    suggestions,
}: {
    friends: BasicUser[];
    received: BasicUser[];
    sent: BasicUser[];
    suggestions: Suggestion[];
}) {
    const [tab, setTab] = useState<'suggestions' | 'friends' | 'requests' | 'sent'>(
        received.length > 0 ? 'requests' : 'suggestions',
    );
    const flash = usePage().props as { status?: string };

    const tabs = [
        ['requests', `Requests${received.length ? ` (${received.length})` : ''}`],
        ['suggestions', 'Suggestions'],
        ['friends', `All friends${friends.length ? ` (${friends.length})` : ''}`],
        ['sent', `Sent${sent.length ? ` (${sent.length})` : ''}`],
    ] as const;

    return (
        <AuthenticatedLayout title="Friends">
            <Head title="Friends" />
            <div className="mx-auto max-w-4xl space-y-4">
                {flash.status && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        {flash.status}
                    </div>
                )}
                <div className="bhas-card flex gap-1 overflow-x-auto p-1.5">
                    {tabs.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={`whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition ${
                                tab === key
                                    ? 'bg-bhas-600 text-white'
                                    : 'text-slate-600 hover:bg-bhas-100 dark:text-slate-300 dark:hover:bg-bhas-800'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'requests' && (
                    <UserGrid users={received} action="accept" emptyText="No friend requests right now." title="Friend requests" />
                )}
                {tab === 'suggestions' && (
                    <UserGrid users={suggestions} action="add" emptyText="No suggestions right now." title="People you may know" />
                )}
                {tab === 'friends' && (
                    <UserGrid users={friends} action="unfriend" emptyText="You have no friends yet — add some!" title="All friends" />
                )}
                {tab === 'sent' && (
                    <UserGrid users={sent} action="cancel" emptyText="No pending sent requests." title="Sent requests" />
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function UserGrid({
    users,
    action,
    emptyText,
    title,
}: {
    users: (BasicUser & { mutual?: number })[];
    action: 'add' | 'accept' | 'cancel' | 'unfriend';
    emptyText: string;
    title: string;
}) {
    const [busy, setBusy] = useState<number | null>(null);

    const post = (url: string, id: number) => {
        if (busy) return;
        setBusy(id);
        void fetch(route(url, { user: id }), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
        }).then(() => window.location.reload());
    };

    const cfg = {
        add: { url: 'friends.request', label: 'Add friend', icon: <PlusIcon className="h-4 w-4" />, primary: true },
        accept: { url: 'friends.accept', label: 'Confirm', icon: <CheckIcon className="h-4 w-4" />, primary: true, decline: true },
        cancel: { url: 'friends.cancel', label: 'Cancel', icon: <SendIcon className="h-4 w-4" />, primary: false },
        unfriend: { url: 'friends.unfriend', label: 'Unfriend', icon: <XIcon className="h-4 w-4" />, primary: false },
    }[action];

    return (
        <section className="bhas-card p-4">
            <h2 className="mb-4 font-bold">{title}</h2>
            {users.length === 0 ? (
                <p className="py-10 text-center text-sm text-slate-400">{emptyText}</p>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {users.map((u) => (
                        <div key={u.id} className="rounded-2xl border border-bhas-100 p-3 dark:border-bhas-800">
                            <Link href={route('profile.show', { user: u.id })} className="flex items-center gap-3">
                                <UserAvatar user={u} size={56} />
                                <div className="min-w-0">
                                    <p className="truncate font-semibold">{u.name}</p>
                                    {u.mutual ? (
                                        <p className="text-xs text-slate-400">{u.mutual} mutual friends</p>
                                    ) : null}
                                </div>
                            </Link>
                            <div className="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    disabled={busy === u.id}
                                    onClick={() => post(cfg.url, u.id)}
                                    className={cfg.primary ? 'bhas-btn-primary flex-1 !py-1.5 text-xs' : 'bhas-btn-ghost flex-1 !py-1.5 text-xs'}
                                >
                                    {cfg.icon} {cfg.label}
                                </button>
                                {'decline' in cfg && cfg.decline && (
                                    <button
                                        type="button"
                                        disabled={busy === u.id}
                                        onClick={() => post('friends.decline', u.id)}
                                        className="bhas-btn-ghost flex-1 !py-1.5 text-xs"
                                    >
                                        <XIcon className="h-4 w-4" /> Delete
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
