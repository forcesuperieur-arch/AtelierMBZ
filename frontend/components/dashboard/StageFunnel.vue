<template>
  <div v-if="total > 0">
    <!-- Barre empilée dans l'ORDRE DU FLUX, pas dans l'ordre des volumes :
         le statut d'un dossier est une position dans un parcours, donc une
         donnée ordinale → une seule teinte, du clair au sombre. L'ancien
         camembert distribuait 10 couleurs sans lien entre elles sur une
         donnée qui a un ordre naturel. -->
    <div class="funnel-bar" role="img" :aria-label="resumeAccessible">
      <div
        v-for="(stage, i) in stagesPresents"
        :key="stage.key"
        class="funnel-seg"
        :style="{ width: pourcent(stage.count) + '%', background: `var(--ramp-${i + 1})` }"
      />
    </div>

    <ul class="funnel-legend">
      <li v-for="(stage, i) in stagesPresents" :key="stage.key" class="funnel-item">
        <span class="funnel-swatch" :style="{ background: `var(--ramp-${i + 1})` }" aria-hidden="true" />
        <span class="funnel-label">{{ stage.label }}</span>
        <span class="funnel-count">{{ stage.count }}</span>
        <span class="funnel-pct">{{ Math.round(pourcent(stage.count)) }} %</span>
      </li>
    </ul>
  </div>

  <AppEmptyState
    v-else
    icon="📋"
    title="Aucun dossier actif"
    description="La répartition apparaît dès qu'un rendez-vous est en cours de traitement."
  />
</template>

<script setup lang="ts">
/**
 * Répartition des dossiers actifs par ÉTAPE du parcours atelier.
 *
 * Les dix statuts techniques sont regroupés en cinq étapes métier : c'est la
 * limite de la rampe ordinale validée (au sixième pas, la teinte la plus sombre
 * passe sous 2:1 de contraste et disparaît du fond), et c'est aussi la façon
 * dont un chef d'atelier raisonne.
 */
const props = defineProps<{ rows: Array<{ statut?: string, count?: number | string }> }>()

const ETAPES = [
  { key: 'a_planifier', label: 'À planifier', statuts: ['en_attente'] },
  { key: 'planifie', label: 'Planifié', statuts: ['reserve', 'confirme'] },
  { key: 'a_latelier', label: 'À l\'atelier', statuts: ['reception', 'gardiennage'] },
  { key: 'en_travaux', label: 'En travaux', statuts: ['en_cours'] },
  { key: 'a_rendre', label: 'À rendre / clôturé', statuts: ['termine', 'restitue', 'restitue_partiel', 'facture', 'paye'] },
]

const stages = computed(() => ETAPES.map(etape => ({
  ...etape,
  count: props.rows
    .filter(r => etape.statuts.includes(String(r.statut ?? '')))
    .reduce((sum, r) => sum + Number(r.count ?? 0), 0),
})))

const stagesPresents = computed(() => stages.value.filter(s => s.count > 0))
const total = computed(() => stages.value.reduce((sum, s) => sum + s.count, 0))

function pourcent(count: number): number {
  return total.value ? count / total.value * 100 : 0
}

const resumeAccessible = computed(() =>
  stagesPresents.value.map(s => `${s.label} : ${s.count}`).join(', '))
</script>

<style scoped>
.funnel-bar {
  display: flex;
  /* Le séparateur entre segments est un vide de la surface, pas une bordure
     dessinée autour des marques. */
  gap: 2px;
  height: 14px;
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 14px;
}
.funnel-seg { min-width: 3px; }

.funnel-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 7px;
}
.funnel-item {
  display: grid;
  grid-template-columns: 10px 1fr auto auto;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}
.funnel-swatch { width: 10px; height: 10px; border-radius: 3px; }
.funnel-label { color: var(--ink-body); }
.funnel-count { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.funnel-pct { color: var(--ink-muted); font-size: 12px; min-width: 42px; text-align: right; font-variant-numeric: tabular-nums; }
</style>
