<template>
  <AppModal v-model:open="isOpen" size="lg">
    <template #header>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:16px;font-weight:700;color:#E8E9ED;">RDV #{{ rdv?.id }}</span>
        <StatusBadge v-if="rdv" :status="rdv.status ?? rdv.statut" />
      </div>
    </template>
    <div v-if="rdv" style="display:flex;flex-direction:column;gap:16px;font-size:13px;color:#D1D5DB;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><span style="color:#6B7280;">Date :</span> {{ formatDisplayDate(rdv.date_rdv) }}</div>
        <div><span style="color:#6B7280;">Heure :</span> {{ rdv.heure_debut || '—' }}</div>
        <div><span style="color:#6B7280;">Type :</span> {{ rdv.type_intervention || '—' }}</div>
        <div><span style="color:#6B7280;">Durée :</span> {{ formatMinutes(rdv.duree_estimee) }}</div>
      </div>
      <div v-if="rdv.pont_nom || rdv.mecanicien_nom" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div v-if="rdv.pont_nom"><span style="color:#6B7280;">Pont :</span> {{ rdv.pont_nom }}</div>
        <div v-if="rdv.mecanicien_nom"><span style="color:#6B7280;">Mécano :</span> {{ rdv.mecanicien_nom }}</div>
      </div>
      <div v-if="rdv.client_nom || rdv.client_prenom || rdv.client_telephone || rdv.client_email" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">{{ clientName }}</div>
        <div v-if="rdv.client_telephone" style="color:#9CA3AF;">📞 {{ rdv.client_telephone }}</div>
        <div v-if="rdv.client_email" style="color:#9CA3AF;">{{ rdv.client_email }}</div>
      </div>
      <div v-if="rdv.vehicule_info || rdv.vehicule_plaque" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">{{ rdv.vehicule_info || 'Véhicule' }}</div>
        <div v-if="rdv.vehicule_plaque" style="color:#9CA3AF;">🏍 {{ rdv.vehicule_plaque }}</div>
      </div>
      <div v-if="rdv.description_probleme || rdv.commentaire" style="padding:12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.04);">
        <div style="font-weight:600;color:#E8E9ED;margin-bottom:6px;">Description</div>
        <div style="color:#9CA3AF;white-space:pre-wrap;">{{ rdv.description_probleme || rdv.commentaire }}</div>
      </div>
      <div v-if="rdv.commandes?.length" style="display:flex;flex-wrap:wrap;gap:4px;">
        <span v-for="cmd in rdv.commandes" :key="cmd" style="font-size:10px;color:#FFD200;background:rgba(255,210,0,0.08);padding:2px 8px;border-radius:4px;border:1px solid rgba(255,210,0,0.15);">
          #{{ cmd }}
        </span>
      </div>
    </div>
    <template #footer>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button class="btn btn-ghost" @click="close">Fermer</button>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
const { isOpen, rdvData: rdv, close } = useRdvDetailModal()

const clientName = computed(() => {
  if (!rdv.value) return 'Client'
  return [rdv.value.client_prenom, rdv.value.client_nom].filter(Boolean).join(' ') || 'Client'
})

function formatDisplayDate(d: string | undefined) {
  if (!d) return '—'
  try {
    return new Date(`${d}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  } catch {
    return d
  }
}
</script>
