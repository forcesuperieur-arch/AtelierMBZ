<template>
  <div
    class="meter"
    role="meter"
    :aria-valuenow="Math.round(value)"
    :aria-valuemin="0"
    :aria-valuemax="Math.round(max)"
    :aria-label="label"
  >
    <div class="meter-track">
      <div class="meter-fill" :class="`meter-fill--${tone}`" :style="{ width: pct + '%' }" />
    </div>
    <div v-if="label" class="meter-label">{{ label }}</div>
  </div>
</template>

<script setup lang="ts">
/**
 * Barre de proportion. N'existe que quand le maximum a un sens réel (capacité,
 * objectif configuré) — jamais comme décoration sous un chiffre.
 */
const props = withDefaults(defineProps<{
  value: number
  max: number
  tone?: 'neutral' | 'good' | 'warn' | 'crit'
  label?: string
}>(), { tone: 'neutral' })

const pct = computed(() => {
  const max = Number(props.max)
  if (!Number.isFinite(max) || max <= 0) return 0
  return Math.max(0, Math.min(100, Number(props.value) / max * 100))
})
</script>

<style scoped>
.meter { margin-top: 10px; }
.meter-track {
  height: 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.07);
  overflow: hidden;
}
.meter-fill {
  height: 100%;
  border-radius: 999px;
  background: var(--ramp-2);
  transition: width 0.4s ease;
}
.meter-fill--good { background: var(--status-good); }
.meter-fill--warn { background: var(--status-warn); }
.meter-fill--crit { background: var(--status-crit); }
.meter-label {
  margin-top: 5px;
  font-size: 11px;
  color: var(--ink-muted);
}
</style>
