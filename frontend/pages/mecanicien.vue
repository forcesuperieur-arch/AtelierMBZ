<template>
  <div>
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(139,92,246,0.15);border:2px solid rgba(139,92,246,0.3);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#C4B5FD;">{{ initials }}</div>
        <div>
          <div class="page-title">Espace Mécanicien</div>
          <div style="font-size:12px;color:#6B7280;">{{ todayLabel }}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">EN COURS</div>
          <div style="font-size:18px;font-weight:700;color:#F59E0B;">{{ kpis.enCours }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">À FAIRE</div>
          <div style="font-size:18px;font-weight:700;color:#E8E9ED;">{{ kpis.aFaire }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">TERMINÉS</div>
          <div style="font-size:18px;font-weight:700;color:#10B981;">{{ kpis.termines }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">JOURNÉE</div>
          <div style="font-size:18px;font-weight:700;color:#FFD200;">{{ kpis.pctDone }}%</div>
        </div>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else>
      <!-- Priority card -->
      <div v-if="priorityAction" style="margin-bottom:20px;padding:14px;border-radius:12px;background:rgba(255,210,0,0.06);border:1px solid rgba(255,210,0,0.15);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <span style="font-size:14px;">⚡</span>
          <span style="font-size:13px;font-weight:600;color:#FFD200;">Prochaine action</span>
        </div>
        <p style="font-size:13px;color:#D1D5DB;">{{ priorityAction }}</p>
      </div>

      <!-- Active intervention -->
      <UCard v-if="activeRdv" style="margin-bottom:24px;border-color:rgba(245,158,11,0.3);">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:15px;font-weight:700;color:#F59E0B;">🔧 Intervention en cours</span>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <span v-if="activeRdv.client_telephone" style="font-size:12px;">
                <a :href="`tel:${activeRdv.client_telephone}`" style="color:#6B7280;text-decoration:none;">📞 Appeler</a>
              </span>
              <NuxtLink v-if="activeOrId" :to="`/ordres/${activeOrId}`" style="font-size:12px;color:#FFD200;text-decoration:none;font-weight:600;">📋 Dossier atelier</NuxtLink>
              <UButton label="💾 Rapport" color="info" variant="outline" size="sm" @click="persistWorkshopReport()" :loading="persistingCheckup" />
              <UButton label="✅ Terminer" color="success" size="sm" @click="finishWork" :loading="finishing" />
            </div>
          </div>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
          <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ activeRdv.client_nom }}</span></div>
          <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ activeRdv.vehicule_info }}</span></div>
          <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ activeRdv.type_intervention }}</span></div>
          <div><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ activeRdv.pont_nom }}</span></div>
        </div>
        <div v-if="activeRdv.description_probleme || activeRdv.commentaire" style="margin-top:12px;font-size:13px;">
          <span style="color:#6B7280;">Description :</span>
          <p style="color:#D1D5DB;">{{ activeRdv.description_probleme || activeRdv.commentaire }}</p>
        </div>

        <!-- Live Chrono -->
        <div v-if="activeRdv.temps_estime" style="margin-top:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#9CA3AF;margin-bottom:6px;">
            <span>Chrono</span>
            <span style="font-family:monospace;font-size:16px;font-weight:700;" :style="{ color: progressPct > 100 ? '#EF4444' : '#FFD200' }">{{ chronoDisplay }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:#9CA3AF;margin-bottom:4px;">
            <span>Progression</span>
            <span :style="{ color: progressPct > 100 ? '#EF4444' : '#FFD200' }">{{ progressPct }}%</span>
          </div>
          <div style="background:var(--dark3,#171B24);border-radius:8px;height:10px;overflow:hidden;">
            <div :style="{ width: Math.min(progressPct, 100) + '%', height: '100%', background: progressPct > 100 ? '#EF4444' : '#FFD200', borderRadius: '8px', transition: 'width 1s ease' }"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#6B7280;margin-top:4px;">
            <span>{{ elapsedMin }}min écoulées</span>
            <span>{{ activeRdv.temps_estime }}min estimées</span>
          </div>
          <div v-if="progressPct > 100" style="margin-top:8px;padding:8px 12px;border-radius:8px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);font-size:12px;color:#FCA5A5;">
            ⚠️ Dépassement +{{ elapsedMin - activeRdv.temps_estime }}min — intervention en retard
          </div>
        </div>

        <!-- Checkup Express -->
        <div style="margin-top:20px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:13px;font-weight:600;color:#E8E9ED;">Checkup Express</span>
            <span style="font-size:11px;color:#6B7280;">{{ checkupDone }}/{{ checkupItems.length }} vérifiés</span>
          </div>
          <div style="font-size:11px;color:#6B7280;margin-bottom:8px;">Le rapport est enregistré dans le dossier atelier.</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div v-for="item in checkupItems" :key="item.key" style="display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.04);font-size:12px;cursor:pointer;" :style="{ background: checkup[item.key] === 'ok' ? 'rgba(16,185,129,0.06)' : checkup[item.key] === 'nok' ? 'rgba(239,68,68,0.06)' : 'transparent' }" @click="cycleCheckup(item.key)">
              <span v-if="checkup[item.key] === 'ok'" style="color:#10B981;">✅</span>
              <span v-else-if="checkup[item.key] === 'nok'" style="color:#EF4444;">❌</span>
              <span v-else style="color:#6B7280;">⬜</span>
              <span style="color:#D1D5DB;">{{ item.label }}</span>
            </div>
          </div>
        </div>

        <!-- Intervention notes -->
        <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <label style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:6px;display:block;">Notes intervention</label>
          <textarea v-model="interventionNotes" class="form-input" rows="2" placeholder="Notes techniques, observations…" />
          <div style="display:flex;justify-content:flex-end;margin-top:6px;">
            <button class="btn btn-ghost" style="font-size:12px;" @click="saveInterventionNotes" :disabled="savingNotes">{{ savingNotes ? 'Sauvegarde…' : 'Sauvegarder' }}</button>
          </div>
        </div>
      </UCard>

      <!-- Todo: RDVs to do -->
      <UCard style="margin-bottom:24px;">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📋 À faire ({{ todoRdvs.length }})</span>
        </template>
        <div v-if="!todoRdvs.length" style="padding:16px;text-align:center;color:#6B7280;">
          Toutes les interventions sont terminées 🎉
        </div>
        <div v-else style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="rdv in todoRdvs" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);">
            <div>
              <p style="font-weight:600;color:#E8E9ED;font-size:13px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</p>
              <p style="font-size:12px;color:#6B7280;">{{ rdv.vehicule_info }} — {{ rdv.type_intervention }}</p>
              <p v-if="rdv.temps_estime" style="font-size:11px;color:#9CA3AF;">⏱ {{ rdv.temps_estime }}min</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <StatusBadge :status="rdv.status" />
              <UButton v-if="rdv.status === 'reception'" size="xs" label="🔧 Démarrer" @click="startWork(rdv.id)" />
            </div>
          </div>
        </div>
      </UCard>

      <!-- Done: Completed RDVs -->
      <UCard v-if="doneRdvs.length">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#10B981;">✅ Terminés ({{ doneRdvs.length }})</span>
        </template>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div v-for="rdv in doneRdvs" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);opacity:0.7;font-size:13px;">
            <div>
              <span style="color:#10B981;">✅</span>
              <span style="color:#D1D5DB;margin-left:8px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }} · {{ rdv.type_intervention }}</span>
            </div>
            <NuxtLink :to="`/rdv/${rdv.id}`" style="color:#FFD200;font-size:11px;text-decoration:none;font-weight:600;">Voir →</NuxtLink>
          </div>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const rdvStore = useRdvStore()
const toast = useToast()
const auth = useAuth()
const loading = ref(true)
const finishing = ref(false)
const savingNotes = ref(false)
const persistingCheckup = ref(false)
const myRdvs = ref<any[]>([])
const ordresByRdvId = ref<Record<number, any>>({})
const interventionNotes = ref('')
const now = ref(Date.now())
let chronoTimer: ReturnType<typeof setInterval> | null = null

const checkupItems = [
  { key: 'pneus', label: 'Pneus' },
  { key: 'freins', label: 'Freins' },
  { key: 'huile', label: 'Huile' },
  { key: 'eclairage', label: 'Éclairage' },
  { key: 'batterie', label: 'Batterie' },
  { key: 'chaine', label: 'Chaîne' },
  { key: 'liquides', label: 'Liquides' },
  { key: 'suspension', label: 'Suspension' },
  { key: 'cablerie', label: 'Câblerie' },
  { key: 'general', label: 'État général' },
]
const checkup = reactive<Record<string, string>>({})
const checkupDone = computed(() => Object.values(checkup).filter(v => v === 'ok' || v === 'nok').length)

function cycleCheckup(key: string) {
  if (!checkup[key]) checkup[key] = 'ok'
  else if (checkup[key] === 'ok') checkup[key] = 'nok'
  else checkup[key] = ''
}

const initials = computed(() => {
  const u = auth.user?.value
  if (!u) return 'M'
  return ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase() || 'M'
})

const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))

const activeRdv = computed(() => myRdvs.value.find(r => r.status === 'en_cours'))
const activeOr = computed(() => activeRdv.value ? ordresByRdvId.value[activeRdv.value.id] ?? null : null)
const activeOrId = computed(() => activeRdv.value?.or_id ?? activeOr.value?.id ?? null)
const activeVehiculeState = computed(() => parseEtatVehicule(activeOr.value?.etat_vehicule))
const receptionPoints = computed(() => Array.isArray(activeVehiculeState.value?.points) ? activeVehiculeState.value.points : [])
const receptionObservations = computed(() => activeVehiculeState.value?.observations ?? '')
const receptionFuelLevel = computed(() => activeVehiculeState.value?.fuel_level ?? '')
const receptionPriority = computed(() => activeVehiculeState.value?.priority ?? '')
const todoRdvs = computed(() => myRdvs.value.filter(r => ['en_attente', 'reserve', 'confirme', 'reception'].includes(r.status)))
const doneRdvs = computed(() => myRdvs.value.filter(r => ['termine', 'restitue', 'facture', 'paye'].includes(r.status)))

const kpis = computed(() => ({
  enCours: activeRdv.value ? 1 : 0,
  aFaire: todoRdvs.value.length,
  termines: doneRdvs.value.length,
  pctDone: myRdvs.value.length ? Math.round(doneRdvs.value.length / myRdvs.value.length * 100) : 0,
}))

const priorityAction = computed(() => {
  const receptions = todoRdvs.value.filter(r => r.status === 'reception')
  if (receptions.length) return `Réceptionner : ${receptions[0].client_nom} — ${receptions[0].vehicule_info}`
  if (activeRdv.value && progressPct.value > 100) return `⚠️ Intervention en cours en retard — terminer rapidement`
  if (todoRdvs.value.length) return `Prochain RDV à ${todoRdvs.value[0].heure_debut?.slice(0, 5)} — ${todoRdvs.value[0].client_nom}`
  return null
})

const elapsedMin = computed(() => {
  const rdv = activeRdv.value
  if (!rdv) return 0
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((now.value - startTime.getTime()) / 60000)
})

const progressPct = computed(() => {
  const rdv = activeRdv.value
  if (!rdv?.temps_estime) return 0
  return Math.round(elapsedMin.value / rdv.temps_estime * 100)
})

const chronoDisplay = computed(() => {
  const min = elapsedMin.value
  const h = Math.floor(min / 60)
  const m = min % 60
  const s = Math.floor(((now.value - getStartTime()) % 60000) / 1000)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(Math.max(0, s)).padStart(2, '0')}`
})

function getStartTime(): number {
  const rdv = activeRdv.value
  if (!rdv) return Date.now()
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return Date.now()
  const t = new Date(started).getTime()
  return isNaN(t) ? Date.now() : t
}

function extractRdvIdFromOrdre(item: any): number | null {
  const rdvRef = item?.rendez_vous ?? item?.rendezVous ?? null
  if (!rdvRef) return null
  if (typeof rdvRef === 'string') {
    const parsed = Number(rdvRef.split('/').pop())
    return Number.isFinite(parsed) ? parsed : null
  }
  const parsed = Number(rdvRef?.id ?? rdvRef?.['@id']?.split?.('/')?.pop?.())
  return Number.isFinite(parsed) ? parsed : null
}

function parseEtatVehicule(raw: any) {
  if (!raw) return {}
  if (typeof raw === 'string') {
    try { return JSON.parse(raw) } catch { return {} }
  }
  return raw
}

function applySavedWorkshopReport() {
  Object.keys(checkup).forEach((key) => { delete checkup[key] })
  const saved = parseEtatVehicule(activeOr.value?.etat_vehicule)
  const savedCheckup = saved?.mechanic_checkup ?? {}
  Object.entries(savedCheckup).forEach(([key, value]) => {
    if (value) checkup[key] = String(value)
  })
  interventionNotes.value = saved?.mechanic_notes ?? activeRdv.value?.commentaire ?? ''
}

async function ensureOrForRdv(rdvItem: any) {
  if (!rdvItem?.id) return null
  const existing = ordresByRdvId.value[rdvItem.id]
  if (existing) return existing

  const datePart = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  const payload = {
    rendez_vous: `/api/rendez-vous/${rdvItem.id}`,
    numero_or: `OR-${datePart}-${String(rdvItem.id).padStart(4, '0')}`,
    type_or: 'initial',
    travaux: rdvItem.description_probleme || rdvItem.commentaire || rdvItem.type_intervention || '',
  }

  const created = await api.post('/ordres-reparation', payload)
  ordresByRdvId.value = { ...ordresByRdvId.value, [rdvItem.id]: created }
  return created
}

async function persistWorkshopReport(showToast = true) {
  if (!activeRdv.value) return
  persistingCheckup.value = true
  try {
    const orItem = await ensureOrForRdv(activeRdv.value)
    const current = parseEtatVehicule(orItem?.etat_vehicule)
    const updated = {
      ...current,
      mechanic_checkup: { ...checkup },
      mechanic_notes: interventionNotes.value,
      last_mechanic_update_at: new Date().toISOString(),
    }

    if (orItem?.id) {
      await api.put(`/ordres-reparation/${orItem.id}`, { etat_vehicule: updated })
      ordresByRdvId.value = {
        ...ordresByRdvId.value,
        [activeRdv.value.id]: {
          ...orItem,
          etat_vehicule: updated,
        },
      }
    }

    await rdvStore.updateRdv(activeRdv.value.id, { commentaire: interventionNotes.value })

    if (showToast) {
      toast.add({ title: 'Rapport atelier sauvegardé', color: 'success' })
    }
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    persistingCheckup.value = false
  }
}

async function startWork(id: number) {
  try {
    await rdvStore.transitionRdv(id, 'start_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Travaux démarrés', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function finishWork() {
  if (!activeRdv.value) return
  if (!checkupDone.value && !interventionNotes.value.trim()) {
    toast.add({ title: 'Rapport atelier requis', description: 'Ajoutez au moins un point de contrôle ou une note avant de terminer.', color: 'warning' })
    return
  }
  finishing.value = true
  try {
    await persistWorkshopReport(false)
    await rdvStore.transitionRdv(activeRdv.value.id, 'terminer')
    await fetchMyRdvs()
    toast.add({ title: 'Intervention terminée', color: 'success' })
  } finally {
    finishing.value = false
  }
}

async function saveInterventionNotes() {
  if (!activeRdv.value) return
  savingNotes.value = true
  try {
    await persistWorkshopReport(false)
    toast.add({ title: 'Notes sauvegardées', color: 'success' })
  } finally {
    savingNotes.value = false
  }
}

async function fetchMyRdvs() {
  const today = new Date().toISOString().slice(0, 10)
  const [rdvData, ordresData] = await Promise.all([
    api.get(`/rendez-vous/mecanicien?date=${today}`),
    api.get('/ordres-reparation').catch(() => []),
  ])

  const ordres = Array.isArray(ordresData) ? ordresData : (ordresData?.['hydra:member'] ?? ordresData?.member ?? [])
  const rdvOrderMap: Record<number, any> = {}
  for (const item of ordres) {
    const rdvId = extractRdvIdFromOrdre(item)
    if (rdvId) rdvOrderMap[rdvId] = item
  }
  ordresByRdvId.value = rdvOrderMap

  const items = Array.isArray(rdvData) ? rdvData : (rdvData?.['hydra:member'] ?? rdvData?.member ?? [])
  myRdvs.value = items.map((r: any) => ({
    ...r,
    status: r.statut ?? r.status,
    heure_debut: r.heure_rdv ?? r.heure_debut,
    temps_estime: r.temps_estime ?? r.duree_estimee ?? 60,
    or_id: rdvOrderMap[r.id]?.id ?? r.or_id ?? null,
  }))
}

watch(activeRdv, () => {
  applySavedWorkshopReport()
})

onMounted(async () => {
  try {
    await fetchMyRdvs()
    applySavedWorkshopReport()
    chronoTimer = setInterval(() => { now.value = Date.now() }, 1000)
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (chronoTimer) clearInterval(chronoTimer)
})
</script>
