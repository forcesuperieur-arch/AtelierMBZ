<template>
  <div>
    <AppPageHeader title="Véhicules d'Occasion" subtitle="Pilotage du stock VO, des dossiers moto actifs et des points de validation OCR.">
      <template #actions>
        <NuxtLink to="/vo/rachats/new" class="topbar-new-btn">+ Nouveau rachat</NuxtLink>
        <NuxtLink to="/vo/depots/new" class="topbar-new-btn vo-secondary-btn">+ Dépôt-vente</NuxtLink>
      </template>
    </AppPageHeader>

    <VONav />

    <div v-if="stats" class="vo-stats-grid">
      <StatsCard title="En stock" :value="stats.en_stock" icon="🏍️" />
      <StatsCard title="Vendus ce mois" :value="stats.vendus" icon="✅" />
      <StatsCard title="Dépôts actifs" :value="stats.depots_actifs" icon="📋" />
      <StatsCard title="Alertes dossiers" :value="stats.alerts_count" icon="⚠️" :color="stats.alerts_count > 0 ? 'warning' : 'primary'" />
    </div>

    <div class="vo-dashboard-grid">
      <UCard>
        <template #header>
          <div class="vo-card-header">
            <span>Stock prioritaire</span>
            <NuxtLink to="/vo/rachats" class="vo-inline-link">Voir tout →</NuxtLink>
          </div>
        </template>

        <div v-if="!quickStock.length" class="vo-empty">Aucun véhicule VO en cours.</div>
        <div v-else class="vo-quick-list">
          <NuxtLink
            v-for="item in quickStock"
            :key="`${item.source}-${item.id}`"
            :to="item.source === 'purchase' ? `/vo/rachats/${item.id}` : `/vo/depots/${item.id}`"
            class="vo-stock-card"
          >
            <div class="vo-stock-card-head">
              <div>
                <div class="vo-stock-title">{{ item.marque || '—' }} {{ item.modele || '' }}</div>
                <div class="vo-stock-meta">{{ item.plaque || 'Sans plaque' }} • {{ item.source === 'purchase' ? 'Rachat' : 'Dépôt' }}</div>
              </div>
              <StatusBadge :status="item.status" />
            </div>

            <div class="vo-stock-metrics">
              <div>
                <span class="vo-k">Prix</span>
                <strong>{{ formatPrice(item.prix_vente || item.prix_achat || 0) }}</strong>
              </div>
              <div v-if="item.source === 'purchase'">
                <span class="vo-k">Marge</span>
                <strong :class="Number(item.marge || 0) >= 0 ? 'text-green' : 'text-red'">{{ formatPrice(item.marge || 0) }}</strong>
              </div>
              <div v-else>
                <span class="vo-k">Net déposant</span>
                <strong>{{ formatPrice(item.deposant_net || 0) }}</strong>
              </div>
            </div>

            <div class="vo-stock-flags">
              <span v-if="item.source === 'purchase'" class="vo-flag">{{ item.jours_stock ?? 0 }} j stock</span>
              <span v-else class="vo-flag">{{ item.jours_restants ?? 0 }} j mandat</span>
              <span v-if="(item.missing_docs || []).length" class="vo-flag is-warning">{{ item.missing_docs?.length }} doc(s) manquant(s)</span>
            </div>
          </NuxtLink>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <div class="vo-card-header">
            <span>Alertes prioritaires</span>
            <NuxtLink to="/vo/rachats" class="vo-inline-link">Ouvrir les dossiers →</NuxtLink>
          </div>
        </template>

        <div v-if="!priorityAlerts.length" class="vo-empty">Aucune alerte prioritaire.</div>
        <div v-else class="vo-alert-list">
          <NuxtLink v-for="(alert, index) in priorityAlerts" :key="`${alert.type}-${index}`" :to="alert.to" class="vo-alert-item is-link">
            <div class="vo-alert-title">{{ alert.title }}</div>
            <div class="vo-alert-text">{{ alert.text }}</div>
          </NuxtLink>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'VO' })

const voStore = useVoStore()
const { formatPrice } = useVoHelpers()

const stats = computed(() => voStore.stats)
const quickStock = computed(() => voStore.stock.slice(0, 5))
const priorityAlerts = computed(() => {
  const documentAlerts = (voStore.alerts || []).slice(0, 4).map((alert: any) => ({
    type: 'document',
    title: alert.type === 'expired' ? 'Document expiré' : 'Document manquant',
    text: alert.message,
    to: alert.purchase_id ? `/vo/rachats/${alert.purchase_id}` : alert.depot_id ? `/vo/depots/${alert.depot_id}` : '/vo',
  }))

  const mandateAlerts = voStore.stock
    .filter((item: any) => item.source === 'depot' && (item.mandat_expire || Number(item.jours_restants ?? 999) <= 7))
    .slice(0, 3)
    .map((item: any) => ({
      type: 'mandat',
      title: item.mandat_expire ? 'Mandat expiré' : 'Mandat à prolonger',
      text: `${item.marque || 'VO'} ${item.modele || ''} • ${item.plaque || 'sans plaque'} • ${item.mandat_expire ? 'mandat expiré' : `${item.jours_restants} jour(s) restants`}`,
      to: `/vo/depots/${item.id}`,
    }))

  return [...documentAlerts, ...mandateAlerts].slice(0, 6)
})

onMounted(async () => {
  await Promise.all([
    voStore.fetchStats(),
    voStore.fetchStock(undefined, 5),
    voStore.fetchAlerts(),
  ])
})
</script>

<style scoped>
.text-green { color:#22c55e; }
.text-red { color:#ef4444; }
.vo-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.vo-subtitle {
  margin-top: 6px;
  color: #9ca3af;
  font-size: 13px;
}

.vo-header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.vo-secondary-btn {
  background: #1d4ed8;
}

.vo-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}

.vo-dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.8fr);
  gap: 16px;
}

.vo-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: #e8e9ed;
  font-weight: 700;
}

.vo-inline-link {
  color: #f59e0b;
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
}

.vo-empty {
  padding: 18px;
  text-align: center;
  color: #6b7280;
}

.vo-quick-list,
.vo-alert-list {
  display: grid;
  gap: 12px;
}

.vo-stock-card {
  display: grid;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
  text-decoration: none;
}

.vo-stock-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.vo-stock-title {
  color: #e8e9ed;
  font-size: 15px;
  font-weight: 700;
}

.vo-stock-meta {
  color: #9ca3af;
  font-size: 12px;
  margin-top: 4px;
}

.vo-stock-metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.vo-stock-metrics strong {
  display: block;
  color: #e8e9ed;
  margin-top: 4px;
}

.vo-k {
  color: #6b7280;
  font-size: 11px;
  text-transform: uppercase;
}

.vo-stock-flags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-flag {
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.05);
  color: #d1d5db;
  font-size: 11px;
  font-weight: 700;
}

.vo-flag.is-warning {
  background: rgba(239, 68, 68, 0.12);
  color: #fca5a5;
}

.vo-alert-item {
  padding: 14px;
  border-radius: 12px;
  border: 1px solid rgba(239, 68, 68, 0.2);
  background: rgba(239, 68, 68, 0.05);
}

.vo-alert-item.is-link {
  display: block;
  text-decoration: none;
}

.vo-alert-title {
  color: #fca5a5;
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 6px;
}

.vo-alert-text {
  color: #d1d5db;
  font-size: 12px;
  line-height: 1.5;
}

@media (max-width: 1024px) {
  .vo-stats-grid,
  .vo-dashboard-grid {
    grid-template-columns: 1fr;
  }
}
</style>