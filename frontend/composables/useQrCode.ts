import QRCode from 'qrcode'
import { ref, watch, type Ref } from 'vue'

/**
 * Generates a QR code data-URL locally (no external service).
 * Returns a reactive `dataUrl` ref that updates when `text` changes.
 */
export function useQrCode(text: Ref<string>, size = 200) {
  const dataUrl = ref('')

  async function generate(value: string) {
    if (!value) {
      dataUrl.value = ''
      return
    }
    try {
      dataUrl.value = await QRCode.toDataURL(value, { width: size, margin: 1 })
    } catch {
      dataUrl.value = ''
    }
  }

  watch(text, (v) => generate(v), { immediate: true })

  return { dataUrl }
}

/**
 * One-shot QR code generation (non-reactive).
 */
export async function generateQrDataUrl(text: string, size = 200): Promise<string> {
  if (!text) return ''
  try {
    return await QRCode.toDataURL(text, { width: size, margin: 1 })
  } catch {
    return ''
  }
}
