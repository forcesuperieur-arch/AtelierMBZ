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
      // CSP en mode Report-Only sur toutes les pages servies par Nuxt.
      // Phase d'observation : on logge les violations sans bloquer, on resserrera après analyse.
      // Politique permissive volontaire : 'unsafe-inline' / 'unsafe-eval' nécessaires pour Vue runtime + Nuxt UI.
      // Whitelist : fonts.googleapis.com (Inter), Mercure (EventSource via /.well-known/mercure → connect-src 'self').
      // Les violations sont envoyées sur l'endpoint back /api/security/csp-report.
      '/**': {
        headers: {
          'Content-Security-Policy-Report-Only': [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "report-uri /api/security/csp-report",
          ].join('; '),
          'X-Frame-Options': 'DENY',
          'X-Content-Type-Options': 'nosniff',
          'Referrer-Policy': 'strict-origin-when-cross-origin',
        },
      },
    },
  },

  devtools: { enabled: true },

  // Evite les erreurs Vite "Failed to resolve import #app-manifest"
  // observées en mode dev Docker lors des reloads/restarts Nuxt.
  experimental: {
    appManifest: false,
  },

  // Polling nécessaire pour que le hot-reload fonctionne dans Docker
  // (les événements inotify ne sont pas transmis depuis l'hôte vers le container).
  vite: {
    server: {
      watch: {
        usePolling: true,
        interval: 500,
      },
    },
  },
})
