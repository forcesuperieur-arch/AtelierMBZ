<template>
  <div>
    <div class="page-header">
      <div>
        <div class="page-title">Suivi Live des Interventions</div>
        <div class="page-sub">Mise à jour en temps réel par mécanicien</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="live-dot"></div>
        <span style="font-size:13px;font-weight:600;color:#10B981;">Live</span>
      </div>
    </div>

    <!-- Alert strip -->
    <div class="alert-strip" style="margin-bottom:20px;">
      <div class="alert-chip" :class="enCoursCount > 0 ? 'success' : ''" style="border-color:rgba(20,184,166,0.25);background:rgba(20,184,166,0.06);color:#5EEAD4;">
        🛠️ En cours: {{ enCoursCount }}
      </div>
      <div class="alert-chip" :class="imminentsCount > 0 ? 'warning' : ''" :style="imminentsCount > 0 ? '' : 'border-color:rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);color:#6B7280;'">
        ⏰ Démarrages imminents: {{ imminentsCount }}
      </div>
      <div class="alert-chip" :class="retardsCount > 0 ? 'danger' : ''" :style="retardsCount > 0 ? '' : 'border-color:rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);color:#6B7280;'">
        ⚠️ Retards: {{ retardsCount }}
      </div>
    </div>

    <div v-if="loading" class="loading-shimmer" style="height:300px;border-radius:14px;"></div>

    <div v-else-if="!mecaGroups.length" style="padding:40px;text-align:center;color:#6B7280;font-size:14px;">
      Aucune intervention en cours
    </div>

    <div v-else style="display:flex;flex-direction:column;gap:16px;">
      <div v-for="group in mecaGroups" :key="group.mecanicien_id || 'unassigned'" style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:20px;">
        <!-- Mechanic header -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
          <div style="width:40px;height:40px;border-radius:50%;background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.3);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#C4B5FD;">
            {{ group.initials }}
          </div>
          <div>
            <div style="font-weight:700;color:#E8E9ED;font-size:15px;">{{ group.mecanicien_nom || 'Non assigné' }}</div>
            <div style="font-size:12px;color:#6B7280;">{{ group.rdvs.length }} intervention(s) aujourd'hui</div>
          </div>
        </div>

        <!-- Interventions -->
        <div style="display:flex;flex-direction:column;gap:10px;">
          <div
            v-for="rdv in group.rdvs"
            :key="rdv.id"
            style="display:flex;align-items:center;gap:16px;padding:12px 16px;border-radius:10px;transition:all 0.15s;"
            :style="{
              background: rdv.status === 'en_cours' ? 'rgba(20,184,166,0.06)' : 'rgba(255,255,255,0.02)',
              border: rdv.status === 'en_cours' ? '1px solid rgba(20,184,166,0.2)' : '1px solid rgba(255,255,255,0.06)',
            }"
          >
            <!-- Time -->
            <div style="min-width:50px;text-align:center;">
              <div style="font-size:14px;font-weight:700;color:#E8E9ED;">{{ rdv.heure_debut?.slice(0, 5) }}</div>
            </div>

            <!-- Info -->
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:600;color:#E8E9ED;">{{ rdv.client_nom }}</div>
              <div style="font-size:12px;color:#6B7280;">{{ rdv.type_intervention }} — {{ rdv.vehicule_info }}</div>
            </div>

            <!-- Progress bar for en_cours -->
            <div v-if="rdv.status === 'en_cours'" style="width:120px;">
              <div style="display:flex;justify-content:space-between;font-size:10px;color:#9CA3AF;margin-bottom:3px;">
                <span>{{ formatMinutes(getElapsed(rdv)) }}</span>
                <span>{{ formatMinutes(rdv.duree_estimee || 60) }}</span>
              </div>
              <div style="height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;">
                <div style="height:100%;border-radius:2px;transition:width 1s;" :style="{ width: getProgress(rdv) + '%', background: getProgress(rdv) > 100 ? '#EF4444' : '#14B8A6' }"></div>
              </div>
            </div>

            <!-- Status -->
            <StatusBadge :status="rdv.status" />

            <!-- Link -->
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openRdvDetail(rdv)">Voir →</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const rdvs = ref<any[]>([])
let refreshInterval: ReturnType<typeof setInterval> | null = null

const today = todayLocalISO()

const normalizedRdvs = computed(() => rdvs.value.map(r => ({
  ...r,
  status: r.statut ?? r.status,
  client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
  vehicule_info: r.vehicule ? `${r.vehicule.marque ?? ''} ${r.vehicule.modele ?? ''}`.trim() : (r.vehicule_info ?? ''),
  heure_debut: r.heure_rdv ?? r.heure_debut,
  mecanicien_id: r.mecanicien?.id ?? r.mecanicien_id,
  mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : '',
  duree_estimee: r.temps_estime ?? r.duree_estimee ?? 60,
  heure_debut_travail: r.heure_debut_travail,
})))

const enCoursCount = computed(() => normalizedRdvs.value.filter(r => r.status === 'en_cours').length)

const imminentsCount = computed(() => {
  const now = new Date()
  return normalizedRdvs.value.filter(r => {
    if (r.status !== 'confirme' && r.status !== 'reception') return false
    const h = r.heure_debut?.split(':')
    if (!h) return false
    const scheduled = new Date()
    scheduled.setHours(Number(h[0]), Number(h[1]), 0)
    const diff = scheduled.getTime() - now.getTime()
    return diff > 0 && diff < 900000 // 15 min
  }).length
})

const retardsCount = computed(() => {
  const now = new Date()
  return normalizedRdvs.value.filter(r => {
    if (!['confirme', 'reception'].includes(r.status)) return false
    const h = r.heure_debut?.split(':')
    if (!h) return false
    const scheduled = new Date()
    scheduled.setHours(Number(h[0]), Number(h[1]), 0)
    return (now.getTime() - scheduled.getTime()) > 600000 // 10 min late
  }).length
})

const mecaGroups = computed(() => {
  const map = new Map<string, any>()
  for (const r of normalizedRdvs.value) {
    const key = r.mecanicien_id ? String(r.mecanicien_id) : 'unassigned'
    if (!map.has(key)) {
      const nameParts = (r.mecanicien_nom || '').split(' ')
      map.set(key, {
        mecanicien_id: r.mecanicien_id,
        mecanicien_nom: r.mecanicien_nom,
        initials: nameParts.map((p: string) => p[0] || '').join('').toUpperCase().slice(0, 2) || '??',
        rdvs: [],
      })
    }
    map.get(key)!.rdvs.push(r)
  }
  // Sort: en_cours first
  for (const g of map.values()) {
    g.rdvs.sort((a: any, b: any) => {
      if (a.status === 'en_cours' && b.status !== 'en_cours') return -1
      if (b.status === 'en_cours' && a.status !== 'en_cours') return 1
      return (a.heure_debut || '').localeCompare(b.heure_debut || '')
    })
  }
  return Array.from(map.values())
})

function getElapsed(rdv: any): number {
  if (!rdv.heure_debut_travail) {
    const h = rdv.heure_debut?.split(':')
    if (!h) return 0
    const start = new Date()
    start.setHours(Number(h[0]), Number(h[1]), 0)
    return Math.max(0, Math.round((Date.now() - start.getTime()) / 60000))
  }
  const start = new Date(rdv.heure_debut_travail)
  // Safari ne parse pas 'YYYY-MM-DD HH:MM:SS' → Invalid Date → 'NaNmin'
  if (Number.isNaN(start.getTime())) return 0
  return Math.max(0, Math.round((Date.now() - start.getTime()) / 60000))
}

function getProgress(rdv: any): number {
  const elapsed = getElapsed(rdv)
  const est = rdv.duree_estimee || 60
  return Math.round((elapsed / est) * 100)
}

async function fetchData() {
  try {
    const data = await api.get(`/rendez-vous?date_rdv=${today}&itemsPerPage=200`)
    const items = Array.isArray(data) ? data : (data?.['hydra:member'] ?? data?.member ?? [])
    rdvs.value = items
  } catch {}
}

onMounted(async () => {
  await fetchData()
  loading.value = false
  refreshInterval = setInterval(fetchData, 15000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>
