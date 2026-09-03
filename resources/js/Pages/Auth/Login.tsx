import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout title="Log in">
            <Head title="Log in" />

            {status && (
                <div className="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <div className="bhas-card w-full max-w-md p-8">
                <h1 className="mb-1 text-2xl font-extrabold tracking-tight">Welcome back</h1>
                <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
                    Log in to Bhasebook to connect with your friends.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="bhas-label" htmlFor="email">
                            Email
                        </label>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="bhas-input"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <label className="bhas-label" htmlFor="password">
                            Password
                        </label>
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="bhas-input"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            Remember me
                        </label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm font-semibold text-bhas-600 hover:underline dark:text-bhas-300"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <button type="submit" className="bhas-btn-primary w-full" disabled={processing}>
                        {processing ? 'Logging in…' : 'Log in'}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    New to Bhasebook?{' '}
                    <Link
                        href={route('register')}
                        className="font-semibold text-bhas-600 hover:underline dark:text-bhas-300"
                    >
                        Create an account
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
