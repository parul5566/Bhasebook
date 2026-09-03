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
            },
        },
    },

    plugins: [forms],
};
