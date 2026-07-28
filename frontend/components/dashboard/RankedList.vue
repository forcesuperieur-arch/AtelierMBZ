<template>
  <div v-if="visibles.length">
    <ul class="rank">
      <li v-for="(row, i) in visibles" :key="row.key ?? i" class="rank-row">
        <span class="rank-pos">{{ i + 1 }}</span>
        <div class="rank-main">
          <div class="rank-label">{{ row.label }}</div>
          <div v-if="row.sub" class="rank-sub">{{ row.sub }}</div>
        </div>
        <div class="rank-metric">
          <div class="rank-value" :class="{ 'rank-value--warn': row.tone === 'warn', 'rank-value--good': row.tone === 'good' }">
            {{ row.value }}
          </div>
          <div v-if="row.valueSub" class="rank-value-sub">{{ row.valueSub }}</div>
        </div>
      </li>
    </ul>

    <!-- Une liste bornée qui annonce ce qu'elle cache. L'ancienne page déroulait
         les 40 mécaniciens et les 156 ponts, ce qui noyait les 3 lignes utiles. -->
    <button v-if="rows.length > limit" type="button" class="rank-more" @click="tout = !tout">
      {{ tout ? 'Afficher seulement le top ' + limit : `Voir les ${rows.length - limit} autres` }}
    </button>
  </div>

  <AppEmptyState
    v-else
    :icon="emptyIcon || '📄'"
    :title="emptyTitle || 'Pas encore de données'"
    :description="emptyDescription || 'Ce classement se remplit au fil des interventions clôturées.'"
  />
</template>

<script setup lang="ts">
interface RankedRow {
  key?: string | number
  label: string
  sub?: string
  value: string
  valueSub?: string
  tone?: 'neutral' | 'good' | 'warn'
}

const props = withDefaults(defineProps<{
  rows: RankedRow[]
  limit?: number
  emptyIcon?: string
  emptyTitle?: string
  emptyDescription?: string
}>(), { limit: 5 })

const tout = ref(false)
const visibles = computed(() => tout.value ? props.rows : props.rows.slice(0, props.limit))
</script>

<style scoped>
.rank { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.rank-row {
  display: grid;
  grid-template-columns: 22px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: var(--radius-sm);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
}
.rank-pos {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-muted);
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.rank-label { font-size: 13px; font-weight: 600; color: var(--ink); }
.rank-sub { font-size: 12px; color: var(--ink-muted); margin-top: 2px; }
.rank-metric { text-align: right; }
.rank-value { font-size: 13px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.rank-value--warn { color: var(--status-warn); }
.rank-value--good { color: var(--status-good); }
.rank-value-sub { font-size: 11px; color: var(--ink-muted); margin-top: 2px; }
.rank-more {
  margin-top: 10px;
  padding: 6px 12px;
  min-height: 32px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.rank-more:hover { border-color: var(--border-hover); color: var(--ink); }
.rank-more:focus-visible { outline: 2px solid var(--orange); outline-offset: 2px; }
</style>
