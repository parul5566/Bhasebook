import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import type { BasicUser } from '@/Pages/Profile/Show';

export default function Restricted({ profileUser }: { profileUser: BasicUser }) {
    return (
        <AuthenticatedLayout title={profileUser.name}>
            <div className="mx-auto max-w-lg">
                <div className="bhas-card p-8 text-center">
                    <div
                        className="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold text-white"
                        style={{ backgroundColor: `hsl(${profileUser.hue} 55% 45%)` }}
                    >
                        {profileUser.name[0]}
                    </div>
                    <h1 className="text-xl font-extrabold">{profileUser.name}</h1>
                    <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        This profile is private. Only friends can view {profileUser.name.split(' ')[0]}'s full profile and posts.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
