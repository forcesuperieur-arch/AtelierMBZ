<template>
  <!-- Notification NON bloquante : carte en coin bas-droit. Ne recouvre jamais
       l'écran et n'intercepte pas les clics ailleurs (décision produit : une
       demande de travaux complémentaires ne doit pas figer toute l'interface,
       même urgente — le staff est alerté mais reste libre d'agir). -->
  <div v-if="visible" class="pk-notif-zone">
    <div class="pk-notif" :class="severite" role="alert">
      <div class="pk-notif-head">
        <span class="pk-notif-glyphe" aria-hidden="true"><AppIcon :name="severityIcon" /></span>
        <div class="pk-notif-titres">
          <h3 class="pk-notif-titre">{{ currentNotif?.title }}</h3>
          <p class="pk-notif-heure">{{ formatTime(currentNotif?.createdAt) }}</p>
        </div>
        <button class="pk-notif-fermer" aria-label="Acquitter et fermer" @click="dismissAndAcknowledge">
          <AppIcon name="i-ri-close-line" />
        </button>
      </div>

      <p class="pk-notif-message">{{ currentNotif?.message }}</p>

      <div class="pk-notif-actions">
        <button v-if="currentNotif?.actionUrl" class="btn btn-primary" @click="navigateToAction">
          Voir la demande
        </button>
        <button class="btn btn-ghost" @click="dismissAndAcknowledge">Acquitter</button>
      </div>

      <p v-if="pendingCount > 1" class="pk-notif-file">
        {{ pendingCount - 1 }} autre{{ pendingCount > 2 ? 's' : '' }} en attente
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

/** Le trio du design system correspondant à la gravité : surface, filet, encre. */
const severite = computed(() => {
  switch (currentNotif.value?.severity) {
    case 'critical': return 'pk-notif--error'
    case 'warning': return 'pk-notif--warning'
    default: return 'pk-notif--info'
  }
})

const severityIcon = computed(() => {
  switch (currentNotif.value?.severity) {
    case 'critical': return 'i-ri-alarm-warning-fill'
    case 'warning': return 'i-ri-error-warning-fill'
    default: return 'i-ri-information-line'
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
/* Le design system interdit ce qui clignote : « rien ne clignote, rien ne
   rebondit, rien n'attend une animation pour être lisible ». La bordure
   pulsait en boucle toutes les 2 s, sur des couleurs écrites en dur qui plus
   est. L'urgence se dit maintenant par le trio de statut, le glyphe et le mot —
   trois porteurs, comme le veut la règle « le sens ne repose jamais sur la
   couleur seule ». */
.pk-notif-zone {
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 50;
  width: min(380px, calc(100vw - 32px));
  pointer-events: none;
}

.pk-notif {
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-left-width: 4px;
  color: var(--pk-ink);
}

.pk-notif--error   { border-left-color: var(--pk-error-line); }
.pk-notif--warning { border-left-color: var(--pk-warning-line); }
.pk-notif--info    { border-left-color: var(--pk-info-line); }

.pk-notif-head { display: flex; align-items: flex-start; gap: 12px; }

.pk-notif-glyphe {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--pk-radius-pill);
  font-size: 19px;
}
.pk-notif--error .pk-notif-glyphe   { background: var(--pk-error-surface);   color: var(--pk-error-ink); }
.pk-notif--warning .pk-notif-glyphe { background: var(--pk-warning-surface); color: var(--pk-warning-ink); }
.pk-notif--info .pk-notif-glyphe    { background: var(--pk-info-surface);    color: var(--pk-info-ink); }

.pk-notif-titres { flex: 1; min-width: 0; }
.pk-notif-titre { margin: 0; font-size: 15px; font-weight: 600; }
.pk-notif-heure { margin: 2px 0 0; font-size: 12px; color: var(--pk-ink-muted); }

.pk-notif-fermer {
  flex-shrink: 0;
  min-width: var(--pk-target-desk);
  min-height: var(--pk-target-desk);
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--pk-ink-muted);
  font-size: 20px;
  cursor: pointer;
}
.pk-notif-fermer:hover { color: var(--pk-ink); }
.pk-notif-fermer:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-notif-message { margin: 0; font-size: 13px; line-height: 1.5; color: var(--pk-ink-quiet); }

.pk-notif-actions { display: flex; gap: var(--pk-target-gap); }
.pk-notif-actions .btn { flex: 1; min-height: var(--pk-target-desk); }

.pk-notif-file { margin: 0; font-size: 12px; color: var(--pk-ink-muted); text-align: center; }
</style>
