<template>
  <div class="booking-page">
    <NuxtLink to="/rdvs" class="back-link">
      <AppIcon name="i-ri-arrow-left-line" />
      Mes rendez-vous
    </NuxtLink>
    <h1 class="title">Prendre un rendez-vous</h1>

    <ol class="stepper">
      <li v-for="(label, i) in stepLabels" :key="label" :class="{ active: step === i + 1, done: step > i + 1 }">
        <span class="stepper-index">{{ i + 1 }}</span>
        {{ label }}
      </li>
    </ol>

    <div v-if="loadingVehicules" class="hint">Chargement…</div>

    <template v-else>
      <!-- Étape 1 : véhicule -->
      <section v-if="step === 1" class="card">
        <h2 class="card-title">Quelle moto ?</h2>
        <p v-if="motos.length === 0" class="hint">Vous n'avez pas encore de moto enregistrée.</p>
        <div class="moto-choices">
          <label v-for="m in motos" :key="m.id" class="moto-choice" :class="{ selected: selectedMotoId === m.id }">
            <input type="radio" name="moto" :value="m.id" v-model="selectedMotoId" />
            <span class="moto-choice-name">{{ m.marque }} {{ m.modele }}</span>
            <span class="moto-choice-meta">
              <span v-if="m.plaque">{{ m.plaque }}</span>
              <span v-if="m.cylindree">{{ m.cylindree }}</span>
            </span>
          </label>
          <label class="moto-choice" :class="{ selected: selectedMotoId === null }">
            <input type="radio" name="moto" :value="null" v-model="selectedMotoId" />
            <span class="moto-choice-name">Sans moto en particulier</span>
          </label>
        </div>
        <p class="moto-add-hint">
          Une autre moto que celles listées ? <NuxtLink to="/motos">Ajouter une moto</NuxtLink>
        </p>
        <div class="step-actions">
          <button class="btn-primary" @click="step = 2">Continuer</button>
        </div>
      </section>

      <!-- Étape 2 : prestations -->
      <section v-else-if="step === 2" class="card">
        <h2 class="card-title">Quel entretien ?</h2>
        <div v-if="loadingPrestations" class="hint">Chargement…</div>
        <div v-else-if="prestationsDisponibles.length === 0" class="hint">
          Aucune prestation disponible en ligne pour le moment. Décrivez votre besoin ci-dessous, l'atelier ajustera.
        </div>
        <div v-else class="presta-list">
          <label v-for="p in prestationsDisponibles" :key="p.id" class="presta-item" :class="{ selected: selectedPrestaIds.includes(p.id) }">
            <input type="checkbox" :value="p.id" v-model="selectedPrestaIds" />
            <span class="presta-info">
              <span class="presta-name">{{ p.nom }}</span>
              <span v-if="p.description" class="presta-desc">{{ p.description }}</span>
            </span>
            <span class="presta-price">{{ formatPrix(p) }}</span>
          </label>
        </div>
        <div class="step-actions">
          <button class="btn-secondary" @click="step = 1">Retour</button>
          <button class="btn-primary" @click="onPrestationsNext">Continuer</button>
        </div>
      </section>

      <!-- Étape 3 : créneau -->
      <section v-else-if="step === 3" class="card">
        <h2 class="card-title">Quel créneau ?</h2>
        <div class="date-nav">
          <button class="date-nav-btn" @click="shiftDate(-1)" :disabled="isToday">Jour précédent</button>
          <input type="date" v-model="selectedDate" :min="todayStr" />
          <button class="date-nav-btn" @click="shiftDate(1)">Jour suivant</button>
        </div>

        <div v-if="loadingSlots" class="hint">Chargement des créneaux…</div>
        <div v-else-if="!bookingEnabled" class="hint">
          La réservation en ligne n'est pas disponible pour votre atelier. Merci de nous appeler pour convenir d'un créneau.
        </div>
        <div v-else-if="dayTimes.length === 0" class="hint">Aucun créneau disponible ce jour-là. Essayez un autre jour.</div>
        <div v-else class="slot-grid">
          <button
            v-for="t in dayTimes"
            :key="t"
            class="slot-btn"
            :class="{ selected: selectedHeure === t }"
            @click="selectedHeure = t"
          >{{ t }}</button>
        </div>

        <div class="step-actions">
          <button class="btn-secondary" @click="step = 2">Retour</button>
          <button class="btn-primary" :disabled="!selectedHeure" @click="step = 4">Continuer</button>
        </div>
      </section>

      <!-- Étape 4 : récapitulatif -->
      <section v-else class="card">
        <h2 class="card-title">Vérifier et confirmer</h2>
        <dl class="recap">
          <dt>Moto</dt>
          <dd>{{ selectedMotoLabel }}</dd>
          <dt>Prestations</dt>
          <dd>{{ selectedPrestaLabel }}</dd>
          <dt>Créneau</dt>
          <dd>{{ formatDate(selectedDate) }} à {{ selectedHeure }}</dd>
          <dt v-if="totalEstime > 0">Total estimé</dt>
          <dd v-if="totalEstime > 0">{{ totalEstime.toFixed(2) }} € (indicatif)</dd>
        </dl>

        <div class="field">
          <label>Un détail à préciser pour l'atelier ? (facultatif)</label>
          <textarea v-model="commentaire" rows="3" placeholder="Bruit, symptôme, demande particulière…" />
        </div>

        <p v-if="submitError" class="error">{{ submitError }}</p>

        <div class="step-actions">
          <button class="btn-secondary" @click="step = 3" :disabled="submitting">Retour</button>
          <button class="btn-primary" :disabled="submitting" @click="submitBooking">
            {{ submitting ? 'Envoi…' : 'Confirmer le rendez-vous' }}
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
const { apiFetch } = useClientApi()
const router = useRouter()

const stepLabels = ['Moto', 'Prestations', 'Créneau', 'Validation']
const step = ref(1)

// --- Étape 1 : motos du client ---
const motos = ref<any[]>([])
const loadingVehicules = ref(true)
const selectedMotoId = ref<number | null>(null)

onMounted(async () => {
  try {
    motos.value = await apiFetch('/api/client/vehicules')
    if (motos.value.length === 1) selectedMotoId.value = motos.value[0].id
  } catch {
    motos.value = []
  } finally {
    loadingVehicules.value = false
  }
})

const selectedMoto = computed(() => motos.value.find(m => m.id === selectedMotoId.value) ?? null)
const selectedMotoLabel = computed(() => selectedMoto.value ? `${selectedMoto.value.marque} ${selectedMoto.value.modele}` : 'Sans moto en particulier')

// --- Étape 2 : prestations ---
const prestations = ref<any[]>([])
const loadingPrestations = ref(false)
const selectedPrestaIds = ref<number[]>([])
let prestationsLoaded = false

async function ensurePrestationsLoaded() {
  if (prestationsLoaded) return
  loadingPrestations.value = true
  try {
    prestations.value = await apiFetch('/api/client/prestations')
  } catch {
    prestations.value = []
  } finally {
    loadingPrestations.value = false
    prestationsLoaded = true
  }
}

function normalizeText(v: any) {
  return String(v ?? '').trim().toLowerCase()
}

const prestationsDisponibles = computed(() => {
  const vehiculeType = normalizeText(selectedMoto.value?.type_moto)
  const cylindree = Number(selectedMoto.value?.cylindree) || 0
  return prestations.value.filter((p: any) => {
    const rawType = normalizeText(p.type_vehicule || 'tous')
    const allowedTypes = rawType.split(/[;,/|]+/).map((t: string) => t.trim()).filter(Boolean)
    const typeMatches = !vehiculeType || !allowedTypes.length || allowedTypes.includes('tous')
      || allowedTypes.some((t: string) => t === vehiculeType || t.includes(vehiculeType) || vehiculeType.includes(t))
    const min = Number(p.cylindree_min) || 0
    const max = Number(p.cylindree_max) || 0
    const cylindreeMatches = (!min || !cylindree || cylindree >= min) && (!max || !cylindree || cylindree <= max)
    return typeMatches && cylindreeMatches
  })
})

const selectedPrestaItems = computed(() => prestations.value.filter((p: any) => selectedPrestaIds.value.includes(p.id)))
const selectedPrestaLabel = computed(() => selectedPrestaItems.value.map((p: any) => p.nom).join(', ') || 'Entretien général')
const totalEstime = computed(() => selectedPrestaItems.value.reduce((s: number, p: any) => {
  const ttc = Number(p.prix_base_ttc) || 0
  const ht = Number(p.prix_base_ht) || 0
  return s + (ttc > 0 ? ttc : ht)
}, 0))
const dureeEstimee = computed(() => selectedPrestaItems.value.reduce((s: number, p: any) => s + (Number(p.temps_estime_minutes) || 60), 0) || 60)

function formatPrix(p: any) {
  const ttc = Number(p.prix_base_ttc) || 0
  const ht = Number(p.prix_base_ht) || 0
  const eff = ttc > 0 ? ttc : ht
  return eff > 0 ? `${eff.toFixed(2)} €` : 'Sur devis'
}

async function onPrestationsNext() {
  step.value = 3
  await loadSlots()
}

// --- Étape 3 : créneau ---
// Toujours dériver la date via les composants LOCAUX (getFullYear/getMonth/getDate),
// jamais via toISOString() : en UTC+1/+2 (Europe/Paris), convertir un minuit local
// en UTC fait sauter le jour (ex. +1 jour redonnait la même date en pleine journée,
// et « aujourd'hui » aurait été compté comme hier entre 00h et 2h locales).
function toYMD(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const todayStr = toYMD(new Date())
const selectedDate = ref(todayStr)
const selectedHeure = ref<string | null>(null)
const loadingSlots = ref(false)
const bookingEnabled = ref(true)
const slotsByDate = ref<Record<string, any[]>>({})

const isToday = computed(() => selectedDate.value <= todayStr)

const dayTimes = computed(() => {
  const day = slotsByDate.value[selectedDate.value] || []
  const seen = new Set<string>()
  const times: string[] = []
  for (const slot of day) {
    if (!seen.has(slot.heure)) {
      seen.add(slot.heure)
      times.push(slot.heure)
    }
  }
  return times.sort()
})

watch(selectedDate, () => {
  selectedHeure.value = null
  loadSlots()
})

async function loadSlots() {
  loadingSlots.value = true
  try {
    const res: any = await apiFetch('/api/client/slots', {
      query: {
        date_debut: selectedDate.value,
        date_fin: selectedDate.value,
        temps_minutes: dureeEstimee.value,
      },
    })
    bookingEnabled.value = !!res.bookingEnabled
    slotsByDate.value = res.slots || {}
  } catch {
    bookingEnabled.value = false
    slotsByDate.value = {}
  } finally {
    loadingSlots.value = false
  }
}

function shiftDate(deltaDays: number) {
  const d = new Date(selectedDate.value + 'T00:00:00')
  d.setDate(d.getDate() + deltaDays)
  const next = toYMD(d)
  if (next >= todayStr) selectedDate.value = next
}

function formatDate(d: string) {
  return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
}

// Chargement des prestations dès qu'on quitte l'étape 1 (évite un flash vide à l'étape 2).
watch(step, (s) => {
  if (s === 2) ensurePrestationsLoaded()
})

// --- Étape 4 : validation ---
const commentaire = ref('')
const submitting = ref(false)
const submitError = ref('')

async function submitBooking() {
  submitting.value = true
  submitError.value = ''
  try {
    const res: any = await apiFetch('/api/client/rdvs', {
      method: 'POST',
      body: {
        vehicule_id: selectedMotoId.value,
        date_rdv: selectedDate.value,
        heure_rdv: selectedHeure.value,
        type_intervention: selectedPrestaLabel.value,
        commentaire: commentaire.value,
        duree_estimee: dureeEstimee.value,
        prestations: selectedPrestaItems.value.map((p: any) => ({
          designation: p.nom,
          prix_ht: p.prix_base_ht,
          prix_ttc: p.prix_base_ttc,
          duree: p.temps_estime_minutes,
        })),
      },
    })
    await router.push(`/rdvs/${res.id}`)
  } catch (e: any) {
    submitError.value = e?.data?.error || 'Impossible d\'enregistrer le rendez-vous. Réessayez.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.booking-page {
  max-width: 560px;
  margin: 0 auto;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--content-3);
  text-decoration: none;
  margin-bottom: 12px;
}
.back-link :deep(svg) {
  width: 16px;
  height: 16px;
}
.title {
  font-size: 20px;
  font-weight: 800;
  margin: 0 0 18px;
}
.stepper {
  display: flex;
  gap: 10px;
  list-style: none;
  margin: 0 0 20px;
  padding: 0;
  font-size: 12px;
  color: var(--content-3);
  flex-wrap: wrap;
}
.stepper li {
  display: flex;
  align-items: center;
  gap: 6px;
}
.stepper li.active,
.stepper li.done {
  color: var(--content-1);
  font-weight: 700;
}
.stepper-index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--overlay-hover);
  font-size: 11px;
}
.stepper li.active .stepper-index,
.stepper li.done .stepper-index {
  background: var(--accent);
  color: var(--accent-ink);
}
.card {
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  padding: 20px;
}
.card-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 14px;
}
.hint {
  color: var(--content-3);
  font-size: 13px;
}
.moto-choices {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}
.moto-choice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--border-1);
  border-radius: 8px;
  cursor: pointer;
}
.moto-choice.selected {
  border-color: var(--accent-graphic);
  background: var(--overlay-hover);
}
.moto-choice-name {
  font-weight: 700;
  flex: 1;
}
.moto-choice-meta {
  display: flex;
  gap: 8px;
  font-size: 12px;
  color: var(--content-3);
}
.moto-add-hint {
  font-size: 12px;
  color: var(--content-3);
  margin: 0 0 4px;
}
.presta-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}
.presta-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--border-1);
  border-radius: 8px;
  cursor: pointer;
}
.presta-item.selected {
  border-color: var(--accent-graphic);
  background: var(--overlay-hover);
}
.presta-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}
.presta-name {
  font-weight: 700;
  font-size: 14px;
}
.presta-desc {
  font-size: 12px;
  color: var(--content-3);
}
.presta-price {
  font-weight: 700;
  font-size: 13px;
  white-space: nowrap;
}
.date-nav {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.date-nav input[type="date"] {
  padding: 8px 10px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-family: inherit;
}
.date-nav-btn {
  padding: 8px 12px;
  border: 1px solid var(--border-1);
  border-radius: 8px;
  background: transparent;
  color: var(--content-1);
  font-size: 13px;
  cursor: pointer;
}
.date-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.slot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
  gap: 8px;
  margin-bottom: 12px;
}
.slot-btn {
  padding: 10px 6px;
  border: 1px solid var(--border-1);
  border-radius: 8px;
  background: transparent;
  color: var(--content-1);
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}
.slot-btn.selected {
  border-color: var(--accent-graphic);
  background: var(--accent);
  color: var(--accent-ink);
}
.recap {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 6px 12px;
  margin: 0 0 16px;
  font-size: 14px;
}
.recap dt {
  color: var(--content-3);
}
.recap dd {
  margin: 0;
  font-weight: 600;
}
.field {
  margin-bottom: 16px;
}
.field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
  margin-bottom: 4px;
}
.field textarea {
  width: 100%;
  padding: 8px 10px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
}
.error {
  color: var(--error-content);
  font-size: 13px;
  margin: 0 0 12px;
}
.step-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn-primary,
.btn-secondary {
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  border: none;
}
.btn-primary {
  background: var(--accent);
  color: var(--accent-ink);
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-secondary {
  background: transparent;
  border: 1px solid var(--border-1);
  color: var(--content-1);
}
</style>
