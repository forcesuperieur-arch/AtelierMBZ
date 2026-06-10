<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Demandes de travaux complémentaires</div>
      </div>
      <button class="btn btn-ghost" @click="load" :disabled="loading">🔄 Rafraîchir</button>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <button v-for="f in filters" :key="f.value" class="btn" :class="statut === f.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="statut = f.value">
        {{ f.label }}
      </button>
    </div>

    <UCard>
      <div v-if="loading" style="text-align:center;padding:32px;color:#9CA3AF;">Chargement…</div>
      <div v-else-if="filtered.length === 0" style="text-align:center;padding:32px;color:#6B7280;">Aucune demande.</div>
      <div v-else style="display:flex;flex-direction:column;gap:10px;">
        <div v-for="d in filtered" :key="d.id" style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
              <div style="display:flex;gap:10px;align-items:center;margin-bottom:4px;">
                <span style="font-weight:800;color:#E8E9ED;font-size:14px;">#{{ d.id }} — {{ d.client_nom || '—' }}</span>
                <span :style="statutStyle(d.statut)" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;">{{ labelStatut(d.statut) }}</span>
                <span v-if="d.urgence === 'urgent'" style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(239,68,68,0.14);color:#FCA5A5;font-weight:700;">URGENT</span>
              </div>
              <div style="font-size:12px;color:#9CA3AF;">
                <span v-if="d.vehicule_info">{{ d.vehicule_info }}</span>
                <span v-if="d.vehicule_plaque"> • {{ d.vehicule_plaque }}</span>
                <span> • RDV #{{ d.rendez_vous_id }}</span>
              </div>
              <div v-if="d.description" style="margin-top:6px;font-size:12px;color:#D1D5DB;font-style:italic;">« {{ d.description }} »</div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                <span v-for="(p, i) in d.prestations" :key="i" style="font-size:11px;padding:3px 9px;border-radius:6px;background:rgba(139,92,246,0.14);color:#C4B5FD;">
                  {{ p.designation }} — {{ formatEuro(p.prix_ttc) }}
                </span>
              </div>
            </div>
            <div style="text-align:right;min-width:140px;">
              <div style="font-size:16px;font-weight:800;color:#FFD200;">{{ formatEuro(d.prix_estime) }}</div>
              <div style="font-size:11px;color:#6B7280;">~{{ formatMinutes(d.temps_estime) }}</div>
              <div v-if="d.decision_client_at" style="font-size:11px;color:#9CA3AF;margin-top:4px;">
                Décidé le {{ new Date(d.decision_client_at).toLocaleString('fr-FR') }}
              </div>
            </div>
          </div>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.05);">
            <template v-if="['en_attente', 'en_attente_validation'].includes(d.statut)">
              <template v-if="showCanalFor === d.id">
                <button
                  class="btn btn-primary"
                  style="font-size:12px;padding:6px 14px;"
                  :disabled="sending === d.id"
                  @click="envoyer(d, 'email')"
                >
                  {{ sending === d.id ? 'Envoi…' : '📧 Email' }}
                </button>
                <button
                  class="btn btn-primary"
                  style="font-size:12px;padding:6px 14px;"
                  :disabled="sending === d.id"
                  @click="envoyer(d, 'sms')"
                >
                  {{ sending === d.id ? 'Envoi…' : '📱 SMS' }}
                </button>
                <button
                  class="btn btn-ghost"
                  style="font-size:12px;padding:6px 14px;"
                  :disabled="sending === d.id"
                  @click="showCanalFor = null"
                >
                  Annuler
                </button>
              </template>
              <button
                v-else
                class="btn btn-primary"
                style="font-size:12px;padding:6px 14px;"
                @click="showCanalFor = d.id"
              >
                📤 Envoyer au client
              </button>
            </template>
            <button
              v-if="d.token && d.statut === 'en_attente_decision_client'"
              class="btn btn-ghost"
              style="font-size:12px;padding:6px 14px;"
              @click="copyLink(d.token)"
            >🔗 Copier le lien client</button>
            <NuxtLink
              v-if="d.or_complementaire_id"
              :to="`/ordres/${d.or_complementaire_id}`"
              class="btn btn-ghost"
              style="font-size:12px;padding:6px 14px;text-decoration:none;"
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
const showCanalFor = ref<number | null>(null)
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
  } catch (e: any) {
    toast.add({ title: 'Erreur chargement', description: e.message, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function envoyer(d: any, canal: 'email' | 'sms') {
  sending.value = d.id
  try {
    const res = await api.post(`/demandes-travaux-supp/${d.id}/envoyer`, { canal })
    if (res.envoye) {
      toast.add({ title: `Envoyé par ${canal === 'email' ? 'e-mail' : 'SMS'}`, description: `Destinataire : ${res.destinataire}`, color: 'success' })
    } else if (res.error) {
      toast.add({ title: 'Erreur d\'envoi', description: res.error, color: 'error' })
    } else {
      toast.add({ title: 'Lien prêt', description: `Lien : ${res.lien_client}`, color: 'warning' })
    }
    showCanalFor.value = null
    await load()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.data?.error || e.message, color: 'error' })
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

function statutStyle(s: string) {
  const map: Record<string, string> = {
    en_attente: 'background:rgba(156,163,175,0.14);color:#9CA3AF;',
    en_attente_validation: 'background:rgba(156,163,175,0.14);color:#9CA3AF;',
    en_attente_decision_client: 'background:rgba(251,191,36,0.14);color:#FCD34D;',
    accepte: 'background:rgba(16,185,129,0.14);color:#6EE7B7;',
    refuse: 'background:rgba(239,68,68,0.14);color:#FCA5A5;',
  }
  return map[s] || 'background:rgba(255,255,255,0.06);color:#9CA3AF;'
}

function formatEuro(v: any): string {
  const n = Number(v) || 0
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

watch(statut, load)
onMounted(load)
</script>
