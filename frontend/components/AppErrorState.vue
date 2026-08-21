<template>
  <div class="pk-state pk-state--error" role="alert">
    <div class="pk-state-head">
      <AppIcon name="i-ri-error-warning-line" class="pk-state-icon" aria-hidden="true" />
      <div class="pk-state-title">{{ title }}</div>
    </div>

    <!-- La cause, puis la conséquence. La seconde rassure : elle dit ce qui
         n'a PAS été perdu, ce qui est la première question qu'on se pose. -->
    <p class="pk-state-text">
      {{ description }}
      <span v-if="consequence" class="pk-state-consequence">{{ consequence }}</span>
    </p>

    <div class="pk-state-actions">
      <button class="btn btn-primary" @click="$emit('retry')">{{ actionLabel }}</button>
      <button v-if="issueLabel" class="btn btn-ghost" @click="$emit('issue')">{{ issueLabel }}</button>
    </div>

    <!-- Le code n'est pas décoratif : il est là pour être lu au téléphone. -->
    <p v-if="code" class="pk-state-code">Erreur {{ code }}<span v-if="heure"> · {{ heure }}</span> · à donner au support</p>
  </div>
</template>

<script setup lang="ts">
/**
 * État d'erreur — maquette 29d.
 *
 * La règle 5 du design system : dire ce qui s'est passé, ce que ça empêche, et
 * la seule action qui sert — puis laisser une issue légitime plutôt que de
 * bloquer. D'où les deux boutons : réessayer, et le contournement qui permet
 * de travailler quand même (« Voir la feuille du jour »).
 *
 * `description` reste le premier paramètre pour ne casser aucun appel existant,
 * mais son défaut a changé : « une erreur est survenue » est explicitement
 * proscrit par le design system, et c'est ce que le repli affichait.
 */
defineEmits(['retry', 'issue'])

defineProps({
  title: { type: String, default: 'Chargement impossible' },
  /** La CAUSE : ce qui s'est passé, côté serveur ou réseau. */
  description: { type: String, default: "Le serveur n'a pas répondu." },
  /** La CONSÉQUENCE : ce qui n'a pas été perdu, ou ce que ça empêche. */
  consequence: { type: String, default: "Rien n'a été modifié." },
  actionLabel: { type: String, default: 'Réessayer' },
  /** L'issue légitime : le contournement qui permet de continuer sans le serveur. */
  issueLabel: { type: String, default: '' },
  /** Code technique, à lire au support. Ex. « PLN-503 ». */
  code: { type: String, default: '' },
  /** Horodatage de l'échec, en 14:52. */
  heure: { type: String, default: '' },
})
</script>

<style scoped>
/* Ni illustration ni centrage décoratif : le texte se lit en colonne, aligné
   à gauche, comme une phrase qu'on prononce au comptoir. */
.pk-state {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px;
  border-radius: var(--pk-radius-card);
  text-align: left;
}

.pk-state--error {
  background: var(--pk-error-surface);
  border: 1px solid var(--pk-error-line);
}

.pk-state-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pk-state-icon {
  font-size: 20px;
  flex-shrink: 0;
  color: var(--pk-error-line);
}

.pk-state-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--pk-error-ink);
}

.pk-state-text {
  margin: 0;
  max-width: 62ch;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pk-error-ink);
}

.pk-state-consequence {
  display: block;
  margin-top: 2px;
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

.pk-state-code {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--pk-ink-muted);
}
</style>
