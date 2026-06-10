<template>
  <div class="legal-page">
    <h1>Clauses particulières</h1>
    <p class="intro">
      Certaines prestations peuvent être soumises à des clauses particulières définies lors de la prise de rendez-vous ou dans le bon de commande.
      Retrouvez ci-dessous l'ensemble des clauses contractuelles applicables.
    </p>

    <div v-if="pending">Chargement des clauses...</div>
    <div v-else-if="error" style="color:#EF4444;">Impossible de charger les clauses.</div>
    <div v-else-if="!clauses?.length" style="color:#6B7280;">Aucune clause disponible.</div>

    <template v-else>
      <nav class="clause-toc">
        <a v-for="c in clauses" :key="c.code" :href="`#clause-${c.code}`">
          {{ c.libelle }}
        </a>
      </nav>

      <section
        v-for="c in clauses"
        :key="c.code"
        :id="`clause-${c.code}`"
        class="clause-section"
      >
        <h2 class="clause-title">{{ c.libelle }}</h2>
        <LegalText :text="c.texte" />
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
const { data: clauses, pending, error } = await useFetch('/api/clauses-legales', {
  key: 'clauses',
  baseURL: '',
  transform: (res: any[]) => res.filter((c: any) => c.isActive),
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
.intro {
  font-size: 14px;
  line-height: 1.6;
  color: #9CA3AF;
  margin-bottom: 20px;
}
.clause-toc {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
  padding: 12px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
}
.clause-toc a {
  font-size: 12px;
  font-weight: 600;
  color: #FFD200;
  text-decoration: none;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(255,210,0,0.08);
  transition: background 0.2s;
}
.clause-toc a:hover {
  background: rgba(255,210,0,0.15);
}
.clause-section {
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.clause-title {
  font-size: 18px;
  font-weight: 700;
  color: #E8E9ED;
  margin-bottom: 12px;
}
</style>
