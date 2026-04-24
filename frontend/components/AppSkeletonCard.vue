<template>
  <UCard>
    <div class="skeleton-card-body">
      <div class="skeleton-shimmer skeleton-title" />
      <div
        v-for="i in lines"
        :key="i"
        class="skeleton-shimmer skeleton-line"
        :class="{ 'skeleton-line-short': i === lines }"
      />
    </div>
  </UCard>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  lines?: number
}>(), {
  lines: 3,
})
</script>

<style scoped>
.skeleton-card-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.skeleton-title {
  height: 20px;
  width: 55%;
  margin-bottom: 4px;
}
.skeleton-line {
  height: 14px;
  width: 100%;
}
.skeleton-line-short {
  width: 45%;
}
</style>
