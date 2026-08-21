<template>
  <div class="pk-state pk-state--filter">
    <div class="pk-state-head">
      <AppIcon name="i-ri-filter-3-line" class="pk-state-icon" aria-hidden="true" />
      <div class="pk-state-title">{{ title }}</div>
    </div>

    <!-- Ce qui distingue ce gabarit du vide : la donnée EXISTE, ce sont les
         filtres qui la cachent. On dit donc combien il y en a, et ce qu'un
         retrait ferait réapparaître — chiffré, sinon c'est une devinette. -->
    <p class="pk-state-text">
      {{ nombreFiltres }} filtre{{ nombreFiltres > 1 ? 's sont actifs' : ' est actif' }}.
      <template v-if="suggestion">
        En retirant « {{ suggestion.filtre }} », {{ suggestion.nombre }} {{ suggestion.objet }} apparaîtrai{{ suggestion.nombre > 1 ? 'ent' : 't' }}.
      </template>
    </p>

    <div class="pk-state-actions">
      <button v-if="suggestion" class="btn btn-primary" @click="$emit('retirer', suggestion.filtre)">
        Retirer « {{ suggestion.filtre }} »
      </button>
      <button class="btn btn-ghost" @click="$emit('effacer')">Tout effacer</button>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Filtre sans résultat — maquette 29b.
 *
 * Un écran vide et un écran filtré à blanc se ressemblent, et ne veulent pas
 * dire la même chose : dans le second, la donnée est là. Le gabarit doit donc
 * nommer le nombre de filtres actifs et, quand on peut le calculer, ce qu'un
 * retrait précis ferait réapparaître. Sans ce chiffre, l'utilisateur retire
 * les filtres au hasard.
 */
defineEmits(['retirer', 'effacer'])

defineProps({
  title: { type: String, default: 'Aucun résultat ne correspond' },
  nombreFiltres: { type: Number, required: true },
  /**
   * Le retrait le plus rentable, calculé par l'écran appelant :
   * { filtre: 'Roubaix', nombre: 4, objet: 'devis critiques' }
   */
  suggestion: { type: Object, default: null },
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

.pk-state--filter {
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
}

.pk-state-head { display: flex; align-items: center; gap: 10px; }
.pk-state-icon { font-size: 20px; flex-shrink: 0; color: var(--pk-ink-muted); }
.pk-state-title { font-size: 15px; font-weight: 600; color: var(--pk-ink); }

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

.pk-state-actions .btn { min-height: var(--pk-target-desk); }
</style>
