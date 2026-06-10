<template>
  <div class="legal-page">
    <h1>Politique de confidentialité</h1>
    <div v-if="pending">Chargement...</div>
    <div v-else-if="error" style="color:#EF4444;">Impossible de charger la politique de confidentialité.</div>
    <LegalText v-else :text="clause.texte" />
  </div>
</template>

<script setup lang="ts">
const { data: clause, pending, error } = await useFetch('/api/clauses-legales/rgpd/active', {
  key: 'rgpd',
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
