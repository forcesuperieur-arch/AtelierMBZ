export default defineNuxtConfig({
  // ═══════════════════════════════════════
  // NUXT 4 COMPATIBILITY
  // ═══════════════════════════════════════
  compatibilityDate: '2026-04-14',

  future: {
    compatibilityVersion: 4,
  },

  // Structure Nuxt 4 : tout l'app est dans app/
  srcDir: 'app',

  alias: {
    '#app-manifest': './app-manifest.stub',
  },

  ssr: false,

  modules: [
    '@nuxt/ui',
    '@nuxt/fonts',
    '@pinia/nuxt',
  ],

  fonts: {
    families: [
      { name: 'Inter', provider: 'google', weights: [400, 500, 600, 700, 800] },
      { name: 'Space Grotesk', provider: 'google', weights: [400, 500, 600, 700] },
      { name: 'JetBrains Mono', provider: 'google', weights: [400, 500, 600, 700] },
    ],
  },

  css: ['~/assets/css/paddock-theme.css', '~/assets/css/design-system.css', '~/assets/css/public-pages.css'],

  app: {
    head: {
      title: 'Paddock',
      htmlAttrs: {
        'data-color-mode-forced': 'light',
      },
      script: [
        {
          innerHTML: 'setTimeout(() => { document.documentElement.classList.remove(\'dark\'); document.documentElement.classList.add(\'light\'); localStorage.setItem(\'paddock-color-mode\', \'light\'); }, 0);',
        },
      ],
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
        { rel: 'icon', type: 'image/svg+xml', href: '/branding/paddock-logo-favicon.svg' },
        { rel: 'apple-touch-icon', sizes: '192x192', href: '/branding/paddock-icon-192.png' },
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
    preference: 'light',
    fallback: 'light',
    classSuffix: '',
    storageKey: 'paddock-color-mode',
  },

  nitro: {
    routeRules: {
      '/api/**': { proxy: (process.env.API_PROXY_URL || 'http://localhost:8000/api/**') },
      // CSP en mode enforce sur toutes les pages servies par Nuxt.
      // 'unsafe-inline' / 'unsafe-eval' nécessaires pour Vue runtime + Nuxt UI.
      // Whitelist : fonts.googleapis.com, fonts.gstatic.com, api.iconify.design.
      // frame-ancestors 'none' interdit l'intégration en iframe.
      // Les violations sont envoyées sur l'endpoint back /api/security/csp-report.
      '/**': {
        headers: {
          'Content-Security-Policy': [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self' https://api.iconify.design",
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
