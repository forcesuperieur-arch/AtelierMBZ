export default defineNuxtConfig({
  compatibilityDate: '2026-04-14',

  alias: {
    '#app-manifest': './app-manifest.stub',
  },

  ssr: false,

  modules: [
    '@nuxt/ui',
    '@pinia/nuxt',
  ],

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      title: 'Paddock',
      meta: [
        { name: 'application-name', content: 'Paddock' },
        { name: 'apple-mobile-web-app-title', content: 'Paddock' },
        { property: 'og:title', content: 'Paddock' },
        { property: 'og:image', content: '/branding/paddock-logo-social.svg' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:title', content: 'Paddock' },
        { name: 'twitter:image', content: '/branding/paddock-logo-social.svg' },
      ],
      link: [
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' },
        { rel: 'icon', type: 'image/svg+xml', href: '/branding/paddock-logo-favicon.svg' },
        { rel: 'apple-touch-icon', href: '/branding/paddock-logo-favicon.svg' },
        { rel: 'manifest', href: '/manifest.json' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: '/api',
      mercureUrl: '/.well-known/mercure',
    },
  },

  colorMode: {
    preference: 'dark',
    fallback: 'dark',
    classSuffix: '',
  },

  vite: {
    optimizeDeps: {
      include: ['chart.js'],
    },
  },

  nitro: {
    routeRules: {
      '/api/**': { proxy: 'http://php:8000/api/**' },
      '/.well-known/mercure': { proxy: 'http://mercure:3000/.well-known/mercure' },
    },
  },

  experimental: {
    appManifest: false,
  },

  devtools: { enabled: true },
})
