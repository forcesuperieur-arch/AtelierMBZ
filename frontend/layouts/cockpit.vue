<template>
  <div class="cockpit-app">
    <!-- NAV RÉSEAU — noire, distincte de la nav d'atelier (49b, 52a).
         Deux navigations, c'est le coût assumé de la version A : on ne doit
         jamais confondre lire le réseau et agir dans un atelier. -->
    <nav class="cockpit-nav">
      <div class="cockpit-brand">
        <img src="/branding/paddock-logo-favicon.svg" alt="" class="cockpit-brand-logo" />
        <span class="cockpit-brand-text">
          <span class="cockpit-brand-name">PADDOCK</span>
          <span class="cockpit-brand-sub">Cockpit réseau</span>
        </span>
      </div>

      <div class="cockpit-group">Réseau</div>
      <NuxtLink
        v-for="vue in vuesReseau"
        :key="vue.value"
        :to="{ path: '/cockpit', query: { vue: vue.value } }"
        :class="['cockpit-link', currentVue === vue.value ? 'is-active' : '']"
      >
        <AppIcon :name="vue.icon" class="cockpit-link-icon" />
        <span>{{ vue.label }}</span>
      </NuxtLink>

      <template v-if="ateliers.length">
        <div class="cockpit-group">Ateliers</div>
        <button
          v-for="atelier in ateliers"
          :key="atelier.id"
          class="cockpit-link cockpit-link-button"
          data-testid="cockpit-atelier"
          :disabled="entering === atelier.id"
          @click="enterAtelier(atelier)"
        >
          <AppIcon name="i-ri-store-2-line" class="cockpit-link-icon" />
          <span>{{ atelier.nom }}</span>
          <AppIcon name="i-ri-arrow-right-line" class="cockpit-link-go" />
        </button>
      </template>

      <div class="cockpit-spacer" />

      <p class="cockpit-note">
        Entrer dans un atelier ouvre l'application complète de ce site, avec un
        bandeau de retour vers ce cockpit.
      </p>

      <button class="cockpit-link cockpit-link-button" @click="auth.logout()">
        <AppIcon name="i-ri-shut-down-line" class="cockpit-link-icon" />
        <span>Déconnexion</span>
      </button>
    </nav>

    <div class="cockpit-main">
      <header class="cockpit-topbar">
        <span class="cockpit-topbar-title">{{ currentVueLabel }}</span>
        <span class="cockpit-topbar-hint">{{ ateliers.length }} atelier{{ ateliers.length > 1 ? 's' : '' }} dans le périmètre</span>
        <div class="cockpit-topbar-spacer" />
        <AppThemeToggle />
        <span class="cockpit-topbar-user">{{ auth.user.value?.email }} · SRC</span>
      </header>

      <main class="cockpit-content">
        <slot />
      </main>
      <UToaster />
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const api = useApi()
const route = useRoute()
const toast = useToast()

const vuesReseau = [
  { value: 'recherche', label: 'Recherche', icon: 'i-ri-search-line' },
  { value: 'file', label: 'File de travail', icon: 'i-ri-list-check-2' },
]

const currentVue = computed(() => (String(route.query.vue || '') === 'file' ? 'file' : 'recherche'))
const currentVueLabel = computed(() => vuesReseau.find(v => v.value === currentVue.value)?.label || 'Cockpit réseau')

const ateliers = ref<any[]>([])
const entering = ref<string | number | null>(null)
const activeAtelierCookie = useCookie<string | null>('active_atelier_id', { default: () => null })
const cockpitOrigin = useCockpitOrigin()

async function loadAteliers() {
  try {
    const res = await api.get('/auth/rdv-ateliers')
    ateliers.value = Array.isArray(res) ? res : (res?.member || res?.['hydra:member'] || [])
  } catch {
    // Le cockpit reste utilisable sans la liste : la recherche est cross-atelier.
    ateliers.value = []
  }
}

/**
 * « Ouvrir » un atelier bascule le contexte actif puis entre dans l'app
 * d'atelier (52a). On mémorise d'où l'on vient pour afficher le bandeau de
 * retour, sinon rien ne distingue un SRC en visite d'un compte du site.
 */
async function enterAtelier(atelier: any) {
  entering.value = atelier.id
  try {
    const res = await api.post('/auth/switch-atelier', { atelier_id: String(atelier.id) })
    activeAtelierCookie.value = String(res?.active_atelier_id ?? atelier.id)
    cockpitOrigin.value = res?.atelier_nom || atelier.nom || ''
    await navigateTo('/')
  } catch (e: any) {
    toast.add({
      title: `Impossible d'ouvrir ${atelier.nom}`,
      description: e?.message
        ? `${e.message} — le cockpit reste ouvert, rien n'a changé de contexte.`
        : "Le serveur a refusé le changement d'atelier. Le cockpit reste ouvert, rien n'a changé de contexte.",
      color: 'error',
    })
  } finally {
    entering.value = null
  }
}

onMounted(loadAteliers)
</script>

<style scoped>
.cockpit-app {
  display: flex;
  height: 100vh;
  min-height: 600px;
}

/* === Nav réseau ===
   Noire dans les deux thèmes : c'est le repère qui dit « étage réseau », pas
   une surface qui suit la préférence d'affichage. */
.cockpit-nav {
  width: 224px;
  flex-shrink: 0;
  background: var(--mb-black);
  color: var(--mb-grey-200);
  display: flex;
  flex-direction: column;
  padding: 12px 0;
  overflow-y: auto;
}
.cockpit-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 12px 16px;
  padding: 8px 10px;
  border: 1px solid var(--mb-grey-900);
  border-radius: 8px;
}
.cockpit-brand-logo { width: 32px; height: 32px; display: block; flex: none; }
.cockpit-brand-text { display: flex; flex-direction: column; min-width: 0; }
.cockpit-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.14em; }
.cockpit-brand-sub { font-size: 11px; color: var(--mb-grey-600); }

.cockpit-group {
  padding: 14px 20px 6px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--mb-grey-700);
}
.cockpit-group:first-of-type { padding-top: 0; }

.cockpit-link {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 8px;
  padding: 8px 12px;
  min-height: 44px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 13px;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
  width: calc(100% - 16px);
}
.cockpit-link:hover { background: var(--mb-grey-900); }
.cockpit-link:focus-visible { outline: 2px solid var(--mb-accent); outline-offset: 2px; }
.cockpit-link:disabled { opacity: 0.5; cursor: progress; }
.cockpit-link.is-active {
  background: var(--mb-accent);
  color: var(--mb-black);
  font-weight: 600;
}
.cockpit-link-icon { font-size: 17px; color: var(--mb-grey-600); flex-shrink: 0; }
.cockpit-link.is-active .cockpit-link-icon { color: var(--mb-black); }
.cockpit-link-go { margin-left: auto; font-size: 15px; color: var(--mb-grey-700); }

.cockpit-spacer { flex: 1; }
.cockpit-note {
  margin: 0 12px 12px;
  padding: 12px;
  border: 1px solid var(--mb-grey-900);
  font-size: 12px;
  line-height: 1.45;
  color: var(--mb-grey-600);
}

.cockpit-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--surface-2);
}
.cockpit-topbar {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 24px;
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-2);
}
.cockpit-topbar-title { font-size: 15px; font-weight: 600; }
.cockpit-topbar-hint { font-size: 12px; color: var(--content-3); }
.cockpit-topbar-spacer { flex: 1; }
.cockpit-topbar-user { font-size: 12px; color: var(--content-3); }

.cockpit-content {
  flex: 1;
  overflow-y: auto;
  padding: 22px 24px;
}

@media (max-width: 1023px) {
  .cockpit-app { flex-direction: column; height: auto; }
  .cockpit-nav { width: 100%; }
}
</style>
