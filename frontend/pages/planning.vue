<template>
  <div>
    <div class="page-header">
      <div class="page-title">Planning</div>
    </div>

    <div v-if="loading" class="loading-shimmer" style="height:400px;border-radius:14px;"></div>

    <template v-else>
      <!-- KPI Cards -->
      <div class="grid-4" style="margin-bottom:20px;">
        <div class="planning-kpi primary">
          <div class="planning-kpi-label">CHARGE VISIBLE</div>
          <div class="planning-kpi-value">{{ kpis.charge }}</div>
          <div class="planning-kpi-sub">{{ kpis.chargeDetail }}</div>
        </div>
        <div class="planning-kpi" :style="kpis.conflicts > 0 ? 'border-color:rgba(239,68,68,0.3);' : ''">
          <div class="planning-kpi-label">CONFLITS</div>
          <div class="planning-kpi-value" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.conflicts }}</div>
          <div class="planning-kpi-sub">{{ kpis.conflicts > 0 ? 'Attention' : 'Aucun conflit' }}</div>
        </div>
        <div class="planning-kpi">
          <div class="planning-kpi-label">SANS AFFECTATION</div>
          <div class="planning-kpi-value">{{ kpis.unassigned }}</div>
          <div class="planning-kpi-sub">RDV non assignés</div>
        </div>
        <div class="planning-kpi" :style="kpis.late > 0 ? 'border-color:rgba(239,68,68,0.3);' : ''">
          <div class="planning-kpi-label">RETARDS</div>
          <div class="planning-kpi-value" :style="kpis.late > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.late }}</div>
          <div class="planning-kpi-sub">{{ kpis.late > 0 ? 'En retard' : 'À l\'heure' }}</div>
        </div>
      </div>

      <!-- Status Legend -->
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:10px 16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;font-size:12px;">
        <span style="color:#6B7280;font-weight:600;">Légende :</span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);"></span><span style="color:#9CA3AF;">Réservé</span></span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.35);"></span><span style="color:#9CA3AF;">Confirmé</span></span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(20,184,166,0.15);border:1px solid rgba(20,184,166,0.4);"></span><span style="color:#9CA3AF;">En cours</span></span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.4);"></span><span style="color:#9CA3AF;">Terminé</span></span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.4);"></span><span style="color:#9CA3AF;">Facturé</span></span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);"></span><span style="color:#9CA3AF;">Annulé</span></span>
      </div>

      <!-- Mechanic Filter Chips -->
      <div v-if="mecaniciens.length" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <span style="font-size:12px;font-weight:600;color:#6B7280;">Mécaniciens :</span>
        <button
          v-for="m in mecaniciens"
          :key="m.id"
          style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.15s;"
          :style="{
            background: activeMecas.includes(m.id) ? 'rgba(139,92,246,0.12)' : 'rgba(255,255,255,0.03)',
            border: activeMecas.includes(m.id) ? '1px solid rgba(139,92,246,0.3)' : '1px solid rgba(255,255,255,0.06)',
            color: activeMecas.includes(m.id) ? '#C4B5FD' : '#6B7280',
          }"
          @click="toggleMeca(m.id)"
        >
          <span style="width:8px;height:8px;border-radius:50%;" :style="{ background: m.couleur || '#8B5CF6' }"></span>
          {{ m.prenom }} {{ m.nom?.charAt(0) }}.
        </button>
        <button
          v-if="activeMecas.length"
          style="padding:5px 10px;border-radius:20px;font-size:11px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);color:#6B7280;cursor:pointer;"
          @click="activeMecas = []"
        >
          ✕ Tous
        </button>
      </div>

      <PlanningGrid
        :ponts="ponts"
        :rdvs="filteredRdvs"
        @select-rdv="onSelectRdv"
      />

      <!-- RDV Detail Modal -->
      <div
        v-if="showRdvModal && selectedRdv"
        class="detail-modal-overlay"
        @click.self="showRdvModal = false"
      >
        <div class="detail-modal-card">
          <div class="detail-modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-weight:700;color:#E8E9ED;font-size:15px;">RDV #{{ selectedRdv.id }}</span>
              <StatusBadge :status="selectedRdv.status" />
            </div>
            <button @click="showRdvModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
          </div>

          <div class="detail-modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
              <div><span style="color:#6B7280;">Date :</span> <span style="color:#D1D5DB;">{{ selectedRdv.date_rdv }}</span></div>
              <div><span style="color:#6B7280;">Heure :</span> <span style="color:#D1D5DB;">{{ selectedRdv.heure_debut }}</span></div>
              <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ selectedRdv.type_intervention }}</span></div>
              <div><span style="color:#6B7280;">Durée :</span> <span style="color:#D1D5DB;">{{ selectedRdv.temps_estime ?? '—' }} min</span></div>
              <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ selectedRdv.client_nom }}</span></div>
              <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ selectedRdv.vehicule_info || '—' }}</span></div>
              <div><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ selectedRdv.pont?.nom || '—' }}</span></div>
              <div><span style="color:#6B7280;">Mécanicien :</span> <span style="color:#D1D5DB;">{{ selectedRdv.mecanicien_nom || '—' }}</span></div>
            </div>

            <div v-if="!selectedRdv.mecanicien_id || !selectedRdv.pont_id" style="margin-top:12px;padding:8px 12px;border-radius:8px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#FBBF24;">
              ⚠️ {{ !selectedRdv.mecanicien_id ? 'Aucun mécanicien assigné' : '' }}{{ !selectedRdv.mecanicien_id && !selectedRdv.pont_id ? ' · ' : '' }}{{ !selectedRdv.pont_id ? 'Aucun pont assigné' : '' }}
            </div>

            <div v-if="selectedRdv.commentaire" style="margin-top:12px;font-size:13px;">
              <span style="color:#6B7280;">Description :</span>
              <p style="color:#D1D5DB;margin-top:4px;">{{ selectedRdv.commentaire }}</p>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
              <NuxtLink :to="`/rdv/${selectedRdv.id}`" class="btn btn-primary" style="font-size:12px;padding:6px 14px;text-decoration:none;" @click="showRdvModal = false">Ouvrir la fiche →</NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const ponts = ref<any[]>([])
const rawRdvs = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const activeMecas = ref<number[]>([])
const showRdvModal = ref(false)
const selectedRdv = ref<any>(null)

function normalizeDateValue(value: unknown): string {
  const raw = value ? String(value) : ''
  return raw ? raw.slice(0, 10) : ''
}

function normalizeTimeValue(value: unknown): string {
  const raw = value ? String(value) : ''
  const match = raw.match(/(\d{2}):(\d{2})/)
  return match ? `${match[1]}:${match[2]}` : ''
}

const normalizedRdvs = computed(() => rawRdvs.value.map(r => ({
  ...r,
  status: r.statut ?? r.status,
  client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
  vehicule_info: r.vehicule ? `${r.vehicule.marque ?? ''} ${r.vehicule.modele ?? ''}`.trim() : (r.vehicule_info ?? ''),
  heure_debut: normalizeTimeValue(r.heure_rdv ?? r.heureRdv ?? r.heure_debut),
  pont_id: r.pont?.id ?? r.pont_id,
  date_rdv: normalizeDateValue(r.date_rdv ?? r.dateRdv),
  type_intervention: r.type_intervention ?? r.typeIntervention,
  temps_estime: r.temps_estime ?? r.tempsEstime ?? r.duree_estimee,
  mecanicien_id: r.mecanicien?.id ?? r.mecanicien_id,
  mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : '',
})))

const filteredRdvs = computed(() => {
  if (!activeMecas.value.length) return normalizedRdvs.value
  return normalizedRdvs.value.filter(r => activeMecas.value.includes(r.mecanicien_id))
})

const kpis = computed(() => {
  const rdvs = normalizedRdvs.value
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.filter(r => r.date_rdv === today)
  const enCours = todayRdvs.filter(r => r.status === 'en_cours').length
  const total = todayRdvs.length
  const unassigned = rdvs.filter(r => !r.mecanicien_id && !['annule', 'paye', 'facture'].includes(r.status)).length
  const late = todayRdvs.filter(r => {
    if (!['confirme', 'reception'].includes(r.status)) return false
    const h = r.heure_debut?.split(':')
    if (!h) return false
    const now = new Date()
    const scheduled = new Date()
    scheduled.setHours(Number(h[0]), Number(h[1]), 0)
    return (now.getTime() - scheduled.getTime()) > 600000
  }).length

  // Detect conflicts: same pont or same mechanic at overlapping times
  const conflictCount = (() => {
    let count = 0
    const active = rdvs.filter(r => !['annule', 'paye', 'facture', 'restitue'].includes(r.status))
    for (let i = 0; i < active.length; i++) {
      for (let j = i + 1; j < active.length; j++) {
        const a = active[i]; const b = active[j]
        if (a.date_rdv !== b.date_rdv) continue
        if (!a.heure_debut || !b.heure_debut) continue
        const aStart = timeToMin(a.heure_debut); const bStart = timeToMin(b.heure_debut)
        const aEnd = aStart + (a.temps_estime || 60); const bEnd = bStart + (b.temps_estime || 60)
        const overlaps = aStart < bEnd && bStart < aEnd
        if (!overlaps) continue
        if ((a.pont_id && a.pont_id === b.pont_id) || (a.mecanicien_id && a.mecanicien_id === b.mecanicien_id)) count++
      }
    }
    return count
  })()

  return {
    charge: `${enCours}/${total}`,
    chargeDetail: `${total} RDV planifiés`,
    conflicts: conflictCount,
    unassigned,
    late,
  }
})

function toggleMeca(id: number) {
  const idx = activeMecas.value.indexOf(id)
  if (idx >= 0) activeMecas.value.splice(idx, 1)
  else activeMecas.value.push(id)
}

function timeToMin(t: string): number {
  const [h, m] = (t || '0:0').split(':').map(Number)
  return (h || 0) * 60 + (m || 0)
}

function onSelectRdv(rdv: any) {
  selectedRdv.value = rdv
  showRdvModal.value = true
}

onMounted(async () => {
  try {
    const [p, r, m] = await Promise.all([
      api.get('/ponts'),
      api.get('/rendez-vous?itemsPerPage=200'),
      api.get('/mecaniciens').catch(() => []),
    ])
    const pontItems = Array.isArray(p) ? p : (p['hydra:member'] ?? p['member'] ?? [])
    const rdvItems = Array.isArray(r) ? r : (r['hydra:member'] ?? r['member'] ?? [])
    const mecaItems = Array.isArray(m) ? m : (m?.['hydra:member'] ?? m?.member ?? [])
    ponts.value = pontItems
    rawRdvs.value = rdvItems
    mecaniciens.value = mecaItems
  } finally {
    loading.value = false
  }
})
</script>
