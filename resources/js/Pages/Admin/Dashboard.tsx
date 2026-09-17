import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { CheckIcon, FlagIcon, TrashIcon, XIcon } from '@/Components/Icons';

type Props = {
    tab: string;
    stats: Record<string, number> & { daily_new_users?: { d: string; c: number }[] };
    users: null | ({
        id: number; name: string; email: string; status: string; suspended_until: string | null;
        is_admin: boolean; posts_count: number; comments_count: number; joined: string;
        avatar_url: string | null;
    })[];
    posts: null | { id: number; content: string; author: string | null; type: string; visibility: string; reactions: number; comments: number; deleted: boolean; created_at: string }[];
    comments: null | { id: number; content: string; author: string | null; post_id: number; reactions: number; created_at: string }[];
    reports: null | { id: number; reason: string; details: string | null; status: string; reporter: string | null; target_type: string; target_id: number; target_preview: string | null; handled: boolean; created_at: string }[];
    groups: null | { id: number; name: string; privacy: string; members_count: number; creator: string | null; created_at: string }[];
    pages: null | { id: number; name: string; category: string; followers_count: number; creator: string | null; created_at: string }[];
    aiFlags: null | { id: number; target_type: string; target_id: number; preview: string | null; risk_score: number; reasons: string[]; suggested_action: string; created_at: string }[];
    settings: null | Record<string, string>;
};

const TABS = [
    ['overview', 'Overview'],
    ['users', 'Users'],
    ['posts', 'Posts'],
    ['comments', 'Comments'],
    ['reports', 'Reports'],
    ['groups', 'Groups'],
    ['pages', 'Pages'],
    ['moderation', 'AI Moderation'],
    ['settings', 'Settings'],
] as const;

async function act(url: string, body?: Record<string, unknown>, confirmMsg?: string) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    await router.post(url, (body ?? {}) as never);
}

export default function Dashboard({ tab, stats, users, posts, comments, reports, groups, pages, aiFlags, settings }: Props) {
    return (
        <AuthenticatedLayout title="Admin">
            <div className="mx-auto max-w-6xl space-y-4">
                <h1 className="flex items-center gap-2 text-xl font-bold">
                    🛡️ <span>Admin dashboard</span>
                </h1>

                <div className="bhas-scroll-x">
                    {TABS.map(([key, label]) => (
                        <Link
                            key={key}
                            href={route('admin.dashboard', { tab: key })}
                            preserveState
                            className={`shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold transition ${tab === key ? 'bg-bhas-600 text-white' : 'bg-bhas-100 text-bhas-700 hover:bg-bhas-200 dark:bg-bhas-800 dark:text-bhas-100'}`}
                        >
                            {label}
                            {key === 'reports' && stats.open_reports > 0 && <span className="ml-1.5 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">{stats.open_reports}</span>}
                            {key === 'moderation' && stats.pending_ai_flags > 0 && <span className="ml-1.5 rounded-full bg-amber-500 px-1.5 text-[10px] text-white">{stats.pending_ai_flags}</span>}
                        </Link>
                    ))}
                </div>

                {tab === 'overview' && <Overview stats={stats} />}
                {tab === 'users' && <Users users={users} />}
                {tab === 'posts' && <PostsTable posts={posts} />}
                {tab === 'comments' && <CommentsTable comments={comments} />}
                {tab === 'reports' && <Reports reports={reports} />}
                {tab === 'groups' && <GroupsTable groups={groups} />}
                {tab === 'pages' && <PagesTable pages={pages} />}
                {tab === 'moderation' && <Moderation flags={aiFlags} />}
                {tab === 'settings' && <SettingsForm settings={settings} />}
            </div>
        </AuthenticatedLayout>
    );
}

function Overview({ stats }: { stats: Props['stats'] }) {
    const cards: [string, number | string][] = [
        ['Total users', stats.total_users],
        ['Active users', stats.active_users],
        ['New this week', stats.new_users_7d],
        ['Posts', stats.total_posts],
        ['Comments', stats.total_comments],
        ['Reactions', stats.total_reactions],
        ['Groups', stats.total_groups],
        ['Pages', stats.total_pages],
        ['Open reports', stats.open_reports],
        ['Banned', stats.banned_users],
    ];
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                {cards.map(([label, value]) => (
                    <div key={label} className="bhas-card p-4">
                        <p className="text-2xl font-extrabold text-bhas-600">{value as number}</p>
                        <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">{label}</p>
                    </div>
                ))}
            </div>
            {stats.daily_new_users && stats.daily_new_users.length > 0 && (
                <div className="bhas-card p-4">
                    <h3 className="mb-3 text-sm font-bold">New users (last 14 days)</h3>
                    <div className="flex h-32 items-end gap-1">
                        {stats.daily_new_users.map((d) => (
                            <div key={d.d} className="flex-1" title={`${d.d}: ${d.c}`}>
                                <div className="rounded-t bg-bhas-500" style={{ height: `${Math.max(6, (d.c / Math.max(...stats.daily_new_users!.map((x) => x.c))) * 100)}%` }} />
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function Users({ users }: { users: Props['users'] }) {
    const [q, setQ] = useState('');
    return (
        <div className="space-y-3">
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.dashboard', { tab: 'users', q })); }}>
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name or email…" className="bhas-input" />
            </form>
            <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
                {(users ?? []).map((u) => (
                    <div key={u.id} className="flex flex-wrap items-center gap-3 p-3">
                        <UserAvatar user={{ id: u.id, name: u.name, avatar_url: u.avatar_url, hue: u.id * 47 % 360 }} size={38} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold">{u.name} {u.is_admin && <span className="rounded bg-bhas-100 px-1.5 text-[10px] font-bold text-bhas-700">ADMIN</span>}</p>
                            <p className="truncate text-xs text-slate-400">{u.email} · {u.posts_count} posts · joined {u.joined}</p>
                        </div>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                            u.status === 'active' ? 'bg-emerald-100 text-emerald-700' :
                            u.status === 'suspended' ? 'bg-amber-100 text-amber-700' :
                            u.status === 'banned' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'
                        }`}>
                            {u.status}
                        </span>
                        <div className="flex gap-1">
                            {u.status === 'active' && (
                                <>
                                    <button type="button" className="rounded-lg bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-700" onClick={() => act(route('admin.users.action', { user: u.id }), { action: 'warn', reason: 'Rule violation' })}>Warn</button>
                                    <button type="button" className="rounded-lg bg-orange-100 px-2 py-1 text-[10px] font-bold text-orange-700" onClick={() => act(route('admin.users.action', { user: u.id }), { action: 'suspend', days: 7 })}>Suspend</button>
                                    <button type="button" className="rounded-lg bg-rose-100 px-2 py-1 text-[10px] font-bold text-rose-700" onClick={() => act(route('admin.users.action', { user: u.id }), { action: 'ban' }, `Ban ${u.name}?`)}>Ban</button>
                                </>
                            )}
                            {(u.status === 'suspended' || u.status === 'banned') && (
                                <button type="button" className="rounded-lg bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-700" onClick={() => act(route('admin.users.action', { user: u.id }), { action: 'unban' })}>Restore</button>
                            )}
                            <button type="button" className="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600" onClick={() => act(route('admin.users.action', { user: u.id }), { action: 'delete' }, `Permanently delete ${u.name}? This removes all their content.`)}>Delete</button>
                        </div>
                    </div>
                ))}
                {users?.length === 0 && <p className="p-6 text-center text-sm text-slate-400">No users found.</p>}
            </div>
        </div>
    );
}

function PostsTable({ posts }: { posts: Props['posts'] }) {
    return (
        <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
            {(posts ?? []).map((p) => (
                <div key={p.id} className="flex items-center gap-3 p-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm">{p.content || <i className="text-slate-400">(media post)</i>}</p>
                        <p className="text-xs text-slate-400">{p.author} · {p.type} · {p.visibility} · {p.created_at} · ❤ {p.reactions} 💬 {p.comments}</p>
                    </div>
                    <Link href={route('posts.show', { post: p.id })} className="text-xs font-bold text-bhas-600 hover:underline">View</Link>
                    {!p.deleted && (
                        <button type="button" onClick={() => { if (confirm('Delete this post?')) router.delete(route('posts.destroy', { post: p.id }), { preserveScroll: true }); }}>
                            <TrashIcon className="h-4 w-4 text-rose-500" />
                        </button>
                    )}
                </div>
            ))}
            {posts?.length === 0 && <p className="p-6 text-center text-sm text-slate-400">No posts.</p>}
        </div>
    );
}

function CommentsTable({ comments }: { comments: Props['comments'] }) {
    return (
        <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
            {(comments ?? []).map((c) => (
                <div key={c.id} className="flex items-center gap-3 p-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm">{c.content || <i className="text-slate-400">(attachment)</i>}</p>
                        <p className="text-xs text-slate-400">{c.author} · on post #{c.post_id} · {c.created_at}</p>
                    </div>
                    <button type="button" onClick={() => { if (confirm('Delete this comment?')) router.delete(route('comments.destroy', { comment: c.id }), { preserveScroll: true }); }}>
                        <TrashIcon className="h-4 w-4 text-rose-500" />
                    </button>
                </div>
            ))}
        </div>
    );
}

function Reports({ reports }: { reports: Props['reports'] }) {
    return (
        <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
            {(reports ?? []).map((r) => (
                <div key={r.id} className="p-3">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="text-sm font-bold">
                                <FlagIcon className="mr-1 inline h-4 w-4 text-amber-500" />
                                {r.reason} <span className="font-normal text-slate-400">on {r.target_type} #{r.target_id}</span>
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Reported by {r.reporter} · {r.created_at}</p>
                            {r.target_preview && <p className="mt-1 rounded-lg bg-bhas-50 px-2 py-1 text-xs italic text-slate-500 dark:bg-bhas-900">"{r.target_preview}"</p>}
                            {r.details && <p className="mt-1 text-xs text-slate-400">Details: {r.details}</p>}
                        </div>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                            r.status === 'open' ? 'bg-amber-100 text-amber-700' : r.status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'
                        }`}>{r.status}</span>
                    </div>
                    {r.status === 'open' && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            <button type="button" className="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-bhas-800" onClick={() => act(route('admin.reports.action', { report: r.id }), { action: 'dismiss' })}>Dismiss</button>
                            <button type="button" className="rounded-lg bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-700" onClick={() => act(route('admin.reports.action', { report: r.id }), { action: 'resolve_remove' }, 'Remove the reported content?')}>Remove content</button>
                            <button type="button" className="rounded-lg bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-700" onClick={() => act(route('admin.reports.action', { report: r.id }), { action: 'warn' })}>Warn author</button>
                            <button type="button" className="rounded-lg bg-orange-100 px-2.5 py-1 text-[11px] font-bold text-orange-700" onClick={() => act(route('admin.reports.action', { report: r.id }), { action: 'suspend' })}>Suspend author</button>
                            <button type="button" className="rounded-lg bg-rose-200 px-2.5 py-1 text-[11px] font-bold text-rose-800" onClick={() => act(route('admin.reports.action', { report: r.id }), { action: 'ban' }, 'Ban the author?')}>Ban author</button>
                        </div>
                    )}
                </div>
            ))}
            {reports?.length === 0 && <p className="p-6 text-center text-sm text-slate-400">No reports here — nice!</p>}
        </div>
    );
}

function GroupsTable({ groups }: { groups: Props['groups'] }) {
    return (
        <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
            {(groups ?? []).map((g) => (
                <div key={g.id} className="flex items-center gap-3 p-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold">{g.name}</p>
                        <p className="text-xs text-slate-400">{g.members_count} members · {g.privacy} · by {g.creator} · {g.created_at}</p>
                    </div>
                    <Link href={route('groups.show', { group: g.id })} className="text-xs font-bold text-bhas-600 hover:underline">View</Link>
                </div>
            ))}
        </div>
    );
}

function PagesTable({ pages }: { pages: Props['pages'] }) {
    return (
        <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
            {(pages ?? []).map((p) => (
                <div key={p.id} className="flex items-center gap-3 p-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold">{p.name}</p>
                        <p className="text-xs text-slate-400">{p.category} · {p.followers_count} followers · by {p.creator} · {p.created_at}</p>
                    </div>
                    <Link href={route('pages.show', { page: p.id })} className="text-xs font-bold text-bhas-600 hover:underline">View</Link>
                </div>
            ))}
        </div>
    );
}

function Moderation({ flags }: { flags: Props['aiFlags'] }) {
    return (
        <div className="space-y-3">
            <p className="text-xs text-slate-500 dark:text-slate-400">
                AI flags content that may violate the rules. You decide — AI only assists.
            </p>
            <div className="bhas-card divide-y divide-bhas-50 dark:divide-bhas-800">
                {(flags ?? []).map((f) => (
                    <div key={f.id} className="p-3">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-sm font-bold">
                                    <span className={`mr-2 rounded-full px-2 py-0.5 text-[10px] font-bold ${f.risk_score >= 0.7 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>
                                        risk {Math.round(f.risk_score * 100)}%
                                    </span>
                                    {f.target_type} #{f.target_id}
                                </p>
                                {f.preview && <p className="mt-1 rounded-lg bg-bhas-50 px-2 py-1 text-xs italic text-slate-500 dark:bg-bhas-900">"{f.preview}"</p>}
                                {f.reasons.length > 0 && <p className="mt-1 text-xs text-slate-400">Reasons: {f.reasons.join(', ')}</p>}
                                <p className="text-xs text-slate-400">Suggested: <b>{f.suggested_action}</b> · {f.created_at}</p>
                            </div>
                        </div>
                        <div className="mt-2 flex gap-1.5">
                            <button type="button" className="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-bhas-800" onClick={() => act(route('admin.flags.action', { flag: f.id }), { action: 'dismiss' })}>Dismiss</button>
                            <button type="button" className="rounded-lg bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-700" onClick={() => act(route('admin.flags.action', { flag: f.id }), { action: 'remove_content' }, 'Remove this content?')}>Remove content</button>
                            <button type="button" className="rounded-lg bg-rose-200 px-2.5 py-1 text-[11px] font-bold text-rose-800" onClick={() => act(route('admin.flags.action', { flag: f.id }), { action: 'ban_author' }, 'Ban the author?')}>Ban author</button>
                        </div>
                    </div>
                ))}
                {flags?.length === 0 && <p className="p-6 text-center text-sm text-slate-400">Nothing flagged right now.</p>}
            </div>
        </div>
    );
}

function SettingsForm({ settings }: { settings: Props['settings'] }) {
    const [values, setValues] = useState<Record<string, string>>(() => {
        const defaults: Record<string, string> = {
            site_name: 'Bhasebook',
            welcome_message: 'Welcome to Bhasebook — share your world!',
            registration_open: '1',
        };
        return { ...defaults, ...(settings ?? {}) };
    });
    const fields: [string, string][] = [
        ['site_name', 'Site name'],
        ['welcome_message', 'Welcome message'],
        ['registration_open', 'Registration open (1 or 0)'],
    ];
    return (
        <form
            className="bhas-card max-w-lg space-y-3 p-4"
            onSubmit={(e) => {
                e.preventDefault();
                void act(route('admin.settings'), { settings: values });
            }}
        >
            {fields.map(([key, label]) => (
                <div key={key}>
                    <label className="bhas-label">{label}</label>
                    <input value={values[key] ?? ''} onChange={(e) => setValues((v) => ({ ...v, [key]: e.target.value }))} className="bhas-input" />
                </div>
            ))}
            <button type="submit" className="bhas-btn-primary">Save settings</button>
        </form>
    );
}
