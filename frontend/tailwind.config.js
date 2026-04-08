/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontSize: {
        '2xs': ['0.75rem', { lineHeight: '1.125rem' }],     // 12px
        'xs-pro': ['0.8125rem', { lineHeight: '1.25rem' }], // 13px
        'sm-pro': ['0.875rem', { lineHeight: '1.375rem' }],  // 14px
        'base-pro': ['0.9375rem', { lineHeight: '1.4375rem' }], // 15px
        'md-pro': ['1rem', { lineHeight: '1.5rem' }],       // 16px
        'lg-pro': ['1.0625rem', { lineHeight: '1.5rem' }], // 17px
        'xl-pro': ['1.125rem', { lineHeight: '1.5rem' }],  // 18px
      },
      colors: {
        slate: {
          850: '#172033',
          925: '#0f1419',
          950: '#0a0d12',
        },
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06)',
        'card-hover': '0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07)',
        'panel': '0 18px 36px -30px rgb(15 23 42 / 0.55), 0 1px 1px 0 rgb(15 23 42 / 0.04)',
        'floating': '0 30px 60px -42px rgb(15 23 42 / 0.6), 0 10px 18px -16px rgb(15 23 42 / 0.22)',
      },
      borderRadius: {
        'xl-soft': '14px',
      },
    },
  },
  plugins: [],
};
