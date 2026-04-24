<template>
  <div class="pdf-embed">
    <div class="pdf-embed-bar">
      <button
        type="button"
        class="pdf-embed-toggle"
        :disabled="loading"
        @click="toggle"
      >
        <span class="pdf-embed-label">{{ label }}</span>
        <span class="pdf-embed-caret">{{ loading ? '…' : expanded ? '▲' : '▼ Aperçu' }}</span>
      </button>
      <button type="button" class="pdf-embed-dl" :disabled="downloading" @click="dl">
        {{ downloading ? '…' : 'Télécharger' }}
      </button>
    </div>

    <div v-if="errorMsg" class="pdf-embed-error">{{ errorMsg }}</div>

    <div v-if="expanded && blobUrl" class="pdf-embed-frame">
      <iframe :src="blobUrl" frameborder="0" class="pdf-embed-iframe" title="Aperçu PDF" />
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  url: string
  label: string
  filename?: string
}>()

const { fetchBlobUrl, downloadPdf } = usePdfDownload()

const expanded = ref(false)
const loading = ref(false)
const downloading = ref(false)
const blobUrl = ref<string | null>(null)
const errorMsg = ref<string | null>(null)

watch(() => props.url, () => {
  if (blobUrl.value) URL.revokeObjectURL(blobUrl.value)
  blobUrl.value = null
  expanded.value = false
})

onUnmounted(() => {
  if (blobUrl.value) URL.revokeObjectURL(blobUrl.value)
})

async function toggle() {
  if (expanded.value) {
    expanded.value = false
    return
  }

  if (blobUrl.value) {
    expanded.value = true
    return
  }

  loading.value = true
  errorMsg.value = null
  try {
    blobUrl.value = await fetchBlobUrl(props.url)
    expanded.value = true
  }
  catch (err: any) {
    errorMsg.value = err.message || 'Erreur lors du chargement'
  }
  finally {
    loading.value = false
  }
}

async function dl() {
  downloading.value = true
  errorMsg.value = null
  try {
    await downloadPdf(props.url, props.filename)
  }
  catch (err: any) {
    errorMsg.value = err.message || 'Erreur lors du téléchargement'
  }
  finally {
    downloading.value = false
  }
}
</script>

<style scoped>
.pdf-embed {
  display: grid;
  gap: 8px;
}

.pdf-embed-bar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pdf-embed-toggle {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #e8e9ed;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.pdf-embed-toggle:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.07);
}

.pdf-embed-toggle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pdf-embed-label {
  color: #f59e0b;
}

.pdf-embed-caret {
  color: #9ca3af;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.pdf-embed-dl {
  padding: 9px 14px;
  border-radius: 10px;
  background: #1f2937;
  border: 1px solid #374151;
  color: #e8e9ed;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.pdf-embed-dl:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pdf-embed-error {
  color: #ef4444;
  font-size: 12px;
  padding: 4px 0;
}

.pdf-embed-frame {
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.pdf-embed-iframe {
  width: 100%;
  height: 500px;
  display: block;
  background: #fff;
}
</style>
