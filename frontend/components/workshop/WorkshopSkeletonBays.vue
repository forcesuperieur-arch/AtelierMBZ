<template>
  <div class="bay-skeleton-grid">
    <AppSkeletonCard v-for="i in 6" :key="i" :lines="4" />
  </div>
</template>

<style scoped>
.bay-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
@media (max-width: 1023px) {
  .bay-skeleton-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 639px) {
  .bay-skeleton-grid {
    grid-template-columns: 1fr;
  }
}
</style>
