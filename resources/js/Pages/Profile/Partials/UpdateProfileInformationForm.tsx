import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage, router } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

const MAX_AVATAR = 4 * 1024 * 1024; // backend allows 4096 KB
const MAX_COVER = 8 * 1024 * 1024; // backend allows 8192 KB

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user as unknown as {
        id: number; name: string; email: string; email_verified_at: string | null;
        avatar_url: string | null; cover_url: string | null;
        bio?: string | null; work?: string | null; education?: string | null;
        location?: string | null; birthday?: string | null; gender?: string | null;
    };

    const avatarInput = useRef<HTMLInputElement>(null);
    const coverInput = useRef<HTMLInputElement>(null);
    const [avatarFile, setAvatarFile] = useState<File | null>(null);
    const [coverFile, setCoverFile] = useState<File | null>(null);
    const [fileError, setFileError] = useState<string | null>(null);

    const { data, setData, post, errors, processing, recentlySuccessful } =
        useForm<{
            name: string; email: string; bio: string; work: string;
            education: string; location: string; birthday: string; gender: string;
            avatar?: File | null; cover?: File | null;
        }>({
            name: user.name,
            email: user.email,
            bio: user.bio ?? '',
            work: user.work ?? '',
            education: user.education ?? '',
            location: user.location ?? '',
            birthday: user.birthday ?? '',
            gender: user.gender ?? '',
        });

    const avatarPreview = avatarFile ? URL.createObjectURL(avatarFile) : user.avatar_url;
    const coverPreview = coverFile ? URL.createObjectURL(coverFile) : user.cover_url;

    const pickImage = (kind: 'avatar' | 'cover', file: File | undefined) => {
        if (!file) return;
        const isImage = /^image\/(jpeg|png|webp|gif)$/.test(file.type);
        const max = kind === 'avatar' ? MAX_AVATAR : MAX_COVER;
        if (!isImage) {
            setFileError('Please choose a JPG, PNG, WebP or GIF image.');
            return;
        }
        if (file.size > max) {
            setFileError(`Image is too large — max ${kind === 'avatar' ? '4' : '8'} MB.`);
            return;
        }
        setFileError(null);
        if (kind === 'avatar') setAvatarFile(file);
        else setCoverFile(file);
    };

    const clearImage = (kind: 'avatar' | 'cover') => {
        setFileError(null);
        if (kind === 'avatar') {
            setAvatarFile(null);
            if (avatarInput.current) avatarInput.current.value = '';
        } else {
            setCoverFile(null);
            if (coverInput.current) coverInput.current.value = '';
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setFileError(null);

        router.post(route('profile.update'), {
            ...data,
            avatar: avatarFile,
            cover: coverFile,
            _method: 'patch',
        }, {
            forceFormData: true,
            onFinish: () => {
                clearImage('avatar');
                clearImage('cover');
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-slate-900 dark:text-slate-100">
                    Profile Information
                </h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Update your photos and profile details.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                {/* Cover photo */}
                <div>
                    <InputLabel value="Cover photo" />
                    <div className="relative mt-1 h-36 overflow-hidden rounded-2xl bg-gradient-to-br from-bhas-500 via-purple-500 to-fuchsia-500">
                        {coverPreview && (
                            <img src={coverPreview} alt="Cover preview" className="h-full w-full object-cover" />
                        )}
                        <div className="absolute inset-x-0 bottom-0 flex justify-end gap-2 bg-gradient-to-t from-black/50 to-transparent p-2">
                            {coverFile && (
                                <button type="button" onClick={() => clearImage('cover')} className="rounded-lg bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-white">
                                    Cancel
                                </button>
                            )}
                            <button type="button" onClick={() => coverInput.current?.click()} className="rounded-lg bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-white">
                                {coverFile ? 'Change again' : user.cover_url ? 'Change cover' : 'Add cover'}
                            </button>
                        </div>
                    </div>
                    <input
                        ref={coverInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(e) => pickImage('cover', e.target.files?.[0])}
                    />
                    <InputError className="mt-2" message={errors.cover} />
                </div>

                {/* Avatar + name row */}
                <div className="flex items-center gap-4">
                    <div className="relative">
                        {avatarPreview ? (
                            <img src={avatarPreview} alt="Avatar preview" className="h-20 w-20 rounded-full object-cover ring-2 ring-bhas-500/50" />
                        ) : (
                            <div className="flex h-20 w-20 items-center justify-center rounded-full bg-bhas-100 text-xl font-bold text-bhas-700 dark:bg-bhas-800 dark:text-bhas-200">
                                {user.name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => avatarInput.current?.click()}
                            className="absolute -bottom-1 -right-1 flex h-8 w-8 items-center justify-center rounded-full bg-bhas-600 text-white shadow-glow-sm transition hover:bg-bhas-700"
                            aria-label="Change profile photo"
                            title="Change profile photo"
                        >
                            📷
                        </button>
                    </div>
                    <div className="min-w-0 flex-1">
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                        />
                        <InputError className="mt-2" message={errors.name} />
                    </div>
                </div>
                {avatarFile && (
                    <p className="-mt-2 text-xs text-slate-500">
                        New photo selected — click <strong>Save</strong> to apply.
                        <button type="button" onClick={() => clearImage('avatar')} className="ml-2 underline">Cancel</button>
                        <input
                            ref={avatarInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            className="hidden"
                            onChange={(e) => pickImage('avatar', e.target.files?.[0])}
                        />
                    </p>
                )}
                {!avatarFile && (
                    <input
                        ref={avatarInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        className="hidden"
                        onChange={(e) => pickImage('avatar', e.target.files?.[0])}
                    />
                )}

                {/* Email */}
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />
                    <InputError className="mt-2" message={errors.email} />

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            Your email address is unverified.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="underline hover:text-bhas-600"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>
                    )}
                    {status === 'verification-link-sent' && (
                        <div className="mt-2 text-sm font-medium text-emerald-600">
                            A new verification link has been sent to your email address.
                        </div>
                    )}
                </div>

                {/* Bio */}
                <div>
                    <InputLabel htmlFor="bio" value="Bio" />
                    <textarea
                        id="bio"
                        rows={3}
                        maxLength={500}
                        className="bhas-input mt-1 block w-full"
                        placeholder="Tell people a little about yourself…"
                        value={data.bio}
                        onChange={(e) => setData('bio', e.target.value)}
                    />
                    <div className="mt-1 flex justify-between">
                        <InputError message={errors.bio} />
                        <span className="text-xs text-slate-400">{data.bio.length}/500</span>
                    </div>
                </div>

                {/* Work / education / location grid */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="work" value="Work" />
                        <TextInput
                            id="work"
                            className="mt-1 block w-full"
                            placeholder="e.g. Software Engineer at Acme"
                            value={data.work}
                            onChange={(e) => setData('work', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.work} />
                    </div>
                    <div>
                        <InputLabel htmlFor="education" value="Education" />
                        <TextInput
                            id="education"
                            className="mt-1 block w-full"
                            placeholder="e.g. studied at MIT"
                            value={data.education}
                            onChange={(e) => setData('education', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.education} />
                    </div>
                    <div>
                        <InputLabel htmlFor="location" value="Location" />
                        <TextInput
                            id="location"
                            className="mt-1 block w-full"
                            placeholder="e.g. Karachi, Pakistan"
                            value={data.location}
                            onChange={(e) => setData('location', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.location} />
                    </div>
                    <div>
                        <InputLabel htmlFor="birthday" value="Birthday" />
                        <TextInput
                            id="birthday"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.birthday}
                            onChange={(e) => setData('birthday', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.birthday} />
                    </div>
                </div>

                {/* Gender */}
                <div>
                    <InputLabel htmlFor="gender" value="Gender" />
                    <select
                        id="gender"
                        className="bhas-input mt-1 block w-full"
                        value={data.gender}
                        onChange={(e) => setData('gender', e.target.value)}
                    >
                        <option value="">Prefer not to say / unset</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                    <InputError className="mt-2" message={errors.gender} />
                </div>

                {fileError && (
                    <p className="text-sm font-semibold text-rose-600">{fileError}</p>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-slate-600 dark:text-slate-400">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
