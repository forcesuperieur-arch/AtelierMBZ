<template>
  <div>
    <AppPageHeader title="Suivi Live des Interventions" subtitle="Mise à jour en temps réel par mécanicien">
      <template #actions>
        <div class="flex items-center gap-2">
          <div class="live-dot" />
          <span class="text-sm font-semibold text-emerald-400">Live</span>
        </div>
      </template>
    </AppPageHeader>

    <!-- Alert strip -->
    <div class="alert-strip mb-5">
      <div class="alert-chip" :class="enCoursCount > 0 ? 'alert-chip--success' : ''">
        🛠️ En cours: {{ enCoursCount }}
      </div>
      <div class="alert-chip" :class="imminentsCount > 0 ? 'alert-chip--warning' : 'alert-chip--muted'">
        ⏰ Démarrages imminents: {{ imminentsCount }}
      </div>
      <div class="alert-chip" :class="retardsCount > 0 ? 'alert-chip--danger' : 'alert-chip--muted'">
        ⚠️ Retards: {{ retardsCount }}
      </div>
    </div>

    <AppLoadingState
      v-if="loading"
      title="Chargement du suivi"
      description="Récupération des interventions en cours…"
    />

    <AppEmptyState
      v-else-if="!mecaGroups.length"
      icon="i-heroicons-wrench"
      title="Aucune intervention en cours"
      description="Le suivi s'actualisera automatiquement dès qu'un mécanicien démarrera un RDV."
    />

    <div v-else class="flex flex-col gap-4">
      <div
        v-for="group in mecaGroups"
        :key="group.mecanicien_id || 'unassigned'"
        class="meca-group"
      >
        <!-- Mechanic header -->
        <div class="flex items-center gap-3 mb-4">
          <div class="meca-avatar">
            {{ group.initials }}
          </div>
          <div>
            <div class="text-[15px] font-bold text-text-primary">{{ group.mecanicien_nom || 'Non assigné' }}</div>
            <div class="text-xs text-gray-500">{{ group.rdvs.length }} intervention(s) aujourd'hui</div>
          </div>
        </div>

        <!-- Interventions -->
        <div class="flex flex-col gap-2.5">
          <div
            v-for="rdv in group.rdvs"
            :key="rdv.id"
            class="rdv-row"
            :class="{ 'rdv-row--active': rdv.status === 'en_cours' }"
          >
            <!-- Time -->
            <div class="min-w-[50px] text-center">
              <div class="text-sm font-bold text-text-primary">{{ rdv.heure_debut?.slice(0, 5) }}</div>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-text-primary truncate">{{ rdv.client_nom }}</div>
              <div class="text-xs text-gray-500 truncate">{{ rdv.type_intervention }} — {{ rdv.vehicule_info }}</div>
            </div>

            <!-- Progress bar for en_cours -->
            <div v-if="rdv.status === 'en_cours'" class="w-[120px] flex-shrink-0">
              <div class="flex justify-between text-[10px] text-gray-400 mb-0.5">
                <span>{{ getElapsed(rdv) }} min</span>
                <span>{{ rdv.duree_estimee || 60 }} min</span>
              </div>
              <div class="h-1 bg-white/5 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-[width] duration-1000"
                  :class="getProgress(rdv) > 100 ? 'bg-red-500' : 'bg-teal-500'"
                  :style="{ width: getProgress(rdv) + '%' }"
                />
              </div>
            </div>

            <!-- Status -->
            <StatusBadge :status="rdv.status" />

            <!-- Link -->
            <NuxtLink :to="`/planning?openRdv=${rdv.id}`" class="text-amber-400 text-xs font-semibold hover:underline flex-shrink-0">
              Voir →
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const rdvs = ref<any[]>([])
let refreshInterval: ReturnType<typeof setInterval> | null = null

const today = new Date().toISOString().slice(0, 10)

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
    rdvs.value = unwrapHydraOrEmpty(data)
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

<style scoped>
.alert-strip {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.alert-chip {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
  color: #6B7280;
}
.alert-chip--success {
  border-color: rgba(20, 184, 166, 0.25);
  background: rgba(20, 184, 166, 0.06);
  color: #5EEAD4;
}
.alert-chip--warning {
  border-color: rgba(245, 158, 11, 0.25);
  background: rgba(245, 158, 11, 0.06);
  color: #FCD34D;
}
.alert-chip--danger {
  border-color: rgba(239, 68, 68, 0.25);
  background: rgba(239, 68, 68, 0.06);
  color: #FCA5A5;
}
.meca-group {
  background: var(--dark2, #1A1D26);
  border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.06));
  border-radius: 12px;
  padding: 20px;
}
.meca-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(139, 92, 246, 0.15);
  border: 1px solid rgba(139, 92, 246, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  color: #C4B5FD;
  flex-shrink: 0;
}
.rdv-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  border-radius: 10px;
  transition: all 150ms ease;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
}
.rdv-row--active {
  background: rgba(20, 184, 166, 0.06);
  border-color: rgba(20, 184, 166, 0.2);
}
</style>
