<template>
  <div class="chrono-live">
    <div class="chrono-circle-wrap">
      <svg class="chrono-circle" viewBox="0 0 120 120">
        <circle class="chrono-track" cx="60" cy="60" r="54" />
        <circle
          class="chrono-progress"
          cx="60"
          cy="60"
          r="54"
          :stroke="progressColor"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="strokeOffset"
        />
      </svg>
      <div class="chrono-text">
        <div class="chrono-time" :style="{ color: progressColor }">{{ displayTime }}</div>
        <div class="chrono-label">{{ label }}</div>
      </div>
    </div>
    <div class="chrono-bar-track">
      <div
        class="chrono-bar-fill"
        :style="{
          width: Math.min(pct, 100) + '%',
          background: progressColor,
        }"
      />
    </div>
    <div class="chrono-meta">
      <span>{{ elapsedLabel }}</span>
      <span>{{ totalLabel }}</span>
    </div>
    <div v-if="pct > 100" class="chrono-alert">
      ⚠️ Dépassement +{{ overtimeLabel }} — intervention en retard
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  pct: number
  displayTime: string
  label?: string
  elapsedLabel: string
  totalLabel: string
  overtimeLabel?: string
}>()

const circumference = 2 * Math.PI * 54

const strokeOffset = computed(() => {
  const progress = Math.min(props.pct, 100) / 100
  return circumference * (1 - progress)
})

const progressColor = computed(() => {
  if (props.pct > 100) return '#EF4444'
  if (props.pct > 75) return '#F59E0B'
  return '#10B981'
})
</script>

<style scoped>
.chrono-live {
  margin-top: 16px;
}
.chrono-circle-wrap {
  position: relative;
  width: 140px;
  height: 140px;
  margin: 0 auto;
}
.chrono-circle {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}
.chrono-track {
  fill: none;
  stroke: rgba(255,255,255,0.06);
  stroke-width: 8;
}
.chrono-progress {
  fill: none;
  stroke-width: 8;
  stroke-linecap: round;
  transition: stroke-dashoffset 1s ease, stroke 0.5s ease;
}
.chrono-text {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.chrono-time {
  font-family: monospace;
  font-size: 20px;
  font-weight: 700;
}
.chrono-label {
  font-size: 11px;
  color: #9CA3AF;
  margin-top: 2px;
}
.chrono-bar-track {
  margin-top: 12px;
  height: 4px;
  background: rgba(255,255,255,0.06);
  border-radius: 999px;
  overflow: hidden;
}
.chrono-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 1s ease, background 0.5s ease;
}
.chrono-meta {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #6B7280;
  margin-top: 6px;
}
.chrono-alert {
  margin-top: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.15);
  color: #FCA5A5;
  font-size: 12px;
}
</style>
