import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { GroupIcon, SearchIcon, StoreIcon, VideoIcon, HashIcon } from '@/Components/Icons';
import type { SerializedPost } from '@/Components/PostCard';
import PostCard from '@/Components/PostCard';

type Person = {
    id: number; name: string; avatar_url: string | null; hue?: number; bio?: string | null;
    mutual?: number; is_friend?: boolean; request_sent?: boolean; request_incoming?: boolean; following?: boolean;
};
type GroupCard = { id: number; name: string; privacy: string; description: string | null; members_count: number; joined: boolean };
type PageCard = { id: number; name: string; category: string; about: string | null; followers_count: number; following: boolean };
type ReelCard = { id: number; author: { name: string } | null; content: string | null; media: { url: string; kind?: string }[]; reaction_total: number; comment_count: number };
type HashtagCard = { tag: string; posts_count: number };

type Props = {
    q: string;
    tab: string;
    results: {
        people?: Person[]; posts?: SerializedPost[]; groups?: GroupCard[];
        pages?: PageCard[]; reels?: ReelCard[]; hashtags?: HashtagCard[];
    };
    recents: string[];
};

const TABS = [
    ['all', 'All'],
    ['people', 'People'],
    ['posts', 'Posts'],
    ['groups', 'Groups'],
    ['pages', 'Pages'],
    ['reels', 'Reels'],
    ['hashtags', 'Hashtags'],
] as const;

async function api(url: string, method = 'POST', body?: unknown) {
    const res = await fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message ?? 'Request failed');
    return res.json();
}

export default function Results({ q, tab, results, recents }: Props) {
    const [friends, setFriends] = useState<Record<number, string>>(() => {
        const map: Record<number, string> = {};
        results.people?.forEach((p) => {
            map[p.id] = p.is_friend ? 'friends' : p.request_sent ? 'sent' : p.request_incoming ? 'incoming' : 'none';
        });
        return map;
    });

    const addFriend = async (id: number) => {
        setFriends((f) => ({ ...f, [id]: 'sent' }));
        try {
            const data = await api(route('friends.request', { user: id }));
            setFriends((f) => ({ ...f, [id]: data.status ?? 'sent' }));
        } catch { setFriends((f) => ({ ...f, [id]: 'none' })); }
    };

    const total = (results.people?.length ?? 0) + (results.posts?.length ?? 0) + (results.groups?.length ?? 0)
        + (results.pages?.length ?? 0) + (results.reels?.length ?? 0) + (results.hashtags?.length ?? 0);

    return (
        <AuthenticatedLayout title={`Search: ${q || 'Bhasebook'}`}>
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="bhas-card p-4">
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        const value = new FormData(e.currentTarget).get('q');
                        if (value) router.visit(route('search.show', { q: String(value) }));
                    }}>
                        <div className="relative">
                            <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input name="q" defaultValue={q} autoFocus className="bhas-input pl-9" placeholder="Search Bhasebook" />
                        </div>
                    </form>
                    <div className="bhas-scroll-x mt-3">
                        {TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => router.visit(route('search.show', { q, tab: key }), { preserveState: true })}
                                className={`shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold transition ${tab === key ? 'bg-bhas-600 text-white' : 'bg-bhas-100 text-bhas-700 hover:bg-bhas-200 dark:bg-bhas-800 dark:text-bhas-100'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {!q && (
                    <div className="bhas-card p-4">
                        <h3 className="mb-2 font-bold">Recent searches</h3>
                        {recents.length === 0 ? (
                            <p className="text-sm text-slate-400">No recent searches yet.</p>
                        ) : (
                            <>
                                <div className="space-y-1">
                                    {recents.map((term) => (
                                        <Link key={term} href={route('search.show', { q: term })} className="block rounded-lg px-3 py-2 text-sm font-semibold hover:bg-bhas-100 dark:hover:bg-bhas-800">
                                            <SearchIcon className="mr-2 inline h-4 w-4 text-slate-400" />{term}
                                        </Link>
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    className="mt-2 text-xs font-bold text-bhas-600 hover:underline"
                                    onClick={async () => { await api(route('search.recents.clear')); router.reload(); }}
                                >
                                    Clear all
                                </button>
                            </>
                        )}
                    </div>
                )}

                {q && total === 0 && (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">
                        No results for “{q}”. Try different keywords.
                    </div>
                )}

                {results.people && results.people.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-400"><GroupIcon className="h-4 w-4" /> People</h2>
                        {results.people.map((p) => (
                            <div key={p.id} className="bhas-card flex items-center gap-3 p-3">
                                <UserAvatar user={p} size={44} />
                                <Link href={route('profile.show', { user: p.id })} className="min-w-0 flex-1">
                                    <p className="truncate font-semibold hover:underline">{p.name}</p>
                                    <p className="truncate text-xs text-slate-400">
                                        {p.mutual ? `${p.mutual} mutual friends` : p.bio ?? ''}
                                    </p>
                                </Link>
                                {friends[p.id] === 'friends' ? (
                                    <span className="rounded-full bg-bhas-100 px-3 py-1.5 text-xs font-bold text-bhas-700 dark:bg-bhas-800 dark:text-bhas-200">Friends</span>
                                ) : friends[p.id] === 'sent' ? (
                                    <span className="text-xs font-bold text-slate-400">Request sent</span>
                                ) : friends[p.id] === 'incoming' ? (
                                    <Link href={route('friends.index')} className="bhas-btn-primary !py-1.5 text-xs">Respond</Link>
                                ) : (
                                    <button type="button" className="bhas-btn-ghost !py-1.5 text-xs" onClick={() => addFriend(p.id)}>Add friend</button>
                                )}
                            </div>
                        ))}
                    </section>
                )}

                {results.posts && results.posts.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Posts</h2>
                        {results.posts.map((post) => <PostCard key={post.id} post={post} />)}
                    </section>
                )}

                {results.groups && results.groups.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-400"><GroupIcon className="h-4 w-4" /> Groups</h2>
                        {results.groups.map((g) => (
                            <Link key={g.id} href={route('groups.show', { group: g.id })} className="bhas-card flex items-center gap-3 p-3 hover:shadow-pop">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-bhas-400 to-bhas-600 font-bold text-white">
                                    {g.name[0]}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">{g.name}</p>
                                    <p className="truncate text-xs text-slate-400">{g.members_count} members · {g.privacy}</p>
                                </div>
                                {g.joined && <span className="text-xs font-bold text-bhas-600">Joined</span>}
                            </Link>
                        ))}
                    </section>
                )}

                {results.pages && results.pages.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-400"><StoreIcon className="h-4 w-4" /> Pages</h2>
                        {results.pages.map((p) => (
                            <Link key={p.id} href={route('pages.show', { page: p.id })} className="bhas-card flex items-center gap-3 p-3 hover:shadow-pop">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-400 to-fuchsia-600 font-bold text-white">
                                    {p.name[0]}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">{p.name}</p>
                                    <p className="truncate text-xs text-slate-400">{p.category} · {p.followers_count} followers</p>
                                </div>
                            </Link>
                        ))}
                    </section>
                )}

                {results.reels && results.reels.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-400"><VideoIcon className="h-4 w-4" /> Reels</h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {results.reels.map((r) => (
                                <Link key={r.id} href={route('posts.show', { post: r.id })} className="bhas-card overflow-hidden">
                                    <video src={r.media.find((m) => m.kind === 'video')?.url ?? r.media[0]?.url} className="aspect-[9/16] w-full object-cover" muted preload="metadata" />
                                    <div className="p-2">
                                        <p className="truncate text-xs font-semibold">{r.author?.name}</p>
                                        <p className="text-[10px] text-slate-400">❤ {r.reaction_total} · 💬 {r.comment_count}</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {results.hashtags && results.hashtags.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-400"><HashIcon className="h-4 w-4" /> Hashtags</h2>
                        {results.hashtags.map((h) => (
                            <Link key={h.tag} href={route('hashtag.show', { tag: h.tag })} className="bhas-card flex items-center gap-3 p-3 hover:shadow-pop">
                                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-bhas-100 text-lg font-bold text-bhas-600 dark:bg-bhas-800">#</span>
                                <div>
                                    <p className="font-semibold">#{h.tag}</p>
                                    <p className="text-xs text-slate-400">{h.posts_count} posts</p>
                                </div>
                            </Link>
                        ))}
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
