<template>
  <div>
    <AppPageHeader title="Clauses légales" back-to="/admin">
      <template #actions>
        <button class="topbar-new-btn" @click="openCreate">+ Nouvelle clause</button>
      </template>
    </AppPageHeader>

    <p style="color:#9CA3AF;font-size:13px;margin-bottom:16px;">
      Toute modification du texte crée une <strong>nouvelle version</strong> et désactive l'ancienne (traçabilité légale).
    </p>

    <UCard>
      <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
        <button v-for="f in statusFilters" :key="String(f.value)" class="btn" :class="activeOnly === f.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="activeOnly = f.value; load()">
          {{ f.label }}
        </button>
      </div>

      <div v-if="loading" style="text-align:center;padding:24px;color:#9CA3AF;">Chargement…</div>
      <div v-else-if="!clauses.length" style="text-align:center;padding:24px;color:#6B7280;">Aucune clause.</div>
      <div v-else style="display:flex;flex-direction:column;gap:10px;">
        <div v-for="c in clauses" :key="c.id" style="padding:12px 14px;border:1px solid rgba(255,255,255,0.06);border-radius:10px;background:rgba(255,255,255,0.02);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
              <div style="display:flex;gap:10px;align-items:center;">
                <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(147,197,253,0.14);color:#93C5FD;font-weight:700;">{{ c.code }}</span>
                <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(139,92,246,0.14);color:#C4B5FD;font-weight:700;">v{{ c.version }}</span>
                <span :style="c.isActive ? 'background:rgba(16,185,129,0.14);color:#6EE7B7;' : 'background:rgba(156,163,175,0.14);color:#9CA3AF;'" style="font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;">
                  {{ c.isActive ? 'Active' : 'Archivée' }}
                </span>
              </div>
              <div style="font-size:14px;font-weight:700;color:#E8E9ED;margin-top:6px;">{{ c.libelle }}</div>
              <div style="font-size:11px;color:#6B7280;margin-top:2px;">En vigueur depuis le {{ c.effectiveFrom }}</div>
              <div style="font-size:12px;color:#D1D5DB;margin-top:6px;white-space:pre-wrap;max-height:100px;overflow:hidden;position:relative;">{{ c.texte.slice(0, 300) }}{{ c.texte.length > 300 ? '…' : '' }}</div>
            </div>
            <div style="display:flex;gap:6px;flex-direction:column;align-items:flex-end;">
              <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;" @click="openEdit(c)">{{ c.isActive ? '✏️ Modifier' : '👁️ Voir' }}</button>
              <button v-if="!c.isActive" class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:#6EE7B7;" @click="toggleActive(c, true)">Réactiver</button>
              <button v-else class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:#FCA5A5;" @click="toggleActive(c, false)">Archiver</button>
            </div>
          </div>
        </div>
      </div>
    </UCard>

    <AppModal :open="showEditModal" @update:open="showEditModal = $event">
      <template #header>
        <div style="font-weight:700;color:#E8E9ED;">{{ editing?.id ? `Modifier la clause ${editing.code} v${editing.version}` : 'Nouvelle clause légale' }}</div>
      </template>
      <div v-if="editing" style="display:flex;flex-direction:column;gap:12px;">
        <UFormField label="Code" required>
          <USelect v-if="!editing.id" v-model="editing.code" :items="CODES" />
          <UInput v-else :model-value="editing.code" disabled />
        </UFormField>
        <UFormField label="Libellé" required>
          <UInput v-model="editing.libelle" />
        </UFormField>
        <UFormField label="En vigueur depuis">
          <UInput v-model="editing.effectiveFrom" type="date" />
        </UFormField>
        <UFormField label="Texte complet" required>
          <UTextarea v-model="editing.texte" :rows="10" placeholder="Texte juridique intégral…" />
        </UFormField>
        <p v-if="editing.id" style="font-size:11px;color:#FCD34D;">
          ⚠️ Toute modification du texte créera une nouvelle version et archivera cette version {{ editing.version }}.
        </p>
      </div>
      <template #footer>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button class="btn btn-ghost" @click="showEditModal = false">Annuler</button>
          <button class="btn btn-primary" :disabled="saving || !editing?.libelle || !editing?.texte || !editing?.code" @click="save">
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Clauses légales' })

const api = useApi()
const toast = useToast()
const loading = ref(false)
const saving = ref(false)
const clauses = ref<any[]>([])
const showEditModal = ref(false)
const editing = ref<any>(null)
const activeOnly = ref(true)

const CODES = [
  'accessoires',
  'cgv',
  'essai',
  'garantie',
  'gardiennage',
  'mandat_reparation',
  'mentions_legales',
  'retention',
  'rgpd',
]

const statusFilters = [
  { value: true, label: 'Actives' },
  { value: false, label: 'Toutes (historique)' },
]

async function load() {
  loading.value = true
  try {
    clauses.value = await api.get(`/clauses-legales?active=${activeOnly.value}`)
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = {
    code: 'cgv',
    libelle: '',
    texte: '',
    effectiveFrom: new Date().toISOString().slice(0, 10),
  }
  showEditModal.value = true
}

function openEdit(c: any) {
  editing.value = { ...c }
  showEditModal.value = true
}

async function save() {
  if (!editing.value) return
  saving.value = true
  try {
    if (editing.value.id) {
      await api.put(`/clauses-legales/${editing.value.id}`, {
        libelle: editing.value.libelle,
        texte: editing.value.texte,
        effectiveFrom: editing.value.effectiveFrom,
      })
    } else {
      await api.post('/clauses-legales', {
        code: editing.value.code,
        libelle: editing.value.libelle,
        texte: editing.value.texte,
        effectiveFrom: editing.value.effectiveFrom,
      })
    }
    toast.add({ title: 'Clause enregistrée', color: 'success' })
    showEditModal.value = false
    await load()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function toggleActive(c: any, isActive: boolean) {
  try {
    await api.put(`/clauses-legales/${c.id}`, { isActive })
    toast.add({ title: isActive ? 'Activée' : 'Archivée', color: 'success' })
    await load()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}

onMounted(load)
</script>
