<template>
  <div class="pk-state pk-state--empty">
    <div class="pk-state-head">
      <AppIcon :name="icon" class="pk-state-icon" aria-hidden="true" />
      <div class="pk-state-title">{{ title }}</div>
    </div>

    <!-- Le point de la maquette 29a : ne pas dire « c'est vide », dire PAR OÙ
         la donnée arrive. Un client se crée à la prise de rendez-vous, pas ici. -->
    <p v-if="description" class="pk-state-text">{{ description }}</p>

    <div v-if="actionLabel || secondaryLabel" class="pk-state-actions">
      <button v-if="actionLabel" class="btn btn-primary" @click="$emit('action')">{{ actionLabel }}</button>
      <button v-if="secondaryLabel" class="btn btn-ghost" @click="$emit('secondary')">{{ secondaryLabel }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * État vide — maquette 29a.
 *
 * « Aucune donnée disponible » ne rend service à personne : la question de
 * celui qui regarde un écran vide est « comment ça se remplit ». La
 * description doit donc nommer l'ORIGINE de la donnée, et les actions offrir
 * les deux chemins — le rapide (importer) et le manuel.
 *
 * Ni illustration décorative, ni centrage : le design system l'interdit.
 */
defineEmits(['action', 'secondary'])

defineProps({
  icon: { type: String, default: 'i-ri-inbox-line' },
  title: { type: String, default: 'Rien à afficher ici' },
  /** D'où vient la donnée, en une phrase. Pas « aucune donnée ». */
  description: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  /** Le second chemin. Ex. « Créer à la main » face à « Importer un fichier ». */
  secondaryLabel: { type: String, default: '' },
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

.pk-state--empty {
  background: var(--pk-surface);
  border: 1px dashed var(--pk-border-control);
}

.pk-state-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pk-state-icon {
  font-size: 20px;
  flex-shrink: 0;
  color: var(--pk-ink-muted);
}

.pk-state-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--pk-ink);
}

.pk-state-text {
  margin: 0;
  max-width: 62ch;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pk-ink-quiet);
}

.pk-state-actions {
  display: flex;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
  margin-top: 4px;
}

.pk-state-actions .btn {
  min-height: var(--pk-target-desk);
}
</style>
