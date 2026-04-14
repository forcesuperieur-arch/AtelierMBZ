<template>
  <div>
    <div class="flex items-center gap-4 mb-4">
      <UButton icon="i-heroicons-chevron-left" variant="ghost" size="sm" @click="prevWeek" />
      <h3 class="font-medium">Semaine du {{ formatDate(weekStart) }}</h3>
      <UButton icon="i-heroicons-chevron-right" variant="ghost" size="sm" @click="nextWeek" />
      <UButton label="Aujourd'hui" variant="outline" size="xs" @click="goToday" />
    </div>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr>
            <th class="w-20 p-2 text-left text-gray-500 border-b">Pont</th>
            <th
              v-for="day in weekDays"
              :key="day.date"
              :class="['p-2 text-center border-b min-w-[140px]', day.isToday ? 'bg-primary-50 dark:bg-primary-950' : '']"
            >
              <div class="font-medium">{{ day.label }}</div>
              <div class="text-xs text-gray-400">{{ day.date }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pont in ponts" :key="pont.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
            <td class="p-2 font-medium">
              <span>{{ pont.nom }}</span>
            </td>
            <td
              v-for="day in weekDays"
              :key="`${pont.id}-${day.date}`"
              :class="['p-1 align-top', day.isToday ? 'bg-primary-50/50 dark:bg-primary-950/50' : '']"
            >
              <div
                v-for="rdv in getRdvsForCell(pont.id, day.date)"
                :key="rdv.id"
                class="mb-1 p-1.5 rounded text-xs cursor-pointer hover:ring-2 ring-primary"
                :class="rdvBgClass(rdv.status)"
                @click="$emit('select-rdv', rdv)"
              >
                <div class="font-medium truncate">{{ rdv.client_nom }}</div>
                <div class="text-[10px] opacity-70">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.type_intervention }}</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  ponts: Array<{ id: number; nom: string }>
  rdvs: Array<any>
}>()

defineEmits<{
  'select-rdv': [rdv: any]
}>()

const currentDate = ref(new Date())

const weekStart = computed(() => {
  const d = new Date(currentDate.value)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  return new Date(d.setDate(diff))
})

const weekDays = computed(() => {
  const days = []
  const labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam']
  const today = new Date().toISOString().slice(0, 10)
  for (let i = 0; i < 6; i++) {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    const date = d.toISOString().slice(0, 10)
    days.push({
      label: labels[i],
      date,
      isToday: date === today,
    })
  }
  return days
})

function prevWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() - 7)
  currentDate.value = d
}

function nextWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() + 7)
  currentDate.value = d
}

function goToday() {
  currentDate.value = new Date()
}

function formatDate(d: Date) {
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

function getRdvsForCell(pontId: number, date: string) {
  return props.rdvs.filter(r => r.pont_id === pontId && r.date_rdv === date)
}

function rdvBgClass(status: string) {
  const map: Record<string, string> = {
    en_attente: 'bg-gray-100 dark:bg-gray-800',
    reserve: 'bg-blue-100 dark:bg-blue-900',
    confirme: 'bg-cyan-100 dark:bg-cyan-900',
    reception: 'bg-yellow-100 dark:bg-yellow-900',
    en_cours: 'bg-orange-100 dark:bg-orange-900',
    termine: 'bg-green-100 dark:bg-green-900',
    facture: 'bg-violet-100 dark:bg-violet-900',
    paye: 'bg-emerald-100 dark:bg-emerald-900',
    annule: 'bg-red-100 dark:bg-red-900',
  }
  return map[status] || 'bg-gray-100 dark:bg-gray-800'
}
</script>
