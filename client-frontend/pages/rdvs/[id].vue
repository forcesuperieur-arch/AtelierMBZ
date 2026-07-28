<template>
  <div>
    <NuxtLink to="/rdvs" style="color:var(--content-3);font-size:13px;text-decoration:none;">← Retour aux RDV</NuxtLink>
    <h1 style="font-size:20px;font-weight:800;margin:12px 0 16px;">Détail du rendez-vous</h1>

    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="!rdv && rdvNotFound" style="color:var(--error-content)">Rendez-vous introuvable.</div>
    <div v-else-if="!rdv" style="color:var(--error-content)">Impossible de charger le rendez-vous pour le moment. Réessayez plus tard.</div>
    <div v-else class="rdv-detail">
      <div class="detail-row"><span>Date</span><span>{{ formatDate(rdv.date_heure) }}</span></div>
      <div class="detail-row"><span>Statut</span><span class="rdv-status-badge" :class="statusClass(rdv.statut)">{{ rdvStatutLabel(rdv.statut) }}</span></div>
      <div v-if="rdv.type_intervention" class="detail-row"><span>Intervention</span><span>{{ rdv.type_intervention }}</span></div>
      <div v-if="rdv.vehicule?.marque || rdv.vehicule?.modele" class="detail-row"><span>Moto</span><span>{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
      <div v-if="rdv.vehicule?.plaque" class="detail-row"><span>Immatriculation</span><span>{{ rdv.vehicule?.plaque }}</span></div>

      <!-- Prestations prévues (réservées) -->
      <div v-if="rdv.prestations_snapshot?.length" class="detail-block" data-testid="rdv-prestations">
        <div class="detail-label">Prestations prévues</div>
        <ul class="prestation-list">
          <li v-for="(p, i) in rdv.prestations_snapshot" :key="i">
            <span>{{ p.designation }}</span>
            <span class="prestation-prix">{{ formatEstimation(prestaPrice(p)) }}</span>
          </li>
        </ul>
        <div v-if="rdv.prix_estime" class="demande-total">
          <span>Total estimé</span>
          <strong>{{ formatEstimation(rdv.prix_estime) }}</strong>
        </div>
        <p style="font-size:11px;color:var(--content-3);margin-top:6px;">Montant indicatif (estimation), hors éventuels travaux supplémentaires.</p>
      </div>

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
        <div v-for="d in rdv.demandes_travaux" :key="d.id" class="demande-card" :class="{ actionable: d.decision_possible || d.confirmation_telephone }">
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
          <!-- Accord donné par téléphone : il ne reste qu'à le confirmer en signant -->
          <div v-else-if="d.confirmation_telephone" class="demande-confirm-tel" data-testid="bloc-confirmation-telephone">
            <p class="demande-confirm-tel-text">
              📞 Vous avez donné votre accord par téléphone le {{ formatDateShort(d.accord_telephone_at) }}.
              Confirmez-le en signant l'ordre de réparation complémentaire.
            </p>
            <button class="btn-accept" :disabled="decisionLoading" @click="ouvrirSignature(d)" data-testid="btn-confirmer-travaux-tel">
              Confirmer et signer
            </button>
          </div>
          <div v-else-if="d.accord_telephone_at && d.signed_at" class="demande-decision-info" data-testid="etat-accord-confirme">
            Accord confirmé le {{ formatDateShort(d.signed_at) }}
          </div>
          <div v-else-if="d.decision" class="demande-decision-info">
            {{ d.decision === 'accepte' ? 'Acceptés et signés' : 'Refusés' }}
            le {{ formatDateShort(d.decision_at) }}
          </div>
        </div>
        <div v-if="decisionError" style="margin-top:8px;font-size:13px;color:var(--error-content);">{{ decisionError }}</div>
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

      <!-- État des lieux d'entrée (visible seulement une fois signé) -->
      <div v-if="rdv.etat_des_lieux" class="detail-block" data-testid="rdv-etat-des-lieux">
        <div class="detail-label">État des lieux d'entrée</div>
        <div class="edl-card">
          <div class="edl-row">
            <span>Signé le</span>
            <span>{{ formatDateShort(rdv.etat_des_lieux.signed_at) }}</span>
          </div>
          <div class="edl-row">
            <span>Kilométrage</span>
            <span>{{ formatKilometrage(rdv.etat_des_lieux.kilometrage) }}</span>
          </div>
          <div class="edl-row">
            <span>Niveau de carburant</span>
            <span>{{ carburantLabel(rdv.etat_des_lieux.niveau_carburant) }}</span>
          </div>
          <p v-if="rdv.etat_des_lieux.observations" class="edl-observations">{{ rdv.etat_des_lieux.observations }}</p>
          <a
            v-if="rdv.etat_des_lieux.pdf_disponible"
            :href="`/api/client/rdvs/${rdv.id}/etat-des-lieux/pdf`"
            target="_blank"
            class="pdf-btn edl-pdf"
          >📄 Télécharger le PDF</a>
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
          <p style="font-size:13px;color:var(--content-1);margin-bottom:10px;">
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
        <div v-if="annulationError" style="margin-top:8px;font-size:13px;color:var(--error-content);">{{ annulationError }}</div>
      </div>
    </div>

    <SignatureModal
      v-if="signatureDemande"
      :title="isConfirmationTel ? 'Confirmez votre accord donné par téléphone' : 'Accepter les travaux supplémentaires'"
      :confirm-label="isConfirmationTel ? 'Confirmer et signer' : 'Accepter et signer'"
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

const { data: rdv, pending, refresh, error } = useAsyncData(
  `client-rdv-${route.params.id}`,
  async () => {
    if (!auth.isAuthenticated) return null
    // On laisse l'erreur REMONTER : useAsyncData conserve alors la dernière
    // valeur de `rdv` au lieu de l'écraser à null. Avant, un simple hoquet
    // réseau pendant le polling (30 s) faisait passer la fiche à « introuvable ».
    return await apiFetch(`/api/client/rdvs/${route.params.id}`)
  },
  // La clé de useAsyncData doit être une string (≠ useFetch). Pour recharger la
  // bonne fiche quand on navigue d'un RDV à un autre sans changer de route nommée
  // (instance de composant réutilisée), on s'appuie sur l'option watch : le
  // handler relit route.params.id à chaque déclenchement.
  { watch: [() => route.params.id] },
)

// Vrai « introuvable » (404) vs erreur temporaire (réseau/500) : ne pas afficher
// « introuvable » sur un simple incident réseau.
const rdvNotFound = computed(() => {
  const s = (error.value as any)?.statusCode ?? (error.value as any)?.response?.status
  return s === 404
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

// La modale de signature sert aux deux cas : acceptation classique et
// confirmation d'un accord déjà donné par téléphone (mêmes payload/endpoint).
const isConfirmationTel = computed(() => Boolean(signatureDemande.value?.confirmation_telephone))

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
  // Anti-double-soumission : le :disabled du template n'agit qu'au prochain tick,
  // un double-tap mobile rapide pouvait poster deux fois (même garde que SignatureModal).
  if (decisionLoading.value) return
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

// Même sémantique de couleur que la liste des RDV (rdvs/index.vue) : le statut
// n'est plus affiché en jaune uni mais en badge bleu (prévu) / vert (terminé) /
// rouge (annulé), pour une lecture cohérente entre liste et détail.
function statusClass(s: string) {
  if (['termine', 'restitue', 'restitue_partiel', 'facture', 'paye', 'livre'].includes(s)) return 'status-termine'
  if (['annule', 'no_show'].includes(s)) return 'status-annule'
  return 'status-prevu'
}

function demandeStatutLabel(d: any) {
  if (d.confirmation_telephone) return 'Accord par téléphone — signature à confirmer'
  if (d.statut === 'accepte') return 'Acceptés'
  if (d.statut === 'refuse') return 'Refusés'
  return 'En attente de votre décision'
}

function demandeBadgeClass(d: any) {
  if (d.confirmation_telephone) return 'waiting'
  if (d.statut === 'accepte') return 'ok'
  if (d.statut === 'refuse') return 'ko'
  return 'waiting'
}

async function demanderAnnulation() {
  if (annulationLoading.value) return
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

/** Libellés français des niveaux de carburant de l'état des lieux. */
const CARBURANT_LABELS: Record<string, string> = {
  vide: 'Vide',
  quart: '1/4',
  moitie: '1/2',
  trois_quarts: '3/4',
  plein: 'Plein',
}

// Prix effectif d'une prestation réservée : TTC si > 0, sinon HT.
function prestaPrice(p: any): number {
  const ttc = Number(p?.prix_ttc ?? 0)
  return ttc > 0 ? ttc : Number(p?.prix_ht ?? 0)
}
function formatEstimation(v: any): string {
  const n = Number(v)
  if (!Number.isFinite(n) || n <= 0) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n)
}

function carburantLabel(niveau?: string | null): string {
  if (!niveau) return '—'
  return CARBURANT_LABELS[niveau] ?? niveau
}

function formatKilometrage(km?: number | null): string {
  if (km === null || km === undefined) return '—'
  // Séparateur de milliers français, en espace simple (ex. « 12 345 km »).
  return `${Number(km).toLocaleString('fr-FR').replace(/[\u202f\u00a0]/g, ' ')} km`
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
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 8px;
  font-size: 14px;
}
.detail-row span:first-child {
  color: var(--content-3);
}
.rdv-status-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  text-transform: uppercase;
}
.rdv-status-badge.status-prevu {
  background: var(--info-soft);
  color: var(--info-content);
}
.rdv-status-badge.status-termine {
  background: var(--success-soft);
  color: var(--success-content);
}
.rdv-status-badge.status-annule {
  background: var(--error-soft);
  color: var(--error-content);
}
.detail-block {
  margin-top: 6px;
}
.detail-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--content-3);
  margin-bottom: 8px;
  text-transform: uppercase;
}
.timeline-card {
  padding: 14px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 8px;
}
.demande-card {
  padding: 14px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 8px;
  margin-bottom: 8px;
}
.demande-card.actionable {
  border-color: var(--accent-graphic);
  background: var(--accent-soft);
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
  background: var(--accent-soft);
  color: var(--accent-content);
  border: 1px solid var(--accent);
}
.demande-badge.ok {
  background: var(--success-soft);
  color: var(--success-content);
  border: 1px solid var(--success);
}
.demande-badge.ko {
  background: var(--error-soft);
  color: var(--error-content);
  border: 1px solid var(--error);
}
.demande-badge.urgent {
  background: var(--error-soft);
  color: var(--error-content);
  border: 1px solid var(--error);
}
.demande-desc {
  font-size: 13px;
  color: var(--content-2);
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
  color: var(--content-1);
}
.prestation-prix {
  color: var(--content-3);
  white-space: nowrap;
}
.demande-total {
  display: flex;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid var(--border-2);
  font-size: 14px;
}
.demande-total strong {
  color: var(--accent-content);
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
  background: var(--accent);
  color: var(--accent-ink);
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
  border: 1px solid var(--error);
  background: var(--error-soft);
  color: var(--error-content);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.btn-refuse:hover:not(:disabled) {
  background: var(--error-soft);
}
.btn-accept:disabled,
.btn-refuse:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.demande-decision-info {
  margin-top: 8px;
  font-size: 12px;
  color: var(--content-3);
}
.demande-confirm-tel {
  margin-top: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  background: var(--accent-soft);
  border: 1px solid var(--accent);
}
.demande-confirm-tel-text {
  margin: 0 0 10px;
  font-size: 13px;
  color: var(--content-1);
  line-height: 1.5;
}
.demande-confirm-tel .btn-accept {
  width: 100%;
}
.edl-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 8px;
}
.edl-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: var(--content-1);
}
.edl-row span:first-child {
  color: var(--content-3);
}
.edl-observations {
  margin: 0;
  padding-top: 8px;
  border-top: 1px solid var(--border-2);
  font-size: 13px;
  color: var(--content-2);
  white-space: pre-line;
}
.edl-pdf {
  align-self: flex-start;
  margin-top: 4px;
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
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 8px;
  font-size: 14px;
}
.pdf-btn {
  font-size: 13px;
  font-weight: 600;
  color: var(--info-content);
  text-decoration: none;
  padding: 6px 12px;
  border: 1px solid var(--info);
  border-radius: 8px;
  background: var(--info-soft);
}
.pdf-btn:hover {
  background: var(--info-soft);
}
.annulation-banner.pending {
  padding: 12px 14px;
  border-radius: 8px;
  background: var(--warning-soft);
  border: 1px solid var(--warning);
  color: var(--warning-content);
  font-size: 13px;
}
.annulation-btn {
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid var(--error);
  background: var(--error-soft);
  color: var(--error-content);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.annulation-btn:hover:not(:disabled) {
  background: var(--error-soft);
}
.annulation-btn.confirm {
  background: var(--error-soft);
  color: var(--error-content);
}
.annulation-btn.cancel {
  border-color: var(--border-1);
  background: var(--overlay-soft);
  color: var(--content-2);
}
.annulation-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.annulation-confirm {
  padding: 14px;
  border-radius: 8px;
  background: var(--overlay-soft);
  border: 1px solid var(--error);
}
</style>
