import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                bhas: {
                    50: '#eef6ff',
                    100: '#d9ebff',
                    200: '#bcdcff',
                    300: '#8ec6ff',
                    400: '#59a6ff',
                    500: '#3387fb',
                    600: '#1e6be8',
                    700: '#1755c4',
                    800: '#18489f',
                    900: '#19407e',
                    950: '#142850',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(0 0 0 / 0.06), 0 1px 3px 0 rgb(0 0 0 / 0.08)',
                pop: '0 8px 24px -6px rgb(24 40 80 / 0.18), 0 2px 8px -2px rgb(24 40 80 / 0.10)',
                glow: '0 0 24px -4px rgb(51 135 251 / 0.45)',
                'glow-sm': '0 0 12px -2px rgb(51 135 251 / 0.4)',
            },
            backgroundImage: {
                'gradient-bhas': 'linear-gradient(135deg, #3387fb 0%, #7c5cff 100%)',
                'gradient-bhas-soft': 'linear-gradient(135deg, rgba(51,135,251,0.10) 0%, rgba(124,92,255,0.10) 100%)',
                'gradient-hero': 'linear-gradient(120deg, #3387fb 0%, #7c5cff 55%, #e34fd0 100%)',
            },
            keyframes: {
                shimmer: {
                    '0%': { backgroundPosition: '-500px 0' },
                    '100%': { backgroundPosition: '500px 0' },
                },
                'pop-in': {
                    '0%': { opacity: '0', transform: 'scale(0.92) translateY(6px)' },
                    '100%': { opacity: '1', transform: 'scale(1) translateY(0)' },
                },
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'badge-bounce': {
                    '0%, 100%': { transform: 'scale(1)' },
                    '40%': { transform: 'scale(1.35)' },
                    '70%': { transform: 'scale(0.9)' },
                },
                'heart-burst': {
                    '0%': { opacity: '0', transform: 'scale(0.3)' },
                    '30%': { opacity: '1', transform: 'scale(1.25)' },
                    '60%': { opacity: '1', transform: 'scale(0.95)' },
                    '100%': { opacity: '0', transform: 'scale(1.4)' },
                },
            },
            animation: {
                shimmer: 'shimmer 1.6s linear infinite',
                'pop-in': 'pop-in 0.25s ease-out both',
                'fade-up': 'fade-up 0.3s ease-out both',
                'badge-bounce': 'badge-bounce 0.5s ease-in-out',
                'heart-burst': 'heart-burst 0.7s ease-out forwards',
            },
        },
    },

    plugins: [forms],
};
