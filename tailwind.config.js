import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: 'var(--color-primary)',
                'primary-light': 'var(--color-primary-light)',
                'primary-dark': 'var(--color-primary-dark)',
                accent: 'var(--color-accent)',
                'accent-light': 'var(--color-accent-light)',
                'accent-dark': 'var(--color-accent-dark)',
                dark: {
                    900: 'var(--color-dark-900)',
                    800: 'var(--color-dark-800)',
                    700: 'var(--color-dark-700)',
                    600: 'var(--color-dark-600)',
                    500: 'var(--color-dark-500)',
                    400: 'var(--color-dark-400)',
                    300: 'var(--color-dark-300)',
                    200: 'var(--color-dark-200)',
                    100: 'var(--color-dark-100)',
                },
            },
        },
    },

    plugins: [forms],
};
