<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <div class="page-title">Véhicules d'occasion</div>
        <div class="vo-subtitle">{{ resume }}</div>
      </div>
      <div class="vo-header-actions">
        <NuxtLink to="/vo/depots/new" class="btn vo-secondary-btn">+ Dépôt-vente</NuxtLink>
        <NuxtLink to="/vo/rachats/new" class="btn btn-primary">+ Nouveau rachat</NuxtLink>
      </div>
    </div>

    <div class="vo-stats-grid">
      <StatsCard title="Immobilisé" :value="formatPrice(immobilise)" />
      <StatsCard title="Stock moyen" :value="`${stockMoyen} j`" />
      <StatsCard title="Vendus ce mois" :value="stats?.vendus ?? 0" />
      <StatsCard title="Dossiers bloqués" :value="bloques.length" :color="bloques.length > 0 ? 'warning' : 'primary'" />
    </div>

    <div class="vo-layout">
      <UCard>
        <div class="vo-filters">
          <UInput v-model="recherche" placeholder="Marque, modèle, plaque, déposant…" />
          <select v-model="origine" class="vo-select" aria-label="Origine du véhicule">
            <option value="all">Rachats et dépôts</option>
            <option value="purchase">Rachats</option>
            <option value="depot">Dépôts</option>
          </select>
        </div>

        <div class="vo-chip-row">
          <button
            v-for="chip in chips"
            :key="chip.value"
            type="button"
            class="vo-chip"
            :class="{ 'is-active': filtre === chip.value }"
            @click="filtre = chip.value"
          >
            <span>{{ chip.label }}</span>
            <strong>{{ chip.count }}</strong>
          </button>
          <NuxtLink to="/vo/livre-police" class="vo-chip vo-chip-link">Livre de police</NuxtLink>
        </div>

        <AppLoadingState
          v-if="voStore.loading && !lignes.length"
          title="Chargement du stock VO"
          description="Rachats et dépôts sont récupérés ensemble."
        />

        <div v-else-if="!lignesFiltrees.length" class="vo-empty-state">
          <strong>Aucun véhicule ne correspond à ces filtres.</strong>
          <span>Élargis la recherche, ou ouvre un dossier de rachat ou de dépôt-vente pour alimenter le stock.</span>
        </div>

        <table v-else class="vo-table">
          <thead>
            <tr>
              <th>Véhicule</th>
              <th>Origine</th>
              <th>Jours de stock</th>
              <th>Ce qui bloque</th>
              <th class="vo-num">Prix de vente</th>
              <th class="vo-num">Marge</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="ligne in lignesFiltrees" :key="`${ligne.source}-${ligne.id}`">
              <td>
                <div class="vo-vehicule">{{ ligne.marque || 'VO' }} {{ ligne.modele || '' }}<span v-if="ligne.annee"> · {{ ligne.annee }}</span></div>
                <div class="vo-vehicule-meta">{{ ligne.plaque || 'Sans plaque' }}<span v-if="ligne.km"> · {{ formatKm(ligne.km) }}</span></div>
              </td>
              <td>{{ ligne.source === 'purchase' ? 'Rachat' : 'Dépôt' }}</td>
              <td>{{ ligne.anciennete }} j</td>
              <td>
                <span :class="['vo-blocage', ligne.blocage.bloquant ? 'is-blocking' : '']">{{ ligne.blocage.texte }}</span>
              </td>
              <td class="vo-num">{{ formatPrice(ligne.prix_vente || 0) }}</td>
              <td class="vo-num">
                <span v-if="ligne.source === 'purchase'" :style="{ color: Number(ligne.marge || 0) >= 0 ? 'var(--success-content)' : 'var(--error-content)' }">
                  {{ Number(ligne.marge || 0) >= 0 ? '+ ' : '' }}{{ formatPrice(ligne.marge || 0) }}
                </span>
                <span v-else>Net {{ formatPrice(ligne.deposant_net || 0) }}</span>
              </td>
              <td class="vo-num">
                <NuxtLink :to="ligne.lien" class="vo-inline-link">{{ ligne.blocage.action }}</NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="lignesFiltrees.length" class="vo-table-note">
          {{ lignesFiltrees.length }} véhicule{{ lignesFiltrees.length > 1 ? 's' : '' }} · le tri met en tête l'ancienneté de stock, pas la date d'entrée.
        </p>
      </UCard>

      <UCard>
        <template #header>
          <div class="vo-card-header">
            <span>Documents à valider</span>
            <strong v-if="bloques.length" class="vo-badge">{{ bloques.length }}</strong>
          </div>
        </template>

        <div v-if="!bloques.length" class="vo-empty">Aucun dossier bloqué : tout le stock est vendable.</div>
        <div v-else class="vo-alert-list">
          <NuxtLink v-for="item in bloques.slice(0, 6)" :key="`bloc-${item.source}-${item.id}`" :to="item.lien" class="vo-alert-item is-link">
            <div class="vo-alert-title">{{ item.blocage.texte }}</div>
            <div class="vo-alert-text">{{ item.marque || 'VO' }} {{ item.modele || '' }} · {{ item.plaque || 'sans plaque' }}</div>
          </NuxtLink>
        </div>

        <div class="vo-police">
          <div class="vo-police-title">Livre de police</div>
          <div class="vo-police-text">Délai légal : 5 jours après l'acquisition.</div>
          <NuxtLink to="/vo/livre-police" class="vo-inline-link">Ouvrir le registre →</NuxtLink>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

/**
 * Liste VO unique — maquette 4b.
 *
 * Rachats et dépôts-vente sont dans la même liste : ce qui compte n'est pas
 * leur mode d'acquisition mais le nombre de jours de stock et ce qui bloque la
 * vente. L'origine devient une colonne et un filtre, pas une arborescence, et
 * la remise en état est un état du véhicule, pas une page (8a).
 *
 * Les deux parcours de saisie restent distincts, et c'est voulu : le dépôt
 * porte un mandat et une commission, le rachat un régime de TVA sur marge.
 * La fusion s'arrête donc à la liste, comme le prévoyait la réserve de 8a.
 */
definePageMeta({ title: 'VO' })

const voStore = useVoStore()
const { formatPrice } = useVoHelpers()

type FiltreVo = 'stock' | 'remise' | 'reserve' | 'vendu' | 'all'
const FILTRES_VO: FiltreVo[] = ['stock', 'remise', 'reserve', 'vendu', 'all']

const route = useRoute()
const router = useRouter()

type OrigineVo = 'all' | 'purchase' | 'depot'
const ORIGINES_VO: OrigineVo[] = ['all', 'purchase', 'depot']

const recherche = ref('')

// Filtre et origine vivent dans l'URL : un compteur mène quelque part, et la
// destination arrive filtrée sur ce que le compteur disait (règle 3). C'est
// aussi ce qui rend une vue partageable — « les dépôts en remise en état ».
const filtreInitial = String(route.query.filtre || '')
const origineInitiale = String(route.query.origine || '')
const filtre = ref<FiltreVo>(FILTRES_VO.includes(filtreInitial as FiltreVo) ? filtreInitial as FiltreVo : 'stock')
const origine = ref<OrigineVo>(ORIGINES_VO.includes(origineInitiale as OrigineVo) ? origineInitiale as OrigineVo : 'all')

watch([filtre, origine], ([valeurFiltre, valeurOrigine]) => {
  if (route.query.filtre === valeurFiltre && route.query.origine === valeurOrigine) return
  router.replace({ query: { ...route.query, filtre: valeurFiltre, origine: valeurOrigine } })
})

const stats = computed(() => voStore.stats)

/** Une campagne de remise en état ouverte, quel que soit le parcours. */
function estEnRemiseEnEtat(item: any): boolean {
  return Boolean(item.refurbishment_status) && String(item.refurbishment_status) !== 'cloturee'
}

function formatKm(km: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(km)} km`
}

/**
 * Ancienneté de stock, en jours.
 *
 * L'API ne calcule `jours_stock` que pour un rachat, depuis sa date d'achat.
 * Un dépôt n'a que la date de début de mandat — qui est précisément le moment
 * où la moto est entrée en stock. Sans ce repli, tous les dépôts afficheraient
 * « 0 j » et tomberaient en bas d'un tri qui doit justement mettre l'ancienneté
 * en tête.
 */
function ancienneteDeStock(item: any): number {
  const fourni = Number(item.jours_stock ?? Number.NaN)
  if (Number.isFinite(fourni)) return fourni

  const entree = Date.parse(String(item.created_at ?? ''))
  if (!Number.isFinite(entree)) return 0
  return Math.max(0, Math.floor((Date.now() - entree) / 86_400_000))
}

/**
 * « Ce qui bloque » : une seule phrase, celle qui empêche la vente aujourd'hui.
 * L'ordre compte — un mandat expiré prime sur un document manquant, parce
 * qu'il faut rendre la moto avant de compléter un dossier.
 */
function decrireBlocage(item: any): { texte: string; action: string; bloquant: boolean } {
  if (item.source === 'depot' && item.mandat_expire) {
    return { texte: 'Mandat expiré', action: 'Renouveler', bloquant: true }
  }
  const manquants = item.missing_docs || []
  if (manquants.length) {
    const premier = manquants[0]
    return {
      texte: manquants.length > 1 ? `${premier} · et ${manquants.length - 1} autre(s)` : String(premier),
      action: 'Compléter',
      bloquant: true,
    }
  }
  if (estEnRemiseEnEtat(item)) {
    return {
      texte: item.refurbishment_blocking_sale ? 'Remise en état · bloque la vente' : 'Remise en état en cours',
      action: 'Ouvrir',
      bloquant: Boolean(item.refurbishment_blocking_sale),
    }
  }
  if (item.source === 'depot' && Number(item.jours_restants ?? 999) <= 7) {
    return { texte: `Mandat à prolonger · ${item.jours_restants} j`, action: 'Renouveler', bloquant: false }
  }
  if (String(item.status) === 'reserve') {
    return { texte: 'Réservé', action: 'Ouvrir', bloquant: false }
  }
  if (String(item.status) === 'vendu') {
    return { texte: 'Vendu', action: 'Ouvrir', bloquant: false }
  }
  return { texte: 'Prêt à vendre', action: 'Ouvrir', bloquant: false }
}

const lignes = computed(() => (voStore.stock || []).map((item: any) => ({
  ...item,
  anciennete: ancienneteDeStock(item),
  lien: item.source === 'depot' ? `/vo/depots/${item.id}` : `/vo/rachats/${item.id}`,
  blocage: decrireBlocage(item),
})).sort((a: any, b: any) => b.anciennete - a.anciennete))

function correspondAuFiltre(item: any): boolean {
  const statut = String(item.status || '')
  if (filtre.value === 'all') return true
  if (filtre.value === 'remise') return estEnRemiseEnEtat(item)
  if (filtre.value === 'reserve') return statut === 'reserve'
  if (filtre.value === 'vendu') return statut === 'vendu'
  return statut !== 'vendu'
}

const lignesFiltrees = computed(() => {
  const q = recherche.value.trim().toLowerCase()
  return lignes.value.filter((item: any) => {
    if (origine.value !== 'all' && item.source !== origine.value) return false
    if (!correspondAuFiltre(item)) return false
    if (!q) return true
    return `${item.marque ?? ''} ${item.modele ?? ''} ${item.plaque ?? ''}`.toLowerCase().includes(q)
  })
})

const chips = computed(() => [
  { value: 'stock', label: 'En stock', count: lignes.value.filter((i: any) => String(i.status) !== 'vendu').length },
  { value: 'remise', label: 'Remise en état', count: lignes.value.filter((i: any) => estEnRemiseEnEtat(i)).length },
  { value: 'reserve', label: 'Réservés', count: lignes.value.filter((i: any) => String(i.status) === 'reserve').length },
  { value: 'vendu', label: 'Vendus', count: lignes.value.filter((i: any) => String(i.status) === 'vendu').length },
])

// Seul un rachat immobilise de l'argent : un dépôt reste la propriété du déposant.
const immobilise = computed(() => lignes.value
  .filter((i: any) => i.source === 'purchase' && String(i.status) !== 'vendu')
  .reduce((total: number, i: any) => total + Number(i.prix_achat || 0), 0))

const stockMoyen = computed(() => {
  const enStock = lignes.value.filter((i: any) => String(i.status) !== 'vendu')
  if (!enStock.length) return 0
  return Math.round(enStock.reduce((total: number, i: any) => total + i.anciennete, 0) / enStock.length)
})

const bloques = computed(() => lignes.value.filter((i: any) => i.blocage.bloquant))

const resume = computed(() => {
  const enStock = lignes.value.filter((i: any) => String(i.status) !== 'vendu').length
  return `${enStock} en stock · ${formatPrice(immobilise.value)} immobilisés`
})

onMounted(async () => {
  await Promise.all([
    voStore.fetchStats(),
    voStore.fetchStock(),
  ])
})
</script>

<style scoped>
.vo-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.vo-subtitle {
  margin-top: 6px;
  color: var(--content-3);
  font-size: 13px;
}

.vo-header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* Aplat d'état : l'encre doit être celle prévue pour lui, sinon le libellé
   prend `--content-1` et ne contraste plus. */
.vo-secondary-btn {
  background: var(--info);
  color: var(--on-info);
  border-color: transparent;
}

.vo-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}

.vo-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.7fr);
  gap: 16px;
  align-items: start;
}

.vo-filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.vo-select {
  min-height: 38px;
  padding: 0 10px;
  border: 1px solid var(--border-2);
  border-radius: 8px;
  background: var(--surface-2);
  color: var(--content-1);
  font: inherit;
  font-size: 13px;
}

.vo-chip-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.vo-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid var(--border-2);
  border-radius: 999px;
  background: var(--surface-2);
  color: var(--content-2);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  text-decoration: none;
}
.vo-chip:hover { background: var(--overlay-hover); }
.vo-chip:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.vo-chip.is-active {
  background: var(--accent);
  border-color: transparent;
  color: var(--accent-ink);
  font-weight: 600;
}
.vo-chip-link { color: var(--accent-content); font-weight: 600; }

.vo-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.vo-table th {
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid var(--border-2);
  color: var(--content-3);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.vo-table td {
  padding: 10px;
  border-bottom: 1px solid var(--border-2);
  vertical-align: top;
}
.vo-table tr:last-child td { border-bottom: none; }
.vo-num { text-align: right; white-space: nowrap; }

.vo-vehicule { font-weight: 600; color: var(--content-1); }
.vo-vehicule-meta { margin-top: 2px; color: var(--content-3); font-size: 12px; }

.vo-blocage { color: var(--content-2); }
.vo-blocage.is-blocking { color: var(--warning-content); font-weight: 600; }

.vo-table-note {
  margin: 12px 0 0;
  color: var(--content-3);
  font-size: 12px;
}

.vo-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--content-1);
  font-weight: 700;
}
.vo-badge {
  min-width: 22px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--warning-soft);
  color: var(--warning-content);
  font-size: 12px;
  text-align: center;
}

.vo-empty,
.vo-empty-state {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 20px 4px;
  color: var(--content-3);
  font-size: 13px;
}
.vo-empty-state strong { color: var(--content-1); font-size: 14px; }

.vo-alert-list { display: flex; flex-direction: column; gap: 8px; }
.vo-alert-item {
  display: block;
  padding: 10px;
  border: 1px solid var(--border-2);
  border-radius: 10px;
  background: var(--overlay-soft);
  text-decoration: none;
}
.vo-alert-item:hover { background: var(--overlay-hover); }
.vo-alert-item:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.vo-alert-title { color: var(--content-1); font-weight: 600; font-size: 13px; }
.vo-alert-text { margin-top: 2px; color: var(--content-3); font-size: 12px; }

.vo-police {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--border-2);
}
.vo-police-title { color: var(--content-1); font-weight: 700; font-size: 13px; }
.vo-police-text { margin: 4px 0 8px; color: var(--content-3); font-size: 12px; }

.vo-inline-link {
  color: var(--accent-content);
  font-weight: 600;
  font-size: 12px;
  text-decoration: none;
}
.vo-inline-link:hover { text-decoration: underline; }

@media (max-width: 1100px) {
  .vo-layout { grid-template-columns: minmax(0, 1fr); }
  .vo-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
