import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { CheckIcon, PlusIcon, SendIcon, XIcon } from '@/Components/Icons';

export type BasicUser = {
    id: number;
    name: string;
    avatar_url: string | null;
    hue?: number;
};

export function UserAvatar({ user, size = 40 }: { user: BasicUser; size?: number }) {
    if (user.avatar_url) {
        return (
            <img
                src={user.avatar_url}
                alt={user.name}
                style={{ width: size, height: size }}
                className="rounded-full object-cover"
            />
        );
    }
    const initials = user.name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
    return (
        <span
            className="flex shrink-0 items-center justify-center rounded-full font-bold text-white"
            style={{ width: size, height: size, backgroundColor: `hsl(${user.hue ?? 200} 55% 45%)`, fontSize: size / 2.6 }}
        >
            {initials}
        </span>
    );
}

export type Relation = {
    is_me: boolean;
    is_friend: boolean;
    request_sent_by_me: boolean;
    request_received: boolean;
    following: boolean;
    blocked_by_me: boolean;
    mutual_count: number;
};

export function RelationButtons({ user, relation }: { user: BasicUser; relation: Relation }) {
    const [busy, setBusy] = useState(false);

    const post = (url: string) => {
        if (busy) return;
        setBusy(true);
        void fetch(route(url, { user: user.id }), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
        }).then(() => window.location.reload());
    };

    if (relation.is_me) {
        return (
            <Link href={route('profile.edit')} className="bhas-btn-ghost">
                Edit profile
            </Link>
        );
    }
    if (relation.blocked_by_me) {
        return (
            <button type="button" className="bhas-btn-ghost" onClick={() => post('users.block')}>
                Unblock
            </button>
        );
    }
    if (relation.is_friend) {
        return (
            <div className="flex gap-2">
                <button type="button" className="bhas-btn-ghost" onClick={() => post('friends.unfriend')}>
                    <CheckIcon className="h-4 w-4" /> Friends
                </button>
                <button type="button" className="bhas-btn-ghost" onClick={() => post('users.follow')}>
                    {relation.following ? 'Unfollow' : 'Follow'}
                </button>
            </div>
        );
    }
    if (relation.request_received) {
        return (
            <div className="flex gap-2">
                <button type="button" className="bhas-btn-primary" onClick={() => post('friends.accept')}>
                    <CheckIcon className="h-4 w-4" /> Confirm
                </button>
                <button type="button" className="bhas-btn-ghost" onClick={() => post('friends.decline')}>
                    <XIcon className="h-4 w-4" /> Delete
                </button>
            </div>
        );
    }
    if (relation.request_sent_by_me) {
        return (
            <button type="button" className="bhas-btn-ghost" onClick={() => post('friends.cancel')}>
                <SendIcon className="h-4 w-4" /> Cancel request
            </button>
        );
    }
    return (
        <div className="flex gap-2">
            <button type="button" className="bhas-btn-primary" onClick={() => post('friends.request')}>
                <PlusIcon className="h-4 w-4" /> Add friend
            </button>
            <button type="button" className="bhas-btn-ghost" onClick={() => post('users.follow')}>
                {relation.following ? 'Unfollow' : 'Follow'}
            </button>
        </div>
    );
}

type ProfileData = BasicUser & {
    cover_url: string | null;
    bio: string | null;
    work: string | null;
    education: string | null;
    location: string | null;
    birthday: string | null;
    gender: string | null;
    friends_count: number;
    followers_count: number;
    is_admin: boolean;
};

export default function Show({ profileUser, relation, friends, posts }: {
    profileUser: ProfileData;
    relation: Relation;
    friends: BasicUser[];
    posts: unknown[];
}) {
    const flash = usePage().props as { status?: string; error?: string };
    return (
        <AuthenticatedLayout title={profileUser.name}>
            <div className="mx-auto max-w-4xl">
                {(flash.status || flash.error) && (
                    <div className={`mb-3 rounded-xl px-4 py-2.5 text-sm font-medium ${flash.error ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'}`}>
                        {flash.status ?? flash.error}
                    </div>
                )}
                <div className="bhas-card overflow-hidden">
                    <div className="relative h-40 bg-gradient-to-r from-bhas-300 to-bhas-500 md:h-56">
                        {profileUser.cover_url && (
                            <img src={profileUser.cover_url} alt="" className="h-full w-full object-cover" />
                        )}
                    </div>
                    <div className="relative px-4 pb-4">
                        <div className="-mt-12 mb-3 flex items-end justify-between">
                            <UserAvatar user={profileUser} size={96} />
                            <div className="flex gap-2 pb-1">
                                <RelationButtons user={profileUser} relation={relation} />
                            </div>
                        </div>
                        <h1 className="text-2xl font-extrabold">
                            {profileUser.name}
                            {profileUser.is_admin && (
                                <span className="ml-2 rounded-lg bg-bhas-100 px-2 py-0.5 align-middle text-xs font-bold text-bhas-700 dark:bg-bhas-800 dark:text-bhas-200">
                                    ADMIN
                                </span>
                            )}
                        </h1>
                        {profileUser.bio && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{profileUser.bio}</p>}
                        <p className="mt-1 text-sm font-medium text-slate-500">
                            {profileUser.friends_count} friends · {profileUser.followers_count} followers
                            {!relation.is_me && relation.mutual_count > 0 && ` · ${relation.mutual_count} mutual`}
                        </p>

                        <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {[
                                ['Work', profileUser.work],
                                ['Education', profileUser.education],
                                ['Location', profileUser.location],
                                ['Birthday', profileUser.birthday],
                            ]
                                .filter(([, v]) => v)
                                .map(([k, v]) => (
                                    <div key={k as string} className="rounded-xl bg-bhas-50 p-3 dark:bg-bhas-800/60">
                                        <div className="text-[11px] font-bold uppercase tracking-wide text-slate-400">{k}</div>
                                        <div className="text-sm font-semibold">{v}</div>
                                    </div>
                                ))}
                        </div>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-[1fr_280px]">
                    <section className="space-y-4">
                        <div className="bhas-card p-4">
                            <h2 className="mb-3 font-bold">Posts</h2>
                            {posts.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-400">No posts yet.</p>
                            ) : (
                                <p className="py-8 text-center text-sm text-slate-400">
                                    {posts.length} post{posts.length > 1 ? 's' : ''} — feed view coming in Phase 3.
                                </p>
                            )}
                        </div>
                    </section>
                    <aside className="space-y-4">
                        <div className="bhas-card p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="font-bold">Friends</h2>
                                <Link href={route('friends.index')} className="text-sm font-semibold text-bhas-600 hover:underline dark:text-bhas-300">
                                    See all
                                </Link>
                            </div>
                            <div className="grid grid-cols-3 gap-2">
                                {friends.map((f) => (
                                    <Link key={f.id} href={route('profile.show', { user: f.id })} className="group">
                                        <UserAvatar user={f} size={72} />
                                        <p className="mt-1 truncate text-xs font-medium group-hover:underline">{f.name}</p>
                                    </Link>
                                ))}
                                {friends.length === 0 && <p className="col-span-3 text-sm text-slate-400">No friends yet.</p>}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
