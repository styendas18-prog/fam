/** @type {import('tailwindcss').Config} */
// Tailwind configuration for the MAXXfam PHP application.
// The React/Vite configuration in tailwind.config.js is intentionally left untouched.
export default {
  darkMode: 'class',
  content: [
    './app/Views/**/*.php',
    './public/**/*.php',
    './resources/css/app.css',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        ink: {
          700: '#101d33',
          800: '#0d1729',
          850: '#0a1221',
          900: '#070d1c',
          950: '#040914',
        },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 30px -18px rgba(15, 23, 42, 0.22)',
        glow: '0 0 40px -12px rgba(37, 99, 235, 0.55)',
      },
      keyframes: {
        shimmer: {
          '100%': { transform: 'translateX(100%)' },
        },
        fadeUp: {
          '0%': { opacity: '0', transform: 'translateY(14px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '0.15', transform: 'scale(0.9)' },
          '50%': { opacity: '0.5', transform: 'scale(1.25)' },
        },
        floatY: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-10px)' },
        },
      },
      animation: {
        shimmer: 'shimmer 2s infinite',
        fadeUp: 'fadeUp .5s cubic-bezier(.2,.8,.2,1) both',
        pulseSoft: 'pulseSoft 2s infinite',
        floatY: 'floatY 6s ease-in-out infinite',
      },
    },
  },
  plugins: [],
};
