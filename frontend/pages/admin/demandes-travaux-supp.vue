<template>
  <div>
    <AppPageHeader title="Demandes de travaux complémentaires" back-to="/admin">
      <template #actions>
        <button class="btn btn-ghost" :disabled="loading" @click="load">🔄 Rafraîchir</button>
      </template>
    </AppPageHeader>

    <div class="filter-bar">
      <button v-for="f in filters" :key="f.value" class="btn filter-btn" :class="statut === f.value ? 'btn-primary' : 'btn-ghost'" @click="statut = f.value">
        {{ f.label }}
      </button>
    </div>

    <UCard>
      <div v-if="loading" class="loading-center">Chargement…</div>
      <AppEmptyState v-else-if="filtered.length === 0" icon="📭" title="Aucune demande." />
      <div v-else class="demande-list">
        <div v-for="d in filtered" :key="d.id" class="demande-card">
          <div class="demande-header">
            <div class="demande-left">
              <div class="demande-title-row">
                <span class="demande-title">#{{ d.id }} — {{ d.client_nom || '—' }}</span>
                <AppStatusBadge :variant="statutVariant(d.statut)" size="sm">{{ labelStatut(d.statut) }}</AppStatusBadge>
                <AppStatusBadge v-if="d.urgence === 'urgent'" variant="error" size="sm">URGENT</AppStatusBadge>
              </div>
              <div class="demande-vehicle">
                <span v-if="d.vehicule_info">{{ d.vehicule_info }}</span>
                <span v-if="d.vehicule_plaque"> • {{ d.vehicule_plaque }}</span>
                <span> • RDV <NuxtLink :to="`/planning?openRdv=${d.rendez_vous_id}`" class="link-yellow">#{{ d.rendez_vous_id }}</NuxtLink></span>
              </div>
              <div v-if="d.description" class="demande-desc">« {{ d.description }} »</div>
              <div class="prestation-list">
                <span v-for="(p, i) in d.prestations" :key="i" class="prestation-chip">
                  {{ p.designation }} — {{ formatEuro(p.prix_ttc) }}
                </span>
              </div>
            </div>
            <div class="demande-right">
              <div class="demande-price">{{ formatEuro(d.prix_estime) }}</div>
              <div class="demande-time">~{{ d.temps_estime }} min</div>
              <div v-if="d.decision_client_at" class="demande-date">
                Décidé le {{ new Date(d.decision_client_at).toLocaleString('fr-FR') }}
              </div>
            </div>
          </div>

          <div class="demande-actions">
            <button
              v-if="['en_attente', 'en_attente_validation'].includes(d.statut)"
              class="btn btn-primary btn-sm"
              :disabled="sending === d.id"
              @click="envoyer(d)"
            >
              {{ sending === d.id ? 'Envoi…' : '📤 Envoyer au client' }}
            </button>
            <button
              v-if="d.token && d.statut === 'en_attente_decision_client'"
              class="btn btn-ghost btn-sm"
              @click="copyLink(d.token)"
            >🔗 Copier le lien client</button>
            <NuxtLink
              v-if="d.or_complementaire_id"
              :to="`/ordres/${d.or_complementaire_id}`"
              class="btn btn-ghost btn-sm"
            >📄 Voir OR complémentaire</NuxtLink>
          </div>
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Demandes complémentaires' })

const api = useApi()
const toast = useToast()
const loading = ref(false)
const sending = ref<number | null>(null)
const demandes = ref<any[]>([])
const statut = ref('')

const filters = [
  { value: '', label: 'Toutes' },
  { value: 'en_attente_validation', label: 'À envoyer' },
  { value: 'en_attente_decision_client', label: 'En attente client' },
  { value: 'accepte', label: 'Acceptées' },
  { value: 'refuse', label: 'Refusées' },
]

const filtered = computed(() => demandes.value)

async function load() {
  loading.value = true
  try {
    const url = statut.value ? `/demandes-travaux-supp?statut=${statut.value}` : '/demandes-travaux-supp'
    demandes.value = await api.get(url)
  } catch (e: unknown) {
    toast.add({ title: 'Erreur chargement', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loading.value = false
  }
}

async function envoyer(d: any) {
  sending.value = d.id
  try {
    const res = await api.post(`/demandes-travaux-supp/${d.id}/envoyer`, {})
    toast.add({ title: 'Envoyé au client', description: `Lien : ${res.lien_client}`, color: 'success' })
    await load()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    sending.value = null
  }
}

async function copyLink(token: string) {
  const url = `${location.origin}/public/demande/${token}`
  try {
    await navigator.clipboard.writeText(url)
    toast.add({ title: 'Lien copié', description: url, color: 'success' })
  } catch {
    prompt('Copier manuellement ce lien :', url)
  }
}

function labelStatut(s: string): string {
  return {
    en_attente: 'À envoyer',
    en_attente_validation: 'À envoyer',
    en_attente_decision_client: 'En attente client',
    accepte: 'Acceptée',
    refuse: 'Refusée',
  }[s] || s
}

function statutVariant(s: string): 'neutral' | 'warning' | 'success' | 'error' | 'default' {
  switch (s) {
    case 'en_attente':
    case 'en_attente_validation':
      return 'neutral'
    case 'en_attente_decision_client':
      return 'warning'
    case 'accepte':
      return 'success'
    case 'refuse':
      return 'error'
    default:
      return 'default'
  }
}

function formatEuro(v: any): string {
  const n = Number(v) || 0
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

watch(statut, load)
onMounted(load)
</script>

<style scoped>
.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.filter-btn { font-size:12px; padding:6px 14px; }
.loading-center { text-align:center; padding:32px; color:#9CA3AF; }
.demande-list { display:flex; flex-direction:column; gap:10px; }
.demande-card { padding:14px; border:1px solid rgba(255,255,255,0.06); border-radius:12px; background:rgba(255,255,255,0.02); }
.demande-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
.demande-left { flex:1; min-width:240px; }
.demande-title-row { display:flex; gap:10px; align-items:center; margin-bottom:4px; }
.demande-title { font-weight:800; color:#E8E9ED; font-size:14px; }
.demande-vehicle { font-size:12px; color:#9CA3AF; }
.link-yellow { color:#FFD200; }
.demande-desc { margin-top:6px; font-size:12px; color:#D1D5DB; font-style:italic; }
.prestation-list { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.prestation-chip { font-size:11px; padding:3px 9px; border-radius:6px; background:rgba(139,92,246,0.14); color:#C4B5FD; }
.demande-right { text-align:right; min-width:140px; }
.demande-price { font-size:16px; font-weight:800; color:#FFD200; }
.demande-time { font-size:11px; color:#6B7280; }
.demande-date { font-size:11px; color:#9CA3AF; margin-top:4px; }
.demande-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.05); }
.btn-sm { font-size:12px; padding:6px 14px; }
</style>
