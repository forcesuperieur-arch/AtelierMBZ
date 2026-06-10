<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Historique</h1>
    <div v-if="pending" style="color:#9CA3AF">Chargement…</div>
    <div v-else-if="items.length === 0" style="color:#9CA3AF">Aucun historique.</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px;">
      <div v-for="item in items" :key="item.id" class="hist-card">
        <div class="hist-date">{{ formatDate(item.signed_at) }}</div>
        <div class="hist-moto">{{ item.vehicule_info }}</div>
        <div class="hist-total">{{ item.numero_or }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const { data: items, pending } = useAsyncData('client-historique', async () => {
  if (!auth.isAuthenticated) return []
  return await $fetch('/api/client/historique', {
    headers: { Authorization: `Bearer ${auth.accessToken}` },
    baseURL: '',
  })
})

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<style scoped>
.hist-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  font-size: 14px;
}
.hist-moto {
  flex: 1;
  margin-left: 16px;
  color: #E8E9ED;
}
.hist-total {
  font-weight: 800;
  color: #FFD200;
}
</style>
