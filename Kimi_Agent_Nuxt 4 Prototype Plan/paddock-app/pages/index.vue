<script setup lang="ts">
import { ref } from 'vue'

type DateFilter = 'Aujourd\'hui' | 'Semaine' | 'Mois'
const activeFilter = ref<DateFilter>('Aujourd\'hui')
const filters: DateFilter[] = ['Aujourd\'hui', 'Semaine', 'Mois']

interface Kpi {
  label: string
  value: string
  trend: string
  trendUp: boolean
  icon: string
}

const kpis = ref<Kpi[]>([
  { label: 'RDV aujourd\'hui', value: '8', trend: '+2 vs hier', trendUp: true, icon: '📅' },
  { label: 'Ponts occupés', value: '4/6', trend: '67% occup.', trendUp: true, icon: '🔧' },
  { label: 'CA du jour', value: '1 240 €', trend: '+12% vs hier', trendUp: true, icon: '💶' },
  { label: 'Devis en attente', value: '3', trend: '1 urgent', trendUp: false, icon: '📋' },
])

const weeklyOverview = ref({
  planned: 18,
  completed: 12,
  cancelled: 2,
  occupancyRate: 78,
})

const performance = ref({
  avgTime: { value: '1h 45min', trend: '-8%', trendUp: true, label: 'vs semaine dernière' },
  satisfaction: { value: '4.8/5', trend: '+0.2', trendUp: true, label: 'vs semaine dernière' },
})

const quickActions = [
  { label: 'Nouveau RDV', icon: '➕', color: 'bg-accent text-white' },
  { label: 'Nouveau client', icon: '👤', color: 'bg-header text-white' },
  { label: 'Nouveau devis', icon: '📝', color: 'bg-emerald-600 text-white' },
  { label: 'Rapport journalier', icon: '📊', color: 'bg-blue-600 text-white' },
]
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-8">
      <!-- Header + Date Filter -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-text-primary">Tableau de bord</h1>
          <p class="text-sm text-text-secondary mt-1">Vue d'ensemble de l'atelier</p>
        </div>
        <div class="flex gap-1 p-1 bg-body-gray rounded-lg w-fit">
          <button
            v-for="f in filters"
            :key="f"
            @click="activeFilter = f"
            :class="[
              'px-4 py-2 rounded-md text-sm font-medium transition-all',
              activeFilter === f
                ? 'bg-white shadow-xs text-text-primary'
                : 'text-text-secondary hover:text-text-primary',
            ]"
          >
            {{ f }}
          </button>
        </div>
      </div>

      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <PaddockCard
          v-for="kpi in kpis"
          :key="kpi.label"
          class="relative overflow-hidden"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs font-semibold uppercase text-text-tertiary tracking-wider">
                {{ kpi.label }}
              </div>
              <div class="text-3xl font-black text-text-primary mt-2 font-mono">
                {{ kpi.value }}
              </div>
            </div>
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center text-lg"
              :class="kpi.trendUp ? 'bg-accent/10' : 'bg-red-50'"
            >
              <span :class="kpi.trendUp ? '' : ''">{{ kpi.icon }}</span>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-1.5">
            <PaddockBadge
              :variant="kpi.trendUp ? 'success' : 'warning'"
              size="sm"
              pulse
            >
              {{ kpi.trendUp ? '▲' : '▼' }} {{ kpi.trend }}
            </PaddockBadge>
          </div>
        </PaddockCard>
      </div>

      <!-- Actions rapides -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button
          v-for="action in quickActions"
          :key="action.label"
          class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all hover:shadow-md hover:-translate-y-0.5"
          :class="action.color"
        >
          <span class="text-lg">{{ action.icon }}</span>
          {{ action.label }}
        </button>
      </div>

      <!-- Vue d'ensemble hebdomadaire -->
      <PaddockCard title="📊 Vue d'ensemble — Semaine 13" elevated>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mt-4">
          <div v-for="(item, key) in [
            { label: 'RDVs planifiés', value: weeklyOverview.planned, max: 20, color: 'bg-blue-500' },
            { label: 'RDVs complétés', value: weeklyOverview.completed, max: 20, color: 'bg-emerald-500' },
            { label: 'RDVs annulés', value: weeklyOverview.cancelled, max: 10, color: 'bg-red-400' },
            { label: 'Taux occupation', value: weeklyOverview.occupancyRate, max: 100, color: 'bg-accent', suffix: '%' },
          ]" :key="key" class="space-y-2">
            <div class="text-xs font-semibold uppercase text-text-tertiary tracking-wider">
              {{ item.label }}
            </div>
            <div class="text-3xl font-black text-text-primary font-mono">
              {{ item.value }}{{ item.suffix || '' }}
            </div>
            <div class="w-full h-2 bg-body-gray rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700 ease-out"
                :class="item.color"
                :style="{ width: `${(item.value / item.max) * 100}%` }"
              />
            </div>
          </div>
        </div>
      </PaddockCard>

      <!-- Ponts en cours -->
      <PaddockCard title="🔧 Ponts en cours" elevated>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mt-4">
          <div
            v-for="i in 6"
            :key="i"
            class="rounded-xl border border-border-light p-4 text-center transition-all hover:shadow-md"
            :class="i <= 4 ? 'bg-accent/5 border-accent/20' : 'bg-white'"
          >
            <div class="text-xs font-semibold text-text-tertiary uppercase tracking-wider">
              Pont {{ i }}
            </div>
            <div
              class="mt-2 text-sm font-bold"
              :class="i <= 4 ? 'text-accent' : 'text-text-secondary'"
            >
              {{ i <= 4 ? 'Occupé' : 'Libre' }}
            </div>
            <div v-if="i <= 4" class="mt-1 text-xs text-text-secondary">
              {{ ['KAWASAKI Z900', 'DUCATI Monster', 'YAMAHA MT-07', 'BMW R1250GS'][i - 1] }}
            </div>
          </div>
        </div>
      </PaddockCard>

      <!-- Performance atelier -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <PaddockCard class="relative overflow-hidden">
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs font-semibold uppercase text-text-tertiary tracking-wider">
                ⏱️ Temps moyen d'intervention
              </div>
              <div class="text-3xl font-black text-text-primary mt-3 font-mono">
                {{ performance.avgTime.value }}
              </div>
            </div>
            <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-xl">
              ⏱️
            </div>
          </div>
          <div class="mt-4 flex items-center gap-2">
            <PaddockBadge variant="success" size="sm" pulse>
              ▲ {{ performance.avgTime.trend }}
            </PaddockBadge>
            <span class="text-xs text-text-secondary">{{ performance.avgTime.label }}</span>
          </div>
        </PaddockCard>

        <PaddockCard class="relative overflow-hidden">
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs font-semibold uppercase text-text-tertiary tracking-wider">
                ⭐ Taux de satisfaction
              </div>
              <div class="text-3xl font-black text-text-primary mt-3 font-mono">
                {{ performance.satisfaction.value }}
              </div>
            </div>
            <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-xl">
              ⭐
            </div>
          </div>
          <div class="mt-4 flex items-center gap-2">
            <PaddockBadge variant="success" size="sm" pulse>
              ▲ {{ performance.satisfaction.trend }}
            </PaddockBadge>
            <span class="text-xs text-text-secondary">{{ performance.satisfaction.label }}</span>
          </div>
        </PaddockCard>
      </div>
    </div>
  </div>
</template>
