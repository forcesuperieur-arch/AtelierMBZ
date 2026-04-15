<template>
  <div class="dark">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
const colorMode = useColorMode()
colorMode.preference = 'dark'
colorMode.value = 'dark'
</script>
