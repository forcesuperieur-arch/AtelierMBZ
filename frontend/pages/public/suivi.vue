<template>
  <div class="public-card">
    <div class="public-card-header">
      <div class="emoji-lg">🔍</div>
      <h1 class="text-gradient title-xl">Suivi de rendez-vous</h1>
    </div>

    <div v-if="!rdv" class="form-stack">
      <UFormField label="Code de suivi">
        <UInput v-model="token" placeholder="Entrez votre code de suivi..." />
      </UFormField>
      <button class="topbar-new-btn btn-full" @click="lookup" :disabled="loading">
        {{ loading ? 'Recherche...' : 'Rechercher' }}
      </button>
      <p v-if="error" class="error-text">{{ error }}</p>
    </div>

    <div v-else class="form-stack">
      <div class="text-center">
        <StatusBadge :status="rdv.statut" />
      </div>

      <div class="info-grid">
        <div><span class="label-muted">Date :</span> <span class="value-light">{{ rdv.date_rdv }}</span></div>
        <div><span class="label-muted">Heure :</span> <span class="value-light">{{ rdv.heure_rdv }}</span></div>
        <div><span class="label-muted">Type :</span> <span class="value-light">{{ rdv.type_intervention }}</span></div>
        <div><span class="label-muted">Véhicule :</span> <span class="value-light">{{ rdv.vehicule ? `${rdv.vehicule.marque} ${rdv.vehicule.modele}` : '' }}</span></div>
      </div>

      <!-- Progress steps -->
      <div class="mt-6">
        <div class="progress-steps">
          <div
            v-for="(step, i) in progressSteps"
            :key="step.key"
            class="progress-step"
          >
            <div
              class="step-circle"
              :class="{ done: step.done }"
            >
              {{ i + 1 }}
            </div>
            <span class="step-label">{{ step.label }}</span>
          </div>
        </div>
      </div>

      <button class="topbar-new-btn btn-full-secondary" @click="rdv = null; token = ''">
        Nouvelle recherche
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string
const route = useRoute()

const token = ref(String(route.params.token || route.query.token || ''))
const loading = ref(false)
const error = ref('')
const rdv = ref<any>(null)

const statusOrder = ['en_attente', 'reserve', 'confirme', 'reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']

const progressSteps = computed(() => {
  if (!rdv.value) return []
  const currentIdx = statusOrder.indexOf(rdv.value.statut)
  return [
    { key: 'en_attente', label: 'Demande reçue', done: currentIdx >= 0 },
    { key: 'reserve', label: 'Créneau réservé', done: currentIdx >= 1 },
    { key: 'confirme', label: 'Confirmé par l\'atelier', done: currentIdx >= 2 },
    { key: 'reception', label: 'Réception', done: currentIdx >= 3 },
    { key: 'en_cours', label: 'En cours', done: currentIdx >= 4 },
    { key: 'termine', label: 'Terminé', done: currentIdx >= 5 },
    { key: 'restitue', label: 'Prêt', done: currentIdx >= 6 },
  ]
})

async function lookup() {
  if (!token.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${baseURL}/public/suivi/${token.value}`)
    if (!res.ok) throw new Error()
    rdv.value = await res.json()
  } catch {
    error.value = 'Aucun rendez-vous trouvé avec ce code'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (token.value) lookup()
})
</script>

<style scoped>
.emoji-lg { font-size:32px; margin-bottom:8px; }
.title-xl { font-size:22px; font-weight:800; }
.form-stack { display:flex; flex-direction:column; gap:16px; }
.btn-full { width:100%; justify-content:center; padding:12px; font-size:14px; }
.error-text { font-size:13px; color:#FCA5A5; text-align:center; }
.text-center { text-align:center; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13px; }
.label-muted { color:#6B7280; }
.value-light { color:#D1D5DB; }
.mt-6 { margin-top:24px; }
.step-circle { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; background:rgba(255,255,255,0.06); color:#6B7280; transition:all 0.3s; }
.step-circle.done { background:linear-gradient(135deg, #FFD200, #D97706); color:#111; }
.step-label { font-size:10px; margin-top:4px; text-align:center; color:#6B7280; }
.btn-full-secondary { width:100%; justify-content:center; padding:10px; font-size:13px; background:rgba(255,255,255,0.06); color:#D1D5DB; margin-top:16px; }
</style>
