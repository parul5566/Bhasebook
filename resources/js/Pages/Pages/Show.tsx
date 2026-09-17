import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Composer from '@/Components/Composer';
import PostCard, { type SerializedPost } from '@/Components/PostCard';
import { UserAvatar } from '@/Pages/Profile/Show';

type Props = {
    page: {
        id: number; name: string; category: string; about: string | null;
        avatar_url: string | null; cover_url: string | null; followers_count: number; created_at: string;
    };
    posts: SerializedPost[];
    my_role: string | null;
    following: boolean;
    team: ({ id: number; name: string; avatar_url: string | null; hue?: number; role: string })[];
    insights: {
        followers: number; posts: number; total_reactions: number; total_comments: number;
        recent: { id: number; content: string | null; reactions: number; comments: number; views: number; created_at: string }[];
    } | null;
};

export default function Show({ page, posts, my_role, following, team, insights }: Props) {
    const [busy, setBusy] = useState(false);

    const toggleFollow = async () => {
        setBusy(true);
        try { await router.post(route('pages.follow', { page: page.id }), {}, { preserveState: true }); } finally { setBusy(false); }
    };

    return (
        <AuthenticatedLayout title={page.name}>
            <div className="mx-auto max-w-5xl space-y-4">
                <div className="bhas-card overflow-hidden">
                    <div
                        className="h-40 bg-gradient-to-br from-violet-400 to-fuchsia-600 sm:h-56"
                        style={page.cover_url ? { backgroundImage: `url(${page.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
                    />
                    <div className="p-4 sm:p-5">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div className="flex items-end gap-3">
                                {page.avatar_url ? (
                                    <img src={page.avatar_url} alt="" className="-mt-12 h-24 w-24 rounded-2xl border-4 border-white object-cover shadow dark:border-bhas-900" />
                                ) : (
                                    <div className="-mt-12 flex h-24 w-24 items-center justify-center rounded-2xl border-4 border-white bg-gradient-to-br from-violet-500 to-fuchsia-600 text-3xl font-extrabold text-white shadow dark:border-bhas-900">
                                        {page.name[0]}
                                    </div>
                                )}
                                <div className="pb-1">
                                    <h1 className="text-2xl font-extrabold">{page.name}</h1>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">{page.category} · {page.followers_count} followers · Created {page.created_at}</p>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button type="button" className={following ? 'bhas-btn-ghost' : 'bhas-btn-primary'} onClick={toggleFollow} disabled={busy}>
                                    {following ? 'Following ✓' : 'Follow'}
                                </button>
                                {my_role === null && following && (
                                    <button type="button" className="bhas-btn-ghost" onClick={() => router.get(route('messenger.index'))}>Message</button>
                                )}
                            </div>
                        </div>
                        {page.about && <p className="mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{page.about}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <section className="space-y-4">
                        {my_role && (
                            <Composer pageId={page.id} contextName={page.name} />
                        )}
                        {posts.length === 0 ? (
                            <div className="bhas-card p-10 text-center text-sm text-slate-400">
                                {my_role ? 'No posts yet — share something as this page!' : 'This page has no posts yet.'}
                            </div>
                        ) : (
                            posts.map((post) => <PostCard key={post.id} post={post} />)
                        )}
                    </section>

                    <aside className="space-y-4">
                        {insights && (
                            <div className="bhas-card p-4">
                                <h3 className="mb-3 text-sm font-bold">Insights</h3>
                                <div className="grid grid-cols-2 gap-2 text-center">
                                    <div className="rounded-xl bg-bhas-50 p-2 dark:bg-bhas-900">
                                        <p className="text-lg font-extrabold text-bhas-600">{insights.followers}</p>
                                        <p className="text-[10px] font-bold uppercase text-slate-400">Followers</p>
                                    </div>
                                    <div className="rounded-xl bg-bhas-50 p-2 dark:bg-bhas-900">
                                        <p className="text-lg font-extrabold text-bhas-600">{insights.posts}</p>
                                        <p className="text-[10px] font-bold uppercase text-slate-400">Posts</p>
                                    </div>
                                    <div className="rounded-xl bg-bhas-50 p-2 dark:bg-bhas-900">
                                        <p className="text-lg font-extrabold text-bhas-600">{insights.total_reactions}</p>
                                        <p className="text-[10px] font-bold uppercase text-slate-400">Reactions</p>
                                    </div>
                                    <div className="rounded-xl bg-bhas-50 p-2 dark:bg-bhas-900">
                                        <p className="text-lg font-extrabold text-bhas-600">{insights.total_comments}</p>
                                        <p className="text-[10px] font-bold uppercase text-slate-400">Comments</p>
                                    </div>
                                </div>
                                {insights.recent.length > 0 && (
                                    <div className="mt-3 space-y-1.5">
                                        <p className="text-xs font-bold uppercase text-slate-400">Recent posts</p>
                                        {insights.recent.map((r) => (
                                            <Link key={r.id} href={route('posts.show', { post: r.id })} className="block rounded-lg px-2 py-1 text-xs hover:bg-bhas-50 dark:hover:bg-bhas-800">
                                                <p className="truncate font-semibold">{r.content ?? '(media post)'}</p>
                                                <p className="text-slate-400">❤ {r.reactions} · 💬 {r.comments} · 👁 {r.views}</p>
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="bhas-card p-4">
                            <h3 className="mb-3 text-sm font-bold">Team</h3>
                            <div className="space-y-2">
                                {team.map((m) => (
                                    <div key={m.id} className="flex items-center gap-2">
                                        <UserAvatar user={m} size={30} />
                                        <Link href={route('profile.show', { user: m.id })} className="min-w-0 flex-1 truncate text-sm font-semibold hover:underline">{m.name}</Link>
                                        <span className="text-[10px] font-bold uppercase text-slate-400">{m.role}</span>
                                    </div>
                                ))}
                            </div>
                            {my_role === 'admin' && <AddTeamMember pageId={page.id} />}
                        </div>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function AddTeamMember({ pageId }: { pageId: number }) {
    const [userId, setUserId] = useState('');
    const [role, setRole] = useState('editor');
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState('');

    return (
        <div className="mt-3 border-t border-bhas-100 pt-3 dark:border-bhas-800">
            <p className="mb-1.5 text-xs font-bold uppercase text-slate-400">Add teammate</p>
            <div className="flex gap-2">
                <input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="User ID" inputMode="numeric" className="bhas-input !py-1.5 text-xs" />
                <select value={role} onChange={(e) => setRole(e.target.value)} className="bhas-input !w-auto !py-1.5 text-xs">
                    <option value="editor">Editor</option>
                    <option value="admin">Admin</option>
                </select>
                <button
                    type="button"
                    className="bhas-btn-primary !py-1.5 text-xs"
                    disabled={busy || !userId}
                    onClick={async () => {
                        setBusy(true);
                        try {
                            await router.post(route('pages.roles.add', { page: pageId }), { user_id: Number(userId), role });
                            setMsg('Added ✓');
                        } catch {
                            setMsg('Failed');
                        } finally {
                            setBusy(false);
                            setUserId('');
                            setTimeout(() => setMsg(''), 1500);
                        }
                    }}
                >
                    Add
                </button>
            </div>
            {msg && <p className="mt-1 text-xs font-bold text-emerald-600">{msg}</p>}
        </div>
    );
}
