import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState, useTransition } from 'react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';
import {
    BookmarkIcon, CommentIcon, FlagIcon, GlobeIcon, LockIcon, MapPinIcon, MoreIcon,
    SendIcon, ShareIcon, SmileyIcon, TrashIcon, EditIcon, UsersIcon, PinIcon,
} from '@/Components/Icons';

export type SerializedPost = {
    id: number;
    author: (BasicUser & { type: string }) | null;
    group: { id: number; name: string } | null;
    content: string | null;
    type: string;
    visibility: string;
    feeling: string | null;
    location: string | null;
    link: { url: string; title: string | null; description: string | null; image: string | null } | null;
    media: { url: string; mime: string; kind: string }[];
    poll: {
        options: { id: number; text: string; votes: number; percent: number; voted: boolean }[];
        total_votes: number;
        my_vote: number | null;
    } | null;
    tags: BasicUser[];
    shared_post: SerializedPost | null;
    reaction_counts: Record<string, number>;
    reaction_total: number;
    my_reaction: string | null;
    comment_count: number;
    saved: boolean;
    pinned: boolean;
    view_count: number;
    created_at: string;
    created_at_iso: string;
    edited: boolean;
    can_edit: boolean;
};

export const REACTIONS: { type: string; emoji: string; label: string; color: string }[] = [
    { type: 'like', emoji: '👍', label: 'Like', color: 'text-bhas-600' },
    { type: 'love', emoji: '❤️', label: 'Love', color: 'text-rose-500' },
    { type: 'care', emoji: '🥰', label: 'Care', color: 'text-amber-500' },
    { type: 'haha', emoji: '😂', label: 'Haha', color: 'text-yellow-500' },
    { type: 'wow', emoji: '😮', label: 'Wow', color: 'text-amber-500' },
    { type: 'sad', emoji: '😢', label: 'Sad', color: 'text-sky-500' },
    { type: 'angry', emoji: '😡', label: 'Angry', color: 'text-red-500' },
];

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
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error ?? data.message ?? `Request failed (${res.status})`);
    }
    return res.json();
}

function csrfHeaders(): Record<string, string> {
    return {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
    };
}

export default function PostCard({ post: initial, onDeleted }: { post: SerializedPost; onDeleted?: (id: number) => void }) {
    const [post, setPost] = useState(initial);
    const [showReactions, setShowReactions] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [commentsOpen, setCommentsOpen] = useState(false);
    const [editing, setEditing] = useState(false);
    const [editText, setEditText] = useState(post.content ?? '');
    const [reporting, setReporting] = useState(false);
    const reactTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => () => { if (reactTimer.current) clearTimeout(reactTimer.current); }, []);

    const react = async (type: string) => {
        setShowReactions(false);
        try {
            const res = await api(route('posts.react', { post: post.id }), 'POST', { type });
            setPost((p) => ({ ...p, reaction_counts: res.reaction_counts, reaction_total: res.reaction_total, my_reaction: res.my_reaction }));
        } catch { /* silent */ }
    };

    const toggleSave = async () => {
        try {
            const res = await api(route('posts.save', { post: post.id }));
            setPost((p) => ({ ...p, saved: res.saved }));
        } catch { /* silent */ }
    };

    const togglePin = async () => {
        setMenuOpen(false);
        try {
            const res = await api(route('posts.pin', { post: post.id }));
            setPost((p) => ({ ...p, pinned: res.pinned }));
        } catch { /* silent */ }
    };

    const del = async () => {
        setMenuOpen(false);
        if (!confirm('Delete this post?')) return;
        try {
            await api(route('posts.destroy', { post: post.id }), 'DELETE');
            onDeleted?.(post.id);
        } catch { /* silent */ }
    };

    const saveEdit = async () => {
        try {
            const res = await api(route('posts.update', { post: post.id }), 'PATCH', { content: editText });
            setPost((p) => ({ ...p, content: res.post.content, edited: true }));
            setEditing(false);
        } catch { /* silent */ }
    };

    const vote = async (optionId: number) => {
        const poll = post.poll;
        if (!poll || poll.my_vote) return;
        try {
            const res = await api(route('posts.vote', { post: post.id }), 'POST', { option_id: optionId });
            setPost((p) => ({ ...p, poll: res.poll }));
        } catch { /* silent */ }
    };

    const myReactionMeta = REACTIONS.find((r) => r.type === post.my_reaction);
    const visIcon = post.visibility === 'public' ? <GlobeIcon className="h-3.5 w-3.5" />
        : post.visibility === 'friends' ? <UsersIcon className="h-3.5 w-3.5" />
        : <LockIcon className="h-3.5 w-3.5" />;

    const inner = post.shared_post;

    return (
        <article className="bhas-card p-4">
            <header className="flex items-start gap-3">
                {post.author && (
                    <Link href={post.author.type === 'page' ? route('pages.show', { page: post.author.id }) : route('profile.show', { user: post.author.id })}>
                        <UserAvatar user={post.author} size={44} />
                    </Link>
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-1 text-[15px]">
                        {post.author && (
                            <Link href={post.author.type === 'page' ? route('pages.show', { page: post.author.id }) : route('profile.show', { user: post.author.id })} className="font-bold hover:underline">
                                {post.author.name}
                            </Link>
                        )}
                        {post.feeling && <span className="text-slate-500">is feeling {post.feeling}</span>}
                        {post.location && (
                            <span className="flex items-center gap-1 text-slate-500">
                                at <MapPinIcon className="h-3.5 w-3.5" /> {post.location}
                            </span>
                        )}
                        {post.group && (
                            <span className="text-slate-500">
                                ▸ <Link href={route('groups.show', { group: post.group.id })} className="font-semibold hover:underline">{post.group.name}</Link>
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-1 text-xs text-slate-400">
                        <span>{post.created_at}</span>
                        {visIcon}
                        {post.pinned && <PinIcon className="h-3.5 w-3.5" />}
                        {post.edited && <span>· edited</span>}
                    </div>
                </div>
                <div className="relative">
                    <button type="button" className="bhas-icon-btn !h-8 !w-8" onClick={() => setMenuOpen((v) => !v)} aria-label="Post menu">
                        <MoreIcon className="h-5 w-5" />
                    </button>
                    {menuOpen && (
                        <>
                            <button type="button" aria-label="close" className="fixed inset-0 z-10 cursor-default" onClick={() => setMenuOpen(false)} />
                            <div className="bhas-card absolute right-0 top-9 z-20 w-44 p-1.5 shadow-pop">
                                <button type="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-bhas-50 dark:hover:bg-bhas-800" onClick={toggleSave}>
                                    <BookmarkIcon className="h-4 w-4" /> {post.saved ? 'Remove from saved' : 'Save post'}
                                </button>
                                {post.can_edit && (
                                    <>
                                        <button type="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-bhas-50 dark:hover:bg-bhas-800" onClick={() => { setEditing(true); setMenuOpen(false); }}>
                                            <EditIcon className="h-4 w-4" /> Edit post
                                        </button>
                                        {post.author?.type === 'user' && (
                                            <button type="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-bhas-50 dark:hover:bg-bhas-800" onClick={togglePin}>
                                                <PinIcon className="h-4 w-4" /> {post.pinned ? 'Unpin' : 'Pin to profile'}
                                            </button>
                                        )}
                                        <button type="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30" onClick={del}>
                                            <TrashIcon className="h-4 w-4" /> Delete
                                        </button>
                                    </>
                                )}
                                {!post.can_edit && (
                                    <button type="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/30" onClick={() => { setMenuOpen(false); setReporting(true); }}>
                                        <FlagIcon className="h-4 w-4" /> Report post
                                    </button>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </header>

            {editing ? (
                <div className="mt-3">
                    <textarea value={editText} onChange={(e) => setEditText(e.target.value)} rows={3} className="bhas-input resize-y" />
                    <div className="mt-2 flex justify-end gap-2">
                        <button type="button" className="bhas-btn-ghost !py-1.5 text-xs" onClick={() => setEditing(false)}>Cancel</button>
                        <button type="button" className="bhas-btn-primary !py-1.5 text-xs" onClick={saveEdit}>Save</button>
                    </div>
                </div>
            ) : (
                post.content && (
                    <p className="mt-3 whitespace-pre-wrap break-words text-[15px] leading-relaxed">
                        {renderContent(post.content)}
                    </p>
                )
            )}

            {post.tags.length > 0 && (
                <p className="mt-1 text-sm text-slate-500">
                    with {post.tags.map((t, i) => (
                        <span key={t.id}>
                            {i > 0 && ', '}
                            <Link href={route('profile.show', { user: t.id })} className="font-semibold text-bhas-600 hover:underline dark:text-bhas-300">{t.name}</Link>
                        </span>
                    ))}
                </p>
            )}

            {/* Media grid */}
            {post.media.length > 0 && (
                <div className={`mt-3 grid gap-1 overflow-hidden rounded-2xl ${post.media.length === 1 ? 'grid-cols-1' : post.media.length === 2 ? 'grid-cols-2' : 'grid-cols-2'}`}>
                    {post.media.slice(0, 4).map((m, i) =>
                        m.kind === 'video' ? (
                            <video key={i} src={m.url} controls className={`w-full bg-black object-contain ${post.media.length === 1 ? 'max-h-[420px]' : 'h-48'}`} />
                        ) : (
                            <img key={i} src={m.url} alt="" loading="lazy" className={`w-full object-cover ${post.media.length === 1 ? 'max-h-[520px]' : 'h-48'}`} />
                        ),
                    )}
                </div>
            )}

            {/* Poll */}
            {post.poll && (
                <div className="mt-3 space-y-2">
                    {post.poll.options.map((o) => (
                        <button
                            key={o.id}
                            type="button"
                            onClick={() => vote(o.id)}
                            disabled={!!post.poll?.my_vote}
                            className="relative w-full overflow-hidden rounded-xl border border-bhas-200 px-4 py-2.5 text-left text-sm font-semibold transition hover:border-bhas-400 dark:border-bhas-700"
                        >
                            {!!post.poll?.my_vote && (
                                <span
                                    className={`absolute inset-y-0 left-0 ${o.voted ? 'bg-bhas-500/80' : 'bg-bhas-200/70 dark:bg-bhas-700/60'}`}
                                    style={{ width: `${o.percent}%` }}
                                />
                            )}
                            <span className="relative flex items-center justify-between">
                                <span>{o.text} {o.voted && '✓'}</span>
                                {post.poll?.my_vote && <span className="text-xs">{o.percent}%</span>}
                            </span>
                        </button>
                    ))}
                    <p className="text-xs text-slate-400">{post.poll.total_votes} vote{post.poll.total_votes === 1 ? '' : 's'}</p>
                </div>
            )}

            {/* Link preview */}
            {post.link && !post.media.length && (
                <a href={post.link.url} target="_blank" rel="noopener noreferrer" className="mt-3 block overflow-hidden rounded-2xl border border-bhas-100 dark:border-bhas-800">
                    {post.link.image && <img src={post.link.image} alt="" className="h-52 w-full object-cover" />}
                    <div className="bg-bhas-50 p-3 dark:bg-bhas-800/60">
                        <p className="truncate text-xs uppercase text-slate-400">{safeHost(post.link.url)}</p>
                        {post.link.title && <p className="font-bold">{post.link.title}</p>}
                        {post.link.description && <p className="line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{post.link.description}</p>}
                    </div>
                </a>
            )}

            {/* Shared post */}
            {inner && (
                <div className="mt-3 overflow-hidden rounded-2xl border border-bhas-100 dark:border-bhas-800">
                    <div className="flex items-center gap-2 p-3 pb-1">
                        <UserAvatar user={inner.author ?? { id: 0, name: '?', avatar_url: null, hue: 0 }} size={32} />
                        <div>
                            <p className="text-sm font-bold">{inner.author?.name ?? 'Unknown'}</p>
                            <p className="text-xs text-slate-400">{inner.created_at}</p>
                        </div>
                    </div>
                    <div className="px-3 pb-3">
                        {inner.content && <p className="whitespace-pre-wrap text-sm">{renderContent(inner.content)}</p>}
                        {inner.media.slice(0, 1).map((m, i) =>
                            m.kind === 'video'
                                ? <video key={i} src={m.url} controls className="mt-2 max-h-80 w-full rounded-xl bg-black object-contain" />
                                : <img key={i} src={m.url} alt="" className="mt-2 max-h-80 w-full rounded-xl object-cover" />,
                        )}
                    </div>
                </div>
            )}

            {/* Counts row */}
            {(post.reaction_total > 0 || post.comment_count > 0) && (
                <div className="mt-3 flex items-center justify-between px-1 text-xs text-slate-500 dark:text-slate-400">
                    <span>{post.reaction_total > 0 && `${post.reaction_total} reaction${post.reaction_total > 1 ? 's' : ''}`}</span>
                    <span>{post.comment_count > 0 && `${post.comment_count} comment${post.comment_count > 1 ? 's' : ''}`}</span>
                </div>
            )}

            {/* Action bar */}
            <div className="mt-2 flex border-t border-bhas-100 pt-1 dark:border-bhas-800">
                <div className="relative flex-1">
                    <button
                        type="button"
                        onClick={() => react(post.my_reaction ?? 'like')}
                        onMouseEnter={() => { reactTimer.current = setTimeout(() => setShowReactions(true), 350); }}
                        onMouseLeave={() => { if (reactTimer.current) clearTimeout(reactTimer.current); }}
                        className={`flex w-full items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold transition hover:bg-bhas-50 dark:hover:bg-bhas-800 ${myReactionMeta ? myReactionMeta.color : 'text-slate-500 dark:text-slate-400'}`}
                    >
                        {myReactionMeta ? <span>{myReactionMeta.emoji}</span> : <SmileyIcon className="h-5 w-5" />}
                        {myReactionMeta ? myReactionMeta.label : 'Like'}
                    </button>
                    {showReactions && (
                        <div
                            className="bhas-card absolute -top-14 left-0 z-30 flex gap-1 p-2 shadow-pop"
                            onMouseEnter={() => setShowReactions(true)}
                            onMouseLeave={() => setShowReactions(false)}
                        >
                            {REACTIONS.map((r) => (
                                <button key={r.type} type="button" onClick={() => react(r.type)} className="rounded-full p-1 text-2xl transition hover:scale-125" title={r.label}>
                                    {r.emoji}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <button type="button" onClick={() => setCommentsOpen((v) => !v)} className="flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-500 transition hover:bg-bhas-50 dark:text-slate-400 dark:hover:bg-bhas-800">
                    <CommentIcon className="h-5 w-5" /> Comment
                </button>
                <ShareButton post={post} />
                <button type="button" onClick={toggleSave} className={`flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold transition hover:bg-bhas-50 dark:hover:bg-bhas-800 ${post.saved ? 'text-bhas-600 dark:text-bhas-300' : 'text-slate-500 dark:text-slate-400'}`}>
                    <BookmarkIcon className="h-5 w-5" /> {post.saved ? 'Saved' : 'Save'}
                </button>
            </div>

            {commentsOpen && <CommentsSection postId={post.id} />}
            {reporting && <ReportModal reportableType="post" reportableId={post.id} onClose={() => setReporting(false)} />}
        </article>
    );
}

export const REPORT_REASONS = [
    ['spam', 'Spam or misleading'],
    ['harassment', 'Harassment or bullying'],
    ['nudity', 'Nudity or sexual content'],
    ['violence', 'Violence or dangerous acts'],
    ['misinformation', 'False information'],
    ['hate', 'Hate speech'],
    ['other', 'Something else'],
] as const;

export function ReportModal({ reportableType, reportableId, onClose }: { reportableType: string; reportableId: number; onClose: () => void }) {
    const [reason, setReason] = useState('spam');
    const [details, setDetails] = useState('');
    const [busy, setBusy] = useState(false);
    const [done, setDone] = useState(false);

    const submit = async () => {
        setBusy(true);
        try {
            const res = await fetch(route('reports.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ reportable_type: reportableType, reportable_id: reportableId, reason, details: details || null }),
            });
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message ?? 'Failed');
            setDone(true);
            setTimeout(onClose, 900);
        } catch {
            setDone(false);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="bhas-card w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                {done ? (
                    <p className="py-6 text-center text-sm font-bold text-emerald-600">Report submitted ✓ Thank you.</p>
                ) : (
                    <>
                        <h3 className="mb-3 text-lg font-bold">Report this {reportableType}</h3>
                        <div className="space-y-1">
                            {REPORT_REASONS.map(([value, label]) => (
                                <label key={value} className="flex cursor-pointer items-center gap-2 rounded-lg p-2 text-sm hover:bg-bhas-50 dark:hover:bg-bhas-800">
                                    <input type="radio" name="reason" value={value} checked={reason === value} onChange={() => setReason(value)} className="h-4 w-4" />
                                    {label}
                                </label>
                            ))}
                        </div>
                        <textarea value={details} onChange={(e) => setDetails(e.target.value)} rows={2} maxLength={1000} placeholder="Anything else we should know? (optional)" className="bhas-input mt-3 resize-none text-sm" />
                        <div className="mt-3 flex justify-end gap-2">
                            <button type="button" className="bhas-btn-ghost" onClick={onClose}>Cancel</button>
                            <button type="button" className="bhas-btn-primary" onClick={submit} disabled={busy}>{busy ? 'Sending…' : 'Submit report'}</button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

function ShareButton({ post }: { post: SerializedPost }) {
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [done, setDone] = useState(false);

    const share = async (visibility: string) => {
        setBusy(true);
        try {
            const body = new FormData();
            body.append('content', text);
            body.append('type', 'share');
            body.append('visibility', visibility);
            body.append('shared_post_id', String(post.id));
            const res = await fetch(route('posts.store'), { method: 'POST', headers: csrfHeaders(), body });
            if (res.ok) {
                setDone(true);
                setTimeout(() => { setOpen(false); setDone(false); setText(''); }, 800);
            }
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className="flex flex-1 items-center justify-center gap-2 rounded-xl py-2 text-sm font-semibold text-slate-500 transition hover:bg-bhas-50 dark:text-slate-400 dark:hover:bg-bhas-800">
                <ShareIcon className="h-5 w-5" /> Share
            </button>
            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setOpen(false)}>
                    <div className="bhas-card w-full max-w-lg p-4" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-3 font-bold">Share post</h3>
                        <textarea value={text} onChange={(e) => setText(e.target.value)} placeholder="Say something about this…" rows={3} className="bhas-input resize-y" />
                        <div className="mt-3 flex justify-end gap-2">
                            {done ? (
                                <span className="bhas-btn-primary !cursor-default">Shared ✓</span>
                            ) : (
                                <>
                                    <button type="button" className="bhas-btn-ghost" onClick={() => setOpen(false)}>Cancel</button>
                                    <button type="button" disabled={busy} className="bhas-btn-primary" onClick={() => share('friends')}>Share now</button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function CommentsSection({ postId }: { postId: number }) {
    const [comments, setComments] = useState<{ id: number; parent_id: number | null; author: BasicUser | null; content: string | null; attachment: { url: string; mime: string } | null; reaction_total: number; my_reaction: string | null; replies: unknown[]; created_at: string; can_edit: boolean }[]>([]);
    const [loading, setLoading] = useState(true);
    const [text, setText] = useState('');
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);

    const load = async () => {
        const res = await api(route('posts.comments', { post: postId }), 'GET');
        setComments(res.comments);
        setLoading(false);
    };
    useEffect(() => { void load(); }, [postId]);

    const submit = async () => {
        if (!text.trim()) return;
        setBusy(true);
        try {
            await api(route('posts.comments.store', { post: postId }), 'POST', { content: text, parent_id: replyTo ?? undefined });
            setText('');
            setReplyTo(null);
            await load();
        } finally {
            setBusy(false);
        }
    };

    const delComment = async (id: number) => {
        await api(route('comments.destroy', { comment: id }), 'DELETE');
        await load();
    };

    const reactComment = async (id: number) => {
        await api(route('comments.react', { comment: id }), 'POST', { type: 'like' });
        await load();
    };

    return (
        <div className="mt-3 space-y-3 border-t border-bhas-100 pt-3 dark:border-bhas-800">
            <div className="flex gap-2">
                <textarea
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    placeholder={replyTo ? 'Reply…' : 'Write a comment…'}
                    rows={1}
                    className="bhas-input flex-1 resize-none !py-2"
                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void submit(); } }}
                />
                <button type="button" onClick={submit} disabled={busy || !text.trim()} className="bhas-btn-primary !px-3" aria-label="Send comment">
                    <SendIcon className="h-4 w-4" />
                </button>
            </div>
            {replyTo && <p className="text-xs text-slate-400">Replying to comment #{replyTo} — <button type="button" className="underline" onClick={() => setReplyTo(null)}>cancel</button></p>}
            {loading ? (
                <div className="space-y-2">
                    {[0, 1].map((i) => <div key={i} className="h-10 animate-pulse rounded-xl bg-bhas-100 dark:bg-bhas-800" />)}
                </div>
            ) : comments.length === 0 ? (
                <p className="py-2 text-center text-sm text-slate-400">No comments yet — be the first!</p>
            ) : (
                <ul className="space-y-3">
                    {comments.map((c) => (
                        <li key={c.id}>
                            <div className="flex gap-2">
                                {c.author && <UserAvatar user={c.author} size={32} />}
                                <div className="min-w-0 flex-1">
                                    <div className="inline-block rounded-2xl bg-bhas-50 px-3 py-2 dark:bg-bhas-800/70">
                                        <Link href={route('profile.show', { user: c.author?.id ?? 0 })} className="text-sm font-bold hover:underline">{c.author?.name}</Link>
                                        <p className="whitespace-pre-wrap break-words text-sm">{c.content}</p>
                                        {c.attachment?.mime.startsWith('image/') && <img src={c.attachment.url} alt="" className="mt-1 max-h-60 rounded-xl" />}
                                        {c.attachment?.mime.startsWith('video/') && <video src={c.attachment.url} controls className="mt-1 max-h-60 rounded-xl" />}
                                    </div>
                                    <div className="mt-1 flex items-center gap-3 pl-2 text-xs text-slate-400">
                                        <span>{c.created_at}</span>
                                        <button type="button" onClick={() => reactComment(c.id)} className={`font-semibold ${c.my_reaction ? 'text-bhas-600 dark:text-bhas-300' : ''} hover:underline`}>
                                            Like {c.reaction_total > 0 && `(${c.reaction_total})`}
                                        </button>
                                        <button type="button" onClick={() => setReplyTo(c.id)} className="font-semibold hover:underline">Reply</button>
                                        {c.can_edit && <button type="button" onClick={() => delComment(c.id)} className="font-semibold text-rose-500 hover:underline">Delete</button>}
                                    </div>
                                    {(c.replies as typeof comments[]).length > 0 && (
                                        <ul className="mt-2 ml-6 space-y-2">
                                            {(c.replies as unknown as typeof c[]).map((r) => (
                                                <li key={r.id} className="flex gap-2">
                                                    {r.author && <UserAvatar user={r.author} size={26} />}
                                                    <div>
                                                        <div className="inline-block rounded-2xl bg-bhas-50 px-3 py-1.5 dark:bg-bhas-800/70">
                                                            <Link href={route('profile.show', { user: r.author?.id ?? 0 })} className="text-xs font-bold hover:underline">{r.author?.name}</Link>
                                                            <p className="text-sm">{r.content}</p>
                                                        </div>
                                                        <div className="flex items-center gap-3 pl-2 text-xs text-slate-400">
                                                            <span>{r.created_at}</span>
                                                            <button type="button" onClick={() => reactComment(r.id)} className={`font-semibold ${r.my_reaction ? 'text-bhas-600' : ''} hover:underline`}>
                                                                Like {r.reaction_total > 0 && `(${r.reaction_total})`}
                                                            </button>
                                                            {r.can_edit && <button type="button" onClick={() => delComment(r.id)} className="font-semibold text-rose-500 hover:underline">Delete</button>}
                                                        </div>
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function renderContent(content: string): React.ReactNode[] {
    const parts = content.split(/(#[\p{L}\p{N}_]{2,50})/u);
    return parts.map((part, i) => {
        if (part.startsWith('#') && part.length > 1) {
            const tag = part.slice(1);
            return (
                <Link key={i} href={route('hashtag.show', { tag })} className="font-semibold text-bhas-600 hover:underline dark:text-bhas-300">
                    {part}
                </Link>
            );
        }
        return <span key={i}>{part}</span>;
    });
}

function safeHost(url: string): string {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}
