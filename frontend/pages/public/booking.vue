<template>
  <div class="public-card">
    <div class="public-card-header">
      <div style="font-size:32px;margin-bottom:8px;">📅</div>
      <h1 class="text-gradient" style="font-size:22px;font-weight:800;">Réserver un rendez-vous</h1>
      <p style="font-size:13px;color:#6B7280;margin-top:4px;">Parcours public complet avec estimation et créneaux réels</p>
    </div>

    <div v-if="errorMessage" style="margin-bottom:16px;padding:12px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;color:#FCA5A5;font-size:13px;">
      {{ errorMessage }}
    </div>

    <form v-if="!confirmation" @submit.prevent="submitBooking" style="display:flex;flex-direction:column;gap:18px;">
      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">1. Vos coordonnées</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <UFormField label="Prénom"><UInput v-model="form.client_prenom" required /></UFormField>
          <UFormField label="Nom"><UInput v-model="form.client_nom" required /></UFormField>
          <UFormField label="Téléphone"><UInput v-model="form.client_telephone" required /></UFormField>
          <UFormField label="Email"><UInput v-model="form.client_email" type="email" required /></UFormField>
        </div>
        <div style="font-size:12px;color:#9CA3AF;margin-top:8px;">Votre demande de créneau sera confirmée par email.</div>
      </div>

      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">2. Votre moto</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <UFormField label="Marque"><UInput v-model="form.vehicule_marque" required /></UFormField>
          <UFormField label="Modèle"><UInput v-model="form.vehicule_modele" required /></UFormField>
          <UFormField label="Plaque"><UInput v-model="form.vehicule_plaque" required @blur="form.vehicule_plaque = form.vehicule_plaque.toUpperCase()" /></UFormField>
          <UFormField label="Type d'intervention">
            <USelect v-model="form.type_intervention" :options="typeOptions" required />
          </UFormField>
        </div>
      </div>

      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">3. Prestations</div>
        <div v-if="loadingPrestas" style="font-size:12px;color:#6B7280;">Chargement des prestations…</div>
        <div v-else-if="prestations.length" style="display:flex;flex-direction:column;gap:8px;">
          <button
            v-for="p in prestations"
            :key="p.id"
            type="button"
            @click="togglePresta(p.id)"
            style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;cursor:pointer;transition:all 0.15s;"
            :style="{
              background: selectedPrestas.includes(p.id) ? 'rgba(255,210,0,0.08)' : 'rgba(255,255,255,0.02)',
              border: selectedPrestas.includes(p.id) ? '1px solid rgba(255,210,0,0.28)' : '1px solid rgba(255,255,255,0.06)',
              color: '#E8E9ED'
            }"
          >
            <span style="width:18px;height:18px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;"
              :style="{ background: selectedPrestas.includes(p.id) ? '#FFD200' : 'rgba(255,255,255,0.06)', color: selectedPrestas.includes(p.id) ? '#111827' : 'transparent' }">✓</span>
            <span style="flex:1;text-align:left;">
              <span style="display:block;font-size:14px;font-weight:700;">{{ p.nom }}</span>
              <span style="display:block;font-size:12px;color:#9CA3AF;">{{ p.description || p.categorie || 'Prestation atelier' }}</span>
            </span>
            <span style="text-align:right;">
              <span style="display:block;font-size:13px;font-weight:700;color:#FFD200;">{{ formatCurrency(p.prix_base_ttc ?? p.prix_base_ht ?? 0) }}</span>
              <span style="display:block;font-size:11px;color:#6B7280;">{{ p.temps_estime_minutes ?? 60 }} min</span>
            </span>
          </button>
        </div>
        <div v-else style="font-size:12px;color:#6B7280;">Le catalogue n’est pas encore chargé. Le type d’intervention restera manuel.</div>
      </div>

      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">4. Créneau</div>
        <UFormField label="Date souhaitée">
          <UInput v-model="form.date_rdv" type="date" :min="minDate" required @change="fetchSlots" />
        </UFormField>

        <div v-if="alternativeDays.length" style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span style="font-size:12px;color:#6B7280;">Autres jours possibles :</span>
          <button
            v-for="day in alternativeDays"
            :key="day.date"
            type="button"
            @click="selectAlternativeDay(day.date)"
            style="padding:5px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#D1D5DB;font-size:12px;cursor:pointer;"
          >
            {{ formatShortDate(day.date) }} · {{ day.count }} créneau{{ day.count > 1 ? 'x' : '' }}
          </button>
        </div>

        <div style="margin-top:12px;">
          <div v-if="loadingSlots" style="font-size:12px;color:#6B7280;">Chargement des créneaux disponibles…</div>
          <div v-else-if="slots.length" style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;">
            <button
              v-for="slot in slots"
              :key="slot.heure"
              type="button"
              :disabled="!slot.disponible"
              :style="{
                padding: '8px 12px',
                borderRadius: '8px',
                border: form.heure_debut === slot.heure ? '2px solid #FFD200' : '1px solid rgba(255,255,255,0.08)',
                background: form.heure_debut === slot.heure ? 'rgba(255,210,0,0.1)' : 'rgba(255,255,255,0.03)',
                color: form.heure_debut === slot.heure ? '#FFD200' : '#D1D5DB',
                opacity: slot.disponible ? 1 : 0.5,
                fontSize: '13px',
                fontWeight: '600',
                cursor: slot.disponible ? 'pointer' : 'not-allowed',
                transition: 'all 0.15s',
              }"
              @click="selectSlot(slot.heure)"
            >
              {{ slot.heure }}
            </button>
          </div>
          <p v-else-if="form.date_rdv && !loadingSlots" style="font-size:13px;color:#6B7280;">Aucun créneau disponible ce jour.</p>
        </div>
      </div>

      <div style="padding:14px;border:1px solid rgba(255,210,0,0.16);border-radius:12px;background:rgba(255,210,0,0.04);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:8px;">Récapitulatif estimatif</div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#D1D5DB;padding:4px 0;">
          <span>Intervention</span>
          <span style="font-weight:700;">{{ selectedPrestaItems.length ? selectedPrestaItems.map(p => p.nom).join(', ') : form.type_intervention }}</span>

        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#D1D5DB;padding:4px 0;">
          <span>Durée estimée</span>
          <span style="font-weight:700;">{{ dureeEstimee }} min</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#D1D5DB;padding:4px 0;">
          <span>Total estimé</span>
          <span style="font-weight:700;color:#FFD200;">{{ formatCurrency(totalEstime) }}</span>
        </div>
        <div v-if="selectedSlotMeta?.heure_fin" style="display:flex;justify-content:space-between;font-size:13px;color:#D1D5DB;padding:4px 0;">
          <span>Fin estimée</span>
          <span style="font-weight:700;">{{ selectedSlotMeta?.heure_fin }}</span>
        </div>
        <div v-if="selectedSlotMeta?.pause_appliquee" style="margin-top:8px;font-size:12px;color:#FDE68A;">
          Ce créneau passe sur la pause de midi : l’heure de fin tient compte de la reprise après pause.
        </div>
      </div>

      <UFormField label="Description du problème">
        <UTextarea v-model="form.description_probleme" :rows="3" placeholder="Exemple : vidange, frein avant bruyant, révision avant départ…" />
      </UFormField>

      <button type="submit" class="topbar-new-btn" :disabled="!canSubmit || submitting" style="width:100%;justify-content:center;padding:12px;font-size:14px;">
        {{ submitting ? 'Envoi...' : 'Envoyer ma demande de créneau' }}
      </button>
    </form>

    <div v-if="confirmation" style="margin-top:16px;padding:16px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:12px;text-align:center;">
      <div style="font-size:32px;margin-bottom:8px;">✅</div>
      <p style="font-weight:600;color:#86EFAC;">Demande de créneau enregistrée</p>
      <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">
        {{ confirmation.message || 'Une confirmation vous sera envoyée par email.' }}
      </p>
      <p v-if="confirmation.heure_fin" style="font-size:13px;color:#D1FAE5;margin-top:6px;">
        Fin estimée : <strong>{{ confirmation.heure_fin }}</strong>
      </p>
      <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">
        Votre code de suivi : <strong>{{ confirmation.token_suivi }}</strong>
      </p>
      <NuxtLink :to="`/public/suivi?token=${confirmation.token_suivi}`" class="topbar-new-btn" style="display:inline-flex;margin-top:12px;font-size:12px;padding:6px 12px;">Suivre mon RDV</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string

const submitting = ref(false)
const loadingSlots = ref(false)
const loadingPrestas = ref(false)
const slots = ref<Array<{ heure: string; heure_fin?: string; pause_appliquee?: boolean; disponible: boolean; pont_id?: number | null }>>([])
const slotsByDate = ref<Record<string, any[]>>({})
const prestations = ref<any[]>([])
const selectedPrestas = ref<Array<number | string>>([])
const confirmation = ref<any>(null)
const errorMessage = ref('')

const fallbackPrestations = [
  { id: 'entretien', nom: 'Entretien / Vidange', description: 'Révision courante et contrôle général', prix_base_ttc: 89, temps_estime_minutes: 60 },
  { id: 'revision', nom: 'Révision complète', description: 'Contrôle sécurité et consommables', prix_base_ttc: 149, temps_estime_minutes: 120 },
  { id: 'diagnostic', nom: 'Diagnostic atelier', description: 'Recherche de panne et essai', prix_base_ttc: 59, temps_estime_minutes: 45 },
  { id: 'pneus', nom: 'Pneus / montage', description: 'Montage et équilibrage', prix_base_ttc: 79, temps_estime_minutes: 90 },
]

const minDate = new Date().toISOString().slice(0, 10)

const form = reactive({
  client_prenom: '', client_nom: '', client_telephone: '', client_email: '',
  vehicule_marque: '', vehicule_modele: '', vehicule_plaque: '',
  type_intervention: 'entretien', date_rdv: minDate, heure_debut: '',
  description_probleme: '',
})

const typeOptions = [
  { value: 'entretien', label: 'Entretien' },
  { value: 'reparation', label: 'Réparation' },
  { value: 'diagnostic', label: 'Diagnostic' },
  { value: 'revision', label: 'Révision' },
  { value: 'pneus', label: 'Pneus' },
]

const selectedPrestaItems = computed(() => prestations.value.filter(p => selectedPrestas.value.includes(p.id)))
const selectedSlotMeta = computed(() => slots.value.find(s => s.heure === form.heure_debut) || null)
const totalEstime = computed(() => selectedPrestaItems.value.reduce((sum, p) => sum + Number(p.prix_base_ttc ?? p.prix_base_ht ?? 0), 0))
const dureeEstimee = computed(() => selectedPrestaItems.value.reduce((sum, p) => sum + Number(p.temps_estime_minutes ?? 60), 0) || 60)
const canSubmit = computed(() => !!form.client_prenom && !!form.client_nom && !!form.client_telephone && !!form.client_email && !!form.vehicule_marque && !!form.vehicule_modele && !!form.vehicule_plaque && !!form.date_rdv && !!form.heure_debut)
const alternativeDays = computed(() => {
  return Object.entries(slotsByDate.value)
    .filter(([date]) => date !== form.date_rdv)
    .map(([date, raw]) => ({
      date,
      count: normalizeSlots(raw).filter(s => s.disponible).length,
    }))
    .filter(d => d.count > 0)
    .slice(0, 4)
})

function formatCurrency(value: number) {
  return `${Number(value || 0).toFixed(2).replace('.', ',')} €`
}

function formatShortDate(date: string) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

function normalizeSlots(raw: any): Array<{ heure: string; heure_fin?: string; pause_appliquee?: boolean; disponible: boolean; pont_id?: number | null }> {
  const list = Array.isArray(raw) ? raw : []
  const byHeure = new Map<string, { heure: string; heure_fin?: string; pause_appliquee?: boolean; disponible: boolean; pont_id?: number | null }>()
  for (const item of list) {
    const heure = typeof item === 'string' ? item : (item?.heure || item?.time || '')
    if (!heure) continue
    if (!byHeure.has(heure)) {
      byHeure.set(heure, {
        heure,
        heure_fin: item?.heure_fin || null,
        pause_appliquee: !!item?.pause_appliquee,
        disponible: item?.disponible !== false && item?.available !== false,
        pont_id: item?.pont_id ?? null,
      })
    }
  }
  return Array.from(byHeure.values()).sort((a, b) => a.heure.localeCompare(b.heure))
}

function togglePresta(id: number) {
  const idx = selectedPrestas.value.indexOf(id)
  if (idx >= 0) selectedPrestas.value.splice(idx, 1)
  else selectedPrestas.value.push(id)
}

function selectSlot(heure: string) {
  form.heure_debut = heure
}

function selectAlternativeDay(date: string) {
  form.date_rdv = date
  slots.value = normalizeSlots(slotsByDate.value[date] || [])
  form.heure_debut = slots.value.find(s => s.disponible)?.heure || ''
}

async function loadPrestations() {
  loadingPrestas.value = true
  try {
    const res = await fetch(`${baseURL}/prestations`, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error('Catalogue indisponible')
    const data = await res.json()
    const raw = Array.isArray(data) ? data : (data?.['hydra:member'] ?? data?.member ?? [])
    prestations.value = raw.filter((p: any) => p.is_active !== false)
    if (!prestations.value.length) prestations.value = fallbackPrestations
  } catch {
    prestations.value = fallbackPrestations
  } finally {
    loadingPrestas.value = false
  }
}

async function fetchSlots() {
  if (!form.date_rdv) return
  loadingSlots.value = true
  errorMessage.value = ''
  try {
    const end = new Date(`${form.date_rdv}T00:00:00`)
    end.setDate(end.getDate() + 3)
    const endStr = end.toISOString().slice(0, 10)
    const res = await fetch(`${baseURL}/public/slots?date_debut=${form.date_rdv}&date_fin=${endStr}&temps_minutes=${dureeEstimee.value}&atelier_id=1`, {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) throw new Error('Impossible de charger les créneaux disponibles.')
    const data = await res.json()
    slotsByDate.value = Array.isArray(data) ? { [form.date_rdv]: data } : (data || {})
    slots.value = normalizeSlots(slotsByDate.value[form.date_rdv] || [])
    if (!slots.value.some(s => s.heure === form.heure_debut && s.disponible)) {
      form.heure_debut = slots.value.find(s => s.disponible)?.heure || ''
    }
  } catch (e: any) {
    slots.value = []
    slotsByDate.value = {}
    errorMessage.value = e?.message || 'Erreur lors du chargement des créneaux.'
  } finally {
    loadingSlots.value = false
  }
}

async function submitBooking() {
  errorMessage.value = ''
  submitting.value = true
  try {
    const typeIntervention = selectedPrestaItems.value.map(p => p.nom).join(', ') || form.type_intervention
    const res = await fetch(`${baseURL}/public/booking`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        prenom: form.client_prenom,
        nom: form.client_nom,
        telephone: form.client_telephone,
        email: form.client_email || null,
        marque: form.vehicule_marque,
        modele: form.vehicule_modele,
        plaque: form.vehicule_plaque.toUpperCase(),
        date_rdv: form.date_rdv,
        heure_rdv: form.heure_debut,
        type_intervention: typeIntervention,
        commentaire: form.description_probleme,
        atelier_id: 1,
        prix_estime: totalEstime.value,
        duree_estimee: dureeEstimee.value,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(data?.error || 'Erreur lors de la réservation.')
    confirmation.value = data
  } catch (e: any) {
    errorMessage.value = e?.message || 'Erreur lors de la réservation. Veuillez réessayer.'
  } finally {
    submitting.value = false
  }
}

watch(dureeEstimee, async () => {
  if (form.date_rdv) await fetchSlots()
})

onMounted(async () => {
  await loadPrestations()
  await fetchSlots()
})
</script>
