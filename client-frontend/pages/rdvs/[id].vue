<template>
  <div>
    <NuxtLink to="/rdvs" style="color:#9CA3AF;font-size:13px;text-decoration:none;">← Retour aux RDV</NuxtLink>
    <h1 style="font-size:20px;font-weight:800;margin:12px 0 16px;">Détail du rendez-vous</h1>

    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="!rdv" style="color:#FCA5A5">Rendez-vous introuvable.</div>
    <div v-else class="rdv-detail">
      <div class="detail-row"><span>Date</span><span>{{ formatDate(rdv.date_heure) }}</span></div>
      <div class="detail-row"><span>Statut</span><span class="statut-current">{{ rdvStatutLabel(rdv.statut) }}</span></div>
      <div v-if="rdv.type_intervention" class="detail-row"><span>Intervention</span><span>{{ rdv.type_intervention }}</span></div>
      <div v-if="rdv.vehicule?.marque || rdv.vehicule?.modele" class="detail-row"><span>Moto</span><span>{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
      <div v-if="rdv.vehicule?.plaque" class="detail-row"><span>Immatriculation</span><span>{{ rdv.vehicule?.plaque }}</span></div>

      <!-- Suivi en temps réel -->
      <div v-if="rdv.timeline?.length" class="detail-block" data-testid="rdv-timeline">
        <div class="detail-label">Suivi de votre moto</div>
        <div class="timeline-card">
          <RdvTimeline :steps="rdv.timeline" />
        </div>
      </div>

      <!-- Travaux supplémentaires à valider -->
      <div v-if="rdv.demandes_travaux?.length" class="detail-block" data-testid="demandes-travaux">
        <div class="detail-label">Travaux supplémentaires</div>
        <div v-for="d in rdv.demandes_travaux" :key="d.id" class="demande-card" :class="{ actionable: d.decision_possible }">
          <div class="demande-head">
            <span class="demande-badge" :class="demandeBadgeClass(d)">{{ demandeStatutLabel(d) }}</span>
            <span v-if="d.urgence === 'urgent'" class="demande-badge urgent">Urgent</span>
          </div>
          <p v-if="d.description" class="demande-desc">{{ d.description }}</p>
          <ul v-if="d.prestations?.length" class="prestation-list">
            <li v-for="(p, i) in d.prestations" :key="i">
              <span>{{ p.designation }}</span>
              <span class="prestation-prix">{{ p.prix_ttc }} € TTC</span>
            </li>
          </ul>
          <div v-if="d.prix_estime" class="demande-total">
            <span>Total estimé</span>
            <strong>{{ d.prix_estime }} € TTC</strong>
          </div>

          <div v-if="d.decision_possible" class="demande-actions">
            <button class="btn-refuse" :disabled="decisionLoading" @click="refuser(d)" data-testid="btn-refuser-travaux">
              Refuser
            </button>
            <button class="btn-accept" :disabled="decisionLoading" @click="ouvrirSignature(d)" data-testid="btn-accepter-travaux">
              Accepter et signer
            </button>
          </div>
          <div v-else-if="d.decision" class="demande-decision-info">
            {{ d.decision === 'accepte' ? 'Acceptés et signés' : 'Refusés' }}
            le {{ formatDateShort(d.decision_at) }}
          </div>
        </div>
        <div v-if="decisionError" style="margin-top:8px;font-size:13px;color:#FCA5A5;">{{ decisionError }}</div>
      </div>

      <!-- Photos de l'intervention, au fil de l'eau -->
      <div v-if="rdv.photos?.length" class="detail-block" data-testid="rdv-photos">
        <div class="detail-label">Photos de l'intervention</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <img
            v-for="photo in rdv.photos"
            :key="photo.id"
            :src="photo.url || `/api/client/photos/${photo.id}`"
            :alt="photo.description || 'Photo de l\'intervention'"
            loading="lazy"
            style="width:120px;height:120px;object-fit:cover;border-radius:8px;"
          />
        </div>
      </div>

      <div v-if="rdv.ordres_reparation?.length" class="detail-block">
        <div class="detail-label">Ordres de réparation</div>
        <ul class="or-list">
          <li v-for="o in rdv.ordres_reparation" :key="o.id" class="or-item">
            <span>{{ o.numero_or }} — {{ o.type_or }}</span>
            <a
              v-if="o.pdf_disponible"
              :href="`/api/client/rdvs/${rdv.id}/or/${o.id}/pdf`"
              target="_blank"
              class="pdf-btn"
            >📄 Télécharger le PDF</a>
          </li>
        </ul>
      </div>

      <!-- Demande d'annulation -->
      <div v-if="rdv.annulation_demandee_at" class="annulation-banner pending">
        Demande d'annulation envoyée le {{ formatDateShort(rdv.annulation_demandee_at) }}. L'atelier va vous recontacter.
      </div>
      <div v-else-if="rdv.annulation_possible" class="detail-block">
        <button v-if="!confirmAnnulation" class="annulation-btn" @click="confirmAnnulation = true">
          Demander l'annulation de ce rendez-vous
        </button>
        <div v-else class="annulation-confirm">
          <p style="font-size:13px;color:#E8E9ED;margin-bottom:10px;">
            Confirmer la demande d'annulation ? L'atelier sera prévenu et vous recontactera.
          </p>
          <div style="display:flex;gap:8px;">
            <button class="annulation-btn confirm" :disabled="annulationLoading" @click="demanderAnnulation">
              {{ annulationLoading ? 'Envoi…' : 'Oui, demander l\'annulation' }}
            </button>
            <button class="annulation-btn cancel" :disabled="annulationLoading" @click="confirmAnnulation = false">
              Non, garder mon RDV
            </button>
          </div>
        </div>
        <div v-if="annulationError" style="margin-top:8px;font-size:13px;color:#FCA5A5;">{{ annulationError }}</div>
      </div>
    </div>

    <SignatureModal
      v-if="signatureDemande"
      title="Accepter les travaux supplémentaires"
      confirm-label="Accepter et signer"
      :saving="decisionLoading"
      :error="decisionError"
      @close="signatureDemande = null"
      @signed="accepter"
    />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const auth = useAuthStore()

const { apiFetch } = useClientApi()

const confirmAnnulation = ref(false)
const annulationLoading = ref(false)
const annulationError = ref('')

const signatureDemande = ref<any>(null)
const decisionLoading = ref(false)
const decisionError = ref('')

const { data: rdv, pending, refresh } = useAsyncData(`client-rdv-${route.params.id}`, async () => {
  if (!auth.isAuthenticated) return null
  try {
    return await apiFetch(`/api/client/rdvs/${route.params.id}`)
  } catch {
    return null
  }
})

// Suivi quasi temps réel : Mercure n'est volontairement pas exposé au public
// (décision sécurité), le portail rafraîchit donc par polling.
const POLL_INTERVAL_MS = 30_000
let pollTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  pollTimer = setInterval(() => {
    if (document.visibilityState === 'visible' && auth.isAuthenticated) refresh()
  }, POLL_INTERVAL_MS)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

function ouvrirSignature(demande: any) {
  decisionError.value = ''
  signatureDemande.value = demande
}

async function accepter(signature: string) {
  if (!signatureDemande.value) return
  await envoyerDecision(signatureDemande.value, 'accepte', signature)
}

async function refuser(demande: any) {
  if (!window.confirm('Refuser ces travaux supplémentaires ? L\'atelier en sera informé.')) return
  await envoyerDecision(demande, 'refuse')
}

async function envoyerDecision(demande: any, decision: 'accepte' | 'refuse', signature?: string) {
  decisionLoading.value = true
  decisionError.value = ''
  try {
    await apiFetch(`/api/client/demandes-travaux-supp/${demande.id}/decision`, {
      method: 'POST',
      body: { decision, ...(signature ? { signature } : {}) },
    })
    signatureDemande.value = null
    await refresh()
  } catch (e: any) {
    decisionError.value = e?.data?.error || 'Impossible d\'enregistrer votre décision. Réessayez ou contactez l\'atelier.'
  } finally {
    decisionLoading.value = false
  }
}

function demandeStatutLabel(d: any) {
  if (d.statut === 'accepte') return 'Acceptés'
  if (d.statut === 'refuse') return 'Refusés'
  return 'En attente de votre décision'
}

function demandeBadgeClass(d: any) {
  if (d.statut === 'accepte') return 'ok'
  if (d.statut === 'refuse') return 'ko'
  return 'waiting'
}

async function demanderAnnulation() {
  annulationLoading.value = true
  annulationError.value = ''
  try {
    await apiFetch(`/api/client/rdvs/${route.params.id}/demande-annulation`, { method: 'POST' })
    confirmAnnulation.value = false
    await refresh()
  } catch (e: any) {
    annulationError.value = e?.data?.error || 'Impossible d\'envoyer la demande. Réessayez ou contactez l\'atelier.'
  } finally {
    annulationLoading.value = false
  }
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
}

function formatDateShort(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.rdv-detail {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  font-size: 14px;
}
.detail-row span:first-child {
  color: #9CA3AF;
}
.statut-current {
  color: #FFD200;
  font-weight: 700;
}
.detail-block {
  margin-top: 6px;
}
.detail-label {
  font-size: 12px;
  font-weight: 700;
  color: #9CA3AF;
  margin-bottom: 8px;
  text-transform: uppercase;
}
.timeline-card {
  padding: 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
}
.demande-card {
  padding: 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  margin-bottom: 8px;
}
.demande-card.actionable {
  border-color: rgba(255, 210, 0, 0.4);
  background: rgba(255, 210, 0, 0.04);
}
.demande-head {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.demande-badge {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 999px;
}
.demande-badge.waiting {
  background: rgba(255, 210, 0, 0.12);
  color: #FFD200;
  border: 1px solid rgba(255, 210, 0, 0.35);
}
.demande-badge.ok {
  background: rgba(34, 197, 94, 0.1);
  color: #86EFAC;
  border: 1px solid rgba(34, 197, 94, 0.3);
}
.demande-badge.ko {
  background: rgba(239, 68, 68, 0.1);
  color: #FCA5A5;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
.demande-badge.urgent {
  background: rgba(239, 68, 68, 0.16);
  color: #FCA5A5;
  border: 1px solid rgba(239, 68, 68, 0.4);
}
.demande-desc {
  font-size: 13px;
  color: #D1D5DB;
  margin: 0 0 8px;
  white-space: pre-line;
}
.prestation-list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.prestation-list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: #E8E9ED;
}
.prestation-prix {
  color: #9CA3AF;
  white-space: nowrap;
}
.demande-total {
  display: flex;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid rgba(255,255,255,0.08);
  font-size: 14px;
}
.demande-total strong {
  color: #FFD200;
}
.demande-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}
.btn-accept {
  flex: 1;
  min-width: 160px;
  padding: 10px 16px;
  border-radius: 8px;
  border: none;
  background: #FFD200;
  color: #0C0D12;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
}
.btn-accept:hover:not(:disabled) {
  filter: brightness(1.08);
}
.btn-refuse {
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid rgba(239,68,68,0.35);
  background: rgba(239,68,68,0.08);
  color: #FCA5A5;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.btn-refuse:hover:not(:disabled) {
  background: rgba(239,68,68,0.16);
}
.btn-accept:disabled,
.btn-refuse:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.demande-decision-info {
  margin-top: 8px;
  font-size: 12px;
  color: #9CA3AF;
}
.or-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.or-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 10px 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  font-size: 14px;
}
.pdf-btn {
  font-size: 13px;
  font-weight: 600;
  color: #93C5FD;
  text-decoration: none;
  padding: 6px 12px;
  border: 1px solid rgba(59,130,246,0.3);
  border-radius: 8px;
  background: rgba(59,130,246,0.08);
}
.pdf-btn:hover {
  background: rgba(59,130,246,0.18);
}
.annulation-banner.pending {
  padding: 12px 14px;
  border-radius: 8px;
  background: rgba(245,158,11,0.08);
  border: 1px solid rgba(245,158,11,0.3);
  color: #FCD34D;
  font-size: 13px;
}
.annulation-btn {
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid rgba(239,68,68,0.35);
  background: rgba(239,68,68,0.08);
  color: #FCA5A5;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.annulation-btn:hover:not(:disabled) {
  background: rgba(239,68,68,0.16);
}
.annulation-btn.confirm {
  background: rgba(239,68,68,0.2);
  color: #FECACA;
}
.annulation-btn.cancel {
  border-color: rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.04);
  color: #D1D5DB;
}
.annulation-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.annulation-confirm {
  padding: 14px;
  border-radius: 8px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(239,68,68,0.2);
}
</style>
