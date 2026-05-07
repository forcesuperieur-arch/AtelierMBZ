<template>
  <div class="pit-table-wrapper">
    <div class="pit-table-scroll">
      <UTable
        :data="rows"
        :columns="mappedColumns"
        :loading="loading"
        :class="['pit-table']"
        v-bind="$attrs"
      >
        <template v-for="(_, name) in $slots" :key="name" #[name]="slotData">
          <slot :name="name" v-bind="slotData" />
        </template>
      </UTable>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  rows?: any[]
  columns?: any[]
  loading?: boolean
}>(), {
  rows: () => [],
  columns: () => [],
  loading: false,
})

// Nuxt UI v4 UTable uses accessorKey/header instead of v3's key/label
const mappedColumns = computed(() =>
  props.columns.map((col: any) => {
    if (col.accessorKey || col.accessorFn || col.id) return col
    const { key, label, ...rest } = col
    if (key) return { accessorKey: key, header: label ?? key, ...rest }
    return col
  })
)
</script>

<style scoped>
.pit-table-wrapper {
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.pit-table-scroll {
  overflow-x: auto;
}

.pit-table :deep(thead tr) {
  background: var(--bg-elevated);
}

.pit-table :deep(thead th) {
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-tertiary);
  padding: 10px 16px;
}

.pit-table :deep(tbody td),
.pit-table :deep([data-slot="td"]) {
  padding: 10px 16px !important;
  color: var(--text-primary);
  font-size: 14px;
  border-bottom: 1px solid var(--border-subtle);
}

/* Override Nuxt UI v4 whitespace-nowrap on td */
.pit-table :deep([data-slot="td"]) {
  white-space: normal !important;
  vertical-align: middle;
}

.pit-table :deep(tbody tr:hover) {
  background: var(--bg-elevated);
}

.pit-table :deep(tbody tr:last-child td) {
  border-bottom: none;
}
</style>
