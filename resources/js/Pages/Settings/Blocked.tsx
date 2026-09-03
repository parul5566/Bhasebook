import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { UserAvatar, type BasicUser } from '@/Pages/Profile/Show';

export default function Blocked({ blocked }: { blocked: BasicUser[] }) {
    const unblock = (id: number) => {
        void fetch(route('users.block', { user: id }), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
        }).then(() => window.location.reload());
    };

    return (
        <AuthenticatedLayout title="Blocked users">
            <Head title="Blocked users" />
            <div className="mx-auto max-w-2xl">
                <div className="bhas-card p-4">
                    <h1 className="mb-1 text-xl font-extrabold">Blocked users</h1>
                    <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        Blocked people can't see your posts, message you, or send you friend requests.
                    </p>
                    {blocked.length === 0 ? (
                        <p className="py-10 text-center text-sm text-slate-400">You haven't blocked anyone.</p>
                    ) : (
                        <ul className="divide-y divide-bhas-100 dark:divide-bhas-800">
                            {blocked.map((u) => (
                                <li key={u.id} className="flex items-center justify-between gap-3 py-3">
                                    <div className="flex items-center gap-3">
                                        <UserAvatar user={u} size={44} />
                                        <span className="font-semibold">{u.name}</span>
                                    </div>
                                    <button type="button" onClick={() => unblock(u.id)} className="bhas-btn-ghost !py-1.5 text-xs">
                                        Unblock
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
