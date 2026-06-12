<template>
  <div>
    <div class="page-header" style="justify-content:space-between;">
      <div>
        <div class="page-title">Rendez-vous</div>
        <div class="page-sub">Vue atelier moderne avec filtres, statut et accès rapide aux fiches</div>
      </div>
      <NuxtLink to="/rdv/new" class="topbar-new-btn">+ Nouveau RDV</NuxtLink>
    </div>

    <div class="rdv-kpis">
      <div class="rdv-kpi-card accent">
        <div class="rdv-kpi-label">TOTAL JOUR</div>
        <div class="rdv-kpi-value">{{ stats.total }}</div>
        <div class="rdv-kpi-sub">{{ formatDisplayDate(filters.date) }}</div>
      </div>
      <div class="rdv-kpi-card">
        <div class="rdv-kpi-label">À TRAITER</div>
        <div class="rdv-kpi-value">{{ stats.pending }}</div>
        <div class="rdv-kpi-sub">en attente, réservé, confirmé</div>
      </div>
      <div class="rdv-kpi-card">
        <div class="rdv-kpi-label">EN COURS</div>
        <div class="rdv-kpi-value">{{ stats.inProgress }}</div>
        <div class="rdv-kpi-sub">interventions actives</div>
      </div>
      <div class="rdv-kpi-card">
        <div class="rdv-kpi-label">TERMINÉS</div>
        <div class="rdv-kpi-value">{{ stats.done }}</div>
        <div class="rdv-kpi-sub">prêts ou restitués</div>
      </div>
    </div>

    <div class="panel" style="margin-bottom:16px;">
      <div class="rdv-filter-bar">
        <div class="filter-field">
          <label class="form-label">Date</label>
          <input v-model="filters.date" type="date" class="form-control" />
        </div>
        <div class="filter-field">
          <label class="form-label">Statut</label>
          <select v-model="filters.status" class="form-control form-select">
            <option v-for="o in statusOptions" :key="o.value || 'all'" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="filter-field search-field">
          <label class="form-label">Recherche</label>
          <input v-model="filters.search" type="text" class="form-control" placeholder="Client, plaque, moto, intervention..." />
        </div>
        <div class="rdv-filter-actions">
          <button class="topbar-new-btn" style="height:40px;" @click="fetchData">Filtrer</button>
          <button class="btn-secondary" style="height:40px;" @click="resetFilters">Reset</button>
        </div>
      </div>
    </div>

    <AppLoadingState
      v-if="rdvStore.loading"
      compact
      title="Chargement des rendez-vous"
      description="La liste atelier est en cours de récupération."
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="Liste des rendez-vous indisponible"
      :description="errorMessage"
      @retry="fetchData"
    />

    <div v-else class="rdv-list">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:12px;color:#9CA3AF;padding:0 4px;">
        <span>{{ filteredRdvs.length }} RDV visible(s)</span>
        <span v-if="filters.status">Filtre actif : {{ statusOptions.find(o => o.value === filters.status)?.label }}</span>
      </div>
      <div v-for="rdv in filteredRdvs" :key="rdv.id" class="rdv-card">
        <div class="rdv-card-time">
          <div class="rdv-time">{{ rdv.heure_debut?.slice(0, 5) || '—' }}</div>
          <div class="rdv-date">{{ shortDate(rdv.date_rdv) }}</div>
          <StatusBadge :status="rdv.status" />
        </div>

        <div class="rdv-card-main">
          <div class="rdv-card-topline">
            <div class="rdv-client">{{ rdv.client_nom || 'Client non renseigné' }}</div>
            <div class="rdv-vehicle">{{ rdv.vehicule_info || 'Véhicule non renseigné' }}</div>
          </div>
          <div class="rdv-card-meta">
            <span>🔧 {{ rdv.type_intervention || 'Intervention' }}</span>
            <span v-if="rdv.vehicule_plaque">🏍 {{ rdv.vehicule_plaque }}</span>
            <span v-if="rdv.pont_nom">📍 {{ rdv.pont_nom }}</span>
            <span v-if="rdv.mecanicien_nom">👤 {{ rdv.mecanicien_nom }}</span>
            <span v-if="rdv.duree_estimee">⏱ {{ formatMinutes(rdv.duree_estimee) }}</span>
          </div>
          <div v-if="rdv.commandes?.length" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
            <span v-for="cmd in rdv.commandes" :key="cmd" style="font-size:10px;color:#FFD200;background:rgba(255,210,0,0.08);padding:2px 8px;border-radius:4px;border:1px solid rgba(255,210,0,0.15);">
              #{{ cmd }}
            </span>
          </div>
          <div v-if="rdv.description_probleme" class="rdv-card-note">
            {{ rdv.description_probleme }}
          </div>
        </div>

        <div class="rdv-card-actions">
          <button class="rdv-action-link" style="background:none;border:none;cursor:pointer;" @click="openRdvModal(rdv)">Détail →</button>
        </div>
      </div>

      <AppEmptyState
        v-if="!filteredRdvs.length"
        title="Aucun rendez-vous trouvé"
        description="Essaie un autre filtre ou crée un nouveau rendez-vous."
        action-label="Réinitialiser"
        @action="resetFilters"
      />
    </div>
  </div>

  <!-- Modal détail RDV -->
  <AppModal v-model:open="showRdvModal" size="lg">
    <template #header>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:16px;font-weight:700;color:#E8E9ED;">RDV #{{ selectedRdv?.id }}</span>
        <StatusBadge v-if="selectedRdv" :status="selectedRdv.status" />
      </div>
    </template>
    <div v-if="selectedRdv" style="display:flex;flex-direction:column;gap:16px;font-size:13px;color:#D1D5DB;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><span style="color:#6B7280;">Date :</span> {{ formatDisplayDate(selectedRdv.date_rdv) }}</div>
        <div><span style="color:#6B7280;">Heure :</span> {{ selectedRdv.heure_debut || '—' }}</div>
        <div><span style="color:#6B7280;">Type :</span> {{ selectedRdv.type_intervention || '—' }}</div>
        <div><span style="color:#6B7280;">Durée :</span> {{ formatMinutes(selectedRdv.duree_estimee) }}</div>
      </div>
      <div v-if="selectedRdv.pont_nom || selectedRdv.mecanicien_nom" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div v-if="selectedRdv.pont_nom"><span style="color:#6B7280;">Pont :</span> {{ selectedRdv.pont_nom }}</div>
        <div v-if="selectedRdv.mecanicien_nom"><span style="color:#6B7280;">Mécano :</span> {{ selectedRdv.mecanicien_nom }}</div>
      </div>
      <div v-if="selectedRdv.client_nom || selectedRdv.client_telephone || selectedRdv.client_email" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">{{ selectedRdv.client_nom || 'Client' }}</div>
        <div v-if="selectedRdv.client_telephone" style="color:#9CA3AF;">📞 {{ selectedRdv.client_telephone }}</div>
        <div v-if="selectedRdv.client_email" style="color:#9CA3AF;">{{ selectedRdv.client_email }}</div>
      </div>
      <div v-if="selectedRdv.vehicule_info || selectedRdv.vehicule_plaque" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">{{ selectedRdv.vehicule_info || 'Véhicule' }}</div>
        <div v-if="selectedRdv.vehicule_plaque" style="color:#9CA3AF;">🏍 {{ selectedRdv.vehicule_plaque }}</div>
      </div>
      <div v-if="selectedRdv.description_probleme" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">Description</div>
        <div style="color:#9CA3AF;white-space:pre-wrap;">{{ selectedRdv.description_probleme }}</div>
      </div>
    </div>
    <template #footer>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button class="btn btn-ghost" @click="showRdvModal = false">Fermer</button>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
const rdvStore = useRdvStore()
const toast = useToast()
const errorMessage = ref('')
const showRdvModal = ref(false)
const selectedRdv = ref<any>(null)

function openRdvModal(rdv: any) {
  selectedRdv.value = rdv
  showRdvModal.value = true
}

const filters = reactive({
  date: todayLocalISO(),
  status: '',
  search: '',
})

const statusOptions = [
  { value: '', label: 'Tous' },
  { value: 'en_attente', label: 'En attente' },
  { value: 'reserve', label: 'Réservé' },
  { value: 'confirme', label: 'Confirmé' },
  { value: 'reception', label: 'Réception' },
  { value: 'en_cours', label: 'En cours' },
  { value: 'termine', label: 'Terminé' },
  { value: 'restitue', label: 'Restitué' },
  { value: 'facture', label: 'Facturé' },
  { value: 'paye', label: 'Payé' },
  { value: 'annule', label: 'Annulé' },
]

const filteredRdvs = computed(() => {
  const q = filters.search.trim().toLowerCase()
  return [...rdvStore.rdvs]
    .filter((rdv: any) => {
      if (!q) return true
      const hay = [
        rdv.client_nom,
        rdv.vehicule_info,
        rdv.vehicule_plaque,
        rdv.type_intervention,
        rdv.pont_nom,
        rdv.mecanicien_nom,
      ].filter(Boolean).join(' ').toLowerCase()
      return hay.includes(q)
    })
    .sort((a: any, b: any) => `${a.date_rdv || ''} ${a.heure_debut || ''}`.localeCompare(`${b.date_rdv || ''} ${b.heure_debut || ''}`))
})

const stats = computed(() => {
  const list = filteredRdvs.value
  return {
    total: list.length,
    pending: list.filter((r: any) => ['en_attente', 'reserve', 'confirme', 'reception'].includes(r.status)).length,
    inProgress: list.filter((r: any) => r.status === 'en_cours').length,
    done: list.filter((r: any) => ['termine', 'restitue', 'facture', 'paye'].includes(r.status)).length,
  }
})

function shortDate(d: string) {
  if (!d) return '—'
  try {
    return new Date(`${d}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
  } catch {
    return d
  }
}

function formatDisplayDate(d: string) {
  if (!d) return '—'
  try {
    return new Date(`${d}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  } catch {
    return d
  }
}

async function fetchData() {
  errorMessage.value = ''
  try {
    await rdvStore.fetchRdvs({ date: filters.date, status: filters.status })
  } catch (e: any) {
    errorMessage.value = e?.message || 'Les rendez-vous n’ont pas pu être chargés.'
    toast.add({ title: 'Erreur RDV', description: errorMessage.value, color: 'error' })
  }
}

function resetFilters() {
  filters.date = todayLocalISO()
  filters.status = ''
  filters.search = ''
  fetchData()
}

watch(() => [filters.date, filters.status], () => { void fetchData() })
onMounted(() => { void fetchData() })
</script>

<style scoped>
.rdv-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.rdv-kpi-card {
  background: var(--dark2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
}

.rdv-kpi-card.accent {
  border-color: rgba(255, 210, 0, 0.22);
  box-shadow: 0 0 0 1px rgba(255, 210, 0, 0.06) inset;
}

.rdv-kpi-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #6B7280;
}

.rdv-kpi-value {
  margin-top: 6px;
  font-size: 28px;
  font-weight: 800;
  color: #F3F4F6;
}

.rdv-kpi-sub {
  margin-top: 2px;
  font-size: 12px;
  color: #9CA3AF;
}

.rdv-filter-bar {
  display: grid;
  grid-template-columns: 160px 180px minmax(240px, 1fr) auto;
  gap: 12px;
  align-items: end;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.search-field {
  min-width: 220px;
}

.rdv-filter-actions {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

.rdv-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.rdv-card {
  display: grid;
  grid-template-columns: 120px 1fr auto;
  gap: 16px;
  align-items: center;
  background: var(--dark2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-lg);
  padding: 16px;
  transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
}

.rdv-card:hover {
  border-color: rgba(255,255,255,0.12);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.rdv-card-time {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}

.rdv-time {
  font-size: 26px;
  line-height: 1;
  font-weight: 800;
  color: #FFD200;
}

.rdv-date {
  font-size: 12px;
  color: #9CA3AF;
}

.rdv-card-main {
  min-width: 0;
}

.rdv-card-topline {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  align-items: baseline;
}

.rdv-client {
  font-size: 16px;
  font-weight: 700;
  color: #F3F4F6;
}

.rdv-vehicle {
  font-size: 14px;
  color: #D1D5DB;
}

.rdv-card-meta {
  margin-top: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  font-size: 12px;
  color: #9CA3AF;
}

.rdv-card-note {
  margin-top: 10px;
  color: #D1D5DB;
  font-size: 13px;
  line-height: 1.45;
}

.rdv-card-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.rdv-action-link {
  color: #FFD200;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}

.rdv-empty-state {
  text-align: center;
  padding: 32px;
}

@media (max-width: 1100px) {
  .rdv-kpis {
    grid-template-columns: repeat(2, 1fr);
  }

  .rdv-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .rdv-card {
    grid-template-columns: 1fr;
  }

  .rdv-card-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 640px) {
  .rdv-kpis,
  .rdv-filter-bar {
    grid-template-columns: 1fr;
  }

  .rdv-filter-actions {
    width: 100%;
  }
}
</style>
