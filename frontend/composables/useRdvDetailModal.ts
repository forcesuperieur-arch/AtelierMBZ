import { readonly, ref } from 'vue'

export interface RdvDetailData {
  id: number
  status?: string
  statut?: string
  date_rdv?: string
  heure_debut?: string
  type_intervention?: string
  duree_estimee?: number
  pont_nom?: string
  mecanicien_nom?: string
  client_nom?: string
  client_prenom?: string
  client_telephone?: string
  client_email?: string
  vehicule_info?: string
  vehicule_plaque?: string
  description_probleme?: string
  commentaire?: string
  commandes?: string[]
}

const isOpen = ref(false)
const rdvData = ref<RdvDetailData | null>(null)

export function useRdvDetailModal() {
  function open(rdv: RdvDetailData | null) {
    rdvData.value = rdv
    isOpen.value = true
  }
  function close() {
    isOpen.value = false
    rdvData.value = null
  }

  return {
    isOpen: readonly(isOpen),
    rdvData: readonly(rdvData),
    open,
    close,
  }
}
