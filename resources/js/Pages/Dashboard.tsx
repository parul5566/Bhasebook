import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import Composer from '@/Components/Composer';
import InfiniteFeed from '@/Components/InfiniteFeed';
import LeftSidebar from '@/Components/LeftSidebar';
import { SparkIcon } from '@/Components/Icons';
import { usePage } from '@inertiajs/react';
import { UserAvatar } from '@/Pages/Profile/Show';

export default function Dashboard() {
    const me = usePage().props.auth.user as unknown as { id: number; name: string; avatar_url: string | null; hue?: number };
    return (
        <AuthenticatedLayout title="Home">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
                <aside className="hidden lg:block">
                    <LeftSidebar />
                </aside>
                <section className="space-y-4">
                    <Composer />
                    <InfiniteFeed />
                </section>
                <aside className="hidden space-y-4 lg:block">
                    <div className="bhas-card p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <SparkIcon className="h-5 w-5 text-bhas-500" />
                            <h3 className="font-bold">Sponsored</h3>
                        </div>
                        <div className="rounded-xl bg-gradient-to-br from-bhas-100 to-bhas-200 p-4 text-center dark:from-bhas-800 dark:to-bhas-900">
                            <p className="font-bold">Welcome to Bhasebook ✨</p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Built fresh, just for you.</p>
                        </div>
                    </div>
                    <div className="bhas-card p-4">
                        <h3 className="mb-3 font-bold">You</h3>
                        <div className="flex items-center gap-3">
                            <UserAvatar user={{ id: me.id, name: me.name, avatar_url: me.avatar_url, hue: me.hue ?? 0 }} size={44} />
                            <div>
                                <p className="font-semibold">{me.name}</p>
                                <p className="text-xs text-slate-400">Keep sharing your world!</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
