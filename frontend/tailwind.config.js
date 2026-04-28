/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        samsung: ['SamsungOne', 'system-ui', '-apple-system', 'sans-serif'],
      },
      fontSize: {
        '2xs':      ['0.6875rem', { lineHeight: '1.4', fontWeight: '400' }],  // 11px
        'xs-pro':   ['0.75rem',   { lineHeight: '1.4', fontWeight: '400' }],  // 12px
        'sm-pro':   ['0.8125rem', { lineHeight: '1.45', fontWeight: '400' }], // 13px
        'base-pro': ['0.9375rem', { lineHeight: '1.5', fontWeight: '500' }],  // 15px
        'md-pro':   ['1.0625rem', { lineHeight: '1.4', fontWeight: '600' }],  // 17px
        'lg-pro':   ['1.25rem',   { lineHeight: '1.3', fontWeight: '700' }],  // 20px
        'xl-pro':   ['1.5rem',    { lineHeight: '1.2', fontWeight: '700' }],  // 24px
      },
      colors: {
        'one-bg':      '#F4F4F6',
        'one-surface': '#FFFFFF',
        'one-primary': '#1259C3',
        'one-accent':  '#25C5DA',
        'one-text':    '#1C1C1E',
        'one-sub':     '#6E6E73',
        'one-muted':   '#AEAEB2',
        'one-divider': '#E5E5EA',
        'one-tint':    '#EBF1FB',
      },
      boxShadow: {
        'card':    '0 2px 12px 0 rgba(0,0,0,0.06)',
        'panel':   '0 4px 20px 0 rgba(0,0,0,0.08)',
        'panel-lg':'0 8px 32px 0 rgba(0,0,0,0.10)',
      },
      borderRadius: {
        'card': '24px',
        'btn':  '14px',
        'sm-card': '16px',
        'xs-card': '10px',
      },
    },
  },
  plugins: [],
};
