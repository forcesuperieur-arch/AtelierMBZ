<template>
  <div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:16px;">Mon profil</h1>
    <form @submit.prevent="save" class="profil-form">
      <div class="field">
        <label>Prénom</label>
        <input v-model="form.prenom" type="text" />
      </div>
      <div class="field">
        <label>Nom</label>
        <input v-model="form.nom" type="text" />
      </div>
      <div class="field">
        <label>Email</label>
        <input v-model="form.email" type="email" />
      </div>
      <div class="field">
        <label>Téléphone</label>
        <input v-model="form.telephone" type="tel" />
      </div>
      <div class="field">
        <label>Adresse</label>
        <textarea v-model="form.adresse" rows="3"></textarea>
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
  saving.value = true
  message.value = ''
  try {
    await $fetch('/api/client/me', {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${auth.accessToken}` },
      body: form,
      baseURL: '',
    })
    await auth.fetchMe()
    message.value = 'Profil mis à jour.'
    messageType.value = 'success'
  } catch (e: any) {
    message.value = e?.message || 'Erreur lors de la mise à jour.'
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
