<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin/templates-documents" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Designer : {{ label }}</div>
      </div>
    </div>

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

const codeLabels: Record<string, string> = {
  ordre_reparation: 'Ordre de réparation',
  facture: 'Facture atelier',
  devis: 'Devis',
  ordre_reparation: 'Ordre de réparation',
  historique_entretien: 'Historique entretien',
  vo_pv_rachat: 'PV de rachat',
  vo_facture: 'Facture VO',
  vo_contrat_depot_vente: 'Contrat dépôt-vente',
  vo_livre_police: 'Livre de police',
  vo_da_siv: 'Préparation DA SIV',
  vo_mandat_immatriculation: 'Mandat d\'immatriculation',
  vo_remise_en_etat: 'Remise en état VO',
}

const label = computed(() => codeLabels[code.value] ?? code.value)

const layout = ref({ elements: [] as any[] })
const defaultLayout = ref({ elements: [] as any[] })
const layoutId = ref<number | null>(null)

const sampleData = ref<Record<string, string>>({
  numero_facture: 'FAC-PREVIEW-001',
  date_facture: '20/05/2026',
  client_nom: 'Dupont',
  client_prenom: 'Jean',
  client_adresse: '12 rue de la Paix, 75001 Paris',
  client_telephone: '06 12 34 56 78',
  vehicule_marque: 'Yamaha',
  vehicule_modele: 'MT-07',
  vehicule_plaque: 'AB-123-CD',
  total_ht: '207,00 €',
  total_tva: '41,40 €',
  total_ttc: '248,40 €',
  or_numero: 'OR-PREVIEW-001',
  date_or: '20/05/2026',
  kilometrage: '15 420 km',
  travaux: 'Révision complète 20 000 km\nVidange huile moteur + filtre\nContrôle freins AV/AR',
  atelier_nom: 'Atelier Principal',
})

async function loadLayout() {
  try {
    const data = await api.get(`/admin/document-layouts/${code.value}`)
    layout.value = { elements: data.layoutJson?.elements ?? data.layoutJson ?? [] }
    layoutId.value = data.id ?? null
  } catch (e: any) {
    layout.value = { elements: [] }
    layoutId.value = null
  }
}

async function saveLayout(newLayout: any) {
  try {
    const payload = {
      label: label.value,
      layoutJson: newLayout,
    }
    if (layoutId.value) {
      await api.put(`/admin/document-layouts/${layoutId.value}`, payload)
      toast.add({ title: 'Template enregistré', color: 'success' })
    } else {
      const res = await api.post('/admin/document-layouts', { ...payload, code: code.value })
      layoutId.value = res.id
      toast.add({ title: 'Template créé', color: 'success' })
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
      body: JSON.stringify({
        layoutJson: newLayout,
        sampleData: sampleData.value,
      }),
    })
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
  } catch (e: any) {
    toast.add({ title: 'Erreur PDF', description: e.message, color: 'error' })
  }
}

onMounted(loadLayout)
</script>
