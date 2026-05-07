import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  theme: {
    extend: {
      colors: {
        'accent': '#e85913',
        'accent-hover': '#d14d0f',
        'accent-dark': '#c44d00',
        'accent-light': '#fff5ed',
        'header': '#141428',
        'body-page': '#f8f7f4',
        'body-gray': '#e4e3de',
        'text-primary': '#141428',
        'text-secondary': '#6b6b7b',
        'text-muted': '#9a9aa8',
        'text-tertiary': '#8a8a9a',
        'border': '#d8d6d1',
        'border-light': '#e4e3de',
        'border-medium': '#d8d6d1',
        'success': '#16a34a',
        'danger': '#dc2626',
        'warning': '#ca8a04',
        'info': '#2563eb',
      },
      zIndex: {
        'drawer': '50',
        'toast': '60',
      },
      boxShadow: {
        'xs': '0 1px 2px 0 rgba(20, 20, 40, 0.04)',
        'card': '0 1px 3px 0 rgba(20, 20, 40, 0.08)',
      },
      animation: {
        'shimmer': 'shimmer 1.5s infinite linear',
        'slide-in-right': 'slideInRight 0.3s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'fade-in': 'fadeIn 0.3s ease-out',
      },
      keyframes: {
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        slideInRight: {
          '0%': { transform: 'translateX(100%)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
}
