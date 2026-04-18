<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <div class="page-title">File atelier VO</div>
        <div class="vo-subtitle">Campagnes actives de remise en etat, priorités, coûts et pièces en attente.</div>
      </div>
    </div>

    <VONav />

    <div v-if="loading" class="vo-loading">Chargement de la file atelier VO...</div>

    <div v-else class="vo-queue-grid">
      <UCard v-for="item in items" :key="item.id">
        <template #header>
          <div class="vo-card-head">
            <div>
              <div class="vo-card-title">{{ item.titre }}</div>
              <div class="vo-card-subtitle">{{ item.sourceLabel }} • {{ item.vehicle?.marque || '—' }} {{ item.vehicle?.modele || '' }}</div>
            </div>
            <span class="vo-chip" :class="item.isBlockingSale ? 'is-warning' : 'is-done'">{{ item.status }}</span>
          </div>
        </template>

        <div class="vo-kpi-grid">
          <div>
            <span class="vo-k">Plaque</span>
            <strong>{{ item.vehicle?.plaque || 'Sans plaque' }}</strong>
          </div>
          <div>
            <span class="vo-k">Priorité</span>
            <strong>{{ item.priority }}</strong>
          </div>
          <div>
            <span class="vo-k">Total estimé</span>
            <strong>{{ formatPrice(item.costSummary?.estimatedTotalCost || 0) }}</strong>
          </div>
          <div>
            <span class="vo-k">Pièces en attente</span>
            <strong>{{ item.pendingPiecesCount }}</strong>
          </div>
        </div>

        <div class="vo-warning-box" v-if="item.isBlockingSale">
          <strong>Blocage vente actif</strong>
          <span>Le dossier source ne peut pas être vendu tant que cette campagne n'est pas clôturée.</span>
        </div>

        <div class="vo-inline-actions">
          <NuxtLink :to="item.dossierPath" class="topbar-new-btn">Ouvrir le dossier</NuxtLink>
        </div>
      </UCard>

      <div v-if="!items.length" class="vo-info-box">
        <strong>Aucune campagne active.</strong>
        <span>La file atelier VO est vide pour l'instant.</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'File atelier VO' })

const voStore = useVoStore()
const { formatPrice } = useVoHelpers()

const loading = ref(true)
const items = computed(() => voStore.refurbishmentQueue || [])

onMounted(async () => {
  try {
    await voStore.fetchRefurbishmentQueue()
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.vo-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.vo-subtitle,
.vo-card-subtitle,
.vo-k {
  color: #9ca3af;
  font-size: 12px;
}

.vo-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.vo-card-title {
  color: #e8e9ed;
  font-weight: 700;
}

.vo-queue-grid,
.vo-kpi-grid {
  display: grid;
  gap: 16px;
}

.vo-kpi-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.vo-kpi-grid strong {
  display: block;
  color: #f8fafc;
  margin-top: 4px;
}

.vo-loading,
.vo-info-box,
.vo-warning-box {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  color: #d1d5db;
}

.vo-warning-box {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.18);
}

.vo-chip {
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.vo-chip.is-warning {
  color: #713f12;
  background: rgba(253, 224, 71, 0.9);
}

.vo-chip.is-done {
  color: #14532d;
  background: rgba(134, 239, 172, 0.9);
}

.vo-inline-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.topbar-new-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 12px;
  border-radius: 10px;
  background: #f59e0b;
  color: #090b10;
  font-weight: 700;
  text-decoration: none;
}

@media (max-width: 960px) {
  .vo-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>