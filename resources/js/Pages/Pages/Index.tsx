import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, StoreIcon } from '@/Components/Icons';

type PageCard = {
    id: number; name: string; category: string; about: string | null;
    avatar_url: string | null; followers_count: number; following: boolean; hue: number;
};

type Props = { discover: PageCard[]; mine: PageCard[]; followed: PageCard[]; q: string; categories: string[] };

export default function Index({ discover, mine, followed, q, categories }: Props) {
    const [creating, setCreating] = useState(false);

    return (
        <AuthenticatedLayout title="Pages">
            <div className="mx-auto max-w-3xl space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="flex items-center gap-2 text-xl font-bold"><StoreIcon className="h-6 w-6 text-violet-500" /> Pages</h1>
                    <button type="button" className="bhas-btn-primary" onClick={() => setCreating(true)}>
                        <PlusIcon className="h-4 w-4" /> Create page
                    </button>
                </div>

                <form onSubmit={(e) => {
                    e.preventDefault();
                    const value = new FormData(e.currentTarget).get('q');
                    router.visit(route('pages.index', { q: value || '' }), { preserveState: true });
                }}>
                    <input name="q" defaultValue={q} className="bhas-input" placeholder="Search pages…" />
                </form>

                {mine.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Pages you manage</h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {mine.map((p) => <PageTile key={p.id} page={p} />)}
                        </div>
                    </section>
                )}

                {followed.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Pages you follow</h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {followed.map((p) => <PageTile key={p.id} page={p} />)}
                        </div>
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-bold uppercase tracking-wide text-slate-400">Discover pages</h2>
                    {discover.length === 0 ? (
                        <div className="bhas-card p-10 text-center text-sm text-slate-400">No pages found.</div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {discover.map((p) => <PageTile key={p.id} page={p} />)}
                        </div>
                    )}
                </section>
            </div>

            {creating && <CreatePageModal categories={categories} onClose={() => setCreating(false)} />}
        </AuthenticatedLayout>
    );
}

function PageTile({ page: p }: { page: PageCard }) {
    return (
        <Link href={route('pages.show', { page: p.id })} className="bhas-card flex items-center gap-3 p-3 transition hover:shadow-pop">
            {p.avatar_url ? (
                <img src={p.avatar_url} alt="" className="h-12 w-12 rounded-full object-cover" />
            ) : (
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-600 text-lg font-bold text-white">
                    {p.name[0]}
                </div>
            )}
            <div className="min-w-0 flex-1">
                <p className="truncate font-bold">{p.name}</p>
                <p className="truncate text-xs text-slate-400">{p.category} · {p.followers_count} followers</p>
            </div>
        </Link>
    );
}

function CreatePageModal({ categories, onClose }: { categories: string[]; onClose: () => void }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const submit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        try {
            const res = await fetch(route('pages.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body: new FormData(e.currentTarget),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message ?? 'Could not create page');
            }
            const data = await res.json();
            window.location.href = route('pages.show', { page: data.id });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed');
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="bhas-card w-full max-w-lg p-5" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-lg font-bold">Create a page</h3>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="bhas-label">Page name</label>
                        <input name="name" required minLength={2} maxLength={100} className="bhas-input" placeholder="e.g. Mountain Coffee Co." />
                    </div>
                    <div>
                        <label className="bhas-label">Category</label>
                        <select name="category" className="bhas-input">
                            {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="bhas-label">About</label>
                        <textarea name="about" rows={3} maxLength={2000} className="bhas-input resize-none" placeholder="Tell people what this page is about…" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="bhas-label">Profile image</label>
                            <input name="avatar" type="file" accept="image/*" className="bhas-input !py-2 text-xs" />
                        </div>
                        <div>
                            <label className="bhas-label">Cover image</label>
                            <input name="cover" type="file" accept="image/*" className="bhas-input !py-2 text-xs" />
                        </div>
                    </div>
                    {error && <p className="text-sm text-rose-600">{error}</p>}
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" className="bhas-btn-ghost" onClick={onClose}>Cancel</button>
                        <button type="submit" className="bhas-btn-primary" disabled={busy}>{busy ? 'Creating…' : 'Create page'}</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
