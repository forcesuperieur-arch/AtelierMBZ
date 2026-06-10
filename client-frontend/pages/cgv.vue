<template>
  <div class="legal-page">
    <h1>Conditions Générales de Vente</h1>
    <div v-if="pending">Chargement...</div>
    <div v-else-if="error" style="color:#EF4444;">Impossible de charger les CGV.</div>
    <LegalText v-else :text="clause.texte" />
  </div>
</template>

<script setup lang="ts">
const { data: clause, pending, error } = await useFetch('/api/clauses-legales/cgv/active', {
  key: 'cgv',
  baseURL: '',
  transform: (res: any) => res.error ? null : res,
})
</script>

<style scoped>
.legal-page {
  max-width: 720px;
}
.legal-page h1 {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 12px;
}
</style>
