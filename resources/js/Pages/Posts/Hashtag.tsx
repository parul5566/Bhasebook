import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PostCard, { type SerializedPost } from '@/Components/PostCard';

export default function Hashtag({ tag, count, posts }: { tag: string; count: number; posts: SerializedPost[] }) {
    return (
        <AuthenticatedLayout title={`#${tag}`}>
            <Head title={`#${tag}`} />
            <div className="mx-auto max-w-xl space-y-4">
                <div className="bhas-card p-6 text-center">
                    <p className="text-3xl font-extrabold text-bhas-600 dark:text-bhas-300">#{tag}</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{count} post{count === 1 ? '' : 's'}</p>
                </div>
                {posts.length === 0 ? (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">No posts with this hashtag yet.</div>
                ) : (
                    posts.map((p) => <PostCard key={p.id} post={p} />)
                )}
            </div>
        </AuthenticatedLayout>
    );
}
