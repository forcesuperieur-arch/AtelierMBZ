<template>
  <ol class="timeline">
    <li
      v-for="(step, i) in steps"
      :key="i"
      class="timeline-step"
      :class="{ current: i === steps.length - 1 }"
    >
      <span class="dot" aria-hidden="true" />
      <div class="step-body">
        <span class="step-label">{{ rdvStatutLabel(step.statut) }}</span>
        <time class="step-date" :datetime="step.date">{{ formatDate(step.date) }}</time>
      </div>
    </li>
  </ol>
</template>

<script setup lang="ts">
interface TimelineStep {
  statut: string
  transition: string | null
  date: string
}

defineProps<{ steps: TimelineStep[] }>()

function formatDate(d: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<style scoped>
.timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.timeline-step {
  position: relative;
  display: flex;
  gap: 12px;
  padding: 0 0 18px 0;
}
/* Ligne verticale reliant les points */
.timeline-step::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: rgba(255, 255, 255, 0.1);
}
.timeline-step:last-child {
  padding-bottom: 0;
}
.timeline-step:last-child::before {
  display: none;
}
.dot {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  border: 2px solid rgba(255, 255, 255, 0.25);
}
.timeline-step.current .dot {
  background: #FFD200;
  border-color: #FFD200;
  box-shadow: 0 0 10px rgba(255, 210, 0, 0.55);
}
.step-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.step-label {
  font-size: 14px;
  color: #D1D5DB;
  font-weight: 600;
}
.timeline-step.current .step-label {
  color: #FFD200;
}
.step-date {
  font-size: 12px;
  color: #9CA3AF;
}
</style>
