export default defineNuxtConfig({
  devtools: { enabled: false },
  ssr: false,
  app: {
    baseURL: '/client',
    head: {
      link: [
        { rel: 'manifest', href: '/client/manifest.json' },
      ],
      // Thème appliqué AVANT la première peinture. Sans ce script, la page
      // s'afficherait en clair (valeur par défaut de `:root`) le temps que le
      // bundle démarre, d'où un flash blanc pour qui a choisi le sombre.
      // Même clé de stockage et même repli que le front staff.
      script: [
        {
          innerHTML: `(function(){try{var s=localStorage.getItem('paddock-theme');var p=(s==='light'||s==='dark'||s==='system')?s:'system';var d=p==='system'?!(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches):p==='dark';var r=document.documentElement;r.setAttribute('data-theme',d?'dark':'light');r.classList.add(d?'dark':'light')}catch(e){}})()`,
          tagPosition: 'head',
        },
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
