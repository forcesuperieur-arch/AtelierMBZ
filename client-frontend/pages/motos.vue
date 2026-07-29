<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mes motos</h1>
    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="error" style="color:var(--error-content)">Impossible de charger vos motos pour le moment. Réessayez plus tard.</div>
    <div v-else-if="motos.length === 0" style="color:var(--content-3)">Aucune moto enregistrée.</div>
    <div v-else style="display:flex;flex-direction:column;gap:12px;">
      <div v-for="moto in motos" :key="moto.id" class="moto-card">
        <div class="moto-name">{{ moto.marque }} {{ moto.modele }}</div>
        <div class="moto-meta">
          <span v-if="moto.plaque">{{ moto.plaque }}</span>
          <span v-if="moto.annee">{{ moto.annee }}</span>
          <span v-if="moto.cylindree">{{ moto.cylindree }}</span>
        </div>

        <div class="moto-field">
          <label>Kilométrage</label>
          <input
            v-model.number="edits[moto.id].kilometrage"
            type="number"
            min="0"
            placeholder="Non renseigné"
          />
        </div>
        <div class="moto-field">
          <label>Notes</label>
          <textarea
            v-model="edits[moto.id].notes"
            rows="2"
            placeholder="Entretiens perso, particularités…"
          />
        </div>

        <button
          class="moto-save"
          :disabled="saving[moto.id]"
          @click="save(moto.id)"
        >{{ saving[moto.id] ? 'Enregistrement…' : 'Enregistrer' }}</button>
        <div v-if="saveError[moto.id]" class="moto-error">{{ saveError[moto.id] }}</div>
        <div v-if="saved[moto.id]" class="moto-saved">Enregistré.</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const { apiFetch } = useClientApi()

// default: () => [] + error → pas d'écran blanc si l'API échoue.
const { data: motos, pending, error } = useAsyncData('client-motos', async () => {
  if (!auth.isAuthenticated) return []
  return await apiFetch('/api/client/vehicules')
}, { default: () => [] })

// Brouillon d'édition par moto, initialisé une fois les données chargées :
// évite qu'un v-model direct sur `motos` réécrive un état serveur avant l'enregistrement.
const edits = reactive<Record<number, { kilometrage: number | null; notes: string }>>({})
const saving = reactive<Record<number, boolean>>({})
const saveError = reactive<Record<number, string>>({})
const saved = reactive<Record<number, boolean>>({})

watch(motos, (list) => {
  for (const m of list || []) {
    if (!(m.id in edits)) {
      edits[m.id] = { kilometrage: m.kilometrage ?? null, notes: m.notes ?? '' }
    }
  }
}, { immediate: true })

async function save(id: number) {
  saving[id] = true
  saveError[id] = ''
  saved[id] = false
  try {
    await apiFetch(`/api/client/vehicules/${id}`, {
      method: 'PATCH',
      body: { kilometrage: edits[id].kilometrage, notes: edits[id].notes },
    })
    saved[id] = true
  } catch (e: any) {
    saveError[id] = e?.data?.error || 'Impossible d\'enregistrer. Réessayez.'
  } finally {
    saving[id] = false
  }
}
</script>

<style scoped>
.moto-card {
  padding: 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
}
.moto-name {
  font-weight: 800;
  font-size: 16px;
  margin-bottom: 4px;
}
.moto-meta {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: var(--content-3);
  margin-bottom: 14px;
}
.moto-field {
  margin-bottom: 10px;
}
.moto-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
  margin-bottom: 4px;
}
.moto-field input,
.moto-field textarea {
  width: 100%;
  padding: 8px 10px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 14px;
  outline: none;
  resize: vertical;
}
.moto-field input:focus,
.moto-field textarea:focus {
  border-color: var(--accent-graphic);
}
.moto-save {
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  background: var(--accent);
  color: var(--accent-ink);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}
.moto-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.moto-error {
  margin-top: 8px;
  font-size: 12px;
  color: var(--error-content);
}
.moto-saved {
  margin-top: 8px;
  font-size: 12px;
  color: var(--success-content);
}
</style>
