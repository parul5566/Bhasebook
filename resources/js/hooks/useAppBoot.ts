import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { initTheme } from '@/Components/ThemeToggle';

export default function useAppBoot() {
    useEffect(() => {
        initTheme();
    }, []);
    return usePage().props;
}

export { useAppBoot };
