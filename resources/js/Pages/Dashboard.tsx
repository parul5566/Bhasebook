import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { route } from 'ziggy-js';

export default function Dashboard() {
    return (
        <AuthenticatedLayout title="Home">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
                <aside className="hidden lg:block">
                    <div className="bhas-card p-2">
                        <p className="px-3 py-2 text-sm text-slate-500">Navigation loading…</p>
                    </div>
                </aside>
                <section className="space-y-4">
                    <div className="bhas-card p-4">
                        <p className="text-sm text-slate-500">
                            Welcome to Bhasebook — your feed is being prepared.
                        </p>
                    </div>
                </section>
                <aside className="hidden lg:block">
                    <div className="bhas-card p-4">
                        <p className="text-sm text-slate-500">Contacts & suggestions coming soon.</p>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
