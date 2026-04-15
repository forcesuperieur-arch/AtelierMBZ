<template>
  <div>
    <div class="page-header">
      <div class="page-title">Atelier</div>
    </div>

    <div v-if="loading" class="loading-shimmer" style="height:400px;border-radius:14px;"></div>

    <template v-else>
      <!-- KPI Bar -->
      <div class="workshop-kpi-bar">
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">OCCUPATION PONTS</div>
          <div class="workshop-kpi-value">{{ kpis.occupation }}%</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">RDV AUJOURD'HUI</div>
          <div class="workshop-kpi-value">{{ kpis.rdvsToday }}</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">MÉCANICIENS ACTIFS</div>
          <div class="workshop-kpi-value">{{ kpis.activeMecas }}</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">CONFLITS</div>
          <div class="workshop-kpi-value" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.conflicts }}</div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab" :class="{ active: activeTab === 'ponts' }" @click="activeTab = 'ponts'">🔧 Ponts</button>
        <button class="tab" :class="{ active: activeTab === 'mecas' }" @click="activeTab = 'mecas'">👤 Mécaniciens</button>
        <button class="tab" :class="{ active: activeTab === 'temps' }" @click="activeTab = 'temps'">⏱ Temps par type</button>
        <button class="tab" :class="{ active: activeTab === 'absences' }" @click="activeTab = 'absences'">📅 Absences</button>
      </div>

      <!-- PONTS TAB -->
      <div v-if="activeTab === 'ponts'" class="pont-grid">
        <div v-for="pont in ponts" :key="pont.id" class="pont-card" :class="pont.current_rdv ? 'pont-occupe' : 'pont-libre'">
          <div class="pont-card-header">
            <span class="pont-name">{{ pont.nom }}</span>
            <StatusBadge :status="pont.current_rdv ? 'en_cours' : 'termine'" />
          </div>
          <div v-if="pont.current_rdv" class="pont-card-body">
            <p style="font-weight:600;color:#E8E9ED;font-size:14px;">{{ pont.current_rdv.client_nom }}</p>
            <p style="color:#6B7280;font-size:12px;">{{ pont.current_rdv.vehicule_info }}</p>
            <p style="color:#6B7280;font-size:12px;">{{ pont.current_rdv.type_intervention }}</p>
            <div style="margin-top:6px;"><StatusBadge :status="pont.current_rdv.status" /></div>
            <NuxtLink :to="`/rdv/${pont.current_rdv.id}`" style="display:inline-block;margin-top:8px;color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir RDV →</NuxtLink>
            <!-- Progress bar -->
            <div v-if="pont.current_rdv.temps_estime" style="margin-top:8px;">
              <div style="background:var(--dark3,#171B24);border-radius:6px;height:6px;overflow:hidden;">
                <div :style="{ width: Math.min(pontProgress(pont), 100) + '%', height: '100%', background: pontProgress(pont) > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px' }"></div>
              </div>
              <div style="font-size:10px;color:#9CA3AF;margin-top:2px;">{{ pontProgress(pont) }}% · {{ pont.current_rdv.temps_estime }}min</div>
            </div>
          </div>
          <div v-else class="pont-card-body">
            <p style="color:#6B7280;font-size:13px;">Aucune intervention en cours</p>
          </div>
          <div class="pont-card-footer">
            Prochains: {{ pont.next_count ?? 0 }} RDV aujourd'hui
          </div>
        </div>
      </div>

      <!-- MECAS TAB -->
      <div v-if="activeTab === 'mecas'" class="meca-grid">
        <div v-for="m in enrichedMecas" :key="m.id" class="meca-card">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;" :style="{ background: m.statusColor + '15', border: '1px solid ' + m.statusColor + '30', color: m.statusColor }">
              {{ (m.prenom?.[0] ?? '') + (m.nom?.[0] ?? '') }}
            </div>
            <div style="flex:1;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-weight:700;color:#E8E9ED;">{{ m.prenom }} {{ m.nom }}</span>
                <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:9999px;" :style="{ background: m.statusColor + '20', color: m.statusColor }">{{ m.statusLabel }}</span>
              </div>
              <div style="font-size:12px;color:#6B7280;">{{ m.specialite ?? 'Mécanicien' }}</div>
            </div>
          </div>
          <!-- Specialties -->
          <div v-if="m.specialites?.length" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;">
            <span v-for="s in m.specialites" :key="s" style="font-size:10px;padding:2px 8px;border-radius:9999px;background:rgba(139,92,246,0.12);color:#C4B5FD;">{{ s }}</span>
          </div>
          <!-- Current intervention -->
          <div v-if="m.currentRdv" style="padding:8px 10px;border-radius:8px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15);font-size:12px;margin-bottom:10px;">
            <div style="color:#F59E0B;font-weight:600;margin-bottom:4px;">🔧 En intervention</div>
            <div style="color:#D1D5DB;">{{ m.currentRdv.client_nom ?? 'Client' }} — {{ m.currentRdv.vehicule_info ?? m.currentRdv.type_intervention }}</div>
            <div v-if="m.currentRdv.temps_estime" style="margin-top:6px;">
              <div style="background:var(--dark3,#171B24);border-radius:6px;height:6px;overflow:hidden;">
                <div :style="{ width: Math.min(m.progressPct, 100) + '%', height: '100%', background: m.progressPct > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px' }"></div>
              </div>
              <div style="font-size:10px;color:#9CA3AF;margin-top:2px;">{{ m.progressPct }}%</div>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:#9CA3AF;">
            <span>📧 {{ m.email ?? '–' }}</span>
            <span>{{ m.rdvCount }} RDV aujourd'hui</span>
          </div>
        </div>
        <div v-if="!mecaniciens.length" style="color:#6B7280;padding:24px;text-align:center;">Aucun mécanicien configuré</div>
      </div>

      <!-- TEMPS TAB -->
      <div v-if="activeTab === 'temps'">
        <UCard>
          <div style="color:#6B7280;padding:16px;text-align:center;">
            Les temps par type d'intervention sont affichés dans la section Tarifs.
            <NuxtLink to="/tarifs" style="color:#FFD200;font-weight:600;text-decoration:none;margin-left:6px;">Voir Tarifs →</NuxtLink>
          </div>
        </UCard>
      </div>

      <!-- ABSENCES TAB -->
      <div v-if="activeTab === 'absences'">
        <UCard>
          <UTable v-if="absences.length" :data="absences" :columns="absenceCols" />
          <div v-else style="color:#6B7280;padding:16px;text-align:center;">Aucune absence enregistrée</div>
        </UCard>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const activeTab = ref('ponts')
const ponts = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const absences = ref<any[]>([])
const rdvs = ref<any[]>([])

const absenceCols = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
]

const kpis = computed(() => {
  const total = ponts.value.length
  const occupied = ponts.value.filter(p => p.current_rdv).length
  const occupation = total ? Math.round(occupied / total * 100) : 0
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => r.date_rdv === today)
  const mecaIds = new Set(ponts.value.filter(p => p.current_rdv?.mecanicien_id).map(p => p.current_rdv.mecanicien_id))
  return {
    occupation,
    rdvsToday: todayRdvs.length,
    activeMecas: mecaIds.size,
    conflicts: 0,
  }
})

const enrichedMecas = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => r.date_rdv === today)
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = a.date_debut?.slice?.(0, 10) ?? ''
    const end = a.date_fin?.slice?.(0, 10) ?? ''
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))

  return mecaniciens.value.map((m: any) => {
    const mecaRdvs = todayRdvs.filter((r: any) => {
      const mid = r.mecanicien?.id ?? r.mecanicien_id
      return mid === m.id
    })
    const currentRdv = mecaRdvs.find((r: any) => r.statut === 'en_cours' || r.status === 'en_cours')
    const isAbsent = absentIds.has(m.id)
    const isWorking = !!currentRdv
    let progressPct = 0
    if (currentRdv?.temps_estime && (currentRdv.heure_debut_travaux || currentRdv.started_at)) {
      const started = new Date(currentRdv.heure_debut_travaux || currentRdv.started_at)
      if (!isNaN(started.getTime())) {
        progressPct = Math.round((Date.now() - started.getTime()) / 60000 / currentRdv.temps_estime * 100)
      }
    }
    return {
      ...m,
      rdvCount: mecaRdvs.length,
      currentRdv,
      progressPct,
      statusLabel: isAbsent ? 'Absent' : isWorking ? 'En intervention' : 'Disponible',
      statusColor: isAbsent ? '#EF4444' : isWorking ? '#F59E0B' : '#22C55E',
      specialites: m.specialites ?? m.competences ?? [],
    }
  })
})

function pontProgress(pont: any) {
  const rdv = pont.current_rdv
  if (!rdv?.temps_estime) return 0
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((Date.now() - startTime.getTime()) / 60000 / rdv.temps_estime * 100)
}

onMounted(async () => {
  try {
    const [p, m, a, r] = await Promise.all([
      api.get('/ponts/status').catch(() => []),
      api.get('/mecaniciens').catch(() => []),
      api.get('/absences').catch(() => []),
      api.get('/rendez-vous?itemsPerPage=200').catch(() => []),
    ])
    ponts.value = Array.isArray(p) ? p : (p?.['hydra:member'] ?? p?.member ?? [])
    const mecaRaw = Array.isArray(m) ? m : (m?.['hydra:member'] ?? m?.member ?? [])
    mecaniciens.value = mecaRaw
    const absRaw = Array.isArray(a) ? a : (a?.['hydra:member'] ?? a?.member ?? [])
    absences.value = absRaw.map((ab: any) => ({
      ...ab,
      mecanicien_nom: ab.mecanicien ? `${ab.mecanicien.prenom ?? ''} ${ab.mecanicien.nom ?? ''}`.trim() : '–',
    }))
    rdvs.value = Array.isArray(r) ? r : (r?.['hydra:member'] ?? r?.member ?? [])
  } finally {
    loading.value = false
  }
})
</script>
