<template>
  <div class="pk-state pk-state--permission" role="alert">
    <div class="pk-state-head">
      <AppIcon name="i-ri-lock-line" class="pk-state-icon" aria-hidden="true" />
      <div class="pk-state-title">{{ title }}</div>
    </div>

    <!-- 29g : dire le PLAFOND et à qui revient la décision au-delà. Un simple
         « accès refusé » laisse l'utilisateur sans recours ; ici les deux
         issues sont offertes — demander, ou rentrer dans le cadre. -->
    <p class="pk-state-text">{{ description }}</p>

    <div class="pk-state-actions">
      <button v-if="demandeLabel" class="btn btn-primary" @click="$emit('demander')">{{ demandeLabel }}</button>
      <button v-if="rentrerLabel" class="btn btn-ghost" @click="$emit('rentrer')">{{ rentrerLabel }}</button>
    </div>

    <p v-if="note" class="pk-state-note">{{ note }}</p>
  </div>
</template>

<script setup lang="ts">
/**
 * Action hors de vos droits — maquette 29g.
 *
 * Le refus ne se contente pas d'interdire : il nomme la limite (« vous pouvez
 * accorder jusqu'à 15 % »), désigne qui décide au-delà, et propose les deux
 * chemins. La note dit ce qui se passera si l'on demande — sans quoi
 * l'utilisateur ne sait pas s'il vient de déclencher quelque chose.
 */
defineEmits(['demander', 'rentrer'])

defineProps({
  title: { type: String, default: 'Cette action dépasse vos droits' },
  /** Le plafond, et à qui revient la décision au-delà. */
  description: { type: String, default: '' },
  /** Ex. « Demander à Pascal M. ». */
  demandeLabel: { type: String, default: '' },
  /** Ex. « Ramener à 15 % ». */
  rentrerLabel: { type: String, default: '' },
  /** Ce qui se passe si l'on demande. */
  note: { type: String, default: '' },
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

.pk-state--permission {
  background: var(--pk-warning-surface);
  border: 1px solid var(--pk-warning-line);
}

.pk-state-head { display: flex; align-items: center; gap: 10px; }
.pk-state-icon { font-size: 20px; flex-shrink: 0; color: var(--pk-warning-line); }
.pk-state-title { font-size: 15px; font-weight: 600; color: var(--pk-warning-ink); }

.pk-state-text {
  margin: 0;
  max-width: 62ch;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pk-warning-ink);
}

.pk-state-actions { display: flex; gap: var(--pk-target-gap); flex-wrap: wrap; margin-top: 4px; }
.pk-state-actions .btn { min-height: var(--pk-target-desk); }

.pk-state-note { margin: 4px 0 0; font-size: 12px; color: var(--pk-ink-quiet); }
</style>
