<template>
  <div v-if="items.length" class="cats">
    <div v-for="item in items" :key="item.key" class="cat">
      <div class="cat-head">
        <span class="cat-swatch" :style="{ background: item.color }" aria-hidden="true" />
        <span class="cat-label">{{ item.label }}</span>
        <span class="cat-value">{{ item.display ?? item.value }}</span>
      </div>
      <div class="cat-track">
        <div class="cat-fill" :style="{ width: largeur(item.value) + '%', background: item.color }" />
      </div>
    </div>
  </div>

  <AppEmptyState
    v-else
    :icon="emptyIcon || '📊'"
    :title="emptyTitle || 'Aucune donnée'"
    :description="emptyDescription || 'Cette répartition se remplit dès que l’activité est enregistrée.'"
  />
</template>

<script setup lang="ts">
/**
 * Répartition par catégorie sous forme de barres horizontales.
 *
 * Remplace les camemberts de l'ancienne page : un anneau ne permet pas de
 * comparer des valeurs proches, et il en restait un à DEUX parts (main-d'œuvre
 * / pièces) là où deux chiffres suffisent.
 *
 * La couleur est fournie par l'appelant et suit l'ENTITÉ (web reste bleu même
 * si le téléphone passe devant) : jamais l'ordre d'affichage, sinon un filtre
 * repeint les survivants et trahit le lecteur qui avait appris la légende.
 */
const props = defineProps<{
  items: Array<{ key: string, label: string, value: number, display?: string, color: string }>
  emptyIcon?: string
  emptyTitle?: string
  emptyDescription?: string
}>()

const max = computed(() => Math.max(1, ...props.items.map(i => Number(i.value) || 0)))

function largeur(value: number): number {
  return Math.max(2, Math.min(100, Number(value) / max.value * 100))
}
</script>

<style scoped>
.cats { display: flex; flex-direction: column; gap: 12px; }
.cat-head {
  display: grid;
  grid-template-columns: 10px 1fr auto;
  align-items: center;
  gap: 10px;
  margin-bottom: 5px;
}
.cat-swatch { width: 10px; height: 10px; border-radius: 3px; }
.cat-label { font-size: 13px; color: var(--ink-body); }
.cat-value { font-size: 13px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.cat-track {
  height: 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  overflow: hidden;
}
.cat-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
</style>
