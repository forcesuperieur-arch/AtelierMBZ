<template>
  <div>
    <div class="page-header" style="justify-content:space-between;">
      <div>
        <div class="page-title">Atelier</div>
        <div class="page-sub">Pilotage des ponts, affectations mécaniciens et charge du jour.</div>
      </div>
    </div>

    <AppLoadingState
      v-if="loading"
      title="Chargement de l’atelier"
      description="Les statuts des ponts et de l’équipe sont en cours de récupération."
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="Atelier temporairement indisponible"
      :description="errorMessage"
      @retry="loadWorkshop"
    />

    <template v-else>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-ghost" :disabled="refreshing" @click="refreshWorkshop">{{ refreshing ? 'Actualisation…' : '↻ Actualiser' }}</button>
          <NuxtLink class="btn btn-primary" to="/planning" style="text-decoration:none;">Ouvrir le planning</NuxtLink>
          <NuxtLink class="btn btn-ghost" :to="buildPlanningCreateLink()" style="text-decoration:none;">+ RDV rapide</NuxtLink>
        </div>
        <div style="font-size:12px;color:#9CA3AF;">Mis à jour {{ lastUpdatedAt || 'à l’instant' }}</div>
      </div>

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
      <div v-if="activeTab === 'ponts'">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
          <p style="margin:0;color:#9CA3AF;font-size:13px;">Ici tu actives ou désactives un pont et tu changes le mécanicien rattaché sans passer par l’admin.</p>
          <NuxtLink to="/planning" style="color:#FFD200;font-size:12px;font-weight:700;text-decoration:none;">Voir le planning →</NuxtLink>
        </div>

        <div v-if="enrichedPonts.length" class="pont-grid">
          <div
            v-for="pont in enrichedPonts"
            :key="pont.id"
            class="pont-card"
            :class="!isActiveFlag(pont.is_active ?? pont.est_actif) ? 'pont-maintenance' : (pont.current_rdv || pont.day_schedule.length ? 'pont-occupe' : 'pont-libre')"
          >
            <div class="pont-card-header">
              <span class="pont-name">{{ pont.nom }}</span>
              <span class="status-badge" :style="pontBadgeStyle(pont)">{{ pontBadgeLabel(pont) }}</span>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px;color:#9CA3AF;margin-bottom:10px;">
              <span>Type {{ (pont.type_pont || 'atelier').toString().toUpperCase() }}</span>
              <span>•</span>
              <span>{{ pont.capacite_kg ? `${pont.capacite_kg} kg` : 'Capacité n.c.' }}</span>
            </div>

            <div class="pont-card-body">
              <div style="padding:10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);margin-bottom:10px;display:grid;gap:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                  <div style="font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;">Configuration pont</div>
                  <button
                    class="btn btn-ghost"
                    style="padding:5px 10px;min-height:32px;"
                    :disabled="pontSettingSaving[pont.id]"
                    @click="togglePontActivation(pont)"
                  >
                    {{ pontSettings[pont.id]?.is_active ? 'Désactiver' : 'Activer' }}
                  </button>
                </div>

                <div>
                  <div style="font-size:11px;color:#9CA3AF;margin-bottom:4px;">Mécanicien rattaché</div>
                  <select v-model="pontSettings[pont.id].mecanicien_id" class="form-input" style="min-height:38px;">
                    <option :value="null">Aucun</option>
                    <option v-for="m in activeMecaniciens" :key="`pont-meca-${pont.id}-${m.id}`" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
                  </select>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                  <button class="btn btn-primary" style="padding:6px 12px;min-height:34px;" :disabled="pontSettingSaving[pont.id]" @click="savePontSettings(pont)">
                    {{ pontSettingSaving[pont.id] ? 'Enregistrement…' : 'Enregistrer l’affectation' }}
                  </button>
                </div>
              </div>

              <div style="padding:8px 10px;border-radius:8px;background:rgba(255,210,0,0.06);border:1px solid rgba(255,210,0,0.14);margin-bottom:10px;font-size:12px;color:#E8E9ED;">
                👤 {{ pont.assigned_meca ? `${pont.assigned_meca.prenom ?? ''} ${pont.assigned_meca.nom ?? ''}`.trim() : 'Aucun mécanicien assigné' }}
              </div>

              <div v-if="pont.current_rdv" style="margin-bottom:10px;">
                <p style="font-weight:700;color:#E8E9ED;font-size:14px;margin:0 0 4px 0;">{{ rdvClientName(pont.current_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0 0 2px 0;">{{ rdvVehicleLabel(pont.current_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0;">Intervention en cours · {{ pont.current_rdv.type_intervention || 'atelier' }}</p>
                <div style="margin-top:6px;"><StatusBadge :status="pont.current_rdv.status ?? pont.current_rdv.statut" /></div>
                <button style="display:inline-block;margin-top:8px;color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openRdvDetail(pont.current_rdv)">Ouvrir le RDV →</button>
                <div v-if="pont.current_rdv.temps_estime" style="margin-top:8px;">
                  <div style="background:var(--dark3,#171B24);border-radius:6px;height:6px;overflow:hidden;">
                    <div :style="{ width: Math.min(pontProgress(pont), 100) + '%', height: '100%', background: pontProgress(pont) > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px' }"></div>
                  </div>
                  <div style="font-size:10px;color:#9CA3AF;margin-top:2px;">{{ pontProgress(pont) }}% · {{ formatMinutes(pont.current_rdv.temps_estime) }} estimées</div>
                </div>
              </div>

              <div v-else-if="pont.next_rdv" style="margin-bottom:10px;">
                <p style="font-weight:700;color:#E8E9ED;font-size:14px;margin:0 0 4px 0;">Prochain passage à {{ formatHourLabel(pont.next_rdv.heure_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0 0 2px 0;">{{ rdvClientName(pont.next_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0;">{{ pont.next_rdv.type_intervention || 'atelier' }} · {{ rdvVehicleLabel(pont.next_rdv) }}</p>
              </div>

              <div v-else style="margin-bottom:10px;">
                <p style="color:#9CA3AF;font-size:13px;margin:0;">{{ isActiveFlag(pont.is_active ?? pont.est_actif) ? 'Aucun RDV planifié aujourd’hui sur ce pont' : 'Pont désactivé pour le moment' }}</p>
              </div>

              <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:10px;">
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">RDV jour</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ pont.total_rdvs_today }}</div>
                </div>
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">Charge</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ formatMinutes(pont.planned_minutes) }}</div>
                </div>
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">File</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ pont.next_count ?? 0 }}</div>
                </div>
              </div>

              <div v-if="pont.day_schedule.length" style="padding-top:8px;border-top:1px solid rgba(107,114,128,0.2);">
                <div style="font-size:11px;color:#9CA3AF;font-weight:700;margin-bottom:6px;">Planning du jour</div>
                <div v-for="rdv in pont.day_schedule.slice(0, 3)" :key="rdv.id" style="display:flex;justify-content:space-between;gap:8px;font-size:12px;margin-bottom:4px;">
                  <span style="color:#FFD200;min-width:42px;">{{ formatHourLabel(rdv.heure_rdv) }}</span>
                  <span style="flex:1;color:#E5E7EB;">{{ rdvClientName(rdv) }}</span>
                  <span style="color:#9CA3AF;white-space:nowrap;">{{ rdv.type_intervention || 'atelier' }}</span>
                </div>
              </div>

              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                <button
                  v-if="getPontQuickAction(pont)?.transition"
                  class="btn btn-primary"
                  style="flex:1;min-width:140px;"
                  :disabled="actioningByPont[pont.id] === getPontQuickAction(pont)?.transition"
                  @click="runPontQuickAction(pont)"
                >
                  {{ actioningByPont[pont.id] === getPontQuickAction(pont)?.transition ? 'Traitement…' : getPontQuickAction(pont)?.label }}
                </button>
                <button
                  v-else-if="getPontQuickAction(pont)?.action"
                  class="btn btn-primary"
                  style="flex:1;min-width:140px;"
                  @click="getPontQuickAction(pont)!.action!()"
                >
                  {{ getPontQuickAction(pont)?.label }}
                </button>
                <NuxtLink
                  v-else-if="getPontQuickAction(pont)?.to"
                  :to="getPontQuickAction(pont)!.to"
                  class="btn btn-primary"
                  style="flex:1;min-width:140px;text-decoration:none;text-align:center;"
                >
                  {{ getPontQuickAction(pont)?.label }}
                </NuxtLink>
              </div>
            </div>

            <div class="pont-card-footer">
              {{ pont.total_rdvs_today ? `${pont.total_rdvs_today} RDV planifiés aujourd’hui` : 'Pont libre aujourd’hui' }}
            </div>
          </div>
        </div>
        <AppEmptyState
          v-else
          icon="🔧"
          title="Aucun pont visible"
          description="Aucun pont n’est remonté pour l’atelier actif. Vérifie la configuration atelier ou recharge la page."
        />
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
        <AppEmptyState
          v-if="!mecaniciens.length"
          icon="👤"
          title="Aucun mécanicien configuré"
          description="Ajoute un mécanicien depuis l’administration pour alimenter cette vue."
        />
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
          <AppEmptyState
            v-else
            icon="📅"
            title="Aucune absence enregistrée"
            description="L’équipe est complète sur la période affichée."
          />
        </UCard>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')
const validTabs = ['ponts', 'mecas', 'temps', 'absences']
const activeTab = ref('ponts')
const ponts = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const absences = ref<any[]>([])
const rdvs = ref<any[]>([])
const lastUpdatedAt = ref('')
const actioningByPont = reactive<Record<number, string>>({})
const pontSettings = reactive<Record<number, { mecanicien_id: number | null; is_active: boolean }>>({})
const pontSettingSaving = reactive<Record<number, boolean>>({})

const absenceCols = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
]

function isActiveFlag(value: any): boolean {
  return value !== false && Number(value ?? 1) !== 0
}

function extractDateKey(value: any): string {
  if (!value) return ''
  if (typeof value === 'string') return value.slice(0, 10)
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10)
}

function formatHourLabel(value: any): string {
  if (!value) return '--:--'
  if (typeof value === 'string') {
    const match = value.match(/(\d{2}):(\d{2})/)
    if (match) return `${match[1]}:${match[2]}`
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '--:--' : date.toISOString().slice(11, 16)
}

function getRdvStatus(rdv: any): string {
  return String(rdv?.status ?? rdv?.statut ?? '').toLowerCase()
}

function isFinalStatus(status: string): boolean {
  return ['termine', 'restitue', 'annule', 'facture', 'paye'].includes(status)
}

function rdvClientName(rdv: any): string {
  if (rdv?.client_nom) return rdv.client_nom
  const prenom = rdv?.client?.prenom ?? ''
  const nom = rdv?.client?.nom ?? ''
  return `${prenom} ${nom}`.trim() || 'Client non renseigné'
}

function rdvVehicleLabel(rdv: any): string {
  if (rdv?.vehicule_info) return rdv.vehicule_info
  const parts = [rdv?.vehicule?.marque, rdv?.vehicule?.modele, rdv?.vehicule_plaque ?? rdv?.vehicule?.plaque].filter(Boolean)
  return parts.join(' • ') || 'Véhicule non renseigné'
}

function asId(value: any): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null
  }
  if (value && typeof value === 'object') {
    return asId(value.id ?? value['@id'])
  }
  return null
}

function normalizeSpecialites(value: any): string[] {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (typeof value === 'string') {
    return value.split(/[;,]/).map((item: string) => item.trim()).filter(Boolean)
  }
  return []
}

const activeMecaniciens = computed(() => {
  return mecaniciens.value
    .filter((m: any) => isActiveFlag(m.is_active ?? m.isActive))
    .sort((a: any, b: any) => `${a.prenom ?? ''} ${a.nom ?? ''}`.localeCompare(`${b.prenom ?? ''} ${b.nom ?? ''}`))
})

function syncPontSettings() {
  for (const pont of ponts.value) {
    pontSettings[pont.id] = {
      mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
      is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
    }
  }
}

async function savePontSettings(pont: any) {
  if (!pont?.id) return
  const settings = pontSettings[pont.id] ?? {
    mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
    is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
  }

  pontSettingSaving[pont.id] = true
  try {
    const mecanicienId = asId(settings.mecanicien_id)
    await api.patch(`/ponts/${pont.id}`, {
      mecanicien_id: mecanicienId,
      mecanicien: mecanicienId ? `/api/mecaniciens/${mecanicienId}` : null,
      est_actif: settings.is_active,
      is_active: settings.is_active ? 1 : 0,
    })
    toast.add({ title: 'Pont mis à jour', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({ title: 'Mise à jour impossible', description: e?.message || 'Vérifie les droits admin de l’atelier.', color: 'error' })
  } finally {
    pontSettingSaving[pont.id] = false
  }
}

async function togglePontActivation(pont: any) {
  const current = pontSettings[pont.id]
  if (!current) return
  current.is_active = !current.is_active
  await savePontSettings(pont)
}

function countConflicts(todayRdvs: any[]): number {
  const seen = new Map<string, number>()
  let conflicts = 0

  for (const rdv of todayRdvs) {
    const hour = formatHourLabel(rdv?.heure_rdv)
    const pontId = rdv?.pont?.id ?? rdv?.pont_id
    const mecaId = rdv?.mecanicien?.id ?? rdv?.mecanicien_id
    const keys = [pontId ? `pont:${pontId}:${hour}` : '', mecaId ? `meca:${mecaId}:${hour}` : ''].filter(Boolean)

    for (const key of keys) {
      const next = (seen.get(key) ?? 0) + 1
      seen.set(key, next)
      if (next === 2) conflicts += 1
    }
  }

  return conflicts
}

const enrichedPonts = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value
    .filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
    .sort((a: any, b: any) => formatHourLabel(a?.heure_rdv).localeCompare(formatHourLabel(b?.heure_rdv)))

  return ponts.value.map((pont: any) => {
    const daySchedule = todayRdvs.filter((r: any) => (r?.pont?.id ?? r?.pont_id) === pont.id)
    const currentFromPlanning = daySchedule.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
    const currentRdv = pont.current_rdv ?? currentFromPlanning ?? null
    const nextRdv = daySchedule.find((r: any) => r.id !== currentRdv?.id) ?? null
    const plannedMinutes = daySchedule.reduce((sum: number, r: any) => sum + Number(r?.temps_estime ?? 60), 0)
    const assignedMecaId = pont?.mecanicien?.id ?? pont?.mecanicien_id ?? null
    const assignedMeca = pont?.mecanicien ?? mecaniciens.value.find((m: any) => m.id === assignedMecaId) ?? null

    return {
      ...pont,
      current_rdv: currentRdv,
      next_rdv: nextRdv,
      day_schedule: daySchedule,
      total_rdvs_today: daySchedule.length,
      planned_minutes: plannedMinutes,
      assigned_meca: assignedMeca,
    }
  })
})

const kpis = computed(() => {
  const total = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif)).length
  const occupied = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif) && (p.current_rdv || p.day_schedule.length)).length
  const occupation = total ? Math.round(occupied / total * 100) : 0
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))
  const activeMecas = mecaniciens.value.filter((m: any) => isActiveFlag(m.is_active ?? m.isActive) && !absentIds.has(m.id)).length

  return {
    occupation,
    rdvsToday: todayRdvs.length,
    activeMecas,
    conflicts: countConflicts(todayRdvs),
  }
})

const enrichedMecas = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))

  return mecaniciens.value.map((m: any) => {
    const mecaRdvs = todayRdvs.filter((r: any) => {
      const mid = r.mecanicien?.id ?? r.mecanicien_id
      return mid === m.id
    })
    const currentRdv = mecaRdvs.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
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
      specialites: normalizeSpecialites(m.specialites ?? m.competences),
    }
  })
})

function pontBadgeLabel(pont: any): string {
  if (!isActiveFlag(pont.is_active ?? pont.est_actif)) return 'Hors service'
  if (pont.current_rdv) return 'En cours'
  if (pont.day_schedule?.length) return 'Planifié'
  return 'Disponible'
}

function pontBadgeStyle(pont: any) {
  if (!isActiveFlag(pont.is_active ?? pont.est_actif)) {
    return { background: 'rgba(239,68,68,0.12)', color: '#FCA5A5' }
  }
  if (pont.current_rdv) {
    return { background: 'rgba(20,184,166,0.12)', color: '#5EEAD4' }
  }
  if (pont.day_schedule?.length) {
    return { background: 'rgba(245,158,11,0.12)', color: '#FCD34D' }
  }
  return { background: 'rgba(34,197,94,0.12)', color: '#86EFAC' }
}

function pontProgress(pont: any) {
  const rdv = pont.current_rdv
  if (!rdv?.temps_estime) return 0
  if (typeof rdv?.temps_ecoule_minutes === 'number') {
    return Math.round(rdv.temps_ecoule_minutes / rdv.temps_estime * 100)
  }
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((Date.now() - startTime.getTime()) / 60000 / rdv.temps_estime * 100)
}

function normalizeCollection(payload: any) {
  return Array.isArray(payload) ? payload : (payload?.['hydra:member'] ?? payload?.member ?? [])
}

function normalizePont(item: any) {
  return {
    ...item,
    is_active: item?.is_active ?? item?.est_actif ?? item?.isActive ?? 1,
    current_rdv: item?.current_rdv ?? null,
    next_count: Number(item?.next_count ?? 0),
  }
}

function addMinutesToTime(time: string, minutesToAdd: number): string {
  const [hours, minutes] = String(time || '09:00').split(':').map(Number)
  const total = ((hours || 0) * 60) + (minutes || 0) + Math.max(15, Number(minutesToAdd || 0))
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

function buildPlanningCreateLink(pont?: any): string {
  const today = new Date().toISOString().slice(0, 10)
  const suggestedTime = pont?.day_schedule?.length
    ? addMinutesToTime(formatHourLabel(pont.day_schedule[pont.day_schedule.length - 1]?.heure_rdv), Number(pont.day_schedule[pont.day_schedule.length - 1]?.temps_estime ?? 60))
    : '10:00'

  const params = new URLSearchParams({ create: '1', date: today, time: suggestedTime })
  if (pont?.id) params.set('pontId', String(pont.id))
  return `/planning?${params.toString()}`
}

function getPontQuickAction(pont: any): { label: string; transition?: string; to?: string; action?: () => void } | null {
  const status = getRdvStatus(pont?.current_rdv)
  if (status === 'reserve' || status === 'confirme') return { label: 'Réceptionner', transition: 'reception' }
  if (status === 'reception') return { label: 'Démarrer', transition: 'start_travail' }
  if (status === 'en_cours') return { label: 'Voir intervention', action: () => openRdvDetail(pont.current_rdv) }
  if (pont?.next_rdv?.id) return { label: 'Ouvrir prochain', action: () => openRdvDetail(pont.next_rdv) }
  return { label: '+ Nouveau RDV', to: buildPlanningCreateLink(pont) }
}

async function runPontQuickAction(pont: any) {
  const action = getPontQuickAction(pont)
  if (!action?.transition || !pont?.current_rdv?.id) return

  actioningByPont[pont.id] = action.transition
  try {
    await api.post(`/rendez-vous/${pont.current_rdv.id}/transition/${action.transition}`, {
      pont_id: pont.id,
      mecanicien_id: pont.assigned_meca?.id ?? pont.mecanicien?.id ?? null,
    })
    toast.add({ title: 'Action atelier effectuée', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({ title: 'Action impossible', description: e?.message || 'Ouvre le dossier atelier pour compléter la transition.', color: 'error' })
  } finally {
    delete actioningByPont[pont.id]
  }
}

async function fetchPontsWithFallback() {
  const statusPayload = await api.get('/ponts/status').catch(() => null)
  const statusPonts = normalizeCollection(statusPayload).map(normalizePont)
  if (statusPonts.length) {
    return statusPonts
  }

  const rawPayload = await api.get('/ponts').catch(() => [])
  return normalizeCollection(rawPayload).map((item: any) => normalizePont(item))
}

async function loadWorkshop() {
  loading.value = true
  errorMessage.value = ''

  const [p, m, a, r] = await Promise.allSettled([
    fetchPontsWithFallback(),
    api.get('/mecaniciens'),
    api.get('/absences'),
    api.get('/rendez-vous?itemsPerPage=200'),
  ])

  const issues: string[] = []

  if (p.status === 'fulfilled') {
    ponts.value = Array.isArray(p.value) ? p.value : normalizeCollection(p.value).map(normalizePont)
    if (!ponts.value.length) {
      issues.push('ponts')
    }
  } else {
    ponts.value = []
    issues.push('ponts')
  }

  if (m.status === 'fulfilled') {
    mecaniciens.value = [...new Map(normalizeCollection(m.value).map((item: any) => [Number(item.id), item])).values()]
  } else {
    mecaniciens.value = []
    issues.push('mécaniciens')
  }

  if (a.status === 'fulfilled') {
    const absRaw = normalizeCollection(a.value)
    absences.value = absRaw.map((ab: any) => ({
      ...ab,
      mecanicien_nom: ab.mecanicien ? `${ab.mecanicien.prenom ?? ''} ${ab.mecanicien.nom ?? ''}`.trim() : '–',
    }))
  } else {
    absences.value = []
    issues.push('absences')
  }

  if (r.status === 'fulfilled') {
    rdvs.value = normalizeCollection(r.value)
  } else {
    rdvs.value = []
    issues.push('rendez-vous')
  }

  if (issues.length === 4) {
    errorMessage.value = 'Aucune donnée atelier n’a pu être chargée. Vérifie la connexion API puis réessaie.'
  } else if (issues.length > 0) {
    toast.add({
      title: 'Chargement partiel',
      description: `Certaines sections sont indisponibles : ${issues.join(', ')}.`,
      color: 'warning',
    })
  }

  syncPontSettings()
  lastUpdatedAt.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  loading.value = false
}

async function refreshWorkshop() {
  refreshing.value = true
  try {
    await loadWorkshop()
  } finally {
    refreshing.value = false
  }
}

onMounted(() => {
  const queryTab = typeof route.query.tab === 'string' ? route.query.tab : ''
  if (validTabs.includes(queryTab)) {
    activeTab.value = queryTab
  }
  loadWorkshop()
})

watch(activeTab, (tab) => {
  if (route.query.tab === tab) return
  router.replace({ query: { ...route.query, tab } })
})
</script>
