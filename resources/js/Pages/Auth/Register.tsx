import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout title="Create account">
            <Head title="Create account" />

            <div className="bhas-card w-full max-w-md p-8">
                <h1 className="mb-1 text-2xl font-extrabold tracking-tight">Join Bhasebook</h1>
                <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
                    It's quick and easy.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="bhas-label" htmlFor="name">
                            Full name
                        </label>
                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="bhas-input"
                            autoComplete="name"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

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
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <label className="bhas-label" htmlFor="password_confirmation">
                            Confirm password
                        </label>
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="bhas-input"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>

                    <button type="submit" className="bhas-btn-primary w-full" disabled={processing}>
                        {processing ? 'Creating account…' : 'Sign up'}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Already have an account?{' '}
                    <Link
                        href={route('login')}
                        className="font-semibold text-bhas-600 hover:underline dark:text-bhas-300"
                    >
                        Log in
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
