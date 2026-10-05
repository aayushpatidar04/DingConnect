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
                surface: {
                    0: 'var(--color-surface-0)',
                    1: 'var(--color-surface-1)',
                    2: 'var(--color-surface-2)',
                    3: 'var(--color-surface-3)',
                },
                ink: {
                    900: 'var(--color-ink-900)',
                    700: 'var(--color-ink-700)',
                    500: 'var(--color-ink-500)',
                    300: 'var(--color-ink-300)',
                    100: 'var(--color-ink-100)',
                },
            },
        },
    },

    plugins: [forms],
};
