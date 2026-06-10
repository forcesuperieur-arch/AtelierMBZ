<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mes motos</h1>
    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="motos.length === 0" style="color:#9CA3AF">Aucune moto enregistrée.</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px;">
      <div v-for="moto in motos" :key="moto.id" class="moto-card">
        <div class="moto-name">{{ moto.marque }} {{ moto.modele }}</div>
        <div class="moto-meta">
          <span>{{ moto.plaque }}</span>
          <span>{{ moto.annee }}</span>
          <span>{{ moto.kilometrage }} km</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const { data: motos, pending } = useAsyncData('client-motos', async () => {
  if (!auth.isAuthenticated) return []
  return await $fetch('/api/client/vehicules', {
    headers: { Authorization: `Bearer ${auth.accessToken}` },
    baseURL: '',
  })
})
</script>

<style scoped>
.moto-card {
  padding: 14px 16px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
}
.moto-name {
  font-weight: 700;
  font-size: 15px;
  margin-bottom: 4px;
}
.moto-meta {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: #9CA3AF;
}
</style>
