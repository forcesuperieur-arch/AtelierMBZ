<template>
  <!-- Aucun axe : la sélection se résume à une ligne de chiffres. -->
  <div v-if="!axes.length" class="somme">
    <div v-for="m in mesures" :key="m" class="somme-item">
      <div class="somme-label">{{ store.mesure(m)?.libelle ?? m }}</div>
      <div class="somme-valeur">{{ format(total[m], m) }}</div>
    </div>
  </div>

  <!-- Un axe : barres horizontales, la longueur porte la première mesure. -->
  <div v-else-if="axes.length === 1" class="barres">
    <div
      v-for="row in rows"
      :key="row.d0"
      class="barre"
      :class="{ 'barre--on': store.estSelectionnee(axes[0], row.d0) }"
      role="button"
      tabindex="0"
      :aria-pressed="store.estSelectionnee(axes[0], row.d0)"
      @click="$emit('selectionner', axes[0], row.d0)"
      @keydown.enter.prevent="$emit('selectionner', axes[0], row.d0)"
      @keydown.space.prevent="$emit('selectionner', axes[0], row.d0)"
    >
      <div class="barre-tete">
        <span class="barre-label">{{ row.d0 || '(non renseigné)' }}</span>
        <span class="barre-mesures">
          <span v-for="m in mesures" :key="m" class="barre-mesure">
            <span class="barre-mesure-label">{{ store.mesure(m)?.libelle ?? m }}</span>
            <strong :class="tonClasse(m, row[m])">{{ format(row[m], m) }}</strong>
          </span>
        </span>
      </div>
      <div class="barre-piste">
        <div class="barre-jauge" :style="{ width: largeur(row) + '%' }" />
      </div>
    </div>
    <p v-if="!rows.length" class="vide">Aucun rendez-vous ne correspond à cette sélection.</p>
  </div>

  <!-- Deux axes : tableau croisé. Les en-têtes de ligne et de colonne filtrent. -->
  <div v-else class="croise-wrap">
    <table class="croise">
      <thead>
        <tr>
          <th class="croise-coin">
            {{ store.libelleAxe(axes[0]) }} <span class="croise-sep">/</span> {{ store.libelleAxe(axes[1]) }}
          </th>
          <th
            v-for="col in colonnes"
            :key="col"
            class="croise-col"
            :class="{ 'croise-on': store.estSelectionnee(axes[1], col) }"
            @click="$emit('selectionner', axes[1], col)"
          >
            {{ col || '(non renseigné)' }}
          </th>
          <th class="croise-total">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ligne in lignes" :key="ligne">
          <th
            class="croise-ligne"
            :class="{ 'croise-on': store.estSelectionnee(axes[0], ligne) }"
            @click="$emit('selectionner', axes[0], ligne)"
          >
            {{ ligne || '(non renseigné)' }}
          </th>
          <td
            v-for="col in colonnes"
            :key="col"
            class="croise-cell"
            :class="{ 'croise-cell--vide': !cellule(ligne, col) }"
          >
            <template v-if="cellule(ligne, col)">
              <span class="croise-valeur" :class="tonClasse(mesurePrincipale, cellule(ligne, col)[mesurePrincipale])">
                {{ format(cellule(ligne, col)[mesurePrincipale], mesurePrincipale) }}
              </span>
              <span v-if="mesurePrincipale !== 'count'" class="croise-appui">{{ cellule(ligne, col).count }} RDV</span>
            </template>
            <span v-else class="croise-tiret">—</span>
          </td>
          <td class="croise-cell croise-total">
            <span class="croise-valeur">{{ format(totalLigne(ligne), mesurePrincipale) }}</span>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="!rows.length" class="vide">Aucun rendez-vous ne correspond à cette sélection.</p>
    <p v-else class="croise-note">
      Cellule vide = aucun rendez-vous dans ce croisement. Clique un en-tête pour filtrer dessus.
    </p>
  </div>
</template>

<script setup lang="ts">
/**
 * Rendu de la sélection. Trois formes selon le nombre d'axes, mais un seul
 * geste : cliquer filtre. Le tableau croisé s'arrête à deux axes — au-delà,
 * une table devient illisible et la cardinalité explose.
 */
const props = defineProps<{
  rows: any[]
  total: Record<string, any>
  axes: string[]
  mesures: string[]
}>()

defineEmits<{ selectionner: [field: string, valeur: string] }>()

const store = useExplorerStore()
const { formatMinutes, formatEuro, formatNombre, formatPourcent } = useDashboardFormat()

const mesurePrincipale = computed(() => props.mesures[0] ?? 'count')

/** Chaque mesure s'affiche dans son unité — une minute n'est pas un euro. */
function format(valeur: any, cle: string): string {
  if (valeur === null || valeur === undefined) return '—'
  switch (store.mesure(cle)?.unite) {
    case 'minutes': {
      const n = Number(valeur)
      // Un écart négatif veut dire « plus rapide que prévu » : le signe compte.
      return (n < 0 ? '− ' : '') + formatMinutes(Math.abs(n))
    }
    case 'pourcent': return formatPourcent(valeur)
    case 'euros': return formatEuro(valeur)
    case 'decimal': return String(valeur).replace('.', ',')
    default: return formatNombre(valeur)
  }
}

/** Coloration seulement quand la mesure a un sens favorable connu. */
function tonClasse(cle: string, valeur: any): string {
  const def = store.mesure(cle)
  if (!def || def.bon === 'neutre' || valeur === null || valeur === undefined) return ''
  const n = Number(valeur)
  if (!Number.isFinite(n) || n === 0) return ''
  if (def.bon === 'bas') return n > 0 ? 'val--attention' : 'val--bon'
  return ''
}

const maxPrincipale = computed(() => Math.max(
  1,
  ...props.rows.map(r => Math.abs(Number(r[mesurePrincipale.value]) || 0)),
))

function largeur(row: any): number {
  return Math.max(1.5, Math.min(100, Math.abs(Number(row[mesurePrincipale.value]) || 0) / maxPrincipale.value * 100))
}

// ── Tableau croisé : on pivote les lignes plates renvoyées par l'API ──
const lignes = computed(() => [...new Set(props.rows.map(r => r.d0))])
const colonnes = computed(() => {
  const compte = new Map<string, number>()
  for (const r of props.rows) {
    compte.set(r.d1, (compte.get(r.d1) ?? 0) + Number(r.count ?? 0))
  }
  // Colonnes ordonnées par volume, bornées : au-delà de 12 le tableau déborde.
  return [...compte.entries()].sort((a, b) => b[1] - a[1]).slice(0, 12).map(([v]) => v)
})

const index = computed(() => {
  const map = new Map<string, any>()
  for (const r of props.rows) map.set(`${r.d0}||${r.d1}`, r)
  return map
})

function cellule(ligne: string, col: string) {
  return index.value.get(`${ligne}||${col}`)
}

/**
 * Total de ligne : on additionne ce qui s'additionne. Une moyenne ou un
 * pourcentage ne se somment pas — dans ce cas on affiche un tiret plutôt qu'un
 * nombre faux.
 */
function totalLigne(ligne: string): number | null {
  const cle = mesurePrincipale.value
  const unite = store.mesure(cle)?.unite
  const sommable = cle === 'count' || cle.endsWith('_total') || unite === 'euros'
  if (!sommable) return null
  return props.rows
    .filter(r => r.d0 === ligne)
    .reduce((somme, r) => somme + (Number(r[cle]) || 0), 0)
}
</script>

<style scoped>
.somme {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.somme-item {
  padding: 14px 16px;
  border-radius: var(--radius);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
}
.somme-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-muted); font-weight: 700; }
.somme-valeur { margin-top: 6px; font-size: 24px; font-weight: 800; color: var(--ink); }

.barres { display: flex; flex-direction: column; gap: 8px; }
.barre {
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
}
.barre:hover { border-color: var(--border-hover); }
.barre:focus-visible { outline: 2px solid var(--orange); outline-offset: 2px; }
.barre--on { border-color: var(--accent-graphic); background: var(--accent-soft); }
.barre-tete {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 7px;
}
.barre-label { font-size: 13px; font-weight: 600; color: var(--ink); }
.barre-mesures { display: flex; flex-wrap: wrap; gap: 14px; }
.barre-mesure { display: inline-flex; align-items: baseline; gap: 5px; font-size: 12px; }
.barre-mesure-label { color: var(--ink-muted); }
.barre-mesure strong { color: var(--ink); font-variant-numeric: tabular-nums; }
.barre-piste { height: 6px; border-radius: 999px; background: var(--overlay-hover); overflow: hidden; }
.barre-jauge { height: 100%; border-radius: 999px; background: var(--viz-1); transition: width 0.3s ease; }
.barre--on .barre-jauge { background: var(--ramp-2); }

.croise-wrap { overflow-x: auto; }
.croise { width: 100%; border-collapse: separate; border-spacing: 0 2px; font-size: 13px; }
.croise-coin {
  text-align: left;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--ink-muted);
  padding: 6px 10px;
  white-space: nowrap;
}
.croise-sep { color: var(--ink-muted); opacity: 0.6; }
.croise-col, .croise-ligne {
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-body);
  cursor: pointer;
  white-space: nowrap;
  border-radius: var(--radius-sm);
  transition: color var(--transition), background var(--transition);
}
.croise-col { text-align: right; }
.croise-ligne { text-align: left; background: var(--glass-bg); }
.croise-col:hover, .croise-ligne:hover { color: var(--ink); background: var(--overlay-hover); }
.croise-on { color: var(--accent-content); background: var(--accent-soft); }
.croise-cell {
  padding: 7px 10px;
  text-align: right;
  background: var(--glass-bg);
  white-space: nowrap;
}
.croise-cell--vide { background: transparent; }
.croise-valeur { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.croise-appui { display: block; font-size: 10px; color: var(--ink-muted); margin-top: 1px; }
.croise-tiret { color: var(--ink-muted); opacity: 0.5; }
.croise-total { font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; color: var(--ink-muted); }
.croise-note { margin: 10px 0 0; font-size: 11px; color: var(--ink-muted); }

.val--attention { color: var(--warning-content); }
.val--bon { color: var(--success-content); }
.vide { margin: 4px 0 0; font-size: 13px; color: var(--ink-muted); }
</style>
