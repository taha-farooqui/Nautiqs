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
                sans: ['Geist', ...defaultTheme.fontFamily.sans],
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
                // Clement's Figma design tokens (--nq-*), named as in the file.
                nq: {
                    text:        '#0b0b0d', // text-primary
                    'text-2':    '#55555e', // text-secondary / nav text
                    'text-3':    '#73737c', // text-tertiary
                    border:      '#e8e8eb', // border-default
                    'border-2':  '#f0f0f2', // border-secondary
                    muted:       '#f0f0f2', // bg-muted
                    subtle:      '#fbfbfb', // bg-subtle / nav bg
                    active:      '#093a5c', // nav-item-active
                    'primary-bg':'#eef4f8',
                    accent:      '#b8864a',
                    'accent-bg': '#fbf7f0',
                    'accent-text':'#7a532c',
                    chart2:      '#2b8fd6',
                    chart3:      '#e59a1a',
                    'success':   '#16a06a',
                    'success-bg':'#ecfaf3',
                    'success-border':'#d1f2e1',
                    'success-text':'#0b6845',
                    'error':     '#e5484d',
                    'error-bg':  '#fef1f1',
                    'error-text':'#a02c30',
                    'info-bg':   '#eef7fe',
                    'warning-bg':'#fff8eb',
                },
            },
            boxShadow: {
                'nq-xs': '0 1px 2px rgba(11, 16, 21, 0.05)',
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
