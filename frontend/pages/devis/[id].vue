<template>
  <div v-if="loading" style="padding:40px;text-align:center;color:var(--content-3);">Chargement…</div>
  <div v-else-if="!devis" style="padding:40px;text-align:center;color:var(--content-3);">Devis introuvable</div>
  <div v-else>
    <!-- Header -->
    <div class="page-header" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <NuxtLink to="/devis" style="color:var(--content-3);text-decoration:none;font-size:20px;" aria-label="Retour aux devis"><AppIcon name="i-ri-arrow-left-line" /></NuxtLink>
      <div class="page-title">Devis {{ devis.numero_devis }}</div>
      <StatusBadge :status="statusMap[devis.statut] || 'en_attente'" />
      <div style="flex:1;" />
      <button class="btn btn-ghost" @click="downloadPdf" style="font-size:13px;"><AppIcon name="i-ri-file-text-line" /> PDF</button>
    </div>

    <!-- Info Row -->
    <div class="detail-summary-grid">
      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-user-line" /> Client</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div><strong>{{ clientNom }}</strong></div>
          <div v-if="client?.telephone"><AppIcon name="i-ri-phone-line" /> {{ client.telephone }}</div>
          <div v-if="client?.email"><AppIcon name="i-ri-mail-line" /> {{ client.email }}</div>
        </div>
      </UCard>

      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-motorbike-line" /> Véhicule</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div v-if="vehicule"><strong>{{ vehicule.marque }} {{ vehicule.modele }}</strong></div>
          <div v-if="vehicule?.plaque">Plaque : {{ vehicule.plaque }}</div>
          <div v-if="devis.kilometrage">Km : {{ devis.kilometrage?.toLocaleString() }}</div>
          <div v-if="!vehicule" style="color:var(--content-3);">Non renseigné</div>
        </div>
      </UCard>

      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-clipboard-line" /> Infos</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div>Créé le : {{ formatDate(devis.date_creation) }}</div>
          <div v-if="devis.date_validite">Valide jusqu'au : {{ formatDate(devis.date_validite) }}</div>
          <div v-if="devis.acompte_demande">Acompte : {{ formatCurrency(devis.acompte_demande) }}</div>
        </div>
      </UCard>
    </div>

    <!-- Lignes du devis -->
    <UCard style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-draft-line" /> Lignes</span></template>
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid var(--border-2);">
            <th style="text-align:left;padding:8px;color:var(--content-3);">Type</th>
            <th style="text-align:left;padding:8px;color:var(--content-3);">Désignation</th>
            <th style="text-align:center;padding:8px;color:var(--content-3);">Qté</th>
            <th style="text-align:right;padding:8px;color:var(--content-3);">P.U. HT</th>
            <th style="text-align:right;padding:8px;color:var(--content-3);">Total HT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!lignes.length">
            <td colspan="5" style="padding:16px;text-align:center;color:var(--error-content);">
              <AppIcon name="i-ri-error-warning-line" /> Ce devis n'a aucune ligne — il ne peut pas être envoyé au client en l'état.
            </td>
          </tr>
          <tr v-for="(l, i) in lignes" :key="i" style="border-bottom:1px solid var(--border-2);">
            <td style="padding:8px;">
              <span style="font-size:11px;padding:2px 8px;border-radius:4px;background:var(--overlay-hover);color:var(--content-3);">{{ typeLabel(l.type) }}</span>
            </td>
            <td style="padding:8px;color:var(--content-1);">{{ l.designation }}</td>
            <td style="text-align:center;padding:8px;color:var(--content-1);">{{ l.quantite }}</td>
            <td style="text-align:right;padding:8px;color:var(--content-1);">{{ formatCurrency(l.prix_unitaire_ht) }}</td>
            <td style="text-align:right;padding:8px;color:var(--accent-content);font-weight:600;">{{ formatCurrency((l.prix_unitaire_ht || 0) * (l.quantite || 1)) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Totaux -->
      <div style="border-top:2px solid var(--border-2);margin-top:8px;padding-top:12px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;font-size:13px;">
        <div v-if="devis.total_mo_ht"><span style="color:var(--content-3);margin-right:16px;">MO HT :</span> {{ formatCurrency(devis.total_mo_ht) }}</div>
        <div v-if="devis.total_pieces_ht"><span style="color:var(--content-3);margin-right:16px;">Pièces HT :</span> {{ formatCurrency(devis.total_pieces_ht) }}</div>
        <div v-if="remise > 0"><span style="color:var(--content-3);margin-right:16px;">Remise ({{ devis.remise_pourcentage }}%) :</span> <span style="color:var(--error-content);">-{{ formatCurrency(remise) }}</span></div>
        <div><span style="color:var(--content-3);margin-right:16px;">Total HT :</span> <strong>{{ formatCurrency(devis.total_ht) }}</strong></div>
        <div style="font-size:16px;font-weight:700;color:var(--accent-content);margin-top:4px;"><span style="color:var(--content-3);margin-right:16px;font-size:13px;font-weight:400;">Total TTC :</span> {{ formatCurrency(devis.total_ttc) }}</div>
      </div>
    </UCard>

    <!-- Notes -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
      <UCard v-if="devis.notes_client">
        <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-pushpin-line" /> Notes client</span></template>
        <div style="font-size:14px;white-space:pre-wrap;color:var(--content-1);">{{ devis.notes_client }}</div>
      </UCard>
      <UCard v-if="devis.notes_internes">
        <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-lock-line" /> Notes internes</span></template>
        <div style="font-size:14px;white-space:pre-wrap;color:var(--content-1);">{{ devis.notes_internes }}</div>
      </UCard>
    </div>

    <!-- Actions -->
    <UCard>
      <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);"><AppIcon name="i-ri-flashlight-line" /> Actions</span></template>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <button v-if="devis.statut === 'brouillon'" class="btn btn-secondary" @click="action('envoyer')" :disabled="acting || !lignes.length" :title="!lignes.length ? 'Ce devis n\'a aucune ligne' : ''"><AppIcon name="i-ri-mail-send-line" /> Envoyer au client</button>
        <button v-if="['envoye','accepte'].includes(devis.statut) && client?.email" class="btn btn-ghost" @click="sendDevisEmail" :disabled="sendingEmail" style="color:var(--info-content);"><AppIcon v-if="!sendingEmail" name="i-ri-mail-line" />{{ sendingEmail ? 'Envoi…' : 'Renvoyer par email' }}</button>
        <button v-if="devis.statut === 'envoye'" class="btn btn-primary" @click="action('accepter')" :disabled="acting"><AppIcon name="i-ri-checkbox-circle-line" /> Accepter</button>
        <button v-if="devis.statut === 'envoye'" class="btn btn-ghost" @click="action('refuser')" :disabled="acting"><AppIcon name="i-ri-close-circle-line" /> Refuser</button>
        <button v-if="devis.statut === 'accepte'" class="btn btn-primary" @click="action('convertir')" :disabled="acting"><AppIcon name="i-ri-refresh-line" /> Convertir en RDV</button>
        <button v-if="['brouillon','envoye'].includes(devis.statut)" class="btn btn-ghost" @click="action('supprimer')" :disabled="acting" style="color:var(--error-content);"><AppIcon name="i-ri-delete-bin-line" /> Supprimer</button>
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
const remise = computed(() => devis.value?.remise_montant || 0)

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
