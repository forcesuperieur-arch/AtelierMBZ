<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mon profil</h1>

    <div class="profil-card">
      <form @submit.prevent="save" class="profil-form">
        <div class="field">
          <label for="p-prenom">Prénom</label>
          <input id="p-prenom" v-model="form.prenom" type="text" autocomplete="given-name" />
        </div>
        <div class="field">
          <label for="p-nom">Nom</label>
          <input id="p-nom" v-model="form.nom" type="text" autocomplete="family-name" />
        </div>
        <div class="field">
          <label for="p-email">Email</label>
          <input id="p-email" v-model="form.email" type="email" autocomplete="email" />
        </div>
        <div class="field">
          <label for="p-tel">Téléphone</label>
          <input id="p-tel" v-model="form.telephone" type="tel" autocomplete="tel" />
        </div>
        <div class="field">
          <label for="p-adresse">Adresse</label>
          <textarea id="p-adresse" v-model="form.adresse" rows="3"></textarea>
        </div>
        <button type="submit" class="save-btn" :disabled="saving">
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
        <div v-if="message" :class="['msg', messageType]">{{ message }}</div>
      </form>
    </div>

    <div class="danger-card">
      <h2 class="danger-title">Supprimer mon compte</h2>
      <p class="danger-text">
        Vos informations personnelles sont anonymisées et vous ne pourrez plus
        vous connecter à cet espace. Vos rendez-vous et documents liés à
        l'atelier sont conservés pour ses obligations légales. Cette action
        est irréversible.
      </p>
      <button v-if="!confirmDelete" class="danger-btn" @click="confirmDelete = true">
        Supprimer mon compte
      </button>
      <div v-else class="danger-confirm">
        <p class="danger-confirm-text">Confirmer la suppression définitive de votre compte ?</p>
        <div style="display:flex;gap:8px;">
          <button class="danger-btn" :disabled="deleting" @click="deleteAccount">
            {{ deleting ? 'Suppression…' : 'Oui, supprimer' }}
          </button>
          <button class="danger-btn-cancel" :disabled="deleting" @click="confirmDelete = false">
            Annuler
          </button>
        </div>
      </div>
      <div v-if="deleteError" class="msg error">{{ deleteError }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const { apiFetch } = useClientApi()

const form = reactive({
  prenom: '',
  nom: '',
  email: '',
  telephone: '',
  adresse: '',
})

const saving = ref(false)
const message = ref('')
const messageType = ref('')

const confirmDelete = ref(false)
const deleting = ref(false)
const deleteError = ref('')

onMounted(async () => {
  await auth.fetchMe()
  if (auth.client) {
    form.prenom = auth.client.prenom || ''
    form.nom = auth.client.nom || ''
    form.email = auth.client.email || ''
    form.telephone = auth.client.telephone || ''
    form.adresse = auth.client.adresse || ''
  }
})

async function save() {
  if (saving.value) return // garde anti-double-soumission (le :disabled n'agit qu'au tick suivant)
  saving.value = true
  message.value = ''
  try {
    await apiFetch('/api/client/me', {
      method: 'PATCH',
      body: form,
    })
    await auth.fetchMe()
    message.value = 'Profil mis à jour.'
    messageType.value = 'success'
  } catch (e: any) {
    // Le message métier du backend est dans e.data.error ; e.message ne contient
    // qu'un libellé technique générique ([PATCH] "…": 400). Cohérent avec le
    // reste du portail (rdvs/[id].vue, forgot/reset-password).
    message.value = e?.data?.error || 'Erreur lors de la mise à jour.'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}

async function deleteAccount() {
  if (deleting.value) return
  deleting.value = true
  deleteError.value = ''
  try {
    await apiFetch('/api/client/archive', { method: 'POST' })
    await auth.logout()
    await navigateTo('/login')
  } catch (e: any) {
    deleteError.value = e?.data?.error || 'Impossible de supprimer le compte pour le moment.'
    deleting.value = false
  }
}
</script>

<style scoped>
.profil-card {
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 14px;
  padding: 24px;
  max-width: 480px;
  margin-bottom: 20px;
}
.profil-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
  margin-bottom: 6px;
}
.field input,
.field textarea {
  width: 100%;
  padding: 10px 12px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-size: 14px;
  resize: vertical;
}
.field input:focus,
.field textarea:focus {
  border-color: var(--accent-graphic);
}
.save-btn {
  padding: 12px;
  background: var(--accent);
  color: var(--accent-ink);
  border: none;
  border-radius: 8px;
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
}
.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.msg {
  font-size: 13px;
  padding: 8px 12px;
  border-radius: 6px;
}
.msg.success {
  background: var(--success-soft);
  color: var(--success-content);
}
.msg.error {
  background: var(--error-soft);
  color: var(--error-content);
}
.danger-card {
  background: var(--surface-1);
  border: 1px solid var(--error);
  border-radius: 14px;
  padding: 24px;
  max-width: 480px;
}
.danger-title {
  font-size: 15px;
  font-weight: 800;
  color: var(--error-content);
  margin: 0 0 8px;
}
.danger-text {
  font-size: 13px;
  color: var(--content-3);
  margin: 0 0 16px;
  line-height: 1.5;
}
.danger-btn {
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid var(--error);
  background: var(--error-soft);
  color: var(--error-content);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
.danger-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.danger-btn-cancel {
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid var(--border-1);
  background: var(--overlay-soft);
  color: var(--content-2);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.danger-confirm-text {
  font-size: 13px;
  color: var(--content-1);
  margin: 0 0 10px;
}
</style>
