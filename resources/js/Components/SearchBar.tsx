import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { SearchIcon } from '@/Components/Icons';

type Suggestion = {
    type: 'person' | 'group' | 'page' | 'hashtag';
    id?: number;
    name?: string;
    tag?: string;
    category?: string;
    members_count?: number;
    followers_count?: number;
    posts_count?: number;
};

export default function SearchBar({ initial = '' }: { initial?: string }) {
    const [q, setQ] = useState(initial);
    const [open, setOpen] = useState(false);
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [loading, setLoading] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);
    const timer = useRef<number | null>(null);

    useEffect(() => {
        const onDoc = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        const term = q.trim();
        if (term.length < 2) { setSuggestions([]); return; }
        timer.current = window.setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(`${route('search.suggest')}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    setSuggestions(data.suggestions ?? []);
                }
            } catch { /* ignore */ } finally { setLoading(false); }
        }, 250);
        return () => { if (timer.current) clearTimeout(timer.current); };
    }, [q]);

    const submit = (value?: string) => {
        const term = (value ?? q).trim();
        if (!term) return;
        setOpen(false);
        router.visit(route('search.show', { q: term }));
    };

    const goSuggestion = (s: Suggestion) => {
        setOpen(false);
        if (s.type === 'person' && s.id) router.visit(route('profile.show', { user: s.id }));
        else if (s.type === 'group' && s.id) router.visit(route('groups.show', { group: s.id }));
        else if (s.type === 'page' && s.id) router.visit(route('pages.show', { page: s.id }));
        else if (s.type === 'hashtag' && s.tag) router.visit(route('hashtag.show', { tag: s.tag }));
    };

    return (
        <div ref={boxRef} className="relative w-full max-w-[280px]">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                className="relative"
            >
                <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    value={q}
                    onChange={(e) => { setQ(e.target.value); setOpen(true); }}
                    onFocus={() => setOpen(true)}
                    placeholder="Search Bhasebook"
                    className="bhas-input !py-2 pl-9 text-sm"
                    aria-label="Search"
                />
            </form>
            {open && q.trim().length >= 2 && (
                <div className="bhas-card absolute left-0 top-11 z-40 w-80 p-2 shadow-pop">
                    {suggestions.map((s, i) => (
                        <button
                            key={`${s.type}-${s.id ?? s.tag}-${i}`}
                            type="button"
                            onClick={() => goSuggestion(s)}
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm hover:bg-bhas-50 dark:hover:bg-bhas-800"
                        >
                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bhas-100 text-sm dark:bg-bhas-800">
                                {s.type === 'person' ? '👤' : s.type === 'group' ? '👥' : s.type === 'page' ? '🏪' : '#'}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-semibold">
                                    {s.type === 'hashtag' ? `#${s.tag}` : s.name}
                                </span>
                                <span className="block truncate text-[11px] text-slate-400">
                                    {s.type === 'person' ? 'Person'
                                        : s.type === 'group' ? `${s.members_count} members`
                                        : s.type === 'page' ? `${s.category} · ${s.followers_count} followers`
                                        : `${s.posts_count} posts`}
                                </span>
                            </span>
                        </button>
                    ))}
                    {suggestions.length === 0 && !loading && (
                        <p className="px-3 py-2 text-xs text-slate-400">No quick matches — press Enter for full search.</p>
                    )}
                    <button
                        type="button"
                        onClick={() => submit()}
                        className="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm hover:bg-bhas-50 dark:hover:bg-bhas-800"
                    >
                        <SearchIcon className="h-4 w-4 text-slate-400" />
                        <span className="truncate">
                            Search for “<b>{q.trim()}</b>”
                        </span>
                    </button>
                </div>
            )}
        </div>
    );
}
