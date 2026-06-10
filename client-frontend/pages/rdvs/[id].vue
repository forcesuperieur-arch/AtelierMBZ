<template>
  <div>
    <NuxtLink to="/rdvs" style="color:#9CA3AF;font-size:13px;text-decoration:none;">← Retour aux RDV</NuxtLink>
    <h1 style="font-size:20px;font-weight:800;margin:12px 0 16px;">Détail du rendez-vous</h1>

    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="!rdv" style="color:#FCA5A5">Rendez-vous introuvable.</div>
    <div v-else class="rdv-detail">
      <div class="detail-row"><span>Date</span><span>{{ formatDate(rdv.date_heure) }}</span></div>
      <div class="detail-row"><span>Statut</span><span>{{ rdv.statut }}</span></div>
      <div class="detail-row"><span>Moto</span><span>{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
      <div class="detail-row"><span>Immatriculation</span><span>{{ rdv.vehicule?.plaque }}</span></div>
      <div v-if="rdv.ordres_reparation?.length" class="detail-block">
        <div class="detail-label">Ordres de réparation</div>
        <ul>
          <li v-for="o in rdv.ordres_reparation" :key="o.id">{{ o.numero_or }} — {{ o.type_or }}</li>
        </ul>
      </div>
      <div v-if="rdv.photos?.length" class="detail-block">
        <div class="detail-label">Photos</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <img v-for="photo in rdv.photos" :key="photo.id" :src="`/uploads/photos/${photo.filename}`" style="width:120px;height:120px;object-fit:cover;border-radius:8px;" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const auth = useAuthStore()

const { apiFetch } = useClientApi()

const { data: rdv, pending } = useAsyncData(`client-rdv-${route.params.id}`, async () => {
  if (!auth.isAuthenticated) return null
  try {
    return await apiFetch(`/api/client/rdvs/${route.params.id}`)
  } catch {
    return null
  }
})

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
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
</style>
