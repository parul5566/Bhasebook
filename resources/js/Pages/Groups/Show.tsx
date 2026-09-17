import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Composer from '@/Components/Composer';
import PostCard, { type SerializedPost } from '@/Components/PostCard';
import { UserAvatar } from '@/Pages/Profile/Show';
import { CheckIcon, LockIcon, GlobeIcon, UsersIcon } from '@/Components/Icons';

type Props = {
    group: {
        id: number; name: string; description: string | null; cover_url: string | null;
        privacy: string; requires_approval: boolean; rules: string | null;
        members_count: number; created_at: string;
    };
    posts: SerializedPost[];
    members: ({ id: number; name: string; avatar_url: string | null; hue?: number; role: string })[];
    pending: ({ id: number; name: string; avatar_url: string | null; hue?: number })[];
    my_role: string | null;
    my_status: string | null;
};

export default function Show({ group, posts, members, pending, my_role, my_status }: Props) {
    const [showRules, setShowRules] = useState(false);
    const isAdmin = my_role === 'owner' || my_role === 'admin';

    return (
        <AuthenticatedLayout title={group.name}>
            <div className="mx-auto max-w-5xl space-y-4">
                {/* Cover */}
                <div className="bhas-card overflow-hidden">
                    <div
                        className={`h-40 bg-gradient-to-br from-bhas-400 to-bhas-600 sm:h-56`}
                        style={group.cover_url ? { backgroundImage: `url(${group.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
                    />
                    <div className="space-y-3 p-4 sm:p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h1 className="text-2xl font-extrabold">{group.name}</h1>
                                <p className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                    <UsersIcon className="h-4 w-4" /> {group.members_count} members
                                    <span className="flex items-center gap-1 text-xs">{group.privacy === 'private' ? <><LockIcon className="h-3.5 w-3.5" /> Private</> : <><GlobeIcon className="h-3.5 w-3.5" /> Public</>}</span>
                                    · Created {group.created_at}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {my_status === 'active' && (
                                    <>
                                        {isAdmin && pending.length > 0 && (
                                            <Link href="#pending" className="bhas-btn-ghost text-xs">{pending.length} pending approval</Link>
                                        )}
                                        <button
                                            type="button"
                                            className="bhas-btn-ghost !text-rose-600 text-xs"
                                            onClick={() => { if (confirm('Leave this group?')) router.post(route('groups.leave', { group: group.id })); }}
                                        >
                                            Leave group
                                        </button>
                                    </>
                                )}
                                {my_status === null && (
                                    <button type="button" className="bhas-btn-primary" onClick={() => router.post(route('groups.join', { group: group.id }))}>
                                        {group.requires_approval ? 'Request to join' : 'Join group'}
                                    </button>
                                )}
                                {my_status === 'pending' && (
                                    <span className="rounded-xl bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700">Request pending</span>
                                )}
                            </div>
                        </div>
                        {group.description && <p className="text-sm text-slate-600 dark:text-slate-300">{group.description}</p>}
                        {group.rules && (
                            <div>
                                <button type="button" className="text-xs font-bold text-bhas-600 hover:underline" onClick={() => setShowRules((s) => !s)}>
                                    {showRules ? 'Hide group rules' : 'View group rules'}
                                </button>
                                {showRules && <pre className="mt-2 whitespace-pre-wrap rounded-xl bg-bhas-50 p-3 text-xs text-slate-600 dark:bg-bhas-900 dark:text-slate-300">{group.rules}</pre>}
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                    {/* Feed */}
                    <section className="space-y-4">
                        {my_status === 'active' && (
                            <Composer groupId={group.id} />
                        )}
                        {posts.length === 0 ? (
                            <div className="bhas-card p-10 text-center text-sm text-slate-400">
                                {my_status === 'active' ? 'No posts yet — start the conversation!' : 'Join this group to see and create posts.'}
                            </div>
                        ) : (
                            posts.map((post) => <PostCard key={post.id} post={post} />)
                        )}
                    </section>

                    {/* Sidebar */}
                    <aside className="space-y-4">
                        {isAdmin && pending.length > 0 && (
                            <div id="pending" className="bhas-card p-4">
                                <h3 className="mb-3 text-sm font-bold">Pending requests ({pending.length})</h3>
                                <div className="space-y-3">
                                    {pending.map((u) => (
                                        <div key={u.id} className="flex items-center gap-2">
                                            <UserAvatar user={u} size={32} />
                                            <p className="min-w-0 flex-1 truncate text-sm font-semibold">{u.name}</p>
                                            <button type="button" className="bhas-btn-primary !px-2 !py-1" aria-label="Approve"
                                                onClick={() => router.post(route('groups.approve', { group: group.id, user: u.id }))}>
                                                <CheckIcon className="h-4 w-4" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="bhas-card p-4">
                            <h3 className="mb-3 text-sm font-bold">Members</h3>
                            <div className="space-y-2">
                                {members.slice(0, 12).map((m) => (
                                    <div key={m.id} className="group flex items-center gap-2">
                                        <UserAvatar user={m} size={30} />
                                        <Link href={route('profile.show', { user: m.id })} className="min-w-0 flex-1 truncate text-sm font-semibold hover:underline">{m.name}</Link>
                                        <span className="text-[10px] font-bold uppercase text-slate-400">{m.role}</span>
                                        {isAdmin && m.role !== 'owner' && (
                                            <div className="hidden gap-1 group-hover:flex">
                                                <button
                                                    type="button"
                                                    className="rounded bg-bhas-100 px-1.5 py-0.5 text-[10px] font-bold text-bhas-700 dark:bg-bhas-800"
                                                    onClick={() => router.post(route('groups.members.role', { group: group.id, user: m.id }), { role: m.role === 'admin' ? 'member' : 'admin' })}
                                                    title={m.role === 'admin' ? 'Demote to member' : 'Promote to admin'}
                                                >
                                                    {m.role === 'admin' ? '↓' : '↑'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-600"
                                                    onClick={() => { if (confirm(`Remove ${m.name}?`)) router.post(route('groups.members.remove', { group: group.id, user: m.id })); }}
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {members.length > 12 && <p className="text-xs text-slate-400">and {members.length - 12} more…</p>}
                            </div>
                        </div>

                        {my_role === 'owner' && (
                            <InviteBox groupId={group.id} members={members} />
                        )}
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function InviteBox({ groupId }: { groupId: number; members: ({ id: number } | never)[] }) {
    const [email, setEmail] = useState('');
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState('');

    // Invite by user id: simplest reliable path is entering a numeric id or name via search; keep simple for MVP.
    const [userId, setUserId] = useState('');

    return (
        <div className="bhas-card p-4">
            <h3 className="mb-2 text-sm font-bold">Invite a friend</h3>
            <p className="mb-2 text-xs text-slate-400">Find them on the <Link href={route('friends.index')} className="font-bold text-bhas-600 hover:underline">Friends page</Link> or search, then invite by their user ID.</p>
            <div className="flex gap-2">
                <input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="User ID" className="bhas-input !py-1.5 text-xs" inputMode="numeric" />
                <button
                    type="button"
                    className="bhas-btn-primary !py-1.5 text-xs"
                    disabled={busy || !userId}
                    onClick={async () => {
                        setBusy(true);
                        try {
                            await router.post(route('groups.invite', { group: groupId }), { user_id: Number(userId) });
                            setMsg('Invited ✓');
                        } finally {
                            setBusy(false);
                            setUserId('');
                            setTimeout(() => setMsg(''), 1500);
                        }
                    }}
                >
                    Invite
                </button>
            </div>
            {msg && <p className="mt-1 text-xs font-bold text-emerald-600">{msg}</p>}
        </div>
    );
}
