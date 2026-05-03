<template>
  <UCard class="alert-card">
    <template #header>
      <div class="alert-header">
        <UIcon name="i-heroicons-exclamation-triangle" class="alert-icon" />
        <span class="alert-title"><slot name="title" /></span>
      </div>
    </template>
    <slot />
  </UCard>
</template>

<style scoped>
.alert-card {
  margin-bottom: 16px;
  border-color: rgba(239, 68, 68, 0.2);
}
.alert-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #FCA5A5;
}
.alert-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}
</style>
