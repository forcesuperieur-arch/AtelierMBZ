<template>
  <div style="padding:24px;color:var(--content-3);font-size:13px;">Ouverture de l'administration…</div>
</template>

<script setup lang="ts">
/**
 * `/admin` n'est plus un menu de cartes : la navigation se fait par les onglets
 * du cadre `pages/admin.vue`. On ouvre donc directement le premier onglet
 * (Configuration), comme un navigateur rouvre son premier onglet.
 */
onMounted(() => {
  // Le contrôle du rôle admin est déjà assuré par le middleware global.
  navigateTo('/admin/config', { replace: true })
})
</script>
