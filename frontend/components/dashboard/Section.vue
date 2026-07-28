<template>
  <UCard>
    <template #header>
      <!-- Bloc conteneur : sans lui, l'en-tête de UCard traite le sous-titre
           comme un élément de flex et le tronque au lieu de le passer à la
           ligne. -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-head-main">
            <span class="sec-title">{{ title }}</span>
            <span v-if="count !== undefined" class="sec-count">{{ count }}</span>
          </div>
          <div v-if="$slots.actions" class="sec-actions">
            <slot name="actions" />
          </div>
        </div>
        <p v-if="subtitle" class="sec-sub">{{ subtitle }}</p>
      </div>
    </template>
    <slot />
  </UCard>
</template>

<script setup lang="ts">
/**
 * En-tête de section normalisé. Avant, chaque bloc réécrivait son titre en
 * style inline (`font-size:15px;font-weight:700;color:#E8E9ED`) : quatorze
 * copies du même code, donc quatorze occasions de divergence.
 */
defineProps<{
  title: string
  subtitle?: string
  count?: number | string
}>()
</script>

<style scoped>
.sec { width: 100%; min-width: 0; }
.sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.sec-head-main {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sec-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}
.sec-count {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  color: var(--ink-body);
}
.sec-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sec-sub {
  margin: 6px 0 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--ink-muted);
  white-space: normal;
  overflow-wrap: break-word;
}
</style>
