<template>
  <AppModal v-model:open="open" size="lg">
    <template #header>
      <span class="moto-histo-title"><AppIcon name="i-ri-history-line" /> Historique de la moto</span>
    </template>
    <template #content>
      <div v-if="loading" class="moto-histo-loading">Chargement…</div>
      <div v-else-if="error" class="moto-histo-error">{{ error }}</div>
      <div v-else-if="historique">
        <div class="moto-histo-vehicule">
          <strong>{{ vehiculeLabel }}</strong>
          <span v-if="historique.vehicule?.plaque">— {{ historique.vehicule.plaque }}</span>
        </div>
        <div v-if="!interventions.length" class="moto-histo-empty">Aucune intervention terminée enregistrée pour cette moto.</div>
        <div v-else class="moto-histo-list">
          <div v-for="(item, idx) in interventions" :key="idx" class="moto-histo-item">
            <div class="moto-histo-item-head">
              <span class="moto-histo-date">{{ formatDate(item.date) }}</span>
              <span v-if="item.kilometrage" class="moto-histo-km">{{ item.kilometrage }} km</span>
            </div>
            <p class="moto-histo-type">{{ item.typeIntervention || 'Intervention' }}</p>
            <p v-if="travauxLabel(item)" class="moto-histo-travaux">{{ travauxLabel(item) }}</p>
            <p v-if="item.mecanicien" class="moto-histo-meca">Mécanicien : {{ item.mecanicien }}</p>
            <div v-if="item.alertes?.length" class="moto-histo-alertes">
              <AppIcon name="i-ri-error-warning-line" /> {{ item.alertes.join(' · ') }}
            </div>
          </div>
        </div>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
const props = defineProps<{
  vehiculeId: number | null
}>()

const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const loading = ref(false)
const error = ref('')
const historique = ref<any>(null)

const vehiculeLabel = computed(() => {
  const v = historique.value?.vehicule
  if (!v) return ''
  return [v.marque, v.modele].filter(Boolean).join(' ') || 'Véhicule'
})

const interventions = computed(() => {
  const items = Array.isArray(historique.value?.interventions) ? historique.value.interventions : []
  return [...items].reverse()
})

function travauxLabel(item: any): string {
  const travaux = Array.isArray(item?.travaux) ? item.travaux : []
  return travaux.filter(Boolean).join(', ')
}

function formatDate(date: string): string {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return date
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

async function load() {
  if (!props.vehiculeId) return
  loading.value = true
  error.value = ''
  historique.value = null
  try {
    historique.value = await api.get(`/vehicules/${props.vehiculeId}/historique-entretien`)
  } catch (e: any) {
    error.value = e.message || "Impossible de charger l'historique de cette moto."
  } finally {
    loading.value = false
  }
}

watch(open, (isOpen) => {
  if (isOpen) load()
})
</script>

<style scoped>
.moto-histo-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 700;
  color: var(--content-1);
}

.moto-histo-loading, .moto-histo-error, .moto-histo-empty {
  padding: 24px 4px;
  text-align: center;
  color: var(--content-3);
  font-size: 14px;
}
.moto-histo-error { color: var(--error-content); }

.moto-histo-vehicule {
  font-size: 15px;
  color: var(--content-1);
  margin-bottom: 14px;
}

.moto-histo-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.moto-histo-item {
  padding: 14px;
  border-radius: 10px;
  border: 1px solid var(--border-2);
  background: var(--overlay-soft);
}

.moto-histo-item-head {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--content-3);
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.moto-histo-type {
  font-size: 14px;
  font-weight: 700;
  color: var(--content-1);
  margin-bottom: 4px;
}

.moto-histo-travaux, .moto-histo-meca {
  font-size: 13px;
  color: var(--content-2);
  margin-bottom: 2px;
}

.moto-histo-alertes {
  margin-top: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  background: var(--error-soft);
  border: 1px solid var(--error);
  color: var(--error-content);
  font-size: 12px;
}
</style>
