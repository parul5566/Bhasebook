import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { CameraIcon, MapPinIcon, PhotoStackIcon, PollIcon, SmileyIcon, SparkIcon, TagUserIcon, VideoCamIcon, XIcon } from '@/Components/Icons';

const FEELINGS = ['happy 😊', 'sad 😢', 'excited 🤩', 'tired 😴', 'grateful 🙏', 'loved ❤️', 'motivated 💪', 'silly 🤪', 'thoughtful 🤔', 'celebrating 🎉'];
const VISIBILITIES = [
    { key: 'public', label: 'Public' },
    { key: 'friends', label: 'Friends' },
    { key: 'private', label: 'Only me' },
] as const;

export default function Composer({ defaultVisibility = 'friends', groupId, pageId, contextName }: { defaultVisibility?: string; groupId?: number; pageId?: number; contextName?: string }) {
    const me = usePage().props.auth.user as unknown as { id: number; name: string; avatar_url: string | null; hue?: number };
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [pollOptions, setPollOptions] = useState<string[]>([]);
    const [feeling, setFeeling] = useState('');
    const [location, setLocation] = useState('');
    const [visibility, setVisibility] = useState<string>(defaultVisibility);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [aiBusy, setAiBusy] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const isPoll = pollOptions.length > 0;
    const hasMedia = files.length > 0;

    const reset = () => {
        setText(''); setFiles([]); setPollOptions([]); setFeeling(''); setLocation(''); setError('');
    };

    const submit = async () => {
        if (!text.trim() && !hasMedia && !isPoll) return;
        setBusy(true);
        setError('');
        try {
            const hasVideo = files.some((f) => f.type.startsWith('video/'));
            const body = new FormData();
            body.append('content', text);
            body.append('type', isPoll ? 'poll' : hasVideo ? 'video' : hasMedia ? 'photo' : 'text');
            body.append('visibility', visibility);
            if (feeling) body.append('feeling', feeling);
            if (location) body.append('location', location);
            if (groupId) body.append('group_id', String(groupId));
            if (pageId) body.append('page_id', String(pageId));
            files.slice(0, 10).forEach((f) => body.append('media[]', f));
            pollOptions.filter((o) => o.trim()).forEach((o) => body.append('poll_options[]', o));
            const res = await fetch(route('posts.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body,
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message ?? data.error ?? 'Could not publish post.');
            }
            reset();
            setOpen(false);
            router.reload({ only: [] });
            window.dispatchEvent(new CustomEvent('bhas:post-created'));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to post.');
        } finally {
            setBusy(false);
        }
    };

    const runAi = async (mode: 'ideas' | 'caption' | 'rewrite' | 'hashtags') => {
        if (aiBusy) return;
        const topic = text.trim();
        if (mode !== 'ideas' && !topic) { setError('Type something first, then let AI polish it.'); return; }
        setAiBusy(true);
        setError('');
        try {
            const res = await fetch(route('ai.assist'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ mode, topic: topic || 'everyday life and what makes you smile' }),
            });
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message ?? 'AI is taking a break — try again.');
            const data = await res.json();
            setText((prev) => {
                if (mode === 'ideas') return prev + (prev ? '\n' : '') + data.result;
                if (mode === 'hashtags') return prev + ' ' + data.result;
                return data.result;
            });
        } catch (e) {
            setError(e instanceof Error ? e.message : 'AI request failed.');
        } finally {
            setAiBusy(false);
        }
    };

    return (
        <>
            {!open ? (
                <div className="bhas-card p-4">
                    <div className="flex items-center gap-3">
                        <UserAvatar user={{ id: me.id, name: me.name, avatar_url: me.avatar_url, hue: me.hue ?? 0 }} size={40} />
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            className="flex-1 rounded-full bg-bhas-100 px-4 py-2.5 text-left text-sm text-slate-500 transition hover:bg-bhas-200 dark:bg-bhas-800 dark:hover:bg-bhas-700 dark:text-slate-400"
                        >
                            What's on your mind, {me.name.split(' ')[0]}?
                        </button>
                    </div>
                    <div className="mt-3 flex border-t border-bhas-100 pt-2 dark:border-bhas-800">
                        <button type="button" onClick={() => { setOpen(true); setTimeout(() => fileRef.current?.click(), 100); }} className="flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-600 hover:bg-bhas-50 dark:text-slate-300 dark:hover:bg-bhas-800">
                            <PhotoStackIcon className="h-5 w-5 text-emerald-500" /> Photo
                        </button>
                        <button type="button" onClick={() => { setOpen(true); setTimeout(() => fileRef.current?.click(), 100); }} className="flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-600 hover:bg-bhas-50 dark:text-slate-300 dark:hover:bg-bhas-800">
                            <VideoCamIcon className="h-5 w-5 text-rose-500" /> Video
                        </button>
                        <button type="button" onClick={() => { setOpen(true); setPollOptions(['', '']); }} className="flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-600 hover:bg-bhas-50 dark:text-slate-300 dark:hover:bg-bhas-800">
                            <PollIcon className="h-5 w-5 text-bhas-600" /> Poll
                        </button>
                        <button type="button" onClick={() => { setOpen(true); setLocation('📍 '); }} className="hidden flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-600 hover:bg-bhas-50 sm:flex dark:text-slate-300 dark:hover:bg-bhas-800">
                            <MapPinIcon className="h-5 w-5 text-amber-500" /> Location
                        </button>
                    </div>
                </div>
            ) : (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 pt-16" onClick={() => !busy && setOpen(false)}>
                    <div className="bhas-card w-full max-w-xl p-4" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-lg font-bold">{contextName ? `Post to ${contextName}` : 'Create post'}</h3>
                            <button type="button" className="bhas-icon-btn" onClick={() => setOpen(false)} aria-label="Close">
                                <XIcon className="h-5 w-5" />
                            </button>
                        </div>

                        <textarea
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            placeholder={`What's on your mind, ${me.name.split(' ')[0]}?`}
                            rows={4}
                            autoFocus
                            className="bhas-input resize-y !bg-transparent !ring-0 dark:!bg-transparent"
                        />

                        {/* Poll builder */}
                        {isPoll && (
                            <div className="mt-2 space-y-2 rounded-2xl border border-bhas-100 p-3 dark:border-bhas-800">
                                <p className="text-xs font-bold uppercase text-slate-400">Poll options</p>
                                {pollOptions.map((opt, i) => (
                                    <input
                                        key={i}
                                        value={opt}
                                        onChange={(e) => setPollOptions((o) => o.map((v, j) => (j === i ? e.target.value : v)))}
                                        placeholder={`Option ${i + 1}`}
                                        className="bhas-input"
                                    />
                                ))}
                                {pollOptions.length < 6 && (
                                    <button type="button" className="bhas-btn-ghost !py-1 text-xs" onClick={() => setPollOptions((o) => [...o, ''])}>+ Add option</button>
                                )}
                            </div>
                        )}

                        {/* Media previews */}
                        {hasMedia && (
                            <div className="mt-2 grid grid-cols-4 gap-2">
                                {files.map((f, i) => (
                                    <div key={i} className="relative">
                                        {f.type.startsWith('video/') ? (
                                            <video src={URL.createObjectURL(f)} className="h-20 w-full rounded-xl object-cover" />
                                        ) : (
                                            <img src={URL.createObjectURL(f)} alt="" className="h-20 w-full rounded-xl object-cover" />
                                        )}
                                        <button type="button" onClick={() => setFiles((prev) => prev.filter((_, j) => j !== i))} className="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-white shadow" aria-label="Remove">
                                            <XIcon className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {(feeling || location) && (
                            <div className="mt-2 flex gap-2">
                                {feeling && (
                                    <span className="rounded-full bg-bhas-100 px-3 py-1 text-xs font-semibold dark:bg-bhas-800">
                                        Feeling {feeling} <button type="button" onClick={() => setFeeling('')} className="ml-1 text-slate-400">✕</button>
                                    </span>
                                )}
                                {location && (
                                    <span className="rounded-full bg-bhas-100 px-3 py-1 text-xs font-semibold dark:bg-bhas-800">
                                        📍 {location} <button type="button" onClick={() => setLocation('')} className="ml-1 text-slate-400">✕</button>
                                    </span>
                                )}
                            </div>
                        )}

                        {/* Toolbar */}
                        <div className="mt-3 flex flex-wrap items-center gap-1 rounded-2xl border border-bhas-100 p-2 dark:border-bhas-800">
                            <input ref={fileRef} type="file" accept="image/*,video/mp4,video/webm" multiple hidden onChange={(e) => setFiles(Array.from(e.target.files ?? []))} />
                            <button type="button" className="bhas-icon-btn !h-9 !w-9" onClick={() => fileRef.current?.click()} title="Photo/video">
                                <PhotoStackIcon className="h-5 w-5 text-emerald-500" />
                            </button>
                            <select value={feeling} onChange={(e) => setFeeling(e.target.value)} className="bhas-input !w-auto !py-1.5 text-xs">
                                <option value="">😊 Feeling…</option>
                                {FEELINGS.map((f) => <option key={f} value={f}>{f}</option>)}
                            </select>
                            <input value={location} onChange={(e) => setLocation(e.target.value)} placeholder="📍 Location" className="bhas-input !w-36 !py-1.5 text-xs" />
                            <button type="button" className="bhas-icon-btn !h-9 !w-9" title="Poll" onClick={() => setPollOptions((o) => (o.length ? [] : ['', '']))}>
                                <PollIcon className="h-5 w-5 text-bhas-600" />
                            </button>
                            <div className="group relative">
                                <button type="button" className="bhas-icon-btn !h-9 !w-9" title="AI assistant" onClick={() => runAi('caption')} disabled={aiBusy}>
                                    <SparkIcon className={`h-5 w-5 text-violet-500 ${aiBusy ? 'animate-pulse' : ''}`} />
                                </button>
                                <div className="absolute bottom-11 left-0 z-20 hidden w-44 rounded-xl bg-white p-1 shadow-pop group-hover:block dark:bg-bhas-800">
                                    <button type="button" className="block w-full rounded-lg px-3 py-1.5 text-left text-xs font-semibold hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => runAi('ideas')}>✨ Ideas for a post</button>
                                    <button type="button" className="block w-full rounded-lg px-3 py-1.5 text-left text-xs font-semibold hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => runAi('caption')}>✨ Write a caption</button>
                                    <button type="button" className="block w-full rounded-lg px-3 py-1.5 text-left text-xs font-semibold hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => runAi('rewrite')}>✨ Improve my text</button>
                                    <button type="button" className="block w-full rounded-lg px-3 py-1.5 text-left text-xs font-semibold hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => runAi('hashtags')}>✨ Suggest hashtags</button>
                                </div>
                            </div>
                            {groupId ? null : pageId ? null : (
                                <div className="ml-auto flex items-center gap-2">
                                    <label className="text-xs font-bold uppercase text-slate-400">Post to</label>
                                    <select value={visibility} onChange={(e) => setVisibility(e.target.value)} className="bhas-input !w-auto !py-1.5 text-xs">
                                        {VISIBILITIES.map((v) => <option key={v.key} value={v.key}>{v.label}</option>)}
                                    </select>
                                </div>
                            )}
                        </div>

                        {error && <p className="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">{error}</p>}

                        <button type="button" onClick={submit} disabled={busy || (!text.trim() && !hasMedia && !isPoll)} className="bhas-btn-primary mt-3 w-full">
                            {busy ? 'Posting…' : 'Post'}
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
