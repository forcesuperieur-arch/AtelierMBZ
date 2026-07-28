<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/" style="color:var(--content-3);text-decoration:none;font-size:18px;" aria-label="Retour à l'accueil"><AppIcon name="i-ri-arrow-left-line" /></NuxtLink>
        <div class="page-title">Demandes de travaux complémentaires</div>
      </div>
      <button class="btn btn-ghost" @click="load" :disabled="loading"><AppIcon name="i-ri-refresh-line" /> Rafraîchir</button>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <button v-for="f in filters" :key="f.value" class="btn" :class="statut === f.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="statut = f.value">
        {{ f.label }}
      </button>
    </div>

    <UCard>
      <div v-if="loading" style="text-align:center;padding:32px;color:var(--content-3);">Chargement…</div>
      <div v-else-if="filtered.length === 0" style="text-align:center;padding:32px;color:var(--content-3);">Aucune demande.</div>
      <div v-else style="display:flex;flex-direction:column;gap:10px;">
        <div v-for="d in filtered" :key="d.id" style="padding:14px;border:1px solid var(--border-2);border-radius:12px;background:var(--overlay-soft);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
              <div style="display:flex;gap:10px;align-items:center;margin-bottom:4px;">
                <span style="font-weight:800;color:var(--content-1);font-size:14px;">#{{ d.id }} — {{ d.client_nom || '—' }}</span>
                <span :style="statutStyle(d.statut)" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;">{{ labelStatut(d.statut) }}</span>
                <span
                  v-if="isSignatureEnAttente(d)"
                  data-testid="badge-signature-attente"
                  style="font-size:11px;padding:3px 10px;border-radius:999px;background:var(--warning-soft);color:var(--warning-content);font-weight:700;"
                ><AppIcon name="i-ri-quill-pen-line" /> Signature en attente</span>
                <span v-if="d.urgence === 'urgent'" style="font-size:11px;padding:3px 10px;border-radius:999px;background:var(--error-soft);color:var(--error-content);font-weight:700;">URGENT</span>
              </div>
              <div style="font-size:12px;color:var(--content-3);">
                <span v-if="d.vehicule_info">{{ d.vehicule_info }}</span>
                <span v-if="d.vehicule_plaque"> • {{ d.vehicule_plaque }}</span>
                <span> • RDV #{{ d.rendez_vous_id }}</span>
              </div>
              <div v-if="d.description" style="margin-top:6px;font-size:12px;color:var(--content-2);font-style:italic;">« {{ d.description }} »</div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                <span v-for="(p, i) in d.prestations" :key="i" style="font-size:11px;padding:3px 9px;border-radius:6px;background:var(--info-soft);color:var(--info-content);">
                  {{ p.designation }} — {{ formatEuro(p.prix_ttc) }}
                </span>
              </div>
            </div>
            <div style="text-align:right;min-width:140px;">
              <div style="font-size:16px;font-weight:800;color:var(--accent-content);">{{ formatEuro(d.prix_estime) }}</div>
              <div style="font-size:11px;color:var(--content-3);">~{{ formatMinutes(d.temps_estime) }}</div>
              <div v-if="d.decision_client_at" style="font-size:11px;color:var(--content-3);margin-top:4px;">
                Décidé le {{ new Date(d.decision_client_at).toLocaleString('fr-FR') }}
              </div>
              <div
                v-if="isSignatureEnAttente(d) && d.decision_enregistree_par"
                style="font-size:11px;color:var(--content-3);margin-top:2px;"
              >
                Accord tél. enregistré{{ staffLabel(d.decision_enregistree_par) }}<span v-if="d.decision_client_at"> le {{ new Date(d.decision_client_at).toLocaleString('fr-FR') }}</span>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border-2);">
            <template v-if="['en_attente', 'en_attente_validation'].includes(d.statut)">
              <template v-if="showCanalFor === d.id">
                <button
                  class="btn btn-primary"
                  style="font-size:12px;padding:6px 14px;"
                  :disabled="sending === d.id"
                  @click="envoyer(d, 'email')"
                >
                  <AppIcon v-if="!(sending === d.id)" name="i-ri-mail-line" />{{ sending === d.id ? 'Envoi…' : 'Email' }}
                </button>
                <button
                  class="btn btn-primary"
                  style="font-size:12px;padding:6px 14px;"
                  :disabled="sending === d.id"
                  @click="envoyer(d, 'sms')"
                >
                  <AppIcon v-if="!(sending === d.id)" name="i-ri-smartphone-line" />{{ sending === d.id ? 'Envoi…' : 'SMS' }}
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
                <AppIcon name="i-ri-upload-line" /> Envoyer au client
              </button>
            </template>
            <button
              v-if="!['accepte', 'refuse'].includes(d.statut)"
              class="btn btn-ghost"
              style="font-size:12px;padding:6px 14px;"
              data-testid="btn-decision-telephone"
              @click="ouvrirDecisionTelephone(d)"
            ><AppIcon name="i-ri-phone-line" /> Décision téléphonique</button>
            <button
              v-if="isSignatureEnAttente(d) && d.token"
              class="btn btn-primary"
              style="font-size:12px;padding:6px 14px;"
              data-testid="btn-faire-signer-comptoir"
              @click="faireSignerComptoir(d)"
            ><AppIcon name="i-ri-quill-pen-line" /> Faire signer au comptoir</button>
            <button
              v-if="d.token && d.statut === 'en_attente_decision_client'"
              class="btn btn-ghost"
              style="font-size:12px;padding:6px 14px;"
              @click="copyLink(d.token)"
            ><AppIcon name="i-ri-links-line" /> Copier le lien client</button>
            <!-- Pas de page de détail OR : on affiche la référence sans lien mort. -->
            <span
              v-if="d.or_complementaire_id"
              style="font-size:12px;padding:6px 14px;color:var(--content-3);"
            ><AppIcon name="i-ri-file-text-line" /> OR complémentaire n° {{ d.or_complementaire_id }}</span>
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
const telephoneModal = useDemandeTravauxSuppTelephoneModal()
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

function isSignatureEnAttente(d: any): boolean {
  return d.statut === 'accepte' && d.decision_canal === 'staff_telephone' && !d.signed_at
}

function staffLabel(p: any): string {
  const nom = [p?.prenom, p?.nom].filter(Boolean).join(' ').trim()
  return nom ? ` par ${nom}` : ''
}

function ouvrirDecisionTelephone(d: any) {
  telephoneModal.open(d, () => load())
}

function faireSignerComptoir(d: any) {
  window.open(`${location.origin}/public/demande/${d.token}`, '_blank')
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
    en_attente: 'background:var(--surface-3);color:var(--content-3);',
    en_attente_validation: 'background:var(--surface-3);color:var(--content-3);',
    en_attente_decision_client: 'background:var(--warning-soft);color:var(--warning-content);',
    accepte: 'background:var(--success-soft);color:var(--success-content);',
    refuse: 'background:var(--error-soft);color:var(--error-content);',
  }
  return map[s] || 'background:var(--overlay-hover);color:var(--content-3);'
}

function formatEuro(v: any): string {
  const n = Number(v) || 0
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

watch(statut, load)
onMounted(load)
</script>
