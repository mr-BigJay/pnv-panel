/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        tg: {
          bg: '#0e1621',
          sidebar: '#17212b',
          panel: '#0e1621',
          bubbleOwn: '#2b5278',
          bubbleOther: '#182533',
          accent: '#6ab2f2',
          muted: '#708499',
          border: '#242f3d',
          hover: '#1e2c3a',
        },
      },
    },
  },
  plugins: [],
};
