import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PostCard, { type SerializedPost } from '@/Components/PostCard';

export default function Memories({ posts }: { posts: SerializedPost[] }) {
    return (
        <AuthenticatedLayout title="Memories">
            <Head title="Memories" />
            <div className="mx-auto max-w-xl space-y-4">
                <div className="bhas-card p-6 text-center">
                    <p className="text-2xl font-extrabold">On this day</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Posts you shared on this date in previous years.
                    </p>
                </div>
                {posts.length === 0 ? (
                    <div className="bhas-card p-10 text-center text-sm text-slate-400">
                        No memories today — come back tomorrow! 💭
                    </div>
                ) : (
                    posts.map((p) => <PostCard key={p.id} post={p} />)
                )}
            </div>
        </AuthenticatedLayout>
    );
}
