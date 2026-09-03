import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { SearchIcon } from '@/Components/Icons';

export default function SearchBar({ initial = '' }: { initial?: string }) {
    const [q, setQ] = useState(initial);
    const [open, setOpen] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onDoc = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    const submit = (value?: string) => {
        const term = (value ?? q).trim();
        if (!term) return;
        setOpen(false);
        router.visit(route('search.show', { q: term }));
    };

    return (
        <div ref={boxRef} className="relative w-full max-w-[240px]">
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
                    onChange={(e) => setQ(e.target.value)}
                    onFocus={() => setOpen(true)}
                    placeholder="Search Bhasebook"
                    className="bhas-input !py-2 pl-9 text-sm"
                    aria-label="Search"
                />
            </form>
            {open && q.trim() && (
                <div className="bhas-card absolute left-0 top-11 z-40 w-72 p-2 shadow-pop">
                    <button
                        type="button"
                        onClick={() => submit()}
                        className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm hover:bg-bhas-50 dark:hover:bg-bhas-800"
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
