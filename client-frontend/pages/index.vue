<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">
      Bonjour {{ auth.client?.prenom || '—' }}
    </h1>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="dash-card">
        <div class="dash-label">Prochain RDV</div>
        <div class="dash-value">{{ prochainRdvText }}</div>
      </div>
      <div class="dash-card">
        <div class="dash-label">Motos</div>
        <div class="dash-value">{{ auth.client?.vehicules?.length || 0 }}</div>
      </div>
      <div class="dash-card">
        <div class="dash-label">RDV passés</div>
        <div class="dash-value">{{ rdvsCount }}</div>
      </div>
      <div class="dash-card">
        <div class="dash-label">Historique</div>
        <NuxtLink to="/historique" class="dash-link">Voir</NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const prochainRdvText = ref('—')
const rdvsCount = ref(0)

onMounted(async () => {
  await auth.fetchMe()
  try {
    const rdvs = await $fetch('/api/client/rdvs', {
      headers: auth.accessToken ? { Authorization: `Bearer ${auth.accessToken}` } : {},
      credentials: 'include',
      baseURL: '',
    })
    rdvsCount.value = rdvs?.length || 0

    const now = new Date()
    const futurs = (rdvs || [])
      .map((r: any) => ({ ...r, d: new Date(r.date_heure) }))
      .filter((r: any) => r.d > now)
      .sort((a: any, b: any) => a.d.getTime() - b.d.getTime())

    if (futurs.length > 0) {
      prochainRdvText.value = futurs[0].d.toLocaleDateString('fr-FR', {
        weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
      })
    } else {
      prochainRdvText.value = 'Aucun'
    }
  } catch {
    prochainRdvText.value = 'Aucun'
    rdvsCount.value = 0
  }
})
</script>

<style scoped>
.dash-card {
  padding: 16px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
}
.dash-label {
  font-size: 12px;
  color: #9CA3AF;
  font-weight: 600;
  margin-bottom: 6px;
}
.dash-value {
  font-size: 18px;
  font-weight: 800;
  color: #FFD200;
}
.dash-link {
  font-size: 14px;
  color: #FFD200;
  text-decoration: none;
  font-weight: 700;
}
</style>
