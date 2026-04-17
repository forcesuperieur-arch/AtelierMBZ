export default defineNuxtConfig({
  compatibilityDate: '2026-04-14',

  ssr: false,

  modules: [
    '@nuxt/ui',
    '@pinia/nuxt',
  ],

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      link: [
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' },
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

  nitro: {
    routeRules: {
      '/api/**': { proxy: 'http://localhost:8000/api/**' },
    },
  },

  devtools: { enabled: true },
})
