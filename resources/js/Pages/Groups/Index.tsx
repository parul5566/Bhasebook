import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { GroupIcon, PlusIcon, UsersIcon } from '@/Components/Icons';
import { UserAvatar } from '@/Pages/Profile/Show';

type GroupCard = {
    id: number; name: string; description: string | null; cover_url: string | null;
    privacy: string; members_count: number; my_status: string | null; hue: number;
};

type Invite = {
    id: number;
    group: { id: number; name: string; description: string | null };
    inviter: { id: number; name: string; avatar_url: string | null };
};

type Props = { discover: GroupCard[]; mine: GroupCard[]; invites: Invite[]; q: string };

export default function Index({ discover, mine, invites, q }: Props) {
    const [creating, setCreating] = useState(false);

    return (
        <AuthenticatedLayout title="Groups">
            <div className="mx-auto max-w-3xl space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="flex items-center gap-2 text-xl font-bold"><GroupIcon className="h-6 w-6 text-bhas-600" /> Groups</h1>
                    <button type="button" className="bhas-btn-primary" onClick={() => setCreating(true)}>
                        <PlusIcon className="h-4 w-4" /> Create group
                    </button>
                </div>

                <form onSubmit={(e) => {
                    e.preventDefault();
                    const value = new FormData(e.currentTarget).get('q');
                    router.visit(route('groups.index', { q: value || '' }), { preserveState: true });
                }}>
                    <input name="q" defaultValue={q} className="bhas-input" placeholder="Search groups by name…" />
                </form>

                {invites.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Invitations</h2>
                        {invites.map((inv) => (
                            <div key={inv.id} className="bhas-card flex items-center gap-3 p-3">
                                <UserAvatar user={inv.inviter} size={40} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm"><b>{inv.inviter.name}</b> invited you to <b>{inv.group.name}</b></p>
                                    <p className="truncate text-xs text-slate-400">{inv.group.description}</p>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        className="bhas-btn-primary !py-1.5 text-xs"
                                        onClick={() => router.post(route('groups.invites.respond', { invite: inv.id }), { action: 'accept' })}
                                    >
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        className="bhas-btn-ghost !py-1.5 text-xs"
                                        onClick={() => router.post(route('groups.invites.respond', { invite: inv.id }), { action: 'decline' })}
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        ))}
                    </section>
                )}

                {mine.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Your groups</h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {mine.map((g) => <GroupTile key={g.id} group={g} />)}
                        </div>
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Discover groups</h2>
                    {discover.length === 0 ? (
                        <div className="bhas-card p-10 text-center text-sm text-slate-400">No groups found. Create the first one!</div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {discover.map((g) => <GroupTile key={g.id} group={g} />)}
                        </div>
                    )}
                </section>
            </div>

            {creating && <CreateGroupModal onClose={() => setCreating(false)} />}
        </AuthenticatedLayout>
    );
}

function GroupTile({ group: g }: { group: GroupCard }) {
    return (
        <Link href={route('groups.show', { group: g.id })} className="bhas-card overflow-hidden transition hover:shadow-pop">
            <div className={`h-20 bg-gradient-to-br ${g.cover_url ? '' : 'from-bhas-400 to-bhas-600'}`} style={g.cover_url ? { backgroundImage: `url(${g.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined} />
            <div className="p-3">
                <p className="truncate font-bold">{g.name}</p>
                <p className="flex items-center gap-1 text-xs text-slate-400">
                    <UsersIcon className="h-3.5 w-3.5" /> {g.members_count} members · {g.privacy}
                </p>
                {g.description && <p className="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{g.description}</p>}
                {g.my_status === 'pending' && <span className="mt-2 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Request pending</span>}
            </div>
        </Link>
    );
}

function CreateGroupModal({ onClose }: { onClose: () => void }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const submit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        const form = e.currentTarget;
        try {
            const body = new FormData(form);
            body.append('requires_approval', (form.elements.namedItem('auto') as HTMLInputElement).checked ? '0' : '1');
            const res = await fetch(route('groups.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body,
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message ?? 'Could not create group');
            }
            const data = await res.json();
            window.location.href = route('groups.show', { group: data.id });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed');
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="bhas-card w-full max-w-lg p-5" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-lg font-bold">Create a group</h3>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="bhas-label">Group name</label>
                        <input name="name" required minLength={3} maxLength={100} className="bhas-input" placeholder="e.g. Weekend Hikers" />
                    </div>
                    <div>
                        <label className="bhas-label">Description</label>
                        <textarea name="description" rows={3} maxLength={2000} className="bhas-input resize-none" placeholder="What is this group about?" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="bhas-label">Privacy</label>
                            <select name="privacy" className="bhas-input">
                                <option value="public">Public — anyone can find and join</option>
                                <option value="private">Private — only members can see posts</option>
                            </select>
                        </div>
                        <div>
                            <label className="bhas-label">Cover image</label>
                            <input name="cover" type="file" accept="image/*" className="bhas-input !py-2 text-xs" />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="auto" className="h-4 w-4 rounded" />
                        Auto-approve new members (skip approval)
                    </label>
                    {error && <p className="text-sm text-rose-600">{error}</p>}
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" className="bhas-btn-ghost" onClick={onClose}>Cancel</button>
                        <button type="submit" className="bhas-btn-primary" disabled={busy}>{busy ? 'Creating…' : 'Create group'}</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
