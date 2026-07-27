<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Templates de documents</div>
      </div>
    </div>

    <p style="color:#9CA3AF;font-size:13px;margin-bottom:12px;">
      Prévisualisation des documents PDF produits par l'application, avec les données de <strong>votre atelier</strong>
      (logo, nom, SIRET…) et un jeu de données fictives complet. « Personnaliser » recompose l'en-tête du document ;
      le corps reste mis en page par l'application.
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
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
              <span :style="categoryStyle(t.category)" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;">
                {{ categoryLabel(t.category) }}
              </span>
              <span v-if="t.orientation === 'landscape'" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;background:rgba(148,163,184,0.16);color:#CBD5E1;">
                Paysage
              </span>
            </div>
            <div style="font-size:14px;font-weight:700;color:#E8E9ED;">{{ t.label }}</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:4px;line-height:1.4;">{{ t.description }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:6px;font-family:monospace;">{{ t.template }}</div>
          </div>
          <div style="display:flex;gap:6px;flex-direction:column;align-items:flex-end;">
            <NuxtLink
              v-if="t.customisableHeader"
              :to="`/admin/templates-documents/designer/${t.code}`"
              class="btn btn-primary"
              style="font-size:12px;padding:6px 14px;white-space:nowrap;text-decoration:none;"
            >
              Personnaliser l'en-tête
            </NuxtLink>
            <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;white-space:nowrap;" :disabled="previewing === t.code" @click="previewTemplate(t.code)">
              {{ previewing === t.code ? 'Génération…' : 'Prévisualiser' }}
            </button>
          </div>
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
  orientation: string
  customisableHeader: boolean
}

const templates = ref<TemplateInfo[]>([])
const loading = ref(true)
const activeCategory = ref('all')
const previewing = ref<string | null>(null)

const CATEGORY_LABELS: Record<string, string> = {
  atelier: 'Atelier',
  vo: 'VO',
  pilotage: 'Pilotage',
}

const CATEGORY_STYLES: Record<string, string> = {
  atelier: 'background:rgba(96,165,250,0.14);color:#93C5FD;',
  vo: 'background:rgba(251,191,36,0.14);color:#FCD34D;',
  pilotage: 'background:rgba(52,211,153,0.14);color:#6EE7B7;',
}

function categoryLabel(category: string) {
  return CATEGORY_LABELS[category] ?? category
}

function categoryStyle(category: string) {
  return CATEGORY_STYLES[category] ?? 'background:rgba(148,163,184,0.16);color:#CBD5E1;'
}

// Les filtres sont dérivés des données : une nouvelle catégorie côté serveur
// apparaît d'elle-même au lieu d'être comptée nulle part.
const categoryFilters = computed(() => {
  const counts = new Map<string, number>()
  for (const t of templates.value) {
    counts.set(t.category, (counts.get(t.category) ?? 0) + 1)
  }

  return [
    { value: 'all', label: 'Tous', count: templates.value.length },
    ...[...counts.entries()].map(([value, count]) => ({
      value,
      label: categoryLabel(value),
      count,
    })),
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
