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
                sans: ['Jost', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                canvas: '#faf8f5',
                surface: '#ffffff',
                border: '#e8e2d9',
                coffee: {
                    DEFAULT: '#4a3324',
                    hover: '#38261a',
                    light: '#7b5537',
                    subtle: '#f5efe6',
                },
                ink: {
                    DEFAULT: '#241e19',
                    secondary: '#66584d',
                    muted: '#9c8e80',
                },
            },
        },
    },

    plugins: [forms],
};
