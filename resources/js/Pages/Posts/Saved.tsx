import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PostCard, { type SerializedPost } from '@/Components/PostCard';

export default function Saved({ posts }: { posts: SerializedPost[] }) {
    return (
        <AuthenticatedLayout title="Saved">
            <Head title="Saved" />
            <div className="mx-auto max-w-xl space-y-4">
                <div className="bhas-card p-6 text-center">
                    <p className="text-2xl font-extrabold">Saved posts</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Everything you bookmarked, in one place.</p>
                </div>
                {posts.length === 0 ? (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">
                        Nothing saved yet — tap Save on any post.
                    </div>
                ) : (
                    posts.map((p) => <PostCard key={p.id} post={p} />)
                )}
            </div>
        </AuthenticatedLayout>
    );
}
