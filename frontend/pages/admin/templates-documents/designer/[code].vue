<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin/templates-documents" style="color:var(--content-3);text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">En-tête : {{ label }}</div>
      </div>
    </div>

    <p style="color:var(--content-3);font-size:13px;margin-bottom:12px;">
      Composez le <strong>bandeau d'en-tête</strong> de ce document : il est appliqué aux PDF réellement générés.
      Le corps du document (lignes, totaux, photos, signatures) reste mis en page par l'application — sa hauteur
      dépend des données et ne peut pas être positionnée au millimètre.
    </p>

    <DocumentDesigner
      v-model="layout"
      :code="code"
      :sample-data="sampleData"
      :default-layout="defaultLayout"
      @save="saveLayout"
      @preview-pdf="previewPdf"
    />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const api = useApi()
const toast = useToast()

const code = computed(() => String(route.params.code))

// Le libellé vient du serveur : la table de correspondance codée en dur ici
// avait une clé dupliquée et ignorait les documents ajoutés depuis.
const label = ref('')

const layout = ref({ elements: [] as any[] })
const defaultLayout = ref({ elements: [] as any[] })
const layoutId = ref<number | null>(null)

/**
 * Jetons disponibles dans l'en-tête. Ils correspondent à ceux que
 * DocumentHeaderRenderer sait résoudre côté serveur : un jeton absent de cette
 * liste est effacé du document final plutôt qu'imprimé en clair.
 */
const sampleData = ref<Record<string, string>>({
  atelier_nom: 'Atelier Principal',
  atelier_adresse: '25 avenue de la République',
  atelier_cp_ville: '59000 Lille',
  atelier_telephone: '03 20 00 00 00',
  atelier_email: 'contact@atelier.test',
  atelier_siret: '812 345 678 00019',
  doc_title: 'Document',
})

async function loadLabel() {
  try {
    const all = await api.get('/admin/templates')
    label.value = all.find((t: any) => t.code === code.value)?.label ?? code.value
  } catch {
    label.value = code.value
  }
}

async function loadLayout() {
  try {
    const data = await api.get(`/admin/document-layouts/${code.value}`)
    layout.value = { elements: data.layoutJson?.elements ?? data.layoutJson ?? [] }
    // Un layout système ne peut pas être écrasé : l'enregistrement doit créer
    // le layout propre à l'atelier plutôt que tenter un PUT interdit.
    layoutId.value = data.isDefault ? null : (data.id ?? null)
  } catch {
    layout.value = { elements: [] }
    layoutId.value = null
  }
}

async function saveLayout(newLayout: any) {
  try {
    const payload = { label: label.value || code.value, layoutJson: newLayout }
    if (layoutId.value) {
      await api.put(`/admin/document-layouts/${layoutId.value}`, payload)
      toast.add({ title: 'En-tête enregistré', color: 'success' })
    } else {
      const res = await api.post('/admin/document-layouts', { ...payload, code: code.value })
      layoutId.value = res.id
      toast.add({ title: 'En-tête créé', color: 'success' })
    }
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function previewPdf(newLayout: any) {
  try {
    const res = await fetch(`/api/admin/document-layouts/${code.value}/preview`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ layoutJson: newLayout, sampleData: sampleData.value }),
    })
    if (!res.ok) {
      throw new Error(`Aperçu indisponible (${res.status})`)
    }
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch (e: any) {
    toast.add({ title: 'Erreur PDF', description: e.message, color: 'error' })
  }
}

onMounted(async () => {
  await loadLabel()
  await loadLayout()
})
</script>
