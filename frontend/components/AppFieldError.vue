<template>
  <p v-if="message" class="pk-field-error" role="alert">
    <AppIcon name="i-ri-error-warning-line" class="pk-field-error-icon" aria-hidden="true" />
    <span>
      {{ message }}
      <!-- 29f : l'erreur nomme la VALEUR ATTENDUE, et laisse une porte de
           sortie légitime plutôt que de bloquer. Un compteur remplacé est un
           cas réel : refuser sèchement obligerait à saisir un faux nombre. -->
      <button v-if="issueLabel" type="button" class="pk-field-error-issue" @click="$emit('issue')">
        {{ issueLabel }}
      </button>
    </span>
  </p>
</template>

<script setup lang="ts">
/**
 * Erreur de saisie — maquette 29f.
 *
 * « Valeur invalide » ne dit rien. Le gabarit veut la valeur de référence et
 * sa date : « Inférieur au dernier relevé connu : 24 180 km en mars 2026. Un
 * compteur ne recule pas. » Et il veut l'issue : le cas légitime où la saisie
 * a raison contre la règle.
 */
defineEmits(['issue'])

defineProps({
  /** Ce qui ne va pas, avec la valeur attendue et son origine. */
  message: { type: String, default: '' },
  /** Le cas légitime qui contourne la règle. Ex. « Compteur remplacé ». */
  issueLabel: { type: String, default: '' },
})
</script>

<style scoped>
.pk-field-error {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 6px 0 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--pk-error-ink);
}

.pk-field-error-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }

.pk-field-error-issue {
  display: inline;
  margin-left: 6px;
  padding: 0;
  border: none;
  background: none;
  color: var(--pk-link);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}
.pk-field-error-issue:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
</style>
