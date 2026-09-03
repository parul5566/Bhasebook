import { useEffect, useState } from 'react';
import { MoonIcon, SunIcon } from '@/Components/Icons';

type Theme = 'light' | 'dark' | 'system';

function apply(theme: Theme) {
    const root = document.documentElement;
    const dark =
        theme === 'dark' ||
        (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', dark);
}

export function initTheme() {
    const stored = (localStorage.getItem('bhas-theme') as Theme | null) ?? 'system';
    apply(stored);
}

export default function ThemeToggle() {
    const [theme, setTheme] = useState<Theme>(
        () => (localStorage.getItem('bhas-theme') as Theme | null) ?? 'system',
    );

    useEffect(() => {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => theme === 'system' && apply('system');
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [theme]);

    const cycle = () => {
        const next: Theme = theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light';
        setTheme(next);
        localStorage.setItem('bhas-theme', next);
        apply(next);
    };

    return (
        <button
            type="button"
            onClick={cycle}
            title={`Theme: ${theme}`}
            className="bhas-icon-btn"
            aria-label="Toggle theme"
        >
            {theme === 'light' ? <SunIcon className="h-5 w-5" /> : <MoonIcon className="h-5 w-5" />}
        </button>
    );
}
