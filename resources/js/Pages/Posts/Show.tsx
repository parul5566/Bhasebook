import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PostCard, { type SerializedPost } from '@/Components/PostCard';

export default function Show({ post }: { post: SerializedPost }) {
    return (
        <AuthenticatedLayout title="Post">
            <Head title="Post" />
            <div className="mx-auto max-w-xl">
                <PostCard post={post} />
            </div>
        </AuthenticatedLayout>
    );
}
