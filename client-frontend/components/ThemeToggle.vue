<template>
  <button
    type="button"
    class="theme-toggle"
    :class="{ 'theme-toggle--floating': floating }"
    :aria-label="label"
    :title="label"
    @click="toggle"
  >
    <!-- Icônes tracées dans un carré de 24, trait de 2, couleur héritée :
         convention d'iconographie du design system. Aucun emoji. -->
    <svg v-if="isDark" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4" />
    </svg>
    <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" />
    </svg>
  </button>
</template>

<script setup lang="ts">
const props = defineProps<{
  /** Positionne le bouton en coin d'écran, pour les pages sans barre de navigation. */
  floating?: boolean
}>()

const { isDark, toggle } = useTheme()

const label = computed(() =>
  isDark.value ? 'Passer en thème clair' : 'Passer en thème sombre',
)

const floating = computed(() => props.floating ?? false)
</script>

<style scoped>
.theme-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  border: var(--bw) solid var(--border-2);
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--content-2);
  cursor: pointer;
  transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease),
    border-color var(--dur-fast) var(--ease);
}

.theme-toggle:hover {
  background: var(--overlay-hover);
  border-color: var(--border-1);
  color: var(--content-1);
}

.theme-toggle:focus-visible {
  outline: var(--bw-2) solid var(--accent);
  outline-offset: 2px;
}

/* Pages sans barre de navigation (connexion, mot de passe oublié) : le réglage
   doit rester accessible avant l'authentification. */
.theme-toggle--floating {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 60;
  background: var(--surface-1);
}
</style>
