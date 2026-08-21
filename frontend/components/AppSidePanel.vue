<template>
  <Teleport to="body">
    <Transition name="pk-panel">
      <aside
        v-if="open"
        ref="panneau"
        class="pk-panel"
        role="dialog"
        :aria-modal="false"
        :aria-label="title"
        @keydown.esc.stop="fermer"
      >
        <header class="pk-panel-head">
          <AppIcon v-if="icon" :name="icon" class="pk-panel-icon" aria-hidden="true" />
          <div class="pk-panel-titles">
            <h2 class="pk-panel-title">{{ title }}</h2>
            <p v-if="subtitle" class="pk-panel-subtitle">{{ subtitle }}</p>
          </div>
          <button class="pk-panel-close" aria-label="Fermer le panneau" @click="fermer">
            <AppIcon name="i-ri-close-line" />
          </button>
        </header>

        <div class="pk-panel-body">
          <slot />
        </div>

        <footer v-if="$slots.footer" class="pk-panel-foot">
          <slot name="footer" />
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
/**
 * Panneau de travail — contrat `SidePanel` du design system Paddock.
 *
 * La règle 4 : « le poste de travail ne se quitte pas ». Réception,
 * restitution et détail d'un rendez-vous s'ouvrent ICI, à droite du planning,
 * qui reste lisible derrière. C'est ce qui distingue le panneau d'une modale :
 * il POUSSE le contenu au lieu de le voiler, et il n'y a ni fond assombri ni
 * flou — le design system les interdit tous les deux.
 *
 * Trois obligations du contrat, tenues ici :
 *  - à l'ouverture, le focus va au PREMIER CHAMP à remplir, pas au titre ;
 *  - Échap ferme et rend le focus à l'élément d'où l'on venait ;
 *  - l'entrée dure 180 ms, et rien d'autre dans le panneau ne s'anime.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  icon: { type: String, default: '' },
  title: { type: String, required: true },
  /** Véhicule · immat · client, sur une ligne. */
  subtitle: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const panneau = ref<HTMLElement | null>(null)
let origine: HTMLElement | null = null

function fermer() {
  emit('close')
}

/**
 * Le focus va au premier champ à remplir. À défaut de champ — un panneau de
 * lecture seule — il va au panneau lui-même, pour qu'Échap soit entendu.
 */
function poserLeFocus() {
  const el = panneau.value
  if (!el) return
  const premierChamp = el.querySelector<HTMLElement>(
    'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])',
  )
  if (premierChamp) {
    premierChamp.focus()
    return
  }
  el.setAttribute('tabindex', '-1')
  el.focus()
}

watch(() => props.open, async (ouvert) => {
  if (ouvert) {
    origine = (document.activeElement as HTMLElement) ?? null
    await nextTick()
    poserLeFocus()
  } else if (origine) {
    // Rendre le focus à la ligne d'où l'on venait, et pas au début de la page.
    origine.focus?.()
    origine = null
  }
})
</script>

<style scoped>
.pk-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 45;
  width: var(--pk-panel-width);
  max-width: 100vw;
  display: flex;
  flex-direction: column;
  background: var(--pk-surface);
  border-left: 1px solid var(--pk-border-control);
  color: var(--pk-ink);
  overflow: hidden;
}

.pk-panel-head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--pk-border);
}

.pk-panel-icon { font-size: 20px; color: var(--pk-accent); flex-shrink: 0; margin-top: 2px; }
.pk-panel-titles { flex: 1; min-width: 0; }

.pk-panel-title {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.15;
}

.pk-panel-subtitle {
  margin: 3px 0 0;
  font-size: 13px;
  color: var(--pk-ink-quiet);
}

.pk-panel-close {
  flex-shrink: 0;
  min-width: var(--pk-target-desk);
  min-height: var(--pk-target-desk);
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--pk-ink-muted);
  font-size: 22px;
  cursor: pointer;
}
.pk-panel-close:hover { background: var(--pk-neutral-surface); }
.pk-panel-close:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-panel-body { flex: 1; overflow-y: auto; }

.pk-panel-foot {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 16px 18px;
  border-top: 1px solid var(--pk-border);
}

/* 180 ms à l'entrée comme à la sortie, et rien d'autre ne bouge. */
.pk-panel-enter-active,
.pk-panel-leave-active {
  transition: transform var(--pk-duration-panel) var(--pk-easing);
}
.pk-panel-enter-from,
.pk-panel-leave-to {
  transform: translateX(100%);
}

@media (max-width: 640px) {
  .pk-panel { width: 100vw; border-left: none; }
}
</style>
