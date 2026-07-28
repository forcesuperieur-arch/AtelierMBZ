<template>
  <div class="facettes">
    <section v-for="groupe in groupes" :key="groupe.key" class="facette">
      <header class="facette-tete">
        <span class="facette-titre">{{ store.libelleAxe(groupe.key) }}</span>
        <button
          v-if="store.filtres.find(f => f.field === groupe.key)"
          type="button"
          class="facette-reset"
          @click="store.retirerFiltre(groupe.key); $emit('change')"
        >
          effacer
        </button>
      </header>
      <ul class="facette-liste">
        <li v-for="valeur in groupe.valeurs" :key="valeur.valeur">
          <button
            type="button"
            class="facette-valeur"
            :class="{ 'facette-valeur--on': store.estSelectionnee(groupe.key, valeur.valeur) }"
            :aria-pressed="store.estSelectionnee(groupe.key, valeur.valeur)"
            @click="store.basculer(groupe.key, valeur.valeur); $emit('change')"
          >
            <span class="facette-nom">{{ valeur.valeur || '(non renseigné)' }}</span>
            <span class="facette-nb">{{ valeur.nb }}</span>
          </button>
        </li>
        <li v-if="!groupe.valeurs.length" class="facette-aucune">aucune valeur ici</li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Panneau de filtres. Ne propose que les valeurs **encore présentes dans la
 * sélection courante**, avec leur volume : c'est ce qui évite de cliquer vers
 * un écran vide, et ce qui rend visible l'effet d'une sélection sur le reste.
 */
const store = useExplorerStore()

defineEmits<{ change: [] }>()

/** Ordre de lecture : d'abord ce qu'on filtre le plus souvent à l'atelier. */
const ORDRE = ['type_intervention', 'mecanicien_nom', 'statut_rdv', 'origine', 'vehicule_marque', 'pont_nom', 'client_segment', 'litige', 'travaux_comp']

const groupes = computed(() => ORDRE
  .filter(key => (store.facettes[key] ?? []).length > 0)
  .map(key => ({
    key,
    // Une seule valeur possible n'apporte aucun choix : on masque le groupe,
    // sauf s'il porte déjà un filtre (il faut pouvoir l'enlever).
    valeurs: (store.facettes[key] ?? []).slice(0, 12),
  }))
  .filter(g => g.valeurs.length > 1 || store.filtres.some(f => f.field === g.key)))
</script>

<style scoped>
.facettes {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}
.facette { min-width: 0; }
.facette-tete {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
}
.facette-titre {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.facette-reset {
  border: 0;
  background: transparent;
  color: var(--orange);
  font-family: inherit;
  font-size: 11px;
  cursor: pointer;
  padding: 0;
}
.facette-liste {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 190px;
  overflow-y: auto;
}
.facette-valeur {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  min-height: 26px;
  padding: 4px 8px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  text-align: left;
  cursor: pointer;
}
.facette-valeur:hover { background: rgba(255, 255, 255, 0.05); }
.facette-valeur:focus-visible { outline: 2px solid var(--orange); outline-offset: 1px; }
.facette-valeur--on {
  border-color: rgba(255, 210, 0, 0.4);
  background: rgba(255, 210, 0, 0.1);
  color: var(--orange);
}
.facette-nom { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.facette-nb { font-variant-numeric: tabular-nums; color: var(--ink-muted); font-size: 11px; flex: 0 0 auto; }
.facette-valeur--on .facette-nb { color: var(--orange); }
.facette-aucune { font-size: 11px; color: var(--ink-muted); padding: 4px 8px; }
</style>
