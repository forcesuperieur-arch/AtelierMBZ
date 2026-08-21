<template>
  <div v-if="horsLigne" class="pk-offline" role="status">
    <div class="pk-offline-main">
      <AppIcon name="i-ri-cloud-off-line" class="pk-offline-icon" aria-hidden="true" />
      <div>
        <div class="pk-offline-title">Hors ligne depuis {{ depuis }}</div>
        <!-- 29e : on annonce d'abord ce qui MARCHE ENCORE. Un bandeau qui ne
             dit que la panne fait arrêter le travail sans raison. -->
        <div class="pk-offline-text">Vous pouvez continuer à {{ actionsPossibles }}</div>
      </div>
      <button class="pk-offline-toggle" :aria-expanded="ouvert" @click="ouvert = !ouvert">
        {{ ouvert ? 'Masquer' : 'Détails' }}
      </button>
    </div>

    <div v-if="ouvert" class="pk-offline-detail">
      <div v-if="enAttente" class="pk-offline-block">
        <div class="pk-offline-label">{{ enAttente }} action{{ enAttente > 1 ? 's' : '' }} en attente d'envoi</div>
        <div v-if="detailAttente" class="pk-offline-text">{{ detailAttente }}</div>
      </div>
      <div v-if="indisponible" class="pk-offline-block">
        <div class="pk-offline-label">Indisponible hors ligne</div>
        <div class="pk-offline-text">{{ indisponible }}</div>
      </div>
      <p class="pk-offline-text pk-offline-reassure">
        Tout repart automatiquement au retour du réseau. Aucune saisie n'est perdue.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Bandeau réseau coupé — maquette 29e.
 *
 * Le poste d'atelier travaille dans des zones sans couverture. La règle 5
 * s'applique en grand : dire ce qui s'est passé, ce que ça empêche — et
 * surtout ce que ça n'empêche PAS, puisque pointer et réceptionner restent
 * possibles hors ligne. Le bandeau est replié par défaut : il informe sans
 * manger la hauteur d'un écran tactile.
 */
const ouvert = ref(false)

defineProps({
  horsLigne: { type: Boolean, default: false },
  /** Durée écoulée, en clair. Ex. « 2 min ». */
  depuis: { type: String, default: 'quelques instants' },
  /** Ce qui reste faisable. Ex. « pointer et à réceptionner ». */
  actionsPossibles: { type: String, default: 'travailler' },
  /** Nombre d'actions en file d'envoi. */
  enAttente: { type: Number, default: 0 },
  /** Détail de la file. Ex. « Pointages et 1 réception · signature capturée ». */
  detailAttente: { type: String, default: '' },
  /** Ce qui ne marche pas. Ex. « Envoi de SMS, encaissement, aperçu PDF ». */
  indisponible: { type: String, default: '' },
})
</script>

<style scoped>
.pk-offline {
  background: var(--pk-warning-surface);
  border-bottom: 1px solid var(--pk-warning-line);
  color: var(--pk-warning-ink);
}

.pk-offline-main {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 20px;
}

.pk-offline-icon { font-size: 20px; flex-shrink: 0; }
.pk-offline-title { font-size: 13px; font-weight: 600; }
.pk-offline-text { font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }

.pk-offline-toggle {
  margin-left: auto;
  min-height: var(--pk-target-desk);
  padding: 0 12px;
  border: 1px solid var(--pk-warning-line);
  border-radius: var(--pk-radius-pill);
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.pk-offline-toggle:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-offline-detail {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0 20px 12px 52px;
}

.pk-offline-label { font-size: 12px; font-weight: 600; }
.pk-offline-reassure { margin: 0; }
</style>
