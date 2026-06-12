<template>
  <div class="public-card">
    <div class="public-card-header">
      <div style="font-size:32px;margin-bottom:8px;">🔍</div>
      <h1 class="text-gradient" style="font-size:22px;font-weight:800;">Suivi de rendez-vous</h1>
    </div>

    <div v-if="!rdv" style="display:flex;flex-direction:column;gap:16px;">
      <UFormField label="Email">
        <UInput v-model="email" type="email" placeholder="votre@email.com" />
      </UFormField>
      <UFormField label="Téléphone">
        <UInput v-model="telephone" placeholder="06 12 34 56 78" />
      </UFormField>
      <button class="topbar-new-btn" style="width:100%;justify-content:center;padding:12px;font-size:14px;" @click="lookup" :disabled="loading">
        {{ loading ? 'Recherche...' : 'Rechercher' }}
      </button>
      <p v-if="error" style="font-size:13px;color:#FCA5A5;text-align:center;">{{ error }}</p>
    </div>

    <div v-else style="display:flex;flex-direction:column;gap:16px;">
      <div style="text-align:center;">
        <StatusBadge :status="rdv.statut" />
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
        <div><span style="color:#6B7280;">Date :</span> <span style="color:#D1D5DB;">{{ rdv.date }}</span></div>
        <div><span style="color:#6B7280;">Heure :</span> <span style="color:#D1D5DB;">{{ rdv.heure }}</span></div>
        <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ rdv.type_intervention }}</span></div>
        <div v-if="rdv.pont"><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ rdv.pont }}</span></div>
        <div v-if="rdv.mecanicien"><span style="color:#6B7280;">Mécano :</span> <span style="color:#D1D5DB;">{{ rdv.mecanicien }}</span></div>
      </div>

      <!-- Progress steps -->
      <div style="margin-top:24px;">
        <div class="progress-steps">
          <div
            v-for="(step, i) in progressSteps"
            :key="step.key"
            class="progress-step"
          >
            <div
              :style="{
                width: '32px',
                height: '32px',
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '12px',
                fontWeight: '700',
                background: step.done ? 'linear-gradient(135deg, #FFD200, #D97706)' : 'rgba(255,255,255,0.06)',
                color: step.done ? '#111' : '#6B7280',
                transition: 'all 0.3s',
              }"
            >
              {{ i + 1 }}
            </div>
            <span style="font-size:10px;margin-top:4px;text-align:center;color:#6B7280;">{{ step.label }}</span>
          </div>
        </div>
      </div>

      <button class="topbar-new-btn" style="width:100%;justify-content:center;padding:10px;font-size:13px;background:rgba(255,255,255,0.06);color:#D1D5DB;margin-top:16px;" @click="rdv = null; email = ''; telephone = ''">
        Nouvelle recherche
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string

const email = ref('')
const telephone = ref('')
const loading = ref(false)
const error = ref('')
const rdv = ref<any>(null)

const statusOrder = ['en_attente', 'reserve', 'confirme', 'reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']

const progressSteps = computed(() => {
  if (!rdv.value) return []
  const currentIdx = statusOrder.indexOf(rdv.value.statut)
  return [
    { key: 'reserve', label: 'Réservé', done: currentIdx >= 1 },
    { key: 'confirme', label: 'Confirmé', done: currentIdx >= 2 },
    { key: 'reception', label: 'Réception', done: currentIdx >= 3 },
    { key: 'en_cours', label: 'En cours', done: currentIdx >= 4 },
    { key: 'termine', label: 'Terminé', done: currentIdx >= 5 },
    { key: 'restitue', label: 'Prêt', done: currentIdx >= 6 },
  ]
})

async function lookup() {
  if (!email.value || !telephone.value) {
    error.value = 'Veuillez renseigner votre email et votre téléphone.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${baseURL}/public/suivi`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: email.value, telephone: telephone.value }),
    })
    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error(data.error || 'Aucun rendez-vous trouvé avec ces coordonnées.')
    }
    const data = await res.json()
    rdv.value = data.rdv
  } catch (e: any) {
    error.value = e?.message || 'Erreur lors de la recherche.'
  } finally {
    loading.value = false
  }
}

// Lien direct depuis l'email d'accusé de réception : /public/suivi?token=…
const route = useRoute()
onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  if (token.length < 16) return
  loading.value = true
  try {
    const res = await fetch(`${baseURL}/public/suivi/token/${encodeURIComponent(token)}`, {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error(data.error || 'Lien de suivi invalide ou expiré.')
    }
    const data = await res.json()
    rdv.value = data.rdv
  } catch (e: any) {
    error.value = e?.message || 'Lien de suivi invalide ou expiré.'
  } finally {
    loading.value = false
  }
})
</script>
