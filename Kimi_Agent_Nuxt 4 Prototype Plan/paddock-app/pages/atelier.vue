<script setup lang="ts">
import { ref } from 'vue'

interface Intervention {
  id: string
  heure: string
  vehicule: string
  type: string
  mecano: string
  mecanoInitials: string
  pont: string
  statut: 'En cours' | 'À démarrer' | 'Planifié'
  duree: string
  typeBadge: string
}

const interventions = ref<Intervention[]>([
  {
    id: 'r1',
    heure: '08:00',
    vehicule: 'KAWASAKI Z900',
    type: 'Révision 12k',
    mecano: 'Marc L.',
    mecanoInitials: 'ML',
    pont: 'Pont 1',
    statut: 'En cours',
    duree: '2h 00',
    typeBadge: 'Révision',
  },
  {
    id: 'r2',
    heure: '10:30',
    vehicule: 'DUCATI Monster',
    type: 'Pneus arrière',
    mecano: 'Thomas M.',
    mecanoInitials: 'TM',
    pont: 'Pont 2',
    statut: 'À démarrer',
    duree: '1h 30',
    typeBadge: 'Pneumatique',
  },
  {
    id: 'r3',
    heure: '14:00',
    vehicule: 'BMW R1250GS',
    type: 'Entretien annuel',
    mecano: 'Marc L.',
    mecanoInitials: 'ML',
    pont: 'Pont 4',
    statut: 'Planifié',
    duree: '3h 00',
    typeBadge: 'Entretien',
  },
])

const statutDot = {
  'En cours': 'bg-accent animate-pulse',
  'À démarrer': 'bg-emerald-500',
  'Planifié': 'bg-gray-300',
}

const statutBadge = {
  'En cours': 'warning',
  'À démarrer': 'success',
  'Planifié': 'gray',
} as const

interface KanbanCol {
  id: string
  label: string
  color: string
  items: Intervention[]
}

const kanban = ref<KanbanCol[]>([
  {
    id: 'todo',
    label: 'À faire',
    color: 'border-gray-300',
    items: interventions.value.filter(i => i.statut === 'Planifié'),
  },
  {
    id: 'doing',
    label: 'En cours',
    color: 'border-accent',
    items: interventions.value.filter(i => i.statut === 'En cours'),
  },
  {
    id: 'done',
    label: 'Terminé',
    color: 'border-emerald-400',
    items: [],
  },
])
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-8">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-text-primary">🔧 Atelier</h1>
          <p class="text-sm text-text-secondary mt-1">Gestion des interventions du jour</p>
        </div>
        <div class="flex items-center gap-3">
          <PaddockButton variant="secondary" size="sm">
            👁️ Vue mécano
          </PaddockButton>
          <PaddockButton variant="primary" size="sm">
            + Nouvelle intervention
          </PaddockButton>
        </div>
      </div>

      <!-- Interventions du jour — Timeline -->
      <PaddockCard title="Interventions du jour" elevated>
        <div class="relative mt-4 pl-4">
          <!-- Vertical line -->
          <div class="absolute left-[19px] top-2 bottom-2 w-0.5 bg-border-light" />

          <div
            v-for="rdv in interventions"
            :key="rdv.id"
            class="relative flex items-start gap-4 pb-6 last:pb-0"
          >
            <!-- Dot -->
            <div
              class="relative z-10 mt-1 w-4 h-4 rounded-full border-2 border-white shadow-sm shrink-0"
              :class="statutDot[rdv.statut]"
            />

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-bold text-text-primary font-mono">{{ rdv.heure }}</span>
                <span class="text-sm font-semibold text-text-primary">— {{ rdv.vehicule }}</span>
                <PaddockBadge :variant="statutBadge[rdv.statut]" size="sm">
                  {{ rdv.statut }}
                </PaddockBadge>
              </div>
              <div class="mt-1 text-sm text-text-secondary">
                {{ rdv.type }} — {{ rdv.mecano }} — {{ rdv.pont }}
              </div>
            </div>

            <!-- Link to mecano view -->
            <NuxtLink
              :to="`/mecano/${rdv.id}`"
              class="shrink-0 text-xs text-accent hover:text-accent-hover font-medium underline underline-offset-2"
            >
              👁️ Voir
            </NuxtLink>
          </div>
        </div>
      </PaddockCard>

      <!-- Kanban -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div
          v-for="col in kanban"
          :key="col.id"
          class="bg-body-gray/60 rounded-xl border-t-4 p-4 space-y-3"
          :class="col.color"
        >
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">{{ col.label }}</h3>
            <span class="text-xs font-bold text-text-secondary bg-white px-2 py-0.5 rounded-full">
              {{ col.items.length }}
            </span>
          </div>

          <div v-if="col.items.length === 0" class="text-sm text-text-tertiary py-6 text-center italic">
            Aucune intervention
          </div>

          <PaddockCard
            v-for="item in col.items"
            :key="item.id"
            class="p-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="text-sm font-bold text-text-primary">{{ item.vehicule }}</div>
              <PaddockBadge :variant="statutBadge[item.statut]" size="sm">
                {{ item.typeBadge }}
              </PaddockBadge>
            </div>
            <div class="mt-1 text-xs text-text-secondary">{{ item.type }}</div>
            <div class="mt-3 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div
                  class="w-7 h-7 rounded-full bg-header text-white text-[10px] font-bold flex items-center justify-center"
                >
                  {{ item.mecanoInitials }}
                </div>
                <span class="text-xs text-text-secondary">{{ item.mecano }}</span>
              </div>
              <div class="text-xs font-mono text-text-tertiary">⏱ {{ item.duree }}</div>
            </div>
            <div class="mt-2 text-xs text-text-tertiary">{{ item.pont }}</div>
          </PaddockCard>
        </div>
      </div>
    </div>
  </div>
</template>
