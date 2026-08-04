<template>
  <div v-if="nokItems.length" class="cpp-panel">
    <div v-for="item in nokItems" :key="item.key" class="cpp-row">
      <span class="cpp-label"><AppIcon name="i-ri-close-circle-line" /> {{ item.label }}</span>
      <div class="cpp-thumbs">
        <div v-for="photo in photosByKey[item.key] || []" :key="photo.id" class="cpp-thumb">
          <img :src="photo.url" :alt="item.label" />
          <button
            v-if="!disabled"
            type="button"
            class="cpp-thumb-remove"
            aria-label="Supprimer la photo"
            @click="$emit('remove-photo', item.key, photo.id)"
          >
            <AppIcon name="i-ri-close-line" />
          </button>
        </div>
        <button
          v-if="!disabled"
          type="button"
          class="cpp-add"
          :disabled="uploadingKey === item.key"
          @click="openPicker(item.key)"
        >
          <AppIcon :name="uploadingKey === item.key ? 'i-ri-loader-4-line' : 'i-ri-camera-line'" />
        </button>
      </div>
    </div>
    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      capture="environment"
      class="cpp-hidden-input"
      @change="onFileChange"
    />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  items: { key: string; label: string }[]
  values: Record<string, string>
  photosByKey: Record<string, { id: number; url: string }[]>
  disabled?: boolean
}>()

const emit = defineEmits<{
  'add-photo': [key: string, file: File]
  'remove-photo': [key: string, photoId: number]
}>()

const nokItems = computed(() => props.items.filter(i => props.values[i.key] === 'nok'))

const fileInput = ref<HTMLInputElement | null>(null)
const uploadingKey = ref<string | null>(null)
let pendingKey: string | null = null

function openPicker(key: string) {
  pendingKey = key
  uploadingKey.value = key
  fileInput.value?.click()
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  const key = pendingKey
  input.value = ''
  uploadingKey.value = null
  pendingKey = null
  if (file && key) emit('add-photo', key, file)
}

defineExpose({ clearUploading: () => { uploadingKey.value = null } })
</script>

<style scoped>
.cpp-panel {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cpp-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.cpp-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--error-content);
}

.cpp-thumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.cpp-thumb {
  position: relative;
  width: 52px;
  height: 52px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border-2);
}

.cpp-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.cpp-thumb-remove {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  cursor: pointer;
}

.cpp-add {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  border: 1px dashed var(--border-control);
  background: var(--overlay-soft);
  color: var(--content-3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  cursor: pointer;
}
.cpp-add:disabled {
  opacity: 0.6;
  cursor: wait;
}

.cpp-hidden-input {
  display: none;
}
</style>
