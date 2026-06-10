export default defineNuxtConfig({
  devtools: { enabled: false },
  ssr: false,
  app: {
    baseURL: '/client',
    head: {
      link: [
        { rel: 'manifest', href: '/client/manifest.json' },
      ],
    },
  },
  css: ['~/assets/css/main.css'],
  modules: ['@pinia/nuxt'],
  runtimeConfig: {
    public: {
      apiBase: '/api',
    },
  },
  nitro: {
    routeRules: {
      '/api/**': { proxy: 'http://php:8000/api/**' },
    },
  },

  vite: {
    sourcemap: false,
    optimizeDeps: {
      include: ['vue', 'vue-router', 'pinia', '@pinia/nuxt'],
    },
  },
})
