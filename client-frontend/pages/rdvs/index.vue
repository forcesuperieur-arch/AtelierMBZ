<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mes rendez-vous</h1>
    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="rdvs.length === 0" style="color:#9CA3AF">Aucun rendez-vous.</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px;">
      <NuxtLink v-for="rdv in rdvs" :key="rdv.id" :to="`/rdvs/${rdv.id}`" class="rdv-card">
        <div class="rdv-date">{{ formatDate(rdv.date_heure) }}</div>
        <div class="rdv-status" :class="statusClass(rdv.statut)">{{ rdv.statut }}</div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const { data: rdvs, pending } = useAsyncData('client-rdvs', async () => {
  if (!auth.isAuthenticated) return []
  return await $fetch('/api/client/rdvs', {
    headers: { Authorization: `Bearer ${auth.accessToken}` },
    baseURL: '',
  })
})

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
}

function statusClass(s: string) {
  if (s === 'Terminé') return 'status-termine'
  if (s === 'Annulé') return 'status-annule'
  return 'status-prevu'
}
</script>

<style scoped>
.rdv-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  text-decoration: none;
  color: inherit;
  transition: background 0.2s;
}
.rdv-card:hover {
  background: rgba(255,255,255,0.04);
}
.rdv-date {
  font-weight: 600;
  font-size: 14px;
}
.rdv-status {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  text-transform: uppercase;
}
.status-prevu {
  background: rgba(59,130,246,0.15);
  color: #60A5FA;
}
.status-termine {
  background: rgba(34,197,94,0.15);
  color: #4ADE80;
}
.status-annule {
  background: rgba(239,68,68,0.15);
  color: #FCA5A5;
}
</style>
