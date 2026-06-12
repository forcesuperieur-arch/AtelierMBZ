<template>
  <div>
    <NuxtLink to="/rdvs" style="color:#9CA3AF;font-size:13px;text-decoration:none;">← Retour aux RDV</NuxtLink>
    <h1 style="font-size:20px;font-weight:800;margin:12px 0 16px;">Détail du rendez-vous</h1>

    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="!rdv" style="color:#FCA5A5">Rendez-vous introuvable.</div>
    <div v-else class="rdv-detail">
      <div class="detail-row"><span>Date</span><span>{{ formatDate(rdv.date_heure) }}</span></div>
      <div class="detail-row"><span>Statut</span><span>{{ rdvStatutLabel(rdv.statut) }}</span></div>
      <div v-if="rdv.type_intervention" class="detail-row"><span>Intervention</span><span>{{ rdv.type_intervention }}</span></div>
      <div v-if="rdv.vehicule?.marque || rdv.vehicule?.modele" class="detail-row"><span>Moto</span><span>{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
      <div v-if="rdv.vehicule?.plaque" class="detail-row"><span>Immatriculation</span><span>{{ rdv.vehicule?.plaque }}</span></div>

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

      <div v-if="rdv.photos?.length" class="detail-block">
        <div class="detail-label">Photos</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <img v-for="photo in rdv.photos" :key="photo.id" :src="`/uploads/photos/${photo.filename}`" style="width:120px;height:120px;object-fit:cover;border-radius:8px;" />
        </div>
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
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const auth = useAuthStore()

const { apiFetch } = useClientApi()

const confirmAnnulation = ref(false)
const annulationLoading = ref(false)
const annulationError = ref('')

const { data: rdv, pending, refresh } = useAsyncData(`client-rdv-${route.params.id}`, async () => {
  if (!auth.isAuthenticated) return null
  try {
    return await apiFetch(`/api/client/rdvs/${route.params.id}`)
  } catch {
    return null
  }
})

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
