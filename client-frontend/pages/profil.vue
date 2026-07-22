<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mon profil</h1>
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
</script>

<style scoped>
.profil-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 480px;
}
.field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #9CA3AF;
  margin-bottom: 6px;
}
.field input,
.field textarea {
  width: 100%;
  padding: 10px 12px;
  background: rgba(0,0,0,0.25);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: #E8E9ED;
  font-size: 14px;
  outline: none;
  resize: vertical;
}
.field input:focus,
.field textarea:focus {
  border-color: #FFD200;
}
.save-btn {
  padding: 12px;
  background: #FFD200;
  color: #111;
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
  background: rgba(34,197,94,0.15);
  color: #4ADE80;
}
.msg.error {
  background: rgba(239,68,68,0.15);
  color: #FCA5A5;
}
</style>
