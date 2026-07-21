<template>
  <!-- Notification NON bloquante : carte en coin bas-droit. Ne recouvre jamais
       l'écran et n'intercepte pas les clics ailleurs (décision produit : une
       demande de travaux complémentaires ne doit pas figer toute l'interface,
       même urgente — le staff est alerté mais reste libre d'agir). -->
  <div v-if="visible" class="fixed bottom-4 right-4 z-50 max-w-sm w-[calc(100%-2rem)] sm:w-full pointer-events-none">
    <div class="bg-gray-900 border border-orange-500 rounded-xl shadow-2xl w-full p-6 animate-pulse-border pointer-events-auto">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
          :class="severityBg">
          <span class="text-xl">{{ severityIcon }}</span>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-bold text-white">{{ currentNotif?.title }}</h3>
          <p class="text-xs text-gray-400">{{ formatTime(currentNotif?.createdAt) }}</p>
        </div>
        <button @click="dismissAndAcknowledge" class="text-gray-400 hover:text-white">
          <span class="text-2xl">&times;</span>
        </button>
      </div>

      <!-- Message -->
      <p class="text-gray-200 mb-6">{{ currentNotif?.message }}</p>

      <!-- Actions -->
      <div class="flex gap-3">
        <button
          @click="navigateToAction"
          v-if="currentNotif?.actionUrl"
          class="flex-1 font-semibold py-2 px-4 rounded-lg transition-colors notif-cta-primary">
          Voir la demande
        </button>
        <button
          @click="dismissAndAcknowledge"
          class="flex-1 bg-gray-700 hover:bg-gray-600 text-gray-200 font-semibold py-2 px-4 rounded-lg transition-colors">
          Acquitter
        </button>
      </div>

      <!-- Queue indicator -->
      <p v-if="pendingCount > 1" class="text-xs text-gray-500 mt-3 text-center">
        {{ pendingCount - 1 }} autre(s) notification(s) en attente
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  filterType?: string
}>()

const notifStore = useNotificationsStore()
const { notifications } = storeToRefs(notifStore)
const { acknowledge } = notifStore
const router = useRouter()
const visible = ref(false)
const currentNotif = ref<any>(null)

const pendingNotifs = computed(() => {
  return notifications.value.filter(n => {
    if (props.filterType && n.type !== props.filterType) return false
    return !n.acknowledgedAt && (n.severity === 'critical' || n.severity === 'warning')
  })
})

const pendingCount = computed(() => pendingNotifs.value.length)

const severityBg = computed(() => {
  switch (currentNotif.value?.severity) {
    case 'critical': return 'bg-red-600'
    case 'warning': return 'bg-orange-600'
    default: return 'bg-blue-600'
  }
})

const severityIcon = computed(() => {
  switch (currentNotif.value?.severity) {
    case 'critical': return '🔴'
    case 'warning': return '🟠'
    default: return 'ℹ️'
  }
})

function formatTime(dateStr?: string) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

async function dismissAndAcknowledge() {
  if (currentNotif.value) {
    try {
      await acknowledge(currentNotif.value.id)
    } catch {
      // Already acknowledged
    }
  }
  showNext()
}

function navigateToAction() {
  const url = currentNotif.value?.actionUrl
  if (url) {
    dismissAndAcknowledge()
    router.push(url)
  }
}

function showNext() {
  if (pendingNotifs.value.length > 0) {
    currentNotif.value = pendingNotifs.value[0]
    visible.value = true
  } else {
    visible.value = false
    currentNotif.value = null
  }
}

watch(pendingNotifs, (val) => {
  if (val.length > 0 && !visible.value) {
    showNext()
  }
}, { immediate: true })
</script>

<style scoped>
@keyframes pulse-border {
  0%, 100% { border-color: rgb(249 115 22); }
  50% { border-color: rgb(239 68 68); }
}
.animate-pulse-border {
  animation: pulse-border 2s ease-in-out infinite;
}
/* CTA primaire au jaune de marque (les autres boutons primaires le sont aussi) ;
   la bordure d'alerte pulsante reste, elle, orange/rouge pour signaler l'urgence. */
.notif-cta-primary {
  background: #FFD200;
  color: #111;
}
.notif-cta-primary:hover {
  background: #FFDE33;
}
</style>
