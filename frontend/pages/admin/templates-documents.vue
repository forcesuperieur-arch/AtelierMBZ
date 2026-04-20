<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Templates de documents</div>
      </div>
    </div>

    <p style="color:#9CA3AF;font-size:13px;margin-bottom:12px;">
      Prévisualisation des templates PDF générés par l'application, avec les données de <strong>votre atelier</strong> (logo, nom, SIRET…) et des données fictives plus complètes.
    </p>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <NuxtLink to="/admin/notifications/providers" class="btn btn-ghost" style="font-size:12px;padding:6px 14px;text-decoration:none;">
        Voir aussi les templates notifications
      </NuxtLink>

      <button v-for="f in categoryFilters" :key="f.value" class="btn" :class="activeCategory === f.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="activeCategory = f.value">
        {{ f.label }} ({{ f.count }})
      </button>
    </div>

    <div v-if="loading" style="text-align:center;padding:24px;color:#9CA3AF;">Chargement…</div>
    <div v-else-if="!filteredTemplates.length" style="text-align:center;padding:24px;color:#6B7280;">Aucun template.</div>
    <div v-else style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:14px;">
      <UCard v-for="t in filteredTemplates" :key="t.code" style="cursor:default;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
          <div style="flex:1;">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
              <span :style="t.category === 'vo' ? 'background:rgba(251,191,36,0.14);color:#FCD34D;' : 'background:rgba(96,165,250,0.14);color:#93C5FD;'" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;">
                {{ t.category === 'vo' ? 'VO' : 'Atelier' }}
              </span>
            </div>
            <div style="font-size:14px;font-weight:700;color:#E8E9ED;">{{ t.label }}</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:4px;line-height:1.4;">{{ t.description }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:6px;font-family:monospace;">{{ t.template }}</div>
          </div>
          <button class="btn btn-primary" style="font-size:12px;padding:6px 14px;white-space:nowrap;" :disabled="previewing === t.code" @click="previewTemplate(t.code)">
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function previewTemplate(code: string) {
  previewing.value = code
  try {
    await openPdf(`/admin/templates/${code}/preview`)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    previewing.value = null
  }
}

onMounted(load)
</script>
