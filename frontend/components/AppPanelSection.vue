<template>
  <section class="pk-panel-section">
    <header v-if="label || aside" class="pk-panel-section-head">
      <span v-if="label" class="pk-panel-section-label">{{ label }}</span>
      <span v-if="aside" class="pk-panel-section-aside">{{ aside }}</span>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
/**
 * Section d'un panneau de travail — contrat `PanelSection` du design system.
 *
 * Le surtitre est en capitales : c'est le seul endroit, avec les mots de
 * statut, où le design system les autorise. `aside` porte la valeur secondaire
 * alignée à droite sur la ligne du libellé — un total, un compte, une date.
 */
defineProps({
  label: { type: String, default: '' },
  aside: { type: String, default: '' },
})
</script>

<style scoped>
.pk-panel-section {
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--pk-border-quiet);
}

.pk-panel-section-head {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.pk-panel-section-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

.pk-panel-section-aside {
  margin-left: auto;
  font-size: 13px;
  color: var(--pk-ink-quiet);
}
</style>
