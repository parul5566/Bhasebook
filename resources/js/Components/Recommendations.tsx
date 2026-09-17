import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { XIcon } from '@/Components/Icons';

type Rec = { id: number; name: string; reason?: string };
type Recs = {
    people: (Rec & { mutual: number })[];
    groups: (Rec & { members_count: number; description?: string | null })[];
    pages: (Rec & { category: string; followers_count: number })[];
};

export default function Recommendations() {
    const [recs, setRecs] = useState<Recs | null>(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let alive = true;
        fetch(route('ai.recommendations'), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('failed'))))
            .then((d) => alive && setRecs(d))
            .catch(() => alive && setError('failed'));
        return () => { alive = false; };
    }, []);

    const hide = async (kind: 'user' | 'group' | 'page', id: number) => {
        setRecs((prev) => {
            if (!prev) return prev;
            const key = kind === 'user' ? 'people' : kind === 'group' ? 'groups' : 'pages';
            return { ...prev, [key]: prev[key].filter((x) => x.id !== id) } as Recs;
        });
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        fetch(route('ai.recommendations.hide'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ kind, item_id: id }),
        }).catch(() => {});
    };

    if (error || !recs) return null;
    const empty = recs.people.length === 0 && recs.groups.length === 0 && recs.pages.length === 0;
    if (empty) return null;

    return (
        <div className="bhas-card p-4">
            <h3 className="mb-3 flex items-center gap-1.5 font-bold">Discover ✨</h3>
            <div className="space-y-4">
                {recs.people.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">People you may know</p>
                        <div className="space-y-2">
                            {recs.people.slice(0, 3).map((p) => (
                                <div key={p.id} className="group flex items-center gap-2">
                                    <UserAvatar user={{ id: p.id, name: p.name, avatar_url: null, hue: p.id * 47 % 360 }} size={32} />
                                    <Link href={route('profile.show', { user: p.id })} className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold hover:underline">{p.name}</p>
                                        <p className="truncate text-[11px] text-slate-400">{p.mutual > 0 ? `${p.mutual} mutual friends` : p.reason}</p>
                                    </Link>
                                    <button type="button" className="opacity-0 transition group-hover:opacity-100" onClick={() => hide('user', p.id)} aria-label="Hide">
                                        <XIcon className="h-3.5 w-3.5 text-slate-400" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                {recs.groups.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Groups for you</p>
                        <div className="space-y-2">
                            {recs.groups.slice(0, 2).map((g) => (
                                <div key={g.id} className="group flex items-center gap-2">
                                    <Link href={route('groups.show', { group: g.id })} className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold hover:underline">{g.name}</p>
                                        <p className="truncate text-[11px] text-slate-400">{g.members_count} members · {g.reason}</p>
                                    </Link>
                                    <button type="button" className="opacity-0 transition group-hover:opacity-100" onClick={() => hide('group', g.id)} aria-label="Hide">
                                        <XIcon className="h-3.5 w-3.5 text-slate-400" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                {recs.pages.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Popular pages</p>
                        <div className="space-y-2">
                            {recs.pages.slice(0, 2).map((p) => (
                                <div key={p.id} className="group flex items-center gap-2">
                                    <Link href={route('pages.show', { page: p.id })} className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold hover:underline">{p.name}</p>
                                        <p className="truncate text-[11px] text-slate-400">{p.followers_count} followers · {p.reason}</p>
                                    </Link>
                                    <button type="button" className="opacity-0 transition group-hover:opacity-100" onClick={() => hide('page', p.id)} aria-label="Hide">
                                        <XIcon className="h-3.5 w-3.5 text-slate-400" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
