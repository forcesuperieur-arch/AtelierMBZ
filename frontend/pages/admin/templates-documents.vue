<template>
  <div>
    <AppPageHeader title="Templates de documents" back-to="/admin" />

    <p class="intro-text">
      Prévisualisation des PDF générés par l'application, avec les données de <strong>votre atelier</strong> et des données fictives. L'écran distingue désormais les documents opposables, les formulaires réglementaires et les simples synthèses internes.
    </p>

    <div class="filter-bar">
      <NuxtLink to="/admin/notifications/providers" class="btn btn-ghost btn-sm">
        Voir aussi les templates notifications
      </NuxtLink>

      <button v-for="f in categoryFilters" :key="f.value" class="btn filter-btn" :class="activeCategory === f.value ? 'btn-primary' : 'btn-ghost'" @click="activeCategory = f.value">
        {{ f.label }} ({{ f.count }})
      </button>
    </div>

    <div v-if="loading" class="loading-center">Chargement…</div>
    <AppEmptyState v-else-if="!filteredTemplates.length" icon="📄" title="Aucun template." />
    <div v-else class="template-grid">
      <UCard v-for="t in filteredTemplates" :key="t.code" class="cursor-default">
        <div class="template-header">
          <div class="template-left">
            <div class="badge-row">
              <AppStatusBadge :variant="t.category === 'vo' ? 'warning' : 'info'" size="sm">
                {{ t.category === 'vo' ? 'VO' : 'Atelier' }}
              </AppStatusBadge>
              <AppStatusBadge :variant="natureVariant(t.nature)" size="sm">
                {{ natureLabel(t.nature) }}
              </AppStatusBadge>
            </div>
            <div class="template-title">{{ t.label }}</div>
            <div class="template-desc">{{ t.description }}</div>
            <div v-if="t.legalRef" class="template-legal">Référence : CERFA {{ t.legalRef }}</div>
            <div class="template-code">{{ t.template }}</div>
          </div>
          <button class="btn btn-primary btn-sm nowrap" :disabled="previewing === t.code" @click="previewTemplate(t.code)">
            {{ previewing === t.code ? '⏳' : '👁️' }} Prévisualiser
          </button>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { openPdf } = usePdfDownload()

interface TemplateInfo {
  code: string
  label: string
  category: string
  template: string
  description: string
  nature: string
  legalRef?: string | null
}

const templates = ref<TemplateInfo[]>([])
const loading = ref(true)
const activeCategory = ref('all')
const previewing = ref<string | null>(null)

const categoryFilters = computed(() => {
  const all = templates.value.length
  const atelier = templates.value.filter(t => t.category === 'atelier').length
  const vo = templates.value.filter(t => t.category === 'vo').length
  return [
    { value: 'all', label: 'Tous', count: all },
    { value: 'atelier', label: 'Atelier', count: atelier },
    { value: 'vo', label: 'VO', count: vo },
  ]
})

const filteredTemplates = computed(() => {
  if (activeCategory.value === 'all') return templates.value
  return templates.value.filter(t => t.category === activeCategory.value)
})

async function load() {
  loading.value = true
  try {
    templates.value = await api.get('/admin/templates')
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loading.value = false
  }
}

async function previewTemplate(code: string) {
  previewing.value = code
  try {
    await openPdf(`/admin/templates/${code}/preview`)
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    previewing.value = null
  }
}

onMounted(load)

function natureLabel(nature: string) {
  if (nature === 'cerfa') return 'Formulaire réglementaire'
  if (nature === 'opposable') return 'Document opposable'
  if (nature === 'registre') return 'Registre / extrait'
  return 'Synthèse interne'
}

function natureVariant(nature: string): 'info' | 'success' | 'warning' | 'neutral' {
  if (nature === 'cerfa') return 'info'
  if (nature === 'opposable') return 'success'
  if (nature === 'registre') return 'warning'
  return 'neutral'
}
</script>

<style scoped>
.intro-text { color:#9CA3AF; font-size:13px; margin-bottom:12px; }
.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-btn { font-size:12px; padding:6px 14px; }
.btn-sm { font-size:12px; padding:6px 14px; }
.loading-center { text-align:center; padding:24px; color:#9CA3AF; }
.template-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:14px; }
.cursor-default { cursor:default; }
.template-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.template-left { flex:1; }
.badge-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.template-title { font-size:14px; font-weight:700; color:#E8E9ED; }
.template-desc { font-size:12px; color:#9CA3AF; margin-top:4px; line-height:1.4; }
.template-legal { font-size:11px; color:#93C5FD; margin-top:6px; }
.template-code { font-size:11px; color:#6B7280; margin-top:6px; font-family:monospace; }
.nowrap { white-space:nowrap; }
</style>
