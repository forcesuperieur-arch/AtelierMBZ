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
        // Les polices du design system (Montserrat + Inter) sont importées
        // depuis assets/css/main.css, avec les seules graisses utilisées.
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

  // `dataValue: 'theme'` fait poser `data-theme="dark|light"` sur <html>, ce
  // qui est le sélecteur des tokens (assets/css/tokens.css). La classe `dark`
  // reste posée en parallèle : c'est elle que lisent les composants Nuxt UI.
  // Les deux mécanismes doivent rester alignés, sinon les UCard/UInput
  // resteraient sombres sur une interface passée en clair.
  // Le module injecte lui-même un script avant peinture : pas de flash.
  colorMode: {
    preference: 'system',
    fallback: 'dark',
    classSuffix: '',
    dataValue: 'theme',
    storageKey: 'paddock-theme',
  },

  // Icônes RemixIcon : tout est résolu à la COMPILATION.
  //
  // Par défaut, `@nuxt/icon` n'embarque que les NOMS et va chercher les tracés
  // au moment de l'affichage, sur `api.iconify.design`. Un atelier dont le
  // poste n'a pas d'accès sortant afficherait alors une interface sans aucune
  // icône. `clientBundle.scan` relève les noms employés dans les sources et
  // inscrit leur tracé dans le bundle ; `fallbackToApi: false` interdit
  // explicitement le repli réseau, de sorte qu'une icône absente se voie au
  // build plutôt qu'en production.
  //
  // La collection est fournie par `@iconify-json/ri`, en devDependency.
  icon: {
    clientBundle: { scan: true, sizeLimitKb: 512 },
    serverBundle: false,
    fallbackToApi: false,
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
