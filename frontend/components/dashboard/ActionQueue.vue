<template>
  <DashboardSection
    title="À traiter"
    :count="total || undefined"
    :subtitle="subtitle"
  >
    <!-- File vide : c'est une bonne nouvelle, on le dit. -->
    <div v-if="!groups.length" class="queue-clear">
      <span class="queue-clear-mark" aria-hidden="true">✓</span>
      <div>
        <div class="queue-clear-title">Rien en attente</div>
        <div class="queue-clear-sub">Aucun litige, aucun dépassement, aucune restitution en retard.</div>
      </div>
    </div>

    <div v-else class="queue">
      <section v-for="group in groups" :key="group.kind" class="queue-group">
        <header class="queue-group-head">
          <!-- Le niveau est ÉCRIT : la gravité n'est jamais portée par la seule
               couleur (lecture daltonienne, impression N&B). Pas d'émoji non
               plus — tous les postes n'ont pas la police qui va avec. -->
          <span class="queue-sev" :class="`queue-sev--${group.severity}`">
            {{ SEVERITE[group.severity].libelle }}
          </span>
          <span class="queue-group-title">{{ group.titre }}</span>
          <span class="queue-group-count">{{ group.total }}</span>
        </header>

        <ul class="queue-rows">
          <li v-for="row in group.rows" :key="`${group.kind}-${row.rdv_id}`" class="queue-row">
            <div class="queue-row-main">
              <span class="queue-plaque">{{ row.vehicule_plaque || 'Sans plaque' }}</span>
              <span class="queue-sep" aria-hidden="true">·</span>
              <span>{{ row.vehicule_info || 'Moto' }}</span>
              <span class="queue-sep" aria-hidden="true">·</span>
              <span class="queue-client">{{ row.client_nom || 'Client inconnu' }}</span>
            </div>
            <div class="queue-row-meta">
              <span v-if="row.detail" class="queue-detail">{{ row.detail }}</span>
              <button type="button" class="queue-action" @click="declencher(group, row)">
                {{ group.action_label }} →
              </button>
            </div>
          </li>
        </ul>

        <p v-if="group.total > group.rows.length" class="queue-more">
          … et {{ group.total - group.rows.length }} autre{{ group.total - group.rows.length > 1 ? 's' : '' }}
          <NuxtLink v-if="group.action.type === 'route'" :to="group.action.to" class="queue-more-link">
            voir la liste complète
          </NuxtLink>
        </p>
      </section>
    </div>
  </DashboardSection>
</template>

<script setup lang="ts">
import type { ActionGroup, ActionRow } from '~/stores/dashboard'

/**
 * File d'attente opérationnelle : le seul bloc de la page qui fait AGIR.
 * L'ancien tableau de bord affichait « 2 litiges — à traiter en priorité »
 * sans aucun moyen de les atteindre : la ligne ne menait nulle part.
 */
const props = defineProps<{ groups: ActionGroup[], total: number, seuils?: any }>()

const SEVERITE = {
  critical: { libelle: 'Critique' },
  warning: { libelle: 'À faire' },
  info: { libelle: 'À surveiller' },
} as const

const { open: openRdvDetail } = useRdvDetailModal()
const router = useRouter()

const subtitle = computed(() => {
  const s = props.seuils
  if (!s) return undefined
  return `Seuils : dépassement au-delà de +${s.depassement_minutes} min, restitution au-delà de ${s.restitution_minutes} min, séjour au-delà de ${s.sejour_heures} h ouvrées.`
})

function declencher(group: ActionGroup, row: ActionRow) {
  if (group.action.type === 'route') {
    router.push(group.action.to)
    return
  }
  openRdvDetail({
    id: row.rdv_id,
    statut: row.statut,
    date_rdv: row.date_rdv,
    // L'API renvoie l'heure SQL complète ; la modale l'affiche telle quelle,
    // d'où le « 14:00:00 » si on ne coupe pas les secondes.
    heure_debut: row.heure_rdv?.slice(0, 5),
    duree_estimee: row.temps_estime ? Number(row.temps_estime) : undefined,
    type_intervention: row.type_intervention || undefined,
    pont_nom: row.pont_nom || undefined,
    mecanicien_nom: row.mecanicien_nom || undefined,
    client_nom: row.client_nom || undefined,
    client_telephone: row.client_telephone || undefined,
    vehicule_info: row.vehicule_info || undefined,
    vehicule_plaque: row.vehicule_plaque || undefined,
  })
}
</script>

<style scoped>
.queue { display: flex; flex-direction: column; gap: 18px; }

.queue-group-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}
.queue-sev {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.queue-sev--critical { background: var(--status-crit-soft); color: #FCA5A5; }
.queue-sev--warning { background: var(--status-warn-soft); color: var(--status-warn); }
.queue-sev--info { background: rgba(255, 255, 255, 0.06); color: var(--ink-muted); }

.queue-group-title { font-size: 13px; font-weight: 700; color: var(--ink); }
.queue-group-count {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-body);
  background: rgba(255, 255, 255, 0.08);
  padding: 1px 7px;
  border-radius: 999px;
}

.queue-rows { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.queue-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--radius-sm);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
}
.queue-row-main {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 13px;
  color: var(--ink-body);
  min-width: 0;
}
.queue-plaque { font-weight: 700; color: var(--ink); }
.queue-client { color: var(--ink-body); }
.queue-sep { color: var(--ink-muted); }
.queue-row-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.queue-detail { font-size: 12px; color: var(--ink-muted); }
.queue-action {
  /* Cible tactile confortable : les listes du dashboard se consultent aussi
     sur tablette au comptoir. */
  min-height: 32px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(255, 210, 0, 0.28);
  background: rgba(255, 210, 0, 0.1);
  color: var(--orange);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background var(--transition), border-color var(--transition);
}
.queue-action:hover { background: rgba(255, 210, 0, 0.18); border-color: rgba(255, 210, 0, 0.45); }
.queue-action:focus-visible { outline: 2px solid var(--orange); outline-offset: 2px; }

.queue-more { margin: 8px 0 0; font-size: 12px; color: var(--ink-muted); }
.queue-more-link { color: var(--orange); font-weight: 600; margin-left: 6px; }

.queue-clear {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 0;
}
.queue-clear-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--status-good-soft);
  color: var(--status-good);
  font-weight: 800;
}
.queue-clear-title { font-size: 14px; font-weight: 700; color: var(--ink); }
.queue-clear-sub { font-size: 12px; color: var(--ink-muted); }
</style>
