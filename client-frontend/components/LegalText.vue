<template>
  <div class="legal-content">
    <template v-for="(block, i) in blocks" :key="i">
      <h2 v-if="block.type === 'heading'">{{ block.text }}</h2>
      <p v-else>{{ block.text }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ text: string }>()

const blocks = computed(() => {
  if (!props.text) return []
  const lines = props.text.split('\n')
  const result: { type: 'heading' | 'paragraph'; text: string }[] = []
  let currentPara = ''

  function flushPara() {
    const trimmed = currentPara.trim()
    if (trimmed) {
      result.push({ type: 'paragraph', text: trimmed })
    }
    currentPara = ''
  }

  for (const line of lines) {
    const trimmed = line.trim()
    if (!trimmed) {
      flushPara()
      continue
    }
    // Détection titre : pas de minuscules (hors accents qui restent maj)
    const hasLower = /[a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]/.test(trimmed)
    if (!hasLower && trimmed.length > 2) {
      flushPara()
      result.push({ type: 'heading', text: trimmed })
    } else {
      currentPara += (currentPara ? ' ' : '') + trimmed
    }
  }
  flushPara()
  return result
})
</script>

<style scoped>
.legal-content h2 {
  font-size: 16px;
  font-weight: 700;
  margin-top: 20px;
  margin-bottom: 8px;
  color: #FFD200;
}
.legal-content p {
  font-size: 14px;
  line-height: 1.6;
  color: #C4C5CA;
  margin-bottom: 12px;
}
</style>
