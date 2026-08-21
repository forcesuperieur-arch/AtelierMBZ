<template>
  <div class="pk-state pk-state--clear">
    <div class="pk-state-head">
      <AppIcon name="i-ri-checkbox-circle-fill" class="pk-state-icon" aria-hidden="true" />
      <div class="pk-state-title">{{ title }}</div>
    </div>

    <!-- 29h : « un vide obtenu se félicite ; il ne se dessine pas comme un
         manque. » D'où le vert et non le gris, et l'énumération de ce qui a
         été traité plutôt que de ce qui manque. -->
    <p class="pk-state-text">
      {{ description }}
      <span v-if="dernierTraitement" class="pk-state-quiet">Le dernier point a été traité à {{ dernierTraitement }}.</span>
    </p>

    <div v-if="actionLabel" class="pk-state-actions">
      <button class="btn btn-ghost" @click="$emit('action')">{{ actionLabel }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
/** Rien à traiter — maquette 29h. Une file vide est un résultat, pas une panne. */
defineEmits(['action'])

defineProps({
  title: { type: String, default: 'Plus rien en attente' },
  description: { type: String, default: '' },
  /** Heure du dernier élément traité, en 14:18. */
  dernierTraitement: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
})
</script>

<style scoped>
.pk-state {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px;
  border-radius: var(--pk-radius-card);
  text-align: left;
}

.pk-state--clear {
  background: var(--pk-success-surface);
  border: 1px solid var(--pk-success-line);
}

.pk-state-head { display: flex; align-items: center; gap: 10px; }
.pk-state-icon { font-size: 20px; flex-shrink: 0; color: var(--pk-success-line); }
.pk-state-title { font-size: 15px; font-weight: 600; color: var(--pk-success-ink); }

.pk-state-text {
  margin: 0;
  max-width: 62ch;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pk-success-ink);
}

.pk-state-quiet { display: block; margin-top: 2px; color: var(--pk-ink-quiet); }

.pk-state-actions { display: flex; gap: var(--pk-target-gap); margin-top: 4px; }
.pk-state-actions .btn { min-height: var(--pk-target-desk); }
</style>
