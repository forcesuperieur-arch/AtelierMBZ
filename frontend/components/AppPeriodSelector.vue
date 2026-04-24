<template>
  <UCard class="period-selector-card">
    <div class="period-selector-row">
      <div class="period-presets">
        <UButton
          v-for="preset in presets"
          :key="preset.key"
          size="sm"
          :variant="selectedPreset === preset.key ? 'solid' : 'ghost'"
          :color="(selectedPreset === preset.key ? 'yellow' : 'neutral') as any"
          @click="selectPreset(preset.key)"
        >
          {{ preset.label }}
        </UButton>
      </div>

      <div class="period-dates">
        <input
          v-model="localFrom"
          type="date"
          class="dashboard-date-input"
          @change="onDateChange"
        />
        <input
          v-model="localTo"
          type="date"
          class="dashboard-date-input"
          @change="onDateChange"
        />
        <UButton
          size="sm"
          color="neutral"
          variant="soft"
          icon="i-heroicons-arrow-path"
          @click="$emit('refresh')"
        >
          Actualiser
        </UButton>
      </div>
    </div>
    <div v-if="$slots.summary" class="period-summary">
      <slot name="summary" />
    </div>
  </UCard>
</template>

<script setup lang="ts">
interface PeriodPreset {
  key: string
  label: string
}

interface PeriodValue {
  from: string
  to: string
}

const props = defineProps<{
  presets: PeriodPreset[]
  modelValue: PeriodValue
  selectedPreset?: string | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: PeriodValue): void
  (e: 'preset', key: string): void
  (e: 'refresh'): void
}>()

const localFrom = ref(props.modelValue.from)
const localTo = ref(props.modelValue.to)

watch(() => props.modelValue, (val) => {
  localFrom.value = val.from
  localTo.value = val.to
}, { deep: true })

function selectPreset(key: string) {
  emit('preset', key)
}

function onDateChange() {
  emit('update:modelValue', { from: localFrom.value, to: localTo.value })
}
</script>
