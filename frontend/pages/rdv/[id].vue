<template>
  <div style="padding:32px 16px;text-align:center;color:#9CA3AF;">
    Ouverture du rendez-vous dans le planning…
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const route = useRoute()

const target = computed(() => {
  const rdvId = Number(route.params.id)
  if (auth.hasSection('planning') && Number.isFinite(rdvId) && rdvId > 0) {
    return `/planning?openRdv=${rdvId}`
  }

  return '/rdv/new'
})

await navigateTo(target.value, { replace: true })
</script>