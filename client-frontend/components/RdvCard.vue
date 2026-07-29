<template>
  <NuxtLink :to="`/rdvs/${rdv.id}`" class="rdv-card">
    <div>
      <div class="rdv-date">{{ formatDate(rdv.date_heure) }}</div>
      <div v-if="rdv.vehicule_info" class="rdv-moto">{{ rdv.vehicule_info }}</div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <span v-if="rdv.annulation_demandee_at" class="annulation-tag">Annulation demandée</span>
      <div class="rdv-status" :class="statusClass(rdv.statut)">{{ rdvStatutLabel(rdv.statut) }}</div>
    </div>
  </NuxtLink>
</template>

<script setup lang="ts">
defineProps<{ rdv: any }>()

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
}

// `statut` porte le CODE (termine, annule, restitue…), pas le libellé FR : la
// comparaison précédente (s === 'Terminé') ne matchait jamais → tout en bleu.
function statusClass(s: string) {
  if (['termine', 'restitue', 'restitue_partiel', 'facture', 'paye', 'livre'].includes(s)) return 'status-termine'
  if (['annule', 'no_show'].includes(s)) return 'status-annule'
  return 'status-prevu'
}
</script>

<style scoped>
.rdv-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s;
}
.rdv-card:hover {
  border-color: var(--border-strong, var(--accent-graphic));
}
.rdv-date {
  font-weight: 600;
  font-size: 14px;
}
.rdv-moto {
  margin-top: 2px;
  font-size: 12px;
  color: var(--content-3);
}
.rdv-status {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  text-transform: uppercase;
  white-space: nowrap;
}
.status-prevu {
  background: var(--info-soft);
  color: var(--info-content);
}
.status-termine {
  background: var(--success-soft);
  color: var(--success-content);
}
.status-annule {
  background: var(--error-soft);
  color: var(--error-content);
}
.annulation-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--warning-soft);
  border: 1px solid var(--warning);
  color: var(--warning-content);
  white-space: nowrap;
}
</style>
