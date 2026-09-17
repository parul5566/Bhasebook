import { useCallback, useEffect, useRef, useState } from 'react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';
import { PlusIcon, XIcon, ChevronLeftIcon, ChevronRightIcon, PlayIcon } from '@/Components/Icons';
import { usePage } from '@inertiajs/react';

export type StoryItem = {
    id: number;
    kind: string;
    media_url: string | null;
    text: string | null;
    background: string | null;
    created_at: string;
    seen_by_me: boolean;
};

export type StoryGroup = {
    user: BasicUser;
    seen: boolean;
    count: number;
    stories: StoryItem[];
};

const BACKGROUNDS = ['bhas', 'sunset', 'ocean', 'forest', 'berry'];
const BG_STYLES: Record<string, string> = {
    bhas: 'from-bhas-500 to-bhas-700',
    sunset: 'from-orange-400 to-rose-600',
    ocean: 'from-cyan-400 to-blue-600',
    forest: 'from-emerald-400 to-teal-600',
    berry: 'from-fuchsia-500 to-purple-700',
};

async function api(url: string, method = 'POST', body?: FormData | Record<string, unknown>) {
    const headers: Record<string, string> = {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        Accept: 'application/json',
    };
    const isForm = body instanceof FormData;
    if (!isForm && body) headers['Content-Type'] = 'application/json';
    const res = await fetch(url, { method, headers, body: isForm ? body : body ? JSON.stringify(body) : undefined });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message ?? 'Request failed');
    return res.json();
}

export default function StoryBar({ onOpenComposer, onView }: { onOpenComposer?: () => void; onView?: (groups: StoryGroup[], index: number) => void } = {}) {
    const [tray, setTray] = useState<StoryGroup[]>([]);
    const [loading, setLoading] = useState(true);
    const [viewer, setViewer] = useState<{ group: number; index: number } | null>(null);
    const [composerOpen, setComposerOpen] = useState(false);
    const me = usePage().props.auth.user as unknown as { id: number; name: string; avatar_url: string | null; hue?: number };

    const load = useCallback(async () => {
        try {
            const data = await api(route('stories.tray'), 'GET');
            setTray(data.tray);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { void load(); }, [load]);

    const myGroup = tray.find((t) => t.user.id === me.id);

    return (
        <>
            <div className="bhas-card flex gap-3 overflow-x-auto p-3 no-scrollbar">
                {/* Create story */}
                <button type="button" onClick={() => (onOpenComposer ? onOpenComposer() : setComposerOpen(true))} className="relative shrink-0" aria-label="Create story">
                    <UserAvatar user={{ id: me.id, name: me.name, avatar_url: me.avatar_url, hue: me.hue ?? 0 }} size={64} />
                    <span className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-bhas-600 text-white dark:border-bhas-900">
                        <PlusIcon className="h-3.5 w-3.5" />
                    </span>
                    <p className="mt-1 text-center text-[10px] font-bold text-slate-500 dark:text-slate-400">Create</p>
                </button>

                {loading
                    ? [0, 1, 2, 3, 4].map((i) => <div key={i} className="h-16 w-16 shrink-0 animate-pulse rounded-full bg-bhas-100 dark:bg-bhas-800" />)
                    : tray.map((t, i) => (
                        <button key={t.user.id} type="button" onClick={() => {
                            if (onView) onView(tray, i);
                            else setViewer({ group: i, index: 0 });
                        }} className="shrink-0">
                            <span className={`block rounded-full p-[3px] ${t.seen ? 'bg-slate-300 dark:bg-bhas-700' : 'bg-gradient-to-tr from-amber-400 via-rose-500 to-fuchsia-600'}`}>
                                <span className="block rounded-full border-2 border-white dark:border-bhas-950">
                                    <UserAvatar user={t.user} size={58} />
                                </span>
                            </span>
                            <p className="mt-1 w-16 truncate text-center text-[10px] font-semibold">{t.user.name.split(' ')[0]}</p>
                        </button>
                    ))}
                {!loading && tray.length === 0 && (
                    <p className="flex items-center px-2 text-xs text-slate-400">No stories yet — add friends to see theirs!</p>
                )}
            </div>

            {viewer !== null && tray[viewer.group] && (
                <StoryViewer
                    tray={tray}
                    start={viewer}
                    onClose={() => { setViewer(null); void load(); }}
                />
            )}

            {composerOpen && (
                <StoryComposer onClose={() => setComposerOpen(false)} onDone={() => { setComposerOpen(false); void load(); }} />
            )}
        </>
    );
}

export { StoryComposer, StoryViewer };

function StoryViewer({ tray, start, onClose }: { tray: StoryGroup[]; start: { group: number; index: number }; onClose: () => void }) {
    const [group, setGroup] = useState(start.group);
    const [index, setIndex] = useState(start.index);
    const [progress, setProgress] = useState(0);
    const [paused, setPaused] = useState(false);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    const current = tray[group]?.stories[index];

    useEffect(() => {
        setProgress(0);
        if (timer.current) clearInterval(timer.current);
        if (!current) return;
        void api(route('stories.view', { story: current.id }));

        const duration = current.kind === 'video' ? 15000 : 5000;
        const step = 100 / (duration / 100);
        timer.current = setInterval(() => {
            setProgress((p) => {
                if (p >= 100) {
                    next();
                    return 0;
                }
                return p + step;
            });
        }, 100);
        return () => { if (timer.current) clearInterval(timer.current); };
    }, [group, index]);

    if (!current) {
        onClose();
        return null;
    }

    const next = () => {
        const g = tray[group];
        if (index < g.stories.length - 1) setIndex(index + 1);
        else if (group < tray.length - 1) { setGroup(group + 1); setIndex(0); }
        else onClose();
    };
    const prev = () => {
        if (index > 0) setIndex(index - 1);
        else if (group > 0) { setGroup(group - 1); setIndex(0); }
    };

    const user = tray[group].user;

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/90" onClick={onClose}>
            <div className="relative h-full w-full max-w-md" onClick={(e) => e.stopPropagation()}>
                {/* Progress bars */}
                <div className="absolute left-3 right-3 top-3 z-20 flex gap-1">
                    {tray[group].stories.map((_, i) => (
                        <div key={i} className="h-1 flex-1 overflow-hidden rounded-full bg-white/30">
                            <div
                                className={`h-full bg-white transition-[width] duration-100 ${i < index ? 'w-full' : i === index ? '' : 'w-0'}`}
                                style={i === index ? { width: `${progress}%` } : undefined}
                            />
                        </div>
                    ))}
                </div>
                {/* Header */}
                <div className="absolute left-3 right-3 top-7 z-20 flex items-center justify-between text-white">
                    <div className="flex items-center gap-2 drop-shadow">
                        <UserAvatar user={user} size={36} />
                        <span className="font-bold">{user.name}</span>
                        <span className="text-xs text-white/70">{current.created_at}</span>
                    </div>
                    <button type="button" className="rounded-full bg-white/20 p-2" onClick={onClose} aria-label="Close">
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>
                {/* Content */}
                <div
                    className="flex h-full w-full items-center justify-center"
                    onPointerDown={() => setPaused(true)}
                    onPointerUp={() => setPaused(false)}
                >
                    {current.kind === 'text' ? (
                        <div className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${BG_STYLES[current.background ?? 'bhas']}`}>
                            <p className="px-8 text-center text-2xl font-extrabold text-white drop-shadow">{current.text}</p>
                        </div>
                    ) : current.kind === 'video' ? (
                        <video src={current.media_url ?? ''} autoPlay muted={false} playsInline className="h-full w-full object-contain" onEnded={next} />
                    ) : (
                        <img src={current.media_url ?? ''} alt="" className="h-full w-full object-contain" />
                    )}
                </div>
                {/* Nav zones */}
                <button type="button" className="absolute left-0 top-0 z-10 h-full w-1/4" onClick={prev} aria-label="Previous" />
                <button type="button" className="absolute right-0 top-0 z-10 h-full w-1/4" onClick={next} aria-label="Next" />
                <div className="absolute bottom-4 left-4 right-4 z-20 flex justify-center gap-2 opacity-60">
                    <span className="flex items-center gap-1 text-white"><PlayIcon className="h-4 w-4" /> tap sides to navigate</span>
                </div>
            </div>
        </div>
    );
}

function StoryComposer({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
    const [mode, setMode] = useState<'text' | 'photo'>('text');
    const [text, setText] = useState('');
    const [background, setBackground] = useState('bhas');
    const [file, setFile] = useState<File | null>(null);
    const [visibility, setVisibility] = useState('friends');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const submit = async () => {
        setBusy(true);
        setError('');
        try {
            const body = new FormData();
            body.append('kind', mode);
            if (mode === 'text') body.append('text', text);
            else if (file) body.append('media', file);
            body.append('background', background);
            body.append('visibility', visibility);
            await api(route('stories.store'), 'POST', body);
            onDone();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="bhas-card w-full max-w-md p-4" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-3 font-bold">Create story</h3>
                <div className="mb-3 flex gap-1 rounded-xl bg-bhas-100 p-1 dark:bg-bhas-800">
                    {(['text', 'photo'] as const).map((m) => (
                        <button key={m} type="button" onClick={() => setMode(m)} className={`flex-1 rounded-lg py-1.5 text-sm font-semibold capitalize ${mode === m ? 'bg-white text-bhas-700 shadow dark:bg-bhas-700 dark:text-white' : 'text-slate-500'}`}>
                            {m === 'photo' ? 'photo/video' : m}
                        </button>
                    ))}
                </div>

                {mode === 'text' ? (
                    <>
                        <textarea value={text} onChange={(e) => setText(e.target.value)} rows={3} placeholder="Share a moment…" className="bhas-input resize-none" />
                        <div className="mt-2 flex gap-2">
                            {BACKGROUNDS.map((b) => (
                                <button key={b} type="button" onClick={() => setBackground(b)} className={`h-8 w-8 rounded-full bg-gradient-to-br ${BG_STYLES[b]} ${background === b ? 'ring-2 ring-bhas-600 ring-offset-2 dark:ring-offset-bhas-900' : ''}`} aria-label={b} />
                            ))}
                        </div>
                    </>
                ) : (
                    <>
                        <input ref={fileRef} type="file" accept="image/*,video/mp4,video/webm" hidden onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                        <button type="button" onClick={() => fileRef.current?.click()} className="flex h-40 w-full items-center justify-center rounded-2xl border-2 border-dashed border-bhas-200 text-sm font-semibold text-slate-500 dark:border-bhas-700">
                            {file ? file.name : 'Choose photo or video'}
                        </button>
                    </>
                )}

                <div className="mt-3 flex items-center justify-between">
                    <select value={visibility} onChange={(e) => setVisibility(e.target.value)} className="bhas-input !w-auto text-xs">
                        <option value="friends">Friends</option>
                        <option value="public">Public</option>
                    </select>
                    <div className="flex gap-2">
                        <button type="button" className="bhas-btn-ghost" onClick={onClose}>Cancel</button>
                        <button type="button" className="bhas-btn-primary" disabled={busy || (mode === 'text' ? !text.trim() : !file)} onClick={submit}>
                            {busy ? 'Sharing…' : 'Share story'}
                        </button>
                    </div>
                </div>
                {error && <p className="mt-2 text-sm text-rose-600">{error}</p>}
            </div>
        </div>
    );
}
