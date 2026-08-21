<template>
  <div class="login-page">
    <!-- Ces pages sont en `layout: false` : la bascule de thème de la mise en
         page ne s'y monte pas, il faut la poser ici. -->
    <ThemeToggle floating />
    <div class="login-card">
      <LogoIcon style="margin-bottom:8px;color:var(--accent-content);" />
      <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">Mon Atelier</h1>
      <p style="font-size:13px;color:var(--content-3);margin-bottom:20px;">Espace client</p>

      <form @submit.prevent="handleSubmit">
        <div class="login-field">
          <label>Email</label>
          <input v-model="email" type="email" required placeholder="votre@email.fr" />
        </div>
        <button type="submit" class="login-btn" :disabled="loading">
          {{ loading ? 'Envoi…' : 'Envoyer le lien' }}
        </button>
        <div v-if="message" :class="['msg', messageType]">{{ message }}</div>
      </form>

      <NuxtLink to="/login" style="font-size:13px;color:var(--content-3);margin-top:16px;display:inline-block;">
        <AppIcon name="i-ri-arrow-left-line" /> Retour à la connexion
      </NuxtLink>
    </div>
    <LegalFooter />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const email = ref('')
const loading = ref(false)
const message = ref('')
const messageType = ref('')

async function handleSubmit() {
  loading.value = true
  message.value = ''
  try {
    await $fetch('/api/client/forgot-password', {
      method: 'POST',
      body: { email: email.value },
      baseURL: '',
    })
    message.value = 'Si un compte existe avec cet email, un lien vous a été envoyé.'
    messageType.value = 'success'
  } catch (e: any) {
    message.value = e?.data?.error || 'Erreur lors de l\'envoi.'
    messageType.value = 'error'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  background: var(--surface-0);
  color: var(--content-1);
}
.login-card {
  width: 100%;
  max-width: 360px;
  padding: 32px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  border-radius: 16px;
  text-align: center;
}
.login-field {
  text-align: left;
  margin-bottom: 14px;
}
.login-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
  margin-bottom: 6px;
}
.login-field input {
  width: 100%;
  padding: 10px 12px;
  background: rgba(0,0,0,0.25);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-size: 14px;
  }
.login-field input:focus {
  border-color: var(--accent-graphic);
}
.login-btn {
  width: 100%;
  padding: 12px;
  background: var(--accent);
  color: var(--accent-ink);
  border: none;
  border-radius: 8px;
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  margin-top: 8px;
}
.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.msg {
  margin-top: 12px;
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
</style>
