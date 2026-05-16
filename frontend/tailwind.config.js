/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      fontSize: {
        '2xs': ['0.8125rem', { lineHeight: '1.25rem' }],
        'xs-pro': ['0.875rem', { lineHeight: '1.375rem' }],
        'sm-pro': ['0.9375rem', { lineHeight: '1.4375rem' }],
        'base-pro': ['1rem', { lineHeight: '1.5rem' }],
        'md-pro': ['1.0625rem', { lineHeight: '1.5rem' }],
        'lg-pro': ['1.125rem', { lineHeight: '1.5rem' }],
        'xl-pro': ['1.1875rem', { lineHeight: '1.5rem' }],
      },
      colors: {
        slate: {
          850: '#172033',
          925: '#0f1419',
          950: '#0a0d12',
        },
        sidebar: {
          DEFAULT: '#1E293B',
          border: '#334155',
        },
      },
      boxShadow: {
        'card': '0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)',
        'card-hover': '0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06)',
        'panel': '0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)',
        'floating': '0 20px 40px rgba(0,0,0,0.15)',
      },
    },
  },
  plugins: [],
};
