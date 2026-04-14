export default defineNuxtConfig({
  compatibilityDate: '2026-04-14',

  ssr: false,

  modules: [
    '@nuxt/ui',
    '@pinia/nuxt',
  ],

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
    },
  },

  devtools: { enabled: true },
})
