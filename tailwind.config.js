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
            colors: {
                obsidian: {
                    950: '#050505',
                    900: '#0b0b0d',
                    850: '#111214',
                    800: '#17181c',
                    700: '#23252b',
                },
                slateglass: {
                    400: '#8d95a3',
                    300: '#b2b8c2',
                    200: '#d5d9e0',
                },
                gold: {
                    500: '#b89a57',
                    400: '#d6bb7a',
                    300: '#e7d3a1',
                },
            },
            fontFamily: {
                sans: ['"Instrument Sans"', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                panel: '0 18px 55px rgba(0, 0, 0, 0.42)',
                glow: '0 0 0 1px rgba(214, 187, 122, 0.08), 0 18px 40px rgba(0, 0, 0, 0.38)',
            },
            backgroundImage: {
                'premium-grid':
                    'linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px)',
            },
        },
    },

    plugins: [forms],
};
