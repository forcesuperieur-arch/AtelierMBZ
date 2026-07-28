<template>
  <div class="tab">
    <AppErrorState v-if="store.erreur.atelier" :description="store.erreur.atelier" @retry="store.loadAtelier()" />

    <template v-else>
      <!-- Ligne d'état : quatre mesures du MOMENT, jamais mélangées avec les
           chiffres de période. C'est la confusion majeure de l'ancienne page. -->
      <div class="tab-tiles">
        <DashboardStatTile
          label="Ponts occupés"
          :value="`${pontsOccupes}/${pontsActifs.length}`"
          :hint="`${pontsActifs.length - pontsOccupes} libre${pontsActifs.length - pontsOccupes > 1 ? 's' : ''} maintenant`"
          :meter="{ value: pontsOccupes, max: pontsActifs.length, label: `${tauxOccupation} % de la capacité du jour` }"
          :tone="pontsActifs.length && pontsOccupes === pontsActifs.length ? 'warn' : 'neutral'"
        />
        <DashboardStatTile
          label="Interventions en cours"
          :value="orEnCours"
          :hint="mecanosActifs + ' mécanicien' + (mecanosActifs > 1 ? 's' : '') + ' au travail'"
        />
        <DashboardStatTile
          label="Charge du jour"
          :value="formatMinutes(chargeJour.planned_minutes)"
          :hint="`${formatMinutes(chargeJour.actual_minutes)} pointé · ${Math.round(Number(chargeJour.ratio ?? 0))} % du planifié`"
          :meter="{ value: chargeJour.actual_minutes ?? 0, max: chargeJour.planned_minutes || 1, label: 'avancement du jour' }"
        />
        <DashboardStatTile
          label="À traiter"
          :value="store.aTraiter.total"
          :tone="toneFile"
          :hint="hintFile"
        />
      </div>

      <DashboardActionQueue
        :groups="store.aTraiter.items"
        :total="store.aTraiter.total"
        :seuils="store.aTraiter.seuils"
      />

      <DashboardSection
        v-if="store.stockAlertes.length"
        title="Pièces sous le seuil de stock"
        :count="store.stockAlertes.length"
        subtitle="À commander pour ne pas bloquer une intervention."
      >
        <DashboardRankedList
          :rows="alertesStock"
          :limit="5"
        />
      </DashboardSection>

      <DashboardSection
        title="Ponts"
        :count="pontsActifs.length || undefined"
        subtitle="Ponts actifs de l'atelier et occupation en direct."
      >
        <div v-if="pontsActifs.length" class="ponts">
          <article
            v-for="pont in pontsVisibles"
            :key="pont.id"
            class="pont"
            :class="pont.current_rdv ? 'pont--occupe' : 'pont--libre'"
          >
            <header class="pont-head">
              <span class="pont-nom">{{ pont.nom }}</span>
              <!-- État écrit, pas seulement une pastille de couleur. -->
              <span class="pont-etat">{{ pont.current_rdv ? 'Occupé' : 'Libre' }}</span>
            </header>
            <p v-if="pont.current_rdv" class="pont-corps">
              <strong>{{ pont.current_rdv.vehicule_info }}</strong><br >
              {{ pont.current_rdv.client_nom }}
            </p>
            <p v-else class="pont-corps pont-corps--vide">Disponible</p>
            <footer class="pont-pied">
              {{ pont.next_count ?? 0 }} RDV restant{{ (pont.next_count ?? 0) > 1 ? 's' : '' }} aujourd'hui
            </footer>
          </article>
        </div>
        <AppEmptyState
          v-else
          icon="🔧"
          title="Aucun pont actif"
          description="Configure les ponts de l'atelier dans Réglages pour suivre leur occupation."
        />
        <button v-if="pontsActifs.length > PONTS_VISIBLES" type="button" class="ponts-more" @click="tousLesPonts = !tousLesPonts">
          {{ tousLesPonts ? `Afficher ${PONTS_VISIBLES} ponts` : `Voir les ${pontsActifs.length - PONTS_VISIBLES} autres ponts` }}
        </button>
      </DashboardSection>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Onglet « Atelier » — l'état du moment et ce qu'il y a à faire.
 * Tient dans un écran : aucune analyse de période ici.
 */
const store = useDashboardStore()
const { formatMinutes } = useDashboardFormat()

const PONTS_VISIBLES = 8
const tousLesPonts = ref(false)

// Les ponts désactivés ne sont pas de la capacité : les compter faussait le
// taux d'occupation (3 % affichés en dev pour 3 ponts réels sur 156 lignes).
const pontsActifs = computed(() => store.ponts.filter((p: any) => p.is_active !== 0 && p.is_active !== false))
const pontsVisibles = computed(() => tousLesPonts.value ? pontsActifs.value : pontsActifs.value.slice(0, PONTS_VISIBLES))
const pontsOccupes = computed(() => pontsActifs.value.filter((p: any) => p.current_rdv).length)
const tauxOccupation = computed(() => pontsActifs.value.length
  ? Math.round(pontsOccupes.value / pontsActifs.value.length * 100)
  : 0)

const orEnCours = computed(() => store.realtime?.or_en_cours?.length ?? 0)
const mecanosActifs = computed(() =>
  (store.realtime?.mecaniciens_actifs ?? []).filter((m: any) => Number(m.nb_interventions) > 0).length)
const chargeJour = computed(() => store.realtime?.charge_jour ?? { planned_minutes: 0, actual_minutes: 0, ratio: 0 })

const alertesStock = computed(() => store.stockAlertes.map((p: any) => ({
  key: p.id,
  label: p.designation,
  sub: p.reference ? `Réf. ${p.reference}` : undefined,
  value: `${p.quantite_stock} en stock`,
  tone: 'warn' as const,
})))

const critiques = computed(() =>
  store.aTraiter.items.filter(g => g.severity === 'critical').reduce((s, g) => s + g.total, 0))

const toneFile = computed(() => {
  if (critiques.value > 0) return 'crit'
  return store.aTraiter.total > 0 ? 'warn' : 'good'
})
const hintFile = computed(() => {
  if (critiques.value > 0) return `dont ${critiques.value} critique${critiques.value > 1 ? 's' : ''}`
  return store.aTraiter.total > 0 ? 'aucun cas critique' : 'file vide'
})
</script>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }
.tab-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 12px;
}

.ponts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 10px;
}
.pont {
  padding: 12px;
  border-radius: var(--radius);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
}
.pont--occupe { border-color: rgba(245, 158, 11, 0.25); }
.pont--libre { border-color: rgba(16, 185, 129, 0.2); }
.pont-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}
.pont-nom { font-size: 13px; font-weight: 700; color: var(--ink); }
.pont-etat {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 999px;
}
.pont--occupe .pont-etat { background: var(--status-warn-soft); color: var(--status-warn); }
.pont--libre .pont-etat { background: var(--status-good-soft); color: var(--status-good); }
.pont-corps { margin: 0 0 8px; font-size: 13px; color: var(--ink-body); }
.pont-corps--vide { color: var(--ink-muted); }
.pont-pied { font-size: 11px; color: var(--ink-muted); }

.ponts-more {
  margin-top: 12px;
  padding: 6px 12px;
  min-height: 32px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.ponts-more:hover { border-color: var(--border-hover); color: var(--ink); }
</style>
