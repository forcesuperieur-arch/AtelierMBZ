<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">
      Bonjour {{ auth.client?.prenom || '—' }}
    </h1>

    <div class="dash-grid">
      <component :is="prochainRdv ? 'NuxtLink' : 'div'" :to="prochainRdv ? `/rdvs/${prochainRdv.id}` : undefined" class="dash-card" :class="{ clickable: !!prochainRdv }">
        <div class="dash-label">Prochain RDV</div>
        <div class="dash-value">{{ prochainRdvText }}</div>
        <div v-if="prochainRdv?.vehicule_info" class="dash-sub">{{ prochainRdv.vehicule_info }}</div>
      </component>
      <NuxtLink to="/motos" class="dash-card clickable">
        <div class="dash-label">Motos</div>
        <div class="dash-value">{{ auth.client?.vehicules?.length || 0 }}</div>
      </NuxtLink>
      <NuxtLink to="/historique" class="dash-card clickable">
        <div class="dash-label">RDV passés</div>
        <div class="dash-value">{{ rdvsCount }}</div>
      </NuxtLink>
      <NuxtLink to="/historique" class="dash-card clickable">
        <div class="dash-label">Historique</div>
        <div class="dash-link">Voir <AppIcon name="i-ri-arrow-left-line" style="transform:rotate(180deg);" /></div>
      </NuxtLink>
    </div>

    <div v-if="auth.client?.vehicules?.length" class="dash-motos">
      <h2 class="dash-section-title">Mes motos</h2>
      <div class="moto-chips">
        <NuxtLink v-for="v in auth.client.vehicules" :key="v.id" to="/motos" class="moto-chip">
          {{ v.marque }} {{ v.modele }}
        </NuxtLink>
      </div>
    </div>

    <div v-if="loadError" style="margin-top:14px;font-size:13px;color:var(--error-content);">
      Impossible de charger vos rendez-vous pour le moment. Réessayez plus tard.
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

const prochainRdvText = ref('—')
const prochainRdv = ref<any>(null)
const rdvsCount = ref(0)
const loadError = ref(false)

const { apiFetch } = useClientApi()

onMounted(async () => {
  try {
    const rdvs = await apiFetch('/api/client/rdvs')

    const now = new Date()
    const avecDate = (rdvs || []).map((r: any) => ({ ...r, d: new Date(r.date_heure) }))
    // « RDV passés » = ceux dont la date est révolue (avant : comptait TOUS les RDV).
    rdvsCount.value = avecDate.filter((r: any) => r.d <= now).length

    const futurs = avecDate
      .filter((r: any) => r.d > now)
      .sort((a: any, b: any) => a.d.getTime() - b.d.getTime())

    if (futurs.length > 0) {
      prochainRdv.value = futurs[0]
      prochainRdvText.value = futurs[0].d.toLocaleDateString('fr-FR', {
        weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
      })
    } else {
      prochainRdvText.value = 'Aucun'
    }
  } catch {
    // Ne pas faire croire à « 0 RDV » quand l'API échoue : afficher une erreur.
    prochainRdvText.value = '—'
    rdvsCount.value = 0
    loadError.value = true
  }
})
</script>

<style scoped>
.dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.dash-card {
  display: block;
  padding: 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s;
}
.dash-card.clickable:hover {
  border-color: var(--accent-graphic);
}
.dash-label {
  font-size: 12px;
  color: var(--content-3);
  font-weight: 600;
  margin-bottom: 6px;
}
.dash-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--accent-content);
}
.dash-sub {
  margin-top: 4px;
  font-size: 12px;
  color: var(--content-3);
}
.dash-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: var(--accent-content);
  font-weight: 700;
}
.dash-motos {
  margin-top: 28px;
}
.dash-section-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--content-3);
  margin: 0 0 10px;
}
.moto-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.moto-chip {
  padding: 8px 14px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--content-1);
  text-decoration: none;
}
.moto-chip:hover {
  border-color: var(--accent-graphic);
}
</style>
