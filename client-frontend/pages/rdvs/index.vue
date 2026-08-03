<template>
  <div>
    <div class="rdv-header">
      <h1 style="font-size:20px;font-weight:800;">Mes rendez-vous</h1>
      <NuxtLink to="/rdvs/new" class="new-rdv-btn">Prendre un rendez-vous</NuxtLink>
    </div>
    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="error" style="color:var(--error-content)">Impossible de charger vos rendez-vous pour le moment. Réessayez plus tard.</div>
    <div v-else-if="rdvs.length === 0" style="color:var(--content-3)">Aucun rendez-vous.</div>
    <template v-else>
      <section v-if="aVenir.length" class="rdv-section">
        <h2 class="rdv-section-title">À venir</h2>
        <div class="rdv-list">
          <RdvCard v-for="rdv in aVenir" :key="rdv.id" :rdv="rdv" />
        </div>
      </section>
      <section v-if="passes.length" class="rdv-section">
        <h2 class="rdv-section-title">Passés</h2>
        <div class="rdv-list">
          <RdvCard v-for="rdv in passes" :key="rdv.id" :rdv="rdv" />
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const { apiFetch } = useClientApi()

// `default: () => []` : sans lui, une erreur API (ex. 500) laisse `data` à null
// et le template plante sur `rdvs.length` (écran blanc). On expose aussi `error`
// pour afficher un message plutôt qu'un écran cassé.
const { data: rdvs, pending, error } = useAsyncData('client-rdvs', async () => {
  if (!auth.isAuthenticated) return []
  return await apiFetch('/api/client/rdvs')
}, { default: () => [] })

const aVenir = computed(() => {
  const now = new Date()
  return (rdvs.value || [])
    .filter((r: any) => new Date(r.date_heure) > now)
    .sort((a: any, b: any) => new Date(a.date_heure).getTime() - new Date(b.date_heure).getTime())
})
const passes = computed(() => {
  const now = new Date()
  return (rdvs.value || [])
    .filter((r: any) => new Date(r.date_heure) <= now)
    .sort((a: any, b: any) => new Date(b.date_heure).getTime() - new Date(a.date_heure).getTime())
})
</script>

<style scoped>
.rdv-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.new-rdv-btn {
  padding: 8px 14px;
  border-radius: 8px;
  background: var(--accent);
  color: var(--accent-ink);
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}
.rdv-section + .rdv-section {
  margin-top: 28px;
}
.rdv-section-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--content-3);
  margin: 0 0 10px;
}
.rdv-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
</style>
