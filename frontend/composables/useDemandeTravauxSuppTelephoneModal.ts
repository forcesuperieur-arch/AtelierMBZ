import { readonly, ref } from 'vue'
import type { DemandeTravauxSuppDetailData } from './useDemandeTravauxSuppDetailModal'

export type DemandeTelephoneUpdatedCallback = (updated: Partial<DemandeTravauxSuppDetailData>) => void

const isOpen = ref(false)
const demandeData = ref<DemandeTravauxSuppDetailData | null>(null)
let onUpdatedCallback: DemandeTelephoneUpdatedCallback | null = null

export function useDemandeTravauxSuppTelephoneModal() {
  function open(demande: DemandeTravauxSuppDetailData, onUpdated?: DemandeTelephoneUpdatedCallback) {
    demandeData.value = demande
    onUpdatedCallback = onUpdated ?? null
    isOpen.value = true
  }
  function close() {
    isOpen.value = false
    demandeData.value = null
    onUpdatedCallback = null
  }
  function notifyUpdated(updated: Partial<DemandeTravauxSuppDetailData>) {
    onUpdatedCallback?.(updated)
  }

  return {
    isOpen: readonly(isOpen),
    demandeData: readonly(demandeData),
    open,
    close,
    notifyUpdated,
  }
}
