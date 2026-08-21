<template>
  <div class="pk-skeleton" role="status" :aria-label="title">
    <!-- 29c : « la forme du tableau est déjà là : la page ne saute pas à
         l'arrivée des données. » On dessine donc la STRUCTURE attendue, pas un
         disque qui tourne au milieu du vide. -->
    <div v-if="!compact" class="pk-skeleton-head">
      <span v-for="c in colonnes" :key="`h-${c}`" class="pk-skeleton-cell pk-skeleton-cell--head" />
    </div>
    <div v-for="l in lignes" :key="`l-${l}`" class="pk-skeleton-row">
      <span v-for="c in colonnes" :key="`c-${l}-${c}`" class="pk-skeleton-cell" />
    </div>
    <span class="sr-only">{{ title }}</span>
  </div>
</template>

<script setup lang="ts">
/**
 * Chargement d'une liste — maquette 29c.
 *
 * Le design system interdit ce qui clignote et ce qui rebondit : un spinner
 * n'apprend rien et déplace la mise en page à l'arrivée des données. Le
 * squelette, lui, réserve exactement la place que le tableau prendra.
 *
 * `title` et `description` restent acceptés — 28 appels les passent — mais
 * seul `title` sert encore, comme étiquette pour les lecteurs d'écran.
 */
defineProps({
  title: { type: String, default: 'Chargement en cours' },
  description: { type: String, default: '' },
  compact: { type: Boolean, default: false },
  /** Nombre de colonnes du tableau attendu. */
  colonnes: { type: Number, default: 5 },
  /** Nombre de lignes à réserver. */
  lignes: { type: Number, default: 6 },
})
</script>

<style scoped>
.pk-skeleton {
  display: flex;
  flex-direction: column;
  gap: 1px;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
  background: var(--pk-border-quiet);
}

.pk-skeleton-head,
.pk-skeleton-row {
  display: flex;
  gap: 16px;
  padding: 14px 16px;
  background: var(--pk-surface);
}

.pk-skeleton-head { background: var(--pk-surface-raised); }

.pk-skeleton-cell {
  flex: 1;
  height: 10px;
  border-radius: var(--pk-radius-block);
  background: var(--pk-neutral-surface);
}

.pk-skeleton-cell--head { height: 8px; max-width: 90px; }
.pk-skeleton-cell:first-child { flex: 1.6; }
.pk-skeleton-cell:last-child { flex: 0.6; }

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
