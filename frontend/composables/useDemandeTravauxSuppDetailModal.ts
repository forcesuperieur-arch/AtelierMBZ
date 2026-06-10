import { readonly, ref } from 'vue'

export interface DemandeTravauxSuppDetailData {
  id: number
  rendez_vous_id?: number
  client_nom?: string
  vehicule_plaque?: string
  vehicule_info?: string
  description?: string
  urgence?: string
  prestations?: Array<{
    designation: string
    prix_ht?: string
    prix_ttc?: string
    temps_minutes?: number
  }>
  prix_estime?: string
  temps_estime?: number
  statut?: string
  decision_client?: string
  decision_client_at?: string
  or_complementaire_id?: number
  token?: string
  created_at?: string
}

const isOpen = ref(false)
const demandeData = ref<DemandeTravauxSuppDetailData | null>(null)

export function useDemandeTravauxSuppDetailModal() {
  function open(demande: DemandeTravauxSuppDetailData | null) {
    demandeData.value = demande
    isOpen.value = true
  }
  function close() {
    isOpen.value = false
    demandeData.value = null
  }
  function updateData(partial: Partial<DemandeTravauxSuppDetailData>) {
    if (demandeData.value) {
      demandeData.value = { ...demandeData.value, ...partial }
    }
  }

  return {
    isOpen: readonly(isOpen),
    demandeData: readonly(demandeData),
    open,
    close,
    updateData,
  }
}
