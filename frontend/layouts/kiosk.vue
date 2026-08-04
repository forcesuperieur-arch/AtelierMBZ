<template>
  <div class="kiosk">
    <header class="kiosk-topbar">
      <div class="kiosk-brand">
        <img v-if="atelierLogoUrl" :src="atelierLogoUrl" :alt="atelierName" class="kiosk-brand-logo" />
        <span v-else class="kiosk-brand-fallback">{{ atelierInitial }}</span>
        <span class="kiosk-brand-name">{{ atelierName }}</span>
      </div>
      <div class="kiosk-topbar-spacer" />
      <AppThemeToggle />
      <button class="kiosk-logout" aria-label="Déconnexion" @click="auth.logout()">
        <AppIcon name="i-ri-shut-down-line" />
        <span>Déconnexion</span>
      </button>
    </header>

    <main class="kiosk-content">
      <slot />
    </main>

    <UToaster />
  </div>
  <RdvDetailModal />
</template>

<script setup lang="ts">
const auth = useAuth()
const atelierStore = useAtelierStore()

const atelierName = computed(() => atelierStore.branding?.nom || 'Paddock')
const atelierLogoUrl = computed(() => atelierStore.branding?.logo_url || '/branding/paddock-logo-symbol.svg')
const atelierInitial = computed(() => atelierName.value.trim().charAt(0).toUpperCase() || 'P')
</script>

<style scoped>
/* Layout dédié à l'usage tactile en atelier (iPad posé sur le plan de travail,
   mains parfois gantées). Pas de sidebar : contrairement à `default.vue`, elle
   se déployait au survol souris — un geste qui n'existe pas au doigt. Le
   mécanicien n'a qu'un seul écran à voir, donc pas besoin de menu. */
.kiosk {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--surface-0);
}

.kiosk-topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  padding: 10px max(16px, env(safe-area-inset-right)) 10px max(16px, env(safe-area-inset-left));
  padding-top: max(10px, env(safe-area-inset-top));
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-2);
  flex-shrink: 0;
}

.kiosk-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.kiosk-brand-logo,
.kiosk-brand-fallback {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: contain;
  background: var(--surface-2);
  flex-shrink: 0;
}

.kiosk-brand-fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  color: var(--content-1);
}

.kiosk-brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--content-1);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kiosk-topbar-spacer {
  flex: 1;
}

.kiosk-logout {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-control);
  background: transparent;
  color: var(--content-2);
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.kiosk-logout:hover {
  background: var(--overlay-hover);
}

.kiosk-content {
  flex: 1;
  overflow-y: auto;
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 16px max(16px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
}

@media (min-width: 700px) {
  .kiosk-content {
    padding: 20px 24px 32px;
  }
}
</style>
