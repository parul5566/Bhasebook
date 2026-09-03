import { useCallback, useEffect, useRef, useState } from 'react';
import PostCard, { type SerializedPost } from '@/Components/PostCard';

export default function InfiniteFeed() {
    const [posts, setPosts] = useState<SerializedPost[]>([]);
    const [cursor, setCursor] = useState<number | null>(null);
    const [initial, setInitial] = useState(true);
    const [loading, setLoading] = useState(false);
    const [done, setDone] = useState(false);
    const [error, setError] = useState('');
    const sentinel = useRef<HTMLDivElement>(null);

    const load = useCallback(async (cur: number | null) => {
        setLoading(true);
        setError('');
        try {
            const res = await fetch(route('feed') + (cur ? `?cursor=${cur}` : ''), { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load feed');
            const data = await res.json();
            setPosts((prev) => {
                const seen = new Set(prev.map((p) => p.id));
                return [...prev, ...data.posts.filter((p: SerializedPost) => !seen.has(p.id))];
            });
            setCursor(data.next_cursor);
            setDone(data.next_cursor === null);
        } catch {
            setError('Could not load the feed. Pull down to retry.');
        } finally {
            setLoading(false);
            setInitial(false);
        }
    }, []);

    useEffect(() => { void load(null); }, [load]);

    // New post event → refresh
    useEffect(() => {
        const onCreate = () => { setPosts([]); setCursor(null); setDone(false); void load(null); };
        window.addEventListener('bhas:post-created', onCreate);
        return () => window.removeEventListener('bhas:post-created', onCreate);
    }, [load]);

    // Infinite scroll
    useEffect(() => {
        const el = sentinel.current;
        if (!el) return;
        const io = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !loading && !done && cursor !== null) {
                void load(cursor);
            }
        }, { rootMargin: '600px' });
        io.observe(el);
        return () => io.disconnect();
    }, [loading, done, cursor, load]);

    if (initial) {
        return (
            <div className="space-y-4">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="bhas-card animate-pulse p-4">
                        <div className="flex items-center gap-3">
                            <div className="h-11 w-11 rounded-full bg-bhas-100 dark:bg-bhas-800" />
                            <div className="flex-1 space-y-2">
                                <div className="h-3 w-32 rounded bg-bhas-100 dark:bg-bhas-800" />
                                <div className="h-2.5 w-20 rounded bg-bhas-100 dark:bg-bhas-800" />
                            </div>
                        </div>
                        <div className="mt-4 space-y-2">
                            <div className="h-3 w-full rounded bg-bhas-100 dark:bg-bhas-800" />
                            <div className="h-3 w-2/3 rounded bg-bhas-100 dark:bg-bhas-800" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {error && (
                <button type="button" onClick={() => load(cursor)} className="w-full rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                    {error} Tap to retry.
                </button>
            )}
            {posts.map((p) => (
                <PostCard key={p.id} post={p} onDeleted={(id) => setPosts((prev) => prev.filter((x) => x.id !== id))} />
            ))}
            {posts.length === 0 && !error && (
                <div className="bhas-card p-10 text-center">
                    <p className="text-lg font-bold">Your feed is empty</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Add friends or share your first post to get started!</p>
                </div>
            )}
            <div ref={sentinel} />
            {loading && (
                <div className="bhas-card animate-pulse p-4">
                    <div className="flex items-center gap-3">
                        <div className="h-11 w-11 rounded-full bg-bhas-100 dark:bg-bhas-800" />
                        <div className="flex-1 space-y-2">
                            <div className="h-3 w-32 rounded bg-bhas-100 dark:bg-bhas-800" />
                            <div className="h-2.5 w-20 rounded bg-bhas-100 dark:bg-bhas-800" />
                        </div>
                    </div>
                </div>
            )}
            {done && posts.length > 0 && (
                <p className="py-4 text-center text-sm text-slate-400">You're all caught up 🎉</p>
            )}
        </div>
    );
}
