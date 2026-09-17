import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { UserAvatar } from '@/Pages/Profile/Show';
import { REACTIONS, type SerializedPost } from '@/Components/PostCard';
import { BookmarkIcon, CommentIcon } from '@/Components/Icons';

type Props = { videos: SerializedPost[] };

export default function Watch({ videos }: Props) {
    return (
        <AuthenticatedLayout title="Watch">
            <div className="mx-auto max-w-3xl space-y-4">
                <h1 className="text-xl font-bold">Watch</h1>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                    Videos from people and pages you follow across Bhasebook.
                </p>

                {videos.length === 0 && (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">
                        No videos yet. When friends share videos, they'll show up here.
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {videos.map((v) => (
                        <div key={v.id} className="bhas-card overflow-hidden">
                            <Link href={route('posts.show', { post: v.id })} className="block bg-black">
                                <video
                                    src={v.media.find((m) => m.kind === 'video')?.url}
                                    className="max-h-72 w-full object-contain"
                                    controls
                                    preload="metadata"
                                    playsInline
                                />
                            </Link>
                            <div className="flex items-start gap-3 p-3">
                                <UserAvatar user={v.author ?? { id: 0, name: '?', avatar_url: null, hue: 0 }} size={36} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">{v.author?.name ?? 'Unknown'}</p>
                                    <p className="line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{v.content ?? '(video)'}</p>
                                    <div className="mt-1.5 flex items-center gap-3 text-xs text-slate-400">
                                        <span>{REACTIONS.find((r) => r.type === (v.my_reaction ?? 'like'))?.emoji} {v.reaction_total}</span>
                                        <span className="flex items-center gap-1"><CommentIcon className="h-3.5 w-3.5" /> {v.comment_count}</span>
                                        <span className="flex items-center gap-1"><BookmarkIcon className="h-3.5 w-3.5" /> {v.saved ? 'Saved' : `${v.view_count} views`}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
