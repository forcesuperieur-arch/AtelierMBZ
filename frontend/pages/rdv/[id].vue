<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/rdv" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">RDV #{{ rdv?.id }}</div>
        <StatusBadge v-if="rdv" :status="rdv.statut" />
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else-if="rdv" class="detail-layout">
      <!-- Main info -->
      <div class="detail-main">
        <UCard>
          <template #header>
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Informations</span>
          </template>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
            <div><span style="color:#6B7280;">Date :</span> <span style="color:#D1D5DB;">{{ formatDate(rdv.date_rdv) }}</span></div>
            <div><span style="color:#6B7280;">Heure :</span> <span style="color:#D1D5DB;">{{ formatTime(rdv.heure_rdv) }}</span></div>
            <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ rdv.type_intervention }}</span></div>
            <div><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ rdv.pont?.nom ?? '—' }}</span></div>
            <div><span style="color:#6B7280;">Mécanicien :</span> <span style="color:#D1D5DB;">{{ rdv.mecanicien ? (rdv.mecanicien.prenom + ' ' + rdv.mecanicien.nom) : '—' }}</span></div>
            <div><span style="color:#6B7280;">Durée prévue :</span> <span style="color:#D1D5DB;">{{ rdv.temps_estime ?? '—' }} min</span></div>
          </div>
          <div v-if="rdv.commentaire" style="margin-top:16px;">
            <span style="color:#6B7280;font-size:13px;">Description :</span>
            <p style="margin-top:4px;color:#D1D5DB;font-size:13px;">{{ rdv.commentaire }}</p>
          </div>
        </UCard>

        <!-- Client & Vehicle -->
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Client & Véhicule</span></template>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
            <div v-if="rdv.client">
              <p style="font-weight:600;color:#E8E9ED;">{{ rdv.client.prenom }} {{ rdv.client.nom }}</p>
              <p style="color:#6B7280;">
                <a v-if="rdv.client.telephone" :href="`tel:${rdv.client.telephone}`" style="color:#6B7280;text-decoration:none;">📞 {{ rdv.client.telephone }}</a>
              </p>
              <p v-if="rdv.client.email" style="color:#6B7280;">{{ rdv.client.email }}</p>
              <NuxtLink :to="`/clients/${rdv.client.id}`" style="display:inline-block;margin-top:6px;color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir fiche client →</NuxtLink>
            </div>
            <div v-if="rdv.vehicule">
              <p style="font-weight:600;color:#E8E9ED;">{{ rdv.vehicule.marque }} {{ rdv.vehicule.modele }}</p>
              <p style="color:#6B7280;">Plaque: {{ rdv.vehicule.plaque }}</p>
              <p v-if="rdv.vehicule.cylindree" style="color:#6B7280;">{{ rdv.vehicule.cylindree }}cc · {{ rdv.vehicule.annee }}</p>
            </div>
          </div>
        </UCard>

        <!-- Prestations -->
        <UCard v-if="rdv.prestations?.length">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">🔧 Prestations</span></template>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="p in rdv.prestations" :key="p.id" style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:8px;font-size:13px;">
              <span style="color:#E8E9ED;">{{ p.nom }}</span>
              <div style="display:flex;gap:12px;align-items:center;">
                <span style="color:#6B7280;">{{ p.temps_estime }}min</span>
                <span style="color:#FFD200;font-weight:600;">{{ formatCurrency(p.prix_ht) }}</span>
              </div>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.06);">
            <span style="color:#9CA3AF;font-size:13px;margin-right:8px;">Total :</span>
            <span style="color:#FFD200;font-weight:700;font-size:15px;">{{ formatCurrency(prestationsTotal) }}</span>
          </div>
        </UCard>

        <!-- Linked OR -->
        <UCard v-if="ordreReparation">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">📋 Ordre de Réparation</span></template>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
            <div>
              <span style="color:#6B7280;">OR :</span>
              <span style="color:#E8E9ED;font-weight:600;margin-left:6px;">{{ ordreReparation.numero_or || ordreReparation.numeroOr }}</span>
            </div>
            <NuxtLink :to="`/ordres/${ordreReparation.id}`" style="color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir dossier atelier →</NuxtLink>
          </div>
        </UCard>

        <!-- Notes / Travaux -->
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Notes internes</span></template>
          <UTextarea v-model="notes" placeholder="Notes..." :rows="3" />
          <div style="margin-top:8px;display:flex;justify-content:flex-end;">
            <button class="btn btn-primary" @click="saveNotes" :disabled="saving">{{ saving ? 'Sauvegarde…' : 'Sauvegarder' }}</button>
          </div>
        </UCard>
      </div>

      <!-- Sidebar actions -->
      <div class="detail-side">
        <!-- Warnings -->
        <div v-if="warnings.length" style="display:flex;flex-direction:column;gap:6px;">
          <div v-for="(w, i) in warnings" :key="i" style="padding:8px 12px;border-radius:8px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#FBBF24;">
            ⚠️ {{ w }}
          </div>
        </div>

        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Actions</span></template>
          <div class="detail-action-stack">
            <button
              v-for="t in availableTransitions.filter(t => t.name !== 'annuler' && (facturationEnabled || t.name !== 'facturer'))"
              :key="t.name"
              :class="actionButtonClass(t.color)"
              @click="t.name === 'annuler' ? showCancelModal = true : applyTransition(t.name)"
              :disabled="transitioning === t.name"
            >
              {{ transitioning === t.name ? 'Traitement…' : t.label }}
            </button>
            <button
              v-if="canAssign"
              class="btn btn-ghost btn-block"
              @click="showAssignModal = true"
            >👤 Assigner mécanicien / pont</button>
            <button
              v-if="canManageOr"
              class="btn btn-primary btn-block"
              @click="openOrDossier"
              :disabled="creatingOr"
            >{{ creatingOr ? 'Ouverture…' : ordreActionLabel }}</button>
            <button
              v-if="canInvoice && facturationEnabled"
              class="btn btn-primary btn-block"
              @click="createInvoice"
            >💶 Créer Facture</button>
            <button
              v-if="availableTransitions.some(t => t.name === 'annuler')"
              class="btn btn-block"
              style="background:rgba(239,68,68,0.14);color:#FCA5A5;border-color:rgba(239,68,68,0.28);"
              @click="showCancelModal = true"
            >❌ Annuler ce RDV</button>
          </div>
        </UCard>

        <!-- Quick info sidebar -->
        <UCard v-if="rdv.statut === 'en_cours'">
          <template #header><span style="font-size:13px;font-weight:600;color:#F59E0B;">⏱ En cours</span></template>
          <div v-if="rdv.heure_debut_travaux || rdv.started_at" style="font-size:13px;">
            <div style="color:#6B7280;">Démarré à : <span style="color:#E8E9ED;">{{ rdv.heure_debut_travaux || rdv.started_at }}</span></div>
            <div v-if="rdv.temps_estime" style="margin-top:8px;">
              <div style="color:#6B7280;margin-bottom:4px;">Progression estimée</div>
              <div style="background:var(--dark3);border-radius:6px;height:8px;overflow:hidden;">
                <div :style="{ width: Math.min(progressPct, 100) + '%', height: '100%', background: progressPct > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px', transition: 'width 0.3s' }"></div>
              </div>
              <div style="font-size:11px;color:#9CA3AF;margin-top:4px;">{{ progressPct }}% · {{ rdv.temps_estime }}min estimé</div>
            </div>
          </div>
        </UCard>

        <!-- Workflow Timeline -->
        <UCard>
          <template #header><span style="font-size:13px;font-weight:600;color:#E8E9ED;">📋 Historique workflow</span></template>
          <div style="display:flex;flex-direction:column;gap:0;">
            <div v-for="(step, i) in workflowSteps" :key="step.key" style="display:flex;gap:10px;">
              <div style="display:flex;flex-direction:column;align-items:center;width:16px;">
                <div style="width:10px;height:10px;border-radius:50%;flex-shrink:0;" :style="{ background: step.done ? '#10B981' : step.active ? '#FFD200' : 'rgba(255,255,255,0.08)', border: step.active ? '2px solid #FFD200' : 'none' }"></div>
                <div v-if="i < workflowSteps.length - 1" style="width:2px;flex:1;min-height:20px;" :style="{ background: step.done ? '#10B981' : 'rgba(255,255,255,0.06)' }"></div>
              </div>
              <div style="font-size:12px;padding-bottom:12px;">
                <span :style="{ color: step.done ? '#10B981' : step.active ? '#FFD200' : '#6B7280', fontWeight: step.active ? 600 : 400 }">{{ step.label }}</span>
              </div>
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <!-- Cancel Modal with Reasons -->
    <AppModal v-model:open="showCancelModal" size="md">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;color:#FCA5A5;">Annuler le RDV</span>
              <button @click="showCancelModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <p style="font-size:13px;color:#9CA3AF;margin-bottom:4px;">Motif d'annulation :</p>
            <label v-for="r in cancelReasons" :key="r.value" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;cursor:pointer;font-size:13px;border:1px solid rgba(255,255,255,0.06);" :style="{ background: cancelReason === r.value ? 'rgba(239,68,68,0.08)' : 'transparent', borderColor: cancelReason === r.value ? 'rgba(239,68,68,0.2)' : 'rgba(255,255,255,0.06)' }">
              <input type="radio" v-model="cancelReason" :value="r.value" style="accent-color:#EF4444;" />
              <span style="color:#D1D5DB;">{{ r.label }}</span>
            </label>
            <div class="form-group" style="margin-top:8px;">
              <label class="form-label">Commentaire (optionnel)</label>
              <textarea v-model="cancelComment" class="form-input" rows="2" placeholder="Détail complémentaire…" />
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
              <button class="btn btn-ghost" @click="showCancelModal = false">Retour</button>
              <button class="btn" style="background:#EF4444;color:#fff;border-color:#EF4444;" :disabled="!cancelReason || cancelling" @click="confirmCancel">
                {{ cancelling ? 'Annulation…' : 'Confirmer l\'annulation' }}
              </button>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>

    <!-- Assign Modal -->
    <AppModal v-model:open="showAssignModal" size="md">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">Assigner mécanicien et pont</span>
              <button @click="showAssignModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div class="form-group">
              <label class="form-label">Mécanicien</label>
              <select v-model="assignForm.mecanicien_id" class="form-input">
                <option :value="null">Non assigné</option>
                <option v-for="m in allMecaniciens" :key="m.id" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Pont</label>
              <select v-model="assignForm.pont_id" class="form-input">
                <option :value="null">Non assigné</option>
                <option v-for="p in allPonts" :key="p.id" :value="p.id">{{ p.nom }}</option>
              </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
              <button class="btn btn-ghost" @click="showAssignModal = false">Annuler</button>
              <button class="btn btn-primary" :disabled="assigning" @click="confirmAssign">{{ assigning ? 'Sauvegarde…' : 'Enregistrer' }}</button>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const rdvStore = useRdvStore()
const billingStore = useBillingStore()
const api = useApi()
const toast = useToast()
const atelierStore = useAtelierStore()
const facturationEnabled = computed(() => atelierStore.isModuleEnabled('facturation'))
const { formatDate, formatTime, formatCurrency } = useFormat()

type TransitionColor = 'neutral' | 'primary' | 'warning' | 'success' | 'info' | 'error' | 'secondary'

const id = Number(route.params.id)
const loading = ref(true)
const saving = ref(false)
const transitioning = ref('')
const availableTransitions = ref<Array<{ name: string; label: string; color: TransitionColor }>>([])
const notes = ref('')
const ordreReparation = ref<any>(null)
const showCancelModal = ref(false)
const showAssignModal = ref(false)
const cancelReason = ref('')
const cancelComment = ref('')
const cancelling = ref(false)
const assigning = ref(false)
const creatingOr = ref(false)
const allMecaniciens = ref<any[]>([])
const allPonts = ref<any[]>([])
const assignForm = reactive({ mecanicien_id: null as number | null, pont_id: null as number | null })

const cancelReasons = [
  { value: 'client_indisponible', label: 'Client indisponible' },
  { value: 'atelier_indisponible', label: 'Atelier indisponible' },
  { value: 'piece_non_disponible', label: 'Pièce non disponible' },
  { value: 'non_presente', label: 'Client non présenté' },
  { value: 'doublon', label: 'Doublon' },
  { value: 'autre', label: 'Autre' },
]

const rdv = computed(() => rdvStore.currentRdv)

const prestationsTotal = computed(() => {
  const prestations = rdv.value?.prestations ?? []
  return prestations.reduce((sum: number, p: any) => sum + Number(p.prix_ht ?? p.prix_ttc ?? 0), 0)
})

const progressPct = computed(() => {
  if (!rdv.value?.temps_estime) return 0
  const startedAt = rdv.value.heure_debut_travaux || rdv.value.started_at
  if (!startedAt) return 0
  const started = new Date(startedAt)
  if (Number.isNaN(started.getTime())) return 0
  return Math.max(0, Math.round(((Date.now() - started.getTime()) / 60000) / rdv.value.temps_estime * 100))
})

const canInvoice = computed(() => rdv.value?.statut === 'restitue')

const canAssign = computed(() => {
  if (!rdv.value) return false
  return ['en_attente', 'reserve', 'confirme', 'reception'].includes(rdv.value.statut)
})

const canManageOr = computed(() => {
  if (ordreReparation.value) return true
  if (!rdv.value) return false
  return ['confirme', 'reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye'].includes(rdv.value.statut)
})

const ordreActionLabel = computed(() => ordreReparation.value ? '📋 Ouvrir dossier atelier' : '📋 Créer dossier atelier')

const warnings = computed(() => {
  if (!rdv.value) return []
  const w: string[] = []
  if (!rdv.value.mecanicien) w.push('Aucun mécanicien assigné')
  if (!rdv.value.pont) w.push('Aucun pont assigné')
  if (canManageOr.value && !ordreReparation.value) w.push('Dossier atelier non créé')
  return w
})

const statusOrder = ['en_attente', 'reserve', 'confirme', 'reception', 'en_cours', 'termine', 'restitue']
const statusLabels: Record<string, string> = {
  en_attente: 'En attente', reserve: 'Réservé', confirme: 'Confirmé', reception: 'Réceptionné',
  en_cours: 'En cours', termine: 'Terminé', restitue: 'Restitué',
}

const transitionCatalog: Record<string, { label: string; color: TransitionColor }> = {
  reserver: { label: '📌 Réserver', color: 'neutral' },
  confirmer: { label: '✅ Confirmer', color: 'primary' },
  reception: { label: '📥 Réceptionner', color: 'warning' },
  start_travail: { label: '🔧 Démarrer intervention', color: 'warning' },
  terminer: { label: '✅ Terminer', color: 'success' },
  restituer: { label: '🚚 Restituer', color: 'info' },
  facturer: { label: '💶 Facturer', color: 'primary' },
  payer: { label: '💳 Encaisser', color: 'success' },
  annuler: { label: '❌ Annuler ce RDV', color: 'error' },
}

function actionButtonClass(color: TransitionColor) {
  if (['primary', 'warning', 'success'].includes(color)) return 'btn btn-primary btn-block'
  return 'btn btn-ghost btn-block'
}

function fallbackTransitionsForStatus(status?: string) {
  const byStatus: Record<string, string[]> = {
    en_attente: ['reserver', 'confirmer', 'annuler'],
    reserve: ['confirmer', 'annuler'],
    confirme: ['reception', 'annuler'],
    reception: ['start_travail'],
    en_cours: ['terminer'],
    termine: ['restituer', 'facturer'],
    restitue: ['facturer'],
    facture: ['payer'],
  }

  return (byStatus[status || ''] || []).map((name) => ({
    name,
    label: transitionCatalog[name]?.label ?? name,
    color: transitionCatalog[name]?.color ?? 'neutral',
  }))
}

async function loadAvailableTransitions() {
  try {
    const data = await api.get(`/rendez-vous/${id}/transitions`)
    const items = Array.isArray(data) ? data : (data?.transitions ?? [])
    availableTransitions.value = items
      .map((item: any) => {
        const name = typeof item === 'string' ? item : item?.name ?? item?.transition ?? ''
        if (!name) return null
        return {
          name,
          label: item?.label ?? transitionCatalog[name]?.label ?? name,
          color: item?.color ?? transitionCatalog[name]?.color ?? 'neutral',
        }
      })
      .filter(Boolean) as Array<{ name: string; label: string; color: TransitionColor }>
  } catch {
    availableTransitions.value = fallbackTransitionsForStatus(rdv.value?.statut)
  }
}

const workflowSteps = computed(() => {
  if (!rdv.value) return []
  const current = rdv.value.statut
  const currentIdx = statusOrder.indexOf(current)
  return statusOrder.map((key, i) => ({
    key,
    label: statusLabels[key] ?? key,
    done: i < currentIdx,
    active: i === currentIdx,
  }))
})

async function applyTransition(name: string) {
  transitioning.value = name
  try {
    await rdvStore.transitionRdv(id, name)
    await loadOrdreReparation()
    await loadAvailableTransitions()

    if (name === 'reception' && !ordreReparation.value) {
      await ensureOrdreReparation(false)
    }

    toast.add({ title: 'Transition effectuée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    transitioning.value = ''
  }
}

async function saveNotes() {
  saving.value = true
  try {
    await rdvStore.updateRdv(id, { commentaire: notes.value })
    toast.add({ title: 'Notes sauvegardées', color: 'success' })
  } finally {
    saving.value = false
  }
}

async function createInvoice() {
  if (!facturationEnabled.value) {
    toast.add({ title: 'Facturation désactivée pour cet atelier', color: 'warning' })
    return
  }
  try {
    await billingStore.createFacture(id)
    toast.add({ title: 'Facture créée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
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

function buildNumeroOr() {
  const datePart = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  return `OR-${datePart}-${String(id).padStart(4, '0')}`
}

async function loadOrdreReparation() {
  try {
    const data = await api.get('/ordres-reparation')
    const items = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    ordreReparation.value = items.find((item: any) => extractRdvIdFromOrdre(item) === id) ?? null
  } catch {
    ordreReparation.value = null
  }
}

async function ensureOrdreReparation(redirect = false) {
  if (ordreReparation.value) {
    if (redirect) await navigateTo(`/ordres/${ordreReparation.value.id}`)
    return ordreReparation.value
  }

  creatingOr.value = true
  try {
    const payload = {
      rendez_vous: `/api/rendez-vous/${id}`,
      numero_or: buildNumeroOr(),
      type_or: 'initial',
      travaux: rdv.value?.commentaire || rdv.value?.type_intervention || '',
    }

    const created = await api.post('/ordres-reparation', payload)
    ordreReparation.value = created
    toast.add({ title: 'Dossier atelier créé', color: 'success' })

    if (redirect) await navigateTo(`/ordres/${created.id}`)
    return created
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message || 'Création du dossier impossible', color: 'error' })
    return null
  } finally {
    creatingOr.value = false
  }
}

async function openOrDossier() {
  if (ordreReparation.value) {
    await navigateTo(`/ordres/${ordreReparation.value.id}`)
    return
  }

  await ensureOrdreReparation(true)
}

async function confirmCancel() {
  cancelling.value = true
  try {
    await rdvStore.transitionRdv(id, 'annuler')
    if (cancelReason.value || cancelComment.value) {
      const motif = `[${cancelReason.value}] ${cancelComment.value}`.trim()
      await rdvStore.updateRdv(id, { commentaire: `${notes.value}\n--- Annulation: ${motif}`.trim() })
    }
    showCancelModal.value = false
    toast.add({ title: 'RDV annulé', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    cancelling.value = false
  }
}

async function confirmAssign() {
  assigning.value = true
  try {
    const payload: any = {}
    if (assignForm.mecanicien_id) payload.mecanicien = `/api/mecaniciens/${assignForm.mecanicien_id}`
    else payload.mecanicien = null
    if (assignForm.pont_id) payload.pont = `/api/ponts/${assignForm.pont_id}`
    else payload.pont = null
    await rdvStore.updateRdv(id, payload)
    showAssignModal.value = false
    toast.add({ title: 'Assignation mise à jour', color: 'success' })
    await rdvStore.fetchRdv(id)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    assigning.value = false
  }
}

onMounted(async () => {
  try {
    await rdvStore.fetchRdv(id)
    notes.value = rdv.value?.commentaire || ''
    assignForm.mecanicien_id = rdv.value?.mecanicien?.id ?? null
    assignForm.pont_id = rdv.value?.pont?.id ?? null
    await Promise.all([
      loadOrdreReparation(),
      loadAvailableTransitions(),
      api.get('/mecaniciens').then((d: any) => { allMecaniciens.value = d?.['hydra:member'] ?? d?.member ?? (Array.isArray(d) ? d : []) }).catch(() => {}),
      api.get('/ponts').then((d: any) => { allPonts.value = d?.['hydra:member'] ?? d?.member ?? (Array.isArray(d) ? d : []) }).catch(() => {}),
    ])

    if (rdv.value?.statut === 'reception' && !ordreReparation.value) {
      await ensureOrdreReparation(false)
    }
  } finally {
    loading.value = false
  }
})
</script>
