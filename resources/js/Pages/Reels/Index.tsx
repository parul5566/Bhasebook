import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';
import { REACTIONS } from '@/Components/PostCard';
import { BookmarkIcon, CommentIcon, SendIcon, XIcon } from '@/Components/Icons';

type Reel = {
    id: number;
    author: (BasicUser & { type: string }) | null;
    content: string | null;
    media: { url: string; mime: string; kind: string }[];
    reaction_counts: Record<string, number>;
    reaction_total: number;
    my_reaction: string | null;
    comment_count: number;
    saved: boolean;
    view_count: number;
};

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

export default function ReelsIndex() {
    const [reels, setReels] = useState<Reel[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [done, setDone] = useState(false);
    const [error, setError] = useState('');
    const [loaded, setLoaded] = useState(false);

    const load = useCallback(async (cur?: string | null) => {
        try {
            setError('');
            const url = route('reels.feed') + (cur ? `?cursor=${cur}` : '');
            const data = await api(url, 'GET');
            setReels((prev) => {
                const seen = new Set(prev.map((r) => r.id));
                return [...prev, ...data.reels.filter((r: Reel) => !seen.has(r.id))];
            });
            setCursor(data.next_cursor ?? null);
            setDone(!data.next_cursor);
            setLoaded(true);
        } catch {
            setError('Could not load reels — tap to retry.');
        }
    }, []);

    useEffect(() => { void load(); }, [load]);

    const update = (id: number, patch: Partial<Reel>) =>
        setReels((prev) => prev.map((r) => (r.id === id ? { ...r, ...patch } : r)));

    return (
        <AuthenticatedLayout title="Reels">
            <Head title="Reels — Bhasebook" />
            <div className="mx-auto max-w-[420px]">
                {!loaded && !error && (
                    <div className="flex h-[60vh] items-center justify-center text-sm text-slate-400">
                        <div className="animate-pulse">Loading reels…</div>
                    </div>
                )}
                {loaded && reels.length === 0 && !error && (
                    <div className="flex h-[60vh] flex-col items-center justify-center gap-2 text-center text-sm text-slate-400">
                        <span className="text-3xl">🎬</span>
                        <p className="font-medium text-slate-500 dark:text-slate-300">No reels yet</p>
                        <p className="text-xs">When people share videos, their reels will show up here.</p>
                    </div>
                )}
                {error && reels.length === 0 && (
                    <button className="flex h-[60vh] w-full items-center justify-center text-sm text-rose-500" onClick={() => load(null)}>{error}</button>
                )}
                <div className="snap-y-mandatory h-[calc(100vh-8rem)] overflow-y-auto rounded-2xl">
                    {reels.map((reel) => (
                        <ReelItem key={reel.id} reel={reel} update={update} />
                    ))}
                    {!done && (
                        <Observer onVisible={() => cursor && load(cursor)} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Observer({ onVisible }: { onVisible: () => void }) {
    const ref = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!ref.current) return;
        const io = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting) onVisible();
        }, { rootMargin: '600px' });
        io.observe(ref.current);
        return () => io.disconnect();
    }, [onVisible]);
    return <div ref={ref} className="h-20" />;
}

function ReelItem({ reel, update }: { reel: Reel; update: (id: number, patch: Partial<Reel>) => void }) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [showReact, setShowReact] = useState(false);

    useEffect(() => {
        const el = videoRef.current;
        if (!el) return;
        const io = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) void el.play().catch(() => {});
                else el.pause();
            },
            { threshold: 0.6 },
        );
        io.observe(el);
        return () => io.disconnect();
    }, []);

    const react = async (type: string) => {
        setShowReact(false);
        const prev = reel.my_reaction;
        const counts = { ...reel.reaction_counts };
        if (prev) counts[prev] = Math.max(0, (counts[prev] ?? 0) - 1);
        const next = prev === type ? null : type;
        if (next) counts[next] = (counts[next] ?? 0) + 1;
        update(reel.id, {
            my_reaction: next,
            reaction_counts: counts,
            reaction_total: Math.max(0, reel.reaction_total + (next ? 1 : -1)),
        });
        try {
            const data = await api(route('posts.react', { post: reel.id }), 'POST', { type });
            update(reel.id, { reaction_counts: data.reaction_counts, reaction_total: data.reaction_total, my_reaction: data.my_reaction });
        } catch { /* optimistic already applied */ }
    };

    const save = async () => {
        update(reel.id, { saved: !reel.saved });
        try { await api(route('posts.save', { post: reel.id })); } catch {}
    };

    const video = reel.media.find((m) => m.kind === 'video') ?? reel.media[0];

    return (
        <div className="snap-start-always relative h-[calc(100vh-8rem)] w-full overflow-hidden rounded-2xl bg-black">
            {video && (
                <video
                    ref={videoRef}
                    src={video.url}
                    loop
                    playsInline
                    muted={false}
                    className="h-full w-full object-contain"
                />
            )}
            {/* Overlay gradient */}
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/70 to-transparent" />

            {/* Author + caption */}
            <div className="absolute bottom-20 left-4 right-20 text-white">
                <div className="mb-2 flex items-center gap-2">
                    <UserAvatar user={reel.author ?? { id: 0, name: 'Unknown', avatar_url: null, hue: 0 }} size={36} />
                    <span className="text-sm font-bold drop-shadow">{reel.author?.name ?? 'Unknown'}</span>
                </div>
                {reel.content && <p className="line-clamp-3 text-sm drop-shadow">{reel.content}</p>}
            </div>

            {/* Action rail */}
            <div className="absolute bottom-20 right-3 flex flex-col items-center gap-4 text-white">
                <div className="relative">
                    <button
                        type="button"
                        className="flex flex-col items-center"
                        onClick={() => react(reel.my_reaction ?? 'like')}
                        onContextMenu={(e) => { e.preventDefault(); setShowReact((s) => !s); }}
                        aria-label="React"
                    >
                        <span className="text-2xl">{REACTIONS.find((r) => r.type === (reel.my_reaction ?? 'like'))?.emoji}</span>
                        <span className="mt-0.5 text-[11px] font-semibold">{reel.reaction_total}</span>
                    </button>
                    {showReact && (
                        <div className="bhas-card absolute bottom-12 right-0 z-10 flex gap-1 p-1.5">
                            {REACTIONS.map((r) => (
                                <button key={r.type} type="button" className="rounded-full p-1 text-xl hover:scale-125 transition" onClick={() => react(r.type)} aria-label={r.label}>
                                    {r.emoji}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <a href={route('posts.show', { post: reel.id })} className="flex flex-col items-center" aria-label="Comments">
                    <CommentIcon className="h-6 w-6 drop-shadow" />
                    <span className="text-[11px] font-semibold">{reel.comment_count}</span>
                </a>
                <button type="button" className="flex flex-col items-center" onClick={save} aria-label="Save">
                    <BookmarkIcon className={`h-6 w-6 drop-shadow ${reel.saved ? 'fill-current text-amber-400' : ''}`} />
                    <span className="text-[11px] font-semibold">Save</span>
                </button>
            </div>
        </div>
    );
}
