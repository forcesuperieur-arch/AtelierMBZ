<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Historique</h1>
    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="error" style="color:var(--error-content)">Impossible de charger votre historique pour le moment. Réessayez plus tard.</div>
    <div v-else-if="items.length === 0" style="color:var(--content-3)">Aucun historique.</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px;">
      <div v-for="item in items" :key="item.id" class="hist-card">
        <div class="hist-head">
          <div>
            <div class="hist-date">{{ formatDate(item.signed_at) }}</div>
            <div class="hist-moto">{{ item.vehicule_info || 'Véhicule non précisé' }}</div>
          </div>
          <div class="hist-total" v-if="item.numero_or">N° {{ item.numero_or }}</div>
        </div>
        <p v-if="item.travaux" class="hist-travaux">{{ item.travaux }}</p>
        <a
          v-if="item.pdf_disponible"
          :href="`/api/client/rdvs/${item.rdv_id}/or/${item.id}/pdf`"
          target="_blank"
          class="pdf-btn"
        ><AppIcon name="i-ri-file-text-line" /> Télécharger le PDF</a>
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
  padding: 14px 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  font-size: 14px;
}
.hist-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.hist-date {
  color: var(--content-3);
  font-size: 12px;
}
.hist-moto {
  color: var(--content-1);
  font-weight: 700;
}
.hist-total {
  font-weight: 800;
  color: var(--accent-content);
  white-space: nowrap;
}
.hist-travaux {
  margin: 10px 0 0;
  padding-top: 10px;
  border-top: 1px solid var(--border-2);
  color: var(--content-2);
  font-size: 13px;
  white-space: pre-line;
}
.pdf-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
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
</style>
