import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#0e4f79',
                    50:  '#e7eff5',
                    100: '#c4d6e4',
                    200: '#9cbad0',
                    300: '#729dbb',
                    400: '#5286ac',
                    500: '#33709d',
                    600: '#266390',
                    700: '#185482',
                    800: '#0e4f79',
                    900: '#093a5c',
                },
            },
        },
    },

    plugins: [
        forms,
        daisyui,
    ],

    daisyui: {
        themes: [
            {
                nautiqs: {
                    'primary':          '#0e4f79',
                    'primary-content':  '#ffffff',
                    'secondary':        '#185482',
                    'accent':           '#266390',
                    'neutral':          '#1f2937',
                    'base-100':         '#ffffff',
                    'base-200':         '#f5f7fa',
                    'base-300':         '#e5e9ef',
                    'info':             '#3abff8',
                    'success':          '#16a34a',
                    'warning':          '#f59e0b',
                    'error':            '#dc2626',
                },
            },
        ],
        logs: false,
    },
};
