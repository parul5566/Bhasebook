import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StoryBar, { type StoryGroup } from '@/Components/StoryBar';
import { useState } from 'react';

export default function StoriesIndex() {
    const [reloadKey, setReloadKey] = useState(0);

    return (
        <AuthenticatedLayout title="Stories">
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">Stories</h1>
                </div>

                <StoryBar key={reloadKey} />

                <div className="bhas-card p-6 text-sm text-slate-500 dark:text-slate-400">
                    Stories disappear after 24 hours. Choose who can see each story when you create it — everyone
                    (public) or just your friends.
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
