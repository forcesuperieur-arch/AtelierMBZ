<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div>
          <div class="page-title">Ateliers</div>
          <div class="page-subtitle">Création et gestion multiatelier réservées au super-admin.</div>
        </div>
      </div>
      <button v-if="isSuperAdmin" class="topbar-new-btn" @click="openCreate">+ Ajouter un atelier</button>
    </div>

    <UCard v-if="!isSuperAdmin">
      <AppErrorState
        title="Accès réservé"
        description="Cette page n’est visible que pour le super-admin."
        action-label="Retour à l’admin"
        @retry="navigateTo('/admin')"
      />
    </UCard>

    <div v-else style="display:flex;flex-direction:column;gap:16px;">
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Total ateliers</div>
          <div class="kpi-value">{{ ateliers.length }}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Ateliers actifs</div>
          <div class="kpi-value">{{ activeCount }}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Configs prêtes</div>
          <div class="kpi-value">{{ readyCount }}</div>
        </div>
      </div>

      <UCard>
        <template #header>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
              <div style="font-size:15px;font-weight:700;color:#E8E9ED;">Parc multiatelier</div>
              <div style="font-size:12px;color:#9CA3AF;">Un atelier créé ici devient immédiatement sélectionnable dans le switch super-admin.</div>
            </div>
            <button class="btn-secondary" @click="loadAteliers">↻ Actualiser</button>
          </div>
        </template>

        <AppLoadingState v-if="loading" title="Chargement des ateliers" description="Lecture de la configuration multiatelier." compact />
        <AppErrorState v-else-if="error" :description="error" action-label="Réessayer" @retry="loadAteliers" />
        <AppEmptyState
          v-else-if="!ateliers.length"
          icon="🏢"
          title="Aucun atelier"
          description="Crée le premier atelier pour activer le multi-site réel."
          action-label="Créer un atelier"
          @action="openCreate"
        />

        <div v-else class="atelier-list">
          <div v-for="atelier in ateliers" :key="atelier.id" class="atelier-row">
            <div class="atelier-main">
              <div class="atelier-head">
                <div class="atelier-name">{{ atelier.nom }}</div>
                <span class="status-chip" :class="atelier.actif ? 'is-active' : 'is-inactive'">
                  {{ atelier.actif ? 'Actif' : 'Inactif' }}
                </span>
                <span v-if="activeAtelierId === atelier.id" class="status-chip is-current">Contexte actif</span>
              </div>

              <div class="atelier-meta">{{ atelier.slug }} · {{ atelier.ville || 'Ville non renseignée' }} · plan {{ atelier.plan || 'starter' }}</div>
              <div class="atelier-meta">{{ atelier.email || 'email non renseigné' }} · {{ atelier.telephone || 'téléphone non renseigné' }}</div>
              <div class="atelier-meta">SIRET {{ atelier.siret || '—' }} · TVA {{ atelier.tva_intracom || '—' }}</div>
            </div>

            <div class="atelier-actions">
              <span class="status-chip" :class="atelier.has_config ? 'is-ready' : 'is-warning'">
                {{ atelier.has_config ? 'Config prête' : 'Config créée au premier accès' }}
              </span>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <button class="btn-sm" @click="switchAtelier(atelier, false)">🎯 Activer</button>
                <button class="btn-sm btn-outline" @click="switchAtelier(atelier, true)">⚙ Configurer</button>
                <button class="btn-sm btn-outline" @click="openEdit(atelier)">✏ Modifier</button>
              </div>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <AppModal v-model:open="showModal" size="xl">
      <template #default>
        <UCard>
          <template #header>
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ editId ? 'Modifier l’atelier' : 'Créer un atelier' }}</span>
          </template>

          <form @submit.prevent="saveAtelier" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <UFormField label="Nom de l’atelier">
                <UInput v-model="atelierForm.nom" required />
              </UFormField>
              <UFormField label="Slug public">
                <UInput v-model="atelierForm.slug" @input="slugTouched = true" placeholder="atelier-mbz-paris" />
              </UFormField>
              <UFormField label="Email">
                <UInput v-model="atelierForm.email" type="email" placeholder="contact@atelier.local" />
              </UFormField>
              <UFormField label="Téléphone">
                <UInput v-model="atelierForm.telephone" placeholder="01 23 45 67 89" />
              </UFormField>
              <UFormField label="Code postal">
                <UInput v-model="atelierForm.cp" placeholder="75000" />
              </UFormField>
              <UFormField label="Ville">
                <UInput v-model="atelierForm.ville" placeholder="Paris" />
              </UFormField>
              <UFormField label="SIRET">
                <UInput v-model="atelierForm.siret" placeholder="12345678901234" />
              </UFormField>
              <UFormField label="TVA intracom">
                <UInput v-model="atelierForm.tva_intracom" placeholder="FR00123456789" />
              </UFormField>
              <UFormField label="Plan / licence">
                <UInput v-model="atelierForm.plan" placeholder="starter" />
              </UFormField>
              <div style="display:flex;align-items:flex-end;padding-bottom:6px;">
                <label style="display:flex;align-items:center;gap:8px;color:#E8E9ED;font-size:13px;cursor:pointer;">
                  <input v-model="atelierForm.actif" type="checkbox" />
                  Atelier actif
                </label>
              </div>
            </div>

            <UFormField label="Adresse">
              <UTextarea v-model="atelierForm.adresse" :rows="3" placeholder="Adresse complète de l’atelier" />
            </UFormField>

            <div style="font-size:12px;color:#9CA3AF;">
              La configuration atelier est initialisée automatiquement pour éviter les collisions multi-site.
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <UButton label="Annuler" variant="outline" @click="showModal = false" />
              <UButton type="submit" :label="editId ? 'Enregistrer' : 'Créer l’atelier'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Ateliers' })

const api = useApi()
const toast = useToast()
const { user } = useAuth()
const activeAtelierCookie = useCookie<string | null>('active_atelier_id', { default: () => null })

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const ateliers = ref<any[]>([])
const showModal = ref(false)
const editId = ref<number | null>(null)
const slugTouched = ref(false)

const atelierForm = reactive({
  nom: '',
  slug: '',
  adresse: '',
  cp: '',
  ville: '',
  telephone: '',
  email: '',
  siret: '',
  tva_intracom: '',
  plan: 'starter',
  actif: true,
})

const isSuperAdmin = computed(() => {
  const roles = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || roles.includes('ROLE_SUPER_ADMIN')
})

const activeAtelierId = computed(() => {
  const raw = activeAtelierCookie.value || user.value?.atelier_id || null
  const parsed = Number(raw)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
})

const activeCount = computed(() => ateliers.value.filter((atelier: any) => atelier.actif).length)
const readyCount = computed(() => ateliers.value.filter((atelier: any) => atelier.has_config).length)

watch(() => atelierForm.nom, (value) => {
  if (!slugTouched.value) {
    atelierForm.slug = slugify(value)
  }
})

onMounted(async () => {
  if (!isSuperAdmin.value) {
    await navigateTo('/admin')
    return
  }

  await loadAteliers()
})

function slugify(value: string) {
  return String(value || '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
}

function normalizeAtelier(item: any) {
  return {
    id: Number(item.id),
    nom: item.nom || 'Atelier sans nom',
    slug: item.slug || '',
    adresse: item.adresse || '',
    cp: item.cp || '',
    ville: item.ville || '',
    telephone: item.telephone || '',
    email: item.email || '',
    siret: item.siret || '',
    tva_intracom: item.tva_intracom ?? item.tvaIntracom ?? '',
    logo_url: item.logo_url ?? item.logoUrl ?? '',
    plan: item.plan || 'starter',
    actif: Boolean(item.actif ?? item.isActive ?? true),
    has_config: Boolean(item.has_config ?? false),
    created_at: item.created_at || item.createdAt || null,
  }
}

function resetForm() {
  editId.value = null
  slugTouched.value = false
  Object.assign(atelierForm, {
    nom: '',
    slug: '',
    adresse: '',
    cp: '',
    ville: '',
    telephone: '',
    email: '',
    siret: '',
    tva_intracom: '',
    plan: 'starter',
    actif: true,
  })
}

function openCreate() {
  resetForm()
  showModal.value = true
}

function openEdit(atelier: any) {
  editId.value = atelier.id
  slugTouched.value = true
  Object.assign(atelierForm, {
    nom: atelier.nom || '',
    slug: atelier.slug || '',
    adresse: atelier.adresse || '',
    cp: atelier.cp || '',
    ville: atelier.ville || '',
    telephone: atelier.telephone || '',
    email: atelier.email || '',
    siret: atelier.siret || '',
    tva_intracom: atelier.tva_intracom || '',
    plan: atelier.plan || 'starter',
    actif: Boolean(atelier.actif),
  })
  showModal.value = true
}

async function loadAteliers() {
  if (!isSuperAdmin.value) return

  loading.value = true
  error.value = ''

  try {
    const data = await api.get('/admin/ateliers')
    ateliers.value = (Array.isArray(data) ? data : []).map(normalizeAtelier)
  } catch (e: any) {
    error.value = e?.message || 'Chargement impossible de la liste multiatelier.'
  } finally {
    loading.value = false
  }
}

async function saveAtelier() {
  saving.value = true

  try {
    const payload = {
      nom: atelierForm.nom.trim(),
      slug: (atelierForm.slug || slugify(atelierForm.nom)).trim(),
      adresse: atelierForm.adresse.trim(),
      cp: atelierForm.cp.trim(),
      ville: atelierForm.ville.trim(),
      telephone: atelierForm.telephone.trim(),
      email: atelierForm.email.trim(),
      siret: atelierForm.siret.trim(),
      tva_intracom: atelierForm.tva_intracom.trim(),
      plan: atelierForm.plan.trim() || 'starter',
      actif: atelierForm.actif,
    }

    if (editId.value) {
      await api.put(`/admin/ateliers/${editId.value}`, payload)
    } else {
      await api.post('/admin/ateliers', payload)
    }

    toast.add({
      title: editId.value ? 'Atelier mis à jour' : 'Atelier créé',
      description: editId.value ? 'Les informations ont été enregistrées.' : 'Le nouvel atelier est prêt pour la configuration.',
      color: 'success',
    })

    showModal.value = false
    await loadAteliers()
  } catch (e: any) {
    toast.add({
      title: 'Action impossible',
      description: e?.message || 'Erreur inconnue',
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}

async function switchAtelier(atelier: any, redirectToConfig = false) {
  try {
    const res = await api.post('/auth/switch-atelier', { atelier_id: atelier.id })
    activeAtelierCookie.value = String(res.active_atelier_id ?? atelier.id)

    toast.add({
      title: 'Atelier actif changé',
      description: atelier.nom,
      color: 'success',
    })

    if (redirectToConfig) {
      await navigateTo('/admin/config')
      return
    }

    await loadAteliers()
  } catch (e: any) {
    toast.add({
      title: 'Impossible de changer de contexte',
      description: e?.message || 'Erreur inconnue',
      color: 'error',
    })
  }
}
</script>

<style scoped>
.page-subtitle {
  color: #9CA3AF;
  font-size: 12px;
  margin-top: 2px;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.kpi-card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 14px;
  padding: 14px 16px;
}

.kpi-label {
  color: #9CA3AF;
  font-size: 12px;
  margin-bottom: 6px;
}

.kpi-value {
  color: #E8E9ED;
  font-size: 24px;
  font-weight: 800;
}

.atelier-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.atelier-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.03);
}

.atelier-head {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.atelier-name {
  color: #E8E9ED;
  font-size: 15px;
  font-weight: 800;
}

.atelier-meta {
  color: #9CA3AF;
  font-size: 12px;
  line-height: 1.4;
}

.atelier-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-end;
}

.status-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 700;
}

.is-active {
  background: rgba(16,185,129,0.14);
  color: #86EFAC;
}

.is-inactive {
  background: rgba(239,68,68,0.14);
  color: #FCA5A5;
}

.is-current {
  background: rgba(59,130,246,0.14);
  color: #93C5FD;
}

.is-ready {
  background: rgba(94,234,212,0.14);
  color: #99F6E4;
}

.is-warning {
  background: rgba(251,191,36,0.14);
  color: #FCD34D;
}

.btn-sm,
.btn-secondary,
.btn-outline {
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.08);
  padding: 7px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-sm,
.btn-secondary {
  background: rgba(255,255,255,0.06);
  color: #E8E9ED;
}

.btn-outline {
  background: transparent;
  color: #C4B5FD;
}

@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }

  .atelier-row {
    grid-template-columns: 1fr;
  }

  .atelier-actions {
    align-items: flex-start;
  }
}
</style>
