<template>
  <component
    :is="to ? 'NuxtLink' : 'div'"
    :to="to"
    class="tile"
    :class="[`tile--${tone}`, { 'tile--link': to }]"
  >
    <div class="tile-label">{{ label }}</div>
    <div class="tile-value">
      {{ value }}<span v-if="unit" class="tile-unit">{{ unit }}</span>
    </div>

    <!-- L'évolution n'est affichée que si elle est réellement calculable :
         jamais un « 0 % » de remplissage quand la période précédente est vide.
         Le repère factuel vient en complément, pas à la place. -->
    <div v-if="deltaInfo" class="tile-delta" :class="`tile-delta--${deltaTone}`">
      <span aria-hidden="true"><AppIcon v-if="deltaInfo.sens === 'hausse' || deltaInfo.sens === 'baisse'" :name="deltaInfo.sens === 'hausse' ? 'i-ri-arrow-up-s-fill' : 'i-ri-arrow-down-s-fill'" /><template v-else>=</template></span>
      {{ deltaInfo.signe }}{{ deltaInfo.pct }} % <span class="tile-delta-ref">vs période précédente</span>
    </div>
    <div v-if="hint" class="tile-hint">{{ hint }}</div>

    <DashboardMeter v-if="meter" :value="meter.value" :max="meter.max" :tone="tone" :label="meter.label" />
  </component>
</template>

<script setup lang="ts">
/**
 * Tuile de mesure. Volontairement dépourvue de « jauge » décorative : dans
 * l'ancienne page, chaque tuile portait une barre remplie sur un maximum
 * inventé en dur (GAUGE_MAX_RDV = 40), donc pleine à 100 % dès 40 RDV et
 * dénuée de sens. Ici, une barre n'apparaît que si un maximum RÉEL existe
 * (capacité de ponts, objectif configuré) via la prop `meter`.
 */
const props = defineProps<{
  label: string
  value: string | number
  unit?: string
  hint?: string
  /** Bloc `comparison.*` de l'API ; ignoré si aucune comparaison n'est possible. */
  comparison?: any
  /** Sens favorable de l'évolution. 'none' = neutre (ni bon ni mauvais). */
  goodWhen?: 'up' | 'down' | 'none'
  tone?: 'neutral' | 'good' | 'warn' | 'crit'
  meter?: { value: number, max: number, label?: string }
  to?: string
}>()

const { delta } = useDashboardFormat()

const tone = computed(() => props.tone ?? 'neutral')
const deltaInfo = computed(() => delta(props.comparison))

const deltaTone = computed(() => {
  const info = deltaInfo.value
  const sens = props.goodWhen ?? 'up'
  if (!info || info.sens === 'stable' || sens === 'none') return 'neutral'
  const favorable = sens === 'up' ? info.sens === 'hausse' : info.sens === 'baisse'
  return favorable ? 'good' : 'warn'
})
</script>

<style scoped>
.tile {
  display: block;
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 14px 16px;
  text-decoration: none;
}
.tile--link {
  transition: border-color var(--transition), background var(--transition);
}
.tile--link:hover {
  border-color: var(--border-hover);
  background: var(--overlay-hover);
}
.tile--good { border-color: var(--success); background: var(--status-good-soft); }
.tile--warn { border-color: var(--warning); background: var(--status-warn-soft); }
.tile--crit { border-color: var(--error); background: var(--status-crit-soft); }

.tile-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.tile-value {
  /* Chiffres proportionnels : `tabular-nums` fait « respirer » un grand nombre
     de façon désagréable. Les colonnes alignées, c'est pour les tableaux. */
  font-size: 26px;
  font-weight: 800;
  line-height: 1.1;
  color: var(--ink);
  margin: 8px 0 4px;
}
.tile--crit .tile-value { color: var(--error-content); }
.tile-unit {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink-muted);
  margin-left: 3px;
}
.tile-delta, .tile-hint {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-muted);
}
.tile-delta--good { color: var(--success-content); }
.tile-delta--warn { color: var(--warning-content); }
.tile-delta-ref { font-weight: 500; color: var(--ink-muted); }
.tile-hint { font-weight: 500; }
.tile-delta + .tile-hint { margin-top: 3px; }
</style>
