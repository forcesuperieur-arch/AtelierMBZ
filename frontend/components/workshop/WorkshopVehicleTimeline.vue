<template>
  <div class="timeline-wrapper">
    <div class="timeline-track">
      <div
        v-for="(step, idx) in steps"
        :key="step.key"
        class="timeline-step"
        :class="[step.status]"
      >
        <div class="timeline-dot" />
        <div v-if="idx < steps.length - 1" class="timeline-connector" :class="{ done: step.status === 'done' }" />
      </div>
    </div>
    <div class="timeline-labels">
      <span
        v-for="step in steps"
        :key="step.key"
        class="timeline-label"
        :class="[step.status]"
      >
        {{ step.label }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  rdv?: any
}

const props = defineProps<Props>()

const steps = computed(() => {
  const rdv = props.rdv
  const status = String(rdv?.status ?? rdv?.statut ?? '').toLowerCase()

  const allSteps = [
    { key: 'reception', label: 'Réception' },
    { key: 'diagnostic', label: 'Diag' },
    { key: 'devis', label: 'Devis' },
    { key: 'pieces', label: 'Pièces' },
    { key: 'reparation', label: 'Répar.' },
    { key: 'qualite', label: 'C.Q.' },
    { key: 'livraison', label: 'Livr.' },
  ]

  let activeIdx = -1
  if (['reserve', 'confirme'].includes(status)) activeIdx = 0
  else if (status === 'reception') activeIdx = 1
  else if (status === 'en_cours') activeIdx = 4
  else if (status === 'termine') activeIdx = 5
  else if (['restitue', 'facture', 'paye'].includes(status)) activeIdx = 6

  return allSteps.map((s, idx) => {
    if (idx < activeIdx) return { ...s, status: 'done' as const }
    if (idx === activeIdx) return { ...s, status: 'active' as const }
    return { ...s, status: 'pending' as const }
  })
})
</script>

<style scoped>
.timeline-wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.timeline-track {
  display: flex;
  align-items: center;
}
.timeline-step {
  display: flex;
  align-items: center;
  flex: 1;
  position: relative;
}
.timeline-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #374151;
  flex-shrink: 0;
}
.timeline-step.active .timeline-dot {
  background: var(--orange);
  box-shadow: 0 0 0 3px rgba(255, 210, 0, 0.2);
}
.timeline-step.done .timeline-dot {
  background: var(--green);
}
.timeline-connector {
  flex: 1;
  height: 2px;
  background: #374151;
  margin: 0 2px;
  border-radius: 1px;
}
.timeline-connector.done {
  background: var(--green);
}
.timeline-labels {
  display: flex;
}
.timeline-label {
  flex: 1;
  font-size: 9px;
  color: #6B7280;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.timeline-label.active {
  color: var(--orange);
  font-weight: 700;
}
.timeline-label.done {
  color: var(--green);
}
</style>
