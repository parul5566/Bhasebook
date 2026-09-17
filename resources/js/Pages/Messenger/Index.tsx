import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useEffect, useRef, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';
import { ChevronLeftIcon, MoreIcon, PaperclipIcon, SearchIcon, SendIcon, SmileyIcon, XIcon } from '@/Components/Icons';

type Conversation = {
    id: number;
    kind: 'direct' | 'group';
    title: string | null;
    avatar_url: string | null;
    hue: number;
    other_id: number | null;
    last_message: { body: string; sender: string | null; mine: boolean; created_at: string } | null;
    unread: number;
    updated_at: string | null;
    participants: BasicUser[] | null;
};

type Msg = {
    id: number;
    conversation_id: number;
    sender: BasicUser | null;
    mine: boolean;
    body: string | null;
    type: 'text' | 'image' | 'video' | 'file' | 'voice';
    attachment_url: string | null;
    attachment_mime: string | null;
    reply_to: { id: number; body: string; sender: string | null } | null;
    reactions: { type: string; user: BasicUser }[];
    edited: boolean;
    created_at: string;
    created_at_iso: string;
};

type Props = {
    conversations: Conversation[];
    active: Conversation | null;
    messages: Msg[];
    friends: BasicUser[];
};

const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

async function api(url: string, method = 'POST', body?: unknown, isForm = false) {
    const headers: Record<string, string> = {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        Accept: 'application/json',
    };
    if (!isForm && body) headers['Content-Type'] = 'application/json';
    const res = await fetch(url, {
        method,
        headers,
        body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message ?? 'Request failed');
    return res.json();
}

export default function Messenger({ conversations: initialConversations, active, messages: initialMessages, friends }: Props) {
    const me = (usePage().props.auth.user as unknown as { id: number }).id;
    const [conversations, setConversations] = useState(initialConversations);
    const [activeId, setActiveId] = useState<number | null>(active?.id ?? null);
    const [messages, setMessages] = useState<Msg[]>(initialMessages);
    const [draft, setDraft] = useState('');
    const [replyTo, setReplyTo] = useState<Msg | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [searchMode, setSearchMode] = useState(false);
    const [searchResults, setSearchResults] = useState<{ id: number; body: string; conversation_id: number; conversation_title: string | null; sender: BasicUser | null; created_at: string }[]>([]);
    const [showNewChat, setShowNewChat] = useState(false);
    const [newChatIds, setNewChatIds] = useState<number[]>([]);
    const [newChatTitle, setNewChatTitle] = useState('');
    const [mobileChatOpen, setMobileChatOpen] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const bottomRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    // poll for new messages in the open conversation
    useEffect(() => {
        const interval = setInterval(async () => {
            if (activeId) {
                try {
                    const data = await api(`${route('messenger.messages', { conversation: activeId })}`, 'GET');
                    setMessages((prev) => {
                        if (data.messages.length === prev.length) return prev;
                        return data.messages;
                    });
                } catch { /* ignore */ }
            }
        }, 5000);
        return () => clearInterval(interval);
    }, [activeId]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    const openConversation = async (id: number) => {
        setActiveId(id);
        setMobileChatOpen(true);
        setReplyTo(null);
        setMessages([]);
        try {
            const data = await api(route('messenger.messages', { conversation: id }), 'GET');
            setMessages(data.messages);
            setConversations((prev) => prev.map((c) => (c.id === id ? { ...c, unread: 0 } : c)));
        } catch {
            setError('Could not load messages.');
        }
    };

    const send = async () => {
        if (!activeId || (!draft.trim() && !replyTo)) return;
        setBusy(true);
        setError('');
        try {
            const data = await api(route('messenger.send', { conversation: activeId }), 'POST', {
                body: draft.trim() || null,
                reply_to_id: replyTo?.id ?? null,
            });
            setMessages((prev) => [...prev, data.message]);
            setDraft('');
            setReplyTo(null);
            setConversations((prev) => prev.map((c) => (c.id === activeId ? { ...c, last_message: { body: data.message.body ?? 'Message', sender: data.message.sender?.name ?? null, mine: true, created_at: 'now' } } : c)));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to send.');
        } finally {
            setBusy(false);
        }
    };

    const sendFile = async (file: File) => {
        if (!activeId) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.append('attachment', file);
            const data = await api(route('messenger.send', { conversation: activeId }), 'POST', form, true);
            setMessages((prev) => [...prev, data.message]);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Upload failed.');
        } finally {
            setBusy(false);
        }
    };

    const reactToMessage = async (msg: Msg, type: string) => {
        const alreadyMine = msg.reactions.find((r) => r.user.id === me);
        const sameType = alreadyMine?.type === type;
        // optimistic update
        setMessages((prev) => prev.map((m) => {
            if (m.id !== msg.id) return m;
            const others = m.reactions.filter((r) => r.user.id !== me);
            const next: Msg['reactions'] = sameType ? others : [...others, { type, user: { id: me, name: 'You', avatar_url: null } }];
            return { ...m, reactions: next };
        }));
        try {
            await api(route('messages.react', { message: msg.id }), 'POST', { type });
        } catch { /* ignore */ }
    };

    const startChat = async () => {
        if (newChatIds.length === 0) return;
        try {
            const data = await api(route('messenger.start'), 'POST', {
                ...(newChatIds.length === 1 ? { user_id: newChatIds[0] } : { participants: newChatIds, title: newChatTitle || undefined }),
            });
            setShowNewChat(false);
            setNewChatIds([]);
            setNewChatTitle('');
            router.visit(route('messenger.index', { c: data.conversation_id }));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed.');
        }
    };

    const searchMessages = async (q: string) => {
        if (!q.trim()) { setSearchResults([]); return; }
        try {
            const data = await api(`${route('messages.search')}?q=${encodeURIComponent(q)}`, 'GET');
            setSearchResults(data.results);
        } catch { setSearchResults([]); }
    };

    const activeConv = conversations.find((c) => c.id === activeId) ?? null;

    return (
        <AuthenticatedLayout title="Messenger">
            <div className="mx-auto flex h-[calc(100vh-9rem)] max-w-6xl overflow-hidden rounded-2xl border border-bhas-100 bg-white shadow-card dark:border-bhas-800 dark:bg-bhas-900/70">
                {/* Conversation list */}
                <div className={`w-full flex-col border-r border-bhas-100 dark:border-bhas-800 sm:flex sm:w-80 lg:w-96 ${mobileChatOpen ? 'hidden sm:flex' : 'flex'}`}>
                    <div className="flex items-center justify-between gap-2 border-b border-bhas-100 p-3 dark:border-bhas-800">
                        <h2 className="font-extrabold">Chats</h2>
                        <div className="flex items-center gap-1">
                            <button type="button" className="bhas-icon-btn !h-8 !w-8" title="Search messages" onClick={() => setSearchMode((s) => !s)}>
                                <SearchIcon className="h-4 w-4" />
                            </button>
                            <button type="button" className="bhas-btn-primary !px-2.5 !py-1.5 text-xs" onClick={() => setShowNewChat(true)}>+ New</button>
                        </div>
                    </div>

                    {searchMode && (
                        <div className="border-b border-bhas-100 p-2 dark:border-bhas-800">
                            <input
                                autoFocus
                                className="bhas-input !py-1.5 text-sm"
                                placeholder="Search all messages…"
                                onChange={(e) => { clearTimeout((window as unknown as { t?: number }).t); (window as unknown as { t?: number }).t = window.setTimeout(() => searchMessages(e.target.value), 300); }}
                            />
                        </div>
                    )}

                    <div className="flex-1 overflow-y-auto">
                        {searchMode ? (
                            searchResults.length === 0 ? (
                                <p className="p-4 text-xs text-slate-400">Type to search your messages.</p>
                            ) : searchResults.map((r) => (
                                <button key={r.id} type="button" className="w-full border-b border-bhas-50 px-3 py-2 text-left hover:bg-bhas-50 dark:border-bhas-800/50 dark:hover:bg-bhas-800/50" onClick={() => { setSearchMode(false); openConversation(r.conversation_id); }}>
                                    <p className="line-clamp-2 text-xs font-semibold">{r.body}</p>
                                    <p className="text-[10px] text-slate-400">{r.sender?.name} · {r.created_at}</p>
                                </button>
                            ))
                        ) : conversations.length === 0 ? (
                            <div className="p-6 text-center text-xs text-slate-400">
                                No chats yet. Start one with a friend!
                            </div>
                        ) : (
                            conversations.map((c) => (
                                <button
                                    key={c.id}
                                    type="button"
                                    onClick={() => openConversation(c.id)}
                                    className={`flex w-full items-center gap-3 border-b border-bhas-50 px-3 py-2.5 text-left transition hover:bg-bhas-50 dark:border-bhas-800/50 dark:hover:bg-bhas-800/50 ${activeId === c.id ? 'bg-bhas-50 dark:bg-bhas-800/60' : ''}`}
                                >
                                    {c.kind === 'group' ? (
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-bhas-400 to-bhas-600 font-bold text-white">
                                            {(c.title ?? 'G')[0]}
                                        </div>
                                    ) : (
                                        <UserAvatar user={{ id: c.other_id ?? 0, name: c.title ?? 'User', avatar_url: c.avatar_url, hue: c.hue }} size={44} />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline justify-between gap-2">
                                            <p className="truncate text-sm font-bold">{c.title ?? 'Conversation'}</p>
                                            <span className="shrink-0 text-[10px] text-slate-400">{c.last_message?.created_at ?? ''}</span>
                                        </div>
                                        <p className={`truncate text-xs ${c.unread > 0 ? 'font-bold text-slate-800 dark:text-slate-100' : 'text-slate-400'}`}>
                                            {c.last_message ? `${c.last_message.mine ? 'You: ' : (c.kind === 'group' && c.last_message.sender ? c.last_message.sender.split(' ')[0] + ': ' : '')}${c.last_message.body}` : 'Say hi 👋'}
                                        </p>
                                    </div>
                                    {c.unread > 0 && <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-bhas-600 px-1.5 text-[10px] font-bold text-white">{c.unread}</span>}
                                </button>
                            ))
                        )}
                    </div>
                </div>

                {/* Chat window */}
                <div className={`flex min-w-0 flex-1 flex-col ${mobileChatOpen ? 'flex' : 'hidden sm:flex'}`}>
                    {!activeConv ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
                            <div className="text-4xl">💬</div>
                            <p className="font-bold">Your messages</p>
                            <p className="max-w-xs text-xs text-slate-400">Send photos, videos and voice messages to friends. Pick a chat or start a new one.</p>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-center gap-2 border-b border-bhas-100 p-3 dark:border-bhas-800">
                                <button type="button" className="bhas-icon-btn !h-8 !w-8 sm:hidden" onClick={() => setMobileChatOpen(false)} aria-label="Back">
                                    <ChevronLeftIcon className="h-5 w-5" />
                                </button>
                                {activeConv.kind === 'group' ? (
                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-bhas-400 to-bhas-600 font-bold text-white">
                                        {(activeConv.title ?? 'G')[0]}
                                    </div>
                                ) : (
                                    <UserAvatar user={{ id: activeConv.other_id ?? 0, name: activeConv.title ?? 'User', avatar_url: activeConv.avatar_url, hue: activeConv.hue }} size={36} />
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold">{activeConv.title ?? 'Conversation'}</p>
                                    {activeConv.kind === 'group' && <p className="text-[10px] text-slate-400">{activeConv.participants?.length ?? ''} members</p>}
                                </div>
                            </div>

                            <div className="flex-1 space-y-2 overflow-y-auto p-3">
                                {messages.length === 0 && <p className="py-8 text-center text-xs text-slate-400">No messages yet — say hi!</p>}
                                {messages.map((m) => <Bubble key={m.id} msg={m} me={me} onReact={reactToMessage} onReply={() => setReplyTo(m)} onEdit={async (text) => {
                                    try {
                                        const data = await api(route('messages.edit', { message: m.id }), 'PATCH', { body: text });
                                        setMessages((prev) => prev.map((x) => (x.id === m.id ? data.message : x)));
                                    } catch { /* ignore */ }
                                }} onDelete={async () => {
                                    setMessages((prev) => prev.filter((x) => x.id !== m.id));
                                    try { await api(route('messages.destroy', { message: m.id }), 'DELETE'); } catch { /* ignore */ }
                                }} />)}
                                <div ref={bottomRef} />
                            </div>

                            {replyTo && (
                                <div className="flex items-center gap-2 border-t border-bhas-100 bg-bhas-50 px-3 py-1.5 text-xs dark:border-bhas-800 dark:bg-bhas-800/50">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-bold text-bhas-600">Replying to {replyTo.sender?.name}</p>
                                        <p className="truncate text-slate-400">{replyTo.body ?? 'Attachment'}</p>
                                    </div>
                                    <button type="button" onClick={() => setReplyTo(null)} aria-label="Cancel reply"><XIcon className="h-4 w-4 text-slate-400" /></button>
                                </div>
                            )}

                            {error && <p className="px-3 py-1 text-xs text-rose-600">{error}</p>}

                            <div className="flex items-end gap-2 border-t border-bhas-100 p-3 dark:border-bhas-800">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.zip"
                                    hidden
                                    onChange={(e) => { const f = e.target.files?.[0]; if (f) void sendFile(f); e.target.value = ''; }}
                                />
                                <button type="button" className="bhas-icon-btn" onClick={() => fileRef.current?.click()} disabled={busy} aria-label="Attach">
                                    <PaperclipIcon className="h-5 w-5" />
                                </button>
                                <textarea
                                    ref={inputRef}
                                    value={draft}
                                    rows={1}
                                    placeholder="Aa"
                                    className="bhas-input max-h-32 flex-1 resize-none"
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void send(); } }}
                                />
                                <button type="button" className="bhas-btn-primary !px-3" onClick={() => void send()} disabled={busy || !draft.trim()} aria-label="Send">
                                    <SendIcon className="h-5 w-5" />
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>

            {showNewChat && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={() => setShowNewChat(false)}>
                    <div className="bhas-card w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-3 text-lg font-bold">New chat</h3>
                        {friends.length === 0 ? (
                            <p className="text-sm text-slate-400">Add friends first, then come back to chat with them.</p>
                        ) : (
                            <>
                                <div className="max-h-72 space-y-1 overflow-y-auto">
                                    {friends.map((f) => (
                                        <label key={f.id} className="flex cursor-pointer items-center gap-3 rounded-xl p-2 hover:bg-bhas-50 dark:hover:bg-bhas-800">
                                            <input
                                                type="checkbox"
                                                checked={newChatIds.includes(f.id)}
                                                onChange={(e) => setNewChatIds((prev) => (e.target.checked ? [...prev, f.id] : prev.filter((x) => x !== f.id)))}
                                                className="h-4 w-4"
                                            />
                                            <UserAvatar user={f} size={34} />
                                            <span className="text-sm font-semibold">{f.name}</span>
                                        </label>
                                    ))}
                                </div>
                                {newChatIds.length > 1 && (
                                    <input value={newChatTitle} onChange={(e) => setNewChatTitle(e.target.value)} placeholder="Group name (optional)" className="bhas-input mt-3" maxLength={100} />
                                )}
                                <div className="mt-4 flex justify-end gap-2">
                                    <button type="button" className="bhas-btn-ghost" onClick={() => setShowNewChat(false)}>Cancel</button>
                                    <button type="button" className="bhas-btn-primary" disabled={newChatIds.length === 0} onClick={() => void startChat()}>
                                        {newChatIds.length > 1 ? 'Create group chat' : 'Start chat'}
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

function Bubble({ msg, me, onReact, onReply, onEdit, onDelete }: {
    msg: Msg; me: number;
    onReact: (m: Msg, t: string) => void; onReply: () => void;
    onEdit: (text: string) => void; onDelete: () => void;
}) {
    const [showActions, setShowActions] = useState(false);
    const [editing, setEditing] = useState(false);
    const [editText, setEditText] = useState(msg.body ?? '');

    return (
        <div className={`group flex gap-2 ${msg.mine ? 'flex-row-reverse' : ''}`}>
            {!msg.mine && <UserAvatar user={msg.sender ?? { id: 0, name: '?', avatar_url: null, hue: 0 }} size={28} />}
            <div className={`relative max-w-[78%] ${msg.mine ? 'items-end' : 'items-start'}`}>
                {msg.reply_to && (
                    <div className={`mb-1 rounded-lg border-l-2 border-bhas-400 bg-bhas-50 px-2 py-1 text-[10px] text-slate-500 dark:bg-bhas-800 ${msg.mine ? 'text-right' : ''}`}>
                        <b>{msg.reply_to.sender}</b>: {msg.reply_to.body}
                    </div>
                )}
                <div className={`rounded-2xl px-3 py-2 text-sm ${msg.mine ? 'bg-bhas-600 text-white' : 'bg-bhas-100 text-slate-800 dark:bg-bhas-800 dark:text-slate-100'}`}>
                    {editing ? (
                        <div className="flex items-center gap-2">
                            <input value={editText} onChange={(e) => setEditText(e.target.value)} className="w-52 rounded-lg bg-white/20 px-2 py-1 text-sm text-white outline-none" autoFocus />
                            <button type="button" className="text-[10px] font-bold underline" onClick={() => { onEdit(editText); setEditing(false); }}>Save</button>
                            <button type="button" className="text-[10px] underline" onClick={() => setEditing(false)}>Cancel</button>
                        </div>
                    ) : msg.type === 'image' && msg.attachment_url ? (
                        <img src={msg.attachment_url} alt="" className="max-h-64 rounded-xl" />
                    ) : msg.type === 'video' && msg.attachment_url ? (
                        <video src={msg.attachment_url} controls className="max-h-64 rounded-xl" />
                    ) : msg.type === 'voice' && msg.attachment_url ? (
                        <audio src={msg.attachment_url} controls className="h-8" />
                    ) : msg.type === 'file' && msg.attachment_url ? (
                        <a href={msg.attachment_url} target="_blank" rel="noopener" className="flex items-center gap-2 underline">
                            📎 {msg.attachment_url.split('/').pop()}
                        </a>
                    ) : (
                        <p className="whitespace-pre-wrap break-words">{msg.body}</p>
                    )}
                </div>
                <div className={`mt-0.5 flex items-center gap-1.5 text-[10px] text-slate-400 ${msg.mine ? 'justify-end' : ''}`}>
                    <span>{msg.created_at}</span>
                    {msg.edited && <span className="italic">edited</span>}
                    {msg.reactions.length > 0 && (
                        <span className="flex -space-x-1">
                            {Object.entries(msg.reactions.reduce<Record<string, number>>((acc, r) => { acc[r.type] = (acc[r.type] ?? 0) + 1; return acc; }, {})).map(([t, n]) => (
                                <span key={t} className="rounded-full bg-bhas-100 px-1 dark:bg-bhas-800">{t}{n > 1 ? n : ''}</span>
                            ))}
                        </span>
                    )}
                </div>

                {/* hover actions */}
                <div className={`absolute -top-3 ${msg.mine ? 'left-0' : 'right-0'} hidden gap-0.5 rounded-full bg-white p-0.5 shadow-pop group-hover:flex dark:bg-bhas-800`}>
                    {QUICK_REACTIONS.slice(0, 3).map((r) => (
                        <button key={r} type="button" className="rounded-full p-0.5 text-sm hover:scale-125" onClick={() => onReact(msg, r)}>{r}</button>
                    ))}
                    <button type="button" className="rounded-full px-1 text-[10px] font-bold text-slate-400" onClick={() => setShowActions((s) => !s)} aria-label="More">⋯</button>
                </div>
                {showActions && (
                    <div className={`absolute top-5 z-10 flex flex-col gap-1 rounded-xl bg-white p-1 text-xs shadow-pop dark:bg-bhas-800 ${msg.mine ? 'left-0' : 'right-0'}`}>
                        {QUICK_REACTIONS.slice(3).map((r) => (
                            <button key={r} type="button" className="rounded p-1 text-left text-sm hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => { onReact(msg, r); setShowActions(false); }}>{r}</button>
                        ))}
                        <button type="button" className="rounded p-1 hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => { onReply(); setShowActions(false); }}>↩ Reply</button>
                        {msg.mine && msg.body && (
                            <button type="button" className="rounded p-1 hover:bg-bhas-50 dark:hover:bg-bhas-700" onClick={() => { setEditing(true); setShowActions(false); }}>✎ Edit</button>
                        )}
                        {msg.mine && (
                            <button type="button" className="rounded p-1 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30" onClick={() => { onDelete(); setShowActions(false); }}>🗑 Delete</button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function usePageUserId(): number {
    // read current user id from Inertia shared props
    const { props } = require('@inertiajs/react').usePage();
    return (props.auth.user as unknown as { id: number }).id;
}
