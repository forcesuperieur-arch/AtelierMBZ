<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Historique</h1>
    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="error" style="color:var(--error-content)">Impossible de charger votre historique pour le moment. Réessayez plus tard.</div>
    <div v-else-if="items.length === 0" style="color:var(--content-3)">Aucun historique.</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px;">
      <div v-for="item in items" :key="item.id" class="hist-card">
        <div class="hist-date">{{ formatDate(item.signed_at) }}</div>
        <div class="hist-moto">{{ item.vehicule_info || 'Véhicule non précisé' }}</div>
        <div class="hist-total" v-if="item.numero_or">N° {{ item.numero_or }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const { apiFetch } = useClientApi()

// default: () => [] + error → pas d'écran blanc si l'API échoue.
const { data: items, pending, error } = useAsyncData('client-historique', async () => {
  if (!auth.isAuthenticated) return []
  return await apiFetch('/api/client/historique')
}, { default: () => [] })

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
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 10px;
  font-size: 14px;
}
.hist-moto {
  flex: 1;
  margin-left: 16px;
  color: var(--content-1);
}
.hist-total {
  font-weight: 800;
  color: var(--accent-content);
}
</style>
