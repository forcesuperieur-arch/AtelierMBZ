/**
 * Download a PDF via authenticated fetch + blob, avoiding window.open
 * which may not attach credentials cross-origin.
 */
export function usePdfDownload() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  async function downloadPdf(path: string, filename?: string): Promise<void> {
    const url = path.startsWith('http') ? path : `${baseURL}${path.startsWith('/') ? path : `/${path}`}`

    const res = await globalThis.fetch(url, {
      credentials: 'include',
      headers: { Accept: 'application/pdf' },
    })

    if (!res.ok) {
      throw new Error(`Téléchargement PDF échoué (${res.status})`)
    }

    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename || extractFilename(res, path)
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()

    // Cleanup
    setTimeout(() => {
      URL.revokeObjectURL(blobUrl)
      document.body.removeChild(link)
    }, 100)
  }

  /** Open a PDF in a new tab via blob URL */
  async function openPdf(path: string): Promise<void> {
    const url = path.startsWith('http') ? path : `${baseURL}${path.startsWith('/') ? path : `/${path}`}`

    const res = await globalThis.fetch(url, {
      credentials: 'include',
      headers: { Accept: 'application/pdf' },
    })

    if (!res.ok) {
      throw new Error(`Ouverture PDF échouée (${res.status})`)
    }

    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)
    window.open(blobUrl, '_blank')
  }

  return { downloadPdf, openPdf }
}

function extractFilename(res: Response, path: string): string {
  const disposition = res.headers.get('content-disposition')
  if (disposition) {
    const match = disposition.match(/filename[^;=\n]*=["']?([^"';\n]*)/)
    if (match?.[1]) return match[1]
  }
  const segments = path.split('/')
  const last = segments[segments.length - 1]
  return last.endsWith('.pdf') ? last : `${last}.pdf`
}
