<script setup lang="ts">
const BaseTable = resolveComponent('UTable')

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  data?: any[]
  columns?: any[]
}>(), {
  data: () => [],
  columns: () => [],
})

const attrs = useAttrs()

function normalizeColumn(column: any): any {
  if (!column || typeof column !== 'object') return column

  const normalized = { ...column }

  if ('key' in normalized && !('accessorKey' in normalized)) {
    normalized.accessorKey = normalized.key
  }

  if ('label' in normalized && !('header' in normalized)) {
    normalized.header = normalized.label
  }

  if (Array.isArray(normalized.columns)) {
    normalized.columns = normalized.columns.map(normalizeColumn)
  }

  return normalized
}

const normalizedColumns = computed(() => props.columns.map(normalizeColumn))
</script>

<template>
  <BaseTable v-bind="attrs" :data="data" :columns="normalizedColumns">
    <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
      <slot :name="slotName" v-bind="slotProps || {}" />
    </template>
  </BaseTable>
</template>
