<template>
  <div v-if="loading" style="padding:40px;text-align:center;color:#9CA3AF;">Chargement…</div>
  <div v-else-if="!devis" style="padding:40px;text-align:center;color:#9CA3AF;">Devis introuvable</div>
  <div v-else>
    <!-- Header -->
    <div class="page-header" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <NuxtLink to="/devis" style="color:#9CA3AF;text-decoration:none;font-size:20px;">←</NuxtLink>
      <div class="page-title">Devis {{ devis.numero_devis || devis.numeroDevis }}</div>
      <StatusBadge :status="statusMap[devis.statut] || 'en_attente'" />
      <div style="flex:1;" />
      <button class="btn btn-ghost" @click="downloadPdf" style="font-size:13px;">📄 PDF</button>
    </div>

    <!-- Info Row -->
    <div class="detail-summary-grid">
      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">👤 Client</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div><strong>{{ clientNom }}</strong></div>
          <div v-if="client?.telephone">📞 {{ client.telephone }}</div>
          <div v-if="client?.email">✉️ {{ client.email }}</div>
        </div>
      </UCard>

      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">🏍 Véhicule</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div v-if="vehicule"><strong>{{ vehicule.marque }} {{ vehicule.modele }}</strong></div>
          <div v-if="vehicule?.plaque">Plaque : {{ vehicule.plaque }}</div>
          <div v-if="devis.kilometrage">Km : {{ devis.kilometrage?.toLocaleString() }}</div>
          <div v-if="!vehicule" style="color:#6B7280;">Non renseigné</div>
        </div>
      </UCard>

      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">📋 Infos</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div>Créé le : {{ formatDate(devis.date_creation || devis.dateCreation) }}</div>
          <div v-if="devis.date_validite || devis.dateValidite">Valide jusqu'au : {{ formatDate(devis.date_validite || devis.dateValidite) }}</div>
          <div v-if="devis.acompte_demande || devis.acompteDemande">Acompte : {{ formatCurrency(devis.acompte_demande || devis.acompteDemande) }}</div>
        </div>
      </UCard>
    </div>

    <!-- Lignes du devis -->
    <UCard style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">📝 Lignes</span></template>
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid rgba(255,255,255,0.08);">
            <th style="text-align:left;padding:8px;color:#9CA3AF;">Type</th>
            <th style="text-align:left;padding:8px;color:#9CA3AF;">Désignation</th>
            <th style="text-align:center;padding:8px;color:#9CA3AF;">Qté</th>
            <th style="text-align:right;padding:8px;color:#9CA3AF;">P.U. HT</th>
            <th style="text-align:right;padding:8px;color:#9CA3AF;">Total HT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l, i) in lignes" :key="i" style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:8px;">
              <span style="font-size:11px;padding:2px 8px;border-radius:4px;background:rgba(255,255,255,0.06);color:#9CA3AF;">{{ typeLabel(l.type) }}</span>
            </td>
            <td style="padding:8px;color:#E8E9ED;">{{ l.designation }}</td>
            <td style="text-align:center;padding:8px;color:#E8E9ED;">{{ l.quantite }}</td>
            <td style="text-align:right;padding:8px;color:#E8E9ED;">{{ formatCurrency(l.prix_unitaire_ht || l.prixUnitaireHt) }}</td>
            <td style="text-align:right;padding:8px;color:#FFD200;font-weight:600;">{{ formatCurrency((l.prix_unitaire_ht || l.prixUnitaireHt || 0) * (l.quantite || 1)) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Totaux -->
      <div style="border-top:2px solid rgba(255,255,255,0.08);margin-top:8px;padding-top:12px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;font-size:13px;">
        <div v-if="devis.total_mo_ht || devis.totalMoHt"><span style="color:#9CA3AF;margin-right:16px;">MO HT :</span> {{ formatCurrency(devis.total_mo_ht || devis.totalMoHt) }}</div>
        <div v-if="devis.total_pieces_ht || devis.totalPiecesHt"><span style="color:#9CA3AF;margin-right:16px;">Pièces HT :</span> {{ formatCurrency(devis.total_pieces_ht || devis.totalPiecesHt) }}</div>
        <div v-if="remise > 0"><span style="color:#9CA3AF;margin-right:16px;">Remise ({{ devis.remise_pourcentage || devis.remisePourcentage }}%) :</span> <span style="color:#EF4444;">-{{ formatCurrency(remise) }}</span></div>
        <div><span style="color:#9CA3AF;margin-right:16px;">Total HT :</span> <strong>{{ formatCurrency(devis.total_ht || devis.totalHt) }}</strong></div>
        <div style="font-size:16px;font-weight:700;color:#FFD200;margin-top:4px;"><span style="color:#9CA3AF;margin-right:16px;font-size:13px;font-weight:400;">Total TTC :</span> {{ formatCurrency(devis.total_ttc || devis.totalTtc) }}</div>
      </div>
    </UCard>

    <!-- Notes -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
      <UCard v-if="devis.notes_client || devis.notesClient">
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">📌 Notes client</span></template>
        <div style="font-size:14px;white-space:pre-wrap;color:#E8E9ED;">{{ devis.notes_client || devis.notesClient }}</div>
      </UCard>
      <UCard v-if="devis.notes_internes || devis.notesInternes">
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">🔒 Notes internes</span></template>
        <div style="font-size:14px;white-space:pre-wrap;color:#E8E9ED;">{{ devis.notes_internes || devis.notesInternes }}</div>
      </UCard>
    </div>

    <!-- Actions -->
    <UCard>
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">⚡ Actions</span></template>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <button v-if="devis.statut === 'brouillon'" class="btn btn-secondary" @click="action('envoyer')" :disabled="acting">📨 Envoyer au client</button>
        <button v-if="['envoye','accepte'].includes(devis.statut) && client?.email" class="btn btn-ghost" @click="sendDevisEmail" :disabled="sendingEmail" style="color:#93C5FD;">{{ sendingEmail ? 'Envoi…' : '📧 Renvoyer par email' }}</button>
        <button v-if="devis.statut === 'envoye'" class="btn btn-primary" @click="action('accepter')" :disabled="acting">✅ Accepter</button>
        <button v-if="devis.statut === 'envoye'" class="btn btn-ghost" @click="action('refuser')" :disabled="acting">❌ Refuser</button>
        <button v-if="devis.statut === 'accepte'" class="btn btn-primary" @click="action('convertir')" :disabled="acting">🔄 Convertir en RDV</button>
        <button v-if="['brouillon','envoye'].includes(devis.statut)" class="btn btn-ghost" @click="action('supprimer')" :disabled="acting" style="color:#EF4444;">🗑 Supprimer</button>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { openPdf } = usePdfDownload()

const loading = ref(true)
const acting = ref(false)
const sendingEmail = ref(false)
const devis = ref<any>(null)

const client = computed(() => devis.value?.client)
const vehicule = computed(() => devis.value?.vehicule)
const clientNom = computed(() => client.value ? `${client.value.prenom ?? ''} ${client.value.nom ?? ''}`.trim() : '—')
const lignes = computed(() => devis.value?.lignes ?? [])
const remise = computed(() => devis.value?.remise_montant || devis.value?.remiseMontant || 0)

const statusMap: Record<string, string> = {
  brouillon: 'en_attente',
  envoye: 'en_cours',
  accepte: 'confirme',
  refuse: 'annule',
  expire: 'annule',
  converti: 'termine',
}

function typeLabel(t: string) {
  const map: Record<string, string> = { forfait_mo: 'MO', piece: 'Pièce', main_oeuvre_libre: 'MO libre' }
  return map[t] || t
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}

function downloadPdf() {
  openPdf(`/devis/${devis.value.id}/pdf`)
}

async function sendDevisEmail() {
  sendingEmail.value = true
  try {
    await api.post(`/devis/${devis.value.id}/email`, {})
    toast.add({ title: 'Devis renvoyé par email', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur envoi email', description: e?.message || 'Échec', color: 'error' })
  } finally {
    sendingEmail.value = false
  }
}

async function action(type: string) {
  acting.value = true
  try {
    const id = devis.value.id
    if (type === 'envoyer') {
      await api.post(`/devis/${id}/envoyer`, {})
      toast.add({ title: 'Devis envoyé', color: 'success' })
    } else if (type === 'accepter') {
      await api.post(`/devis/${id}/accepter`, {})
      toast.add({ title: 'Devis accepté', color: 'success' })
    } else if (type === 'refuser') {
      await api.post(`/devis/${id}/refuser`, {})
      toast.add({ title: 'Devis refusé', color: 'warning' })
    } else if (type === 'convertir') {
      await api.post(`/devis/${id}/convertir`, {})
      toast.add({ title: 'Converti en RDV', color: 'success' })
      router.push('/rdv')
      return
    } else if (type === 'supprimer') {
      await api.del(`/devis/${id}`)
      toast.add({ title: 'Devis supprimé', color: 'success' })
      router.push('/devis')
      return
    }
    await loadDevis()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec', color: 'error' })
  } finally {
    acting.value = false
  }
}

async function loadDevis() {
  try {
    devis.value = await api.get(`/devis/${route.params.id}`)
  } catch {
    devis.value = null
  }
}

onMounted(async () => {
  await loadDevis()
  loading.value = false
})
</script>
