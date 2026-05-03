<template>
  <div class="redirect-message">
    Redirection en cours…
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()

const target = computed(() => {
  const roles = auth.user.value?.roles || []
  if (roles.includes('ROLE_SERVICE_CLIENT') || !auth.hasSection('planning')) {
    return '/rdv/new'
  }

  return '/planning'
})

await navigateTo(target.value, { replace: true })
</script>

<style scoped>
.redirect-message { padding:32px 16px; text-align:center; color:#9CA3AF; }
</style>
