<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
// Titre d'onglet par défaut du portail client (auparavant l'onglet affichait
// l'URL brute). Les pages peuvent le préciser via useHead({ title: '…' }).
useHead({
  titleTemplate: (title?: string) => (title ? `${title} — Paddock` : 'Mon Atelier — Paddock'),
})
</script>
