<template>
  <div class="login-page">
    <div class="login-card">
      <img src="/branding/paddock-logo-stacked.svg" alt="Paddock" style="width:72px;height:72px;margin:0 auto 12px;display:block;" />
      <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">Espace Client</h1>
      <p style="font-size:13px;color:#9CA3AF;margin-bottom:20px;">Connexion à votre espace client</p>

      <form @submit.prevent="handleLogin">
        <div class="login-field">
          <label>Email</label>
          <input v-model="email" type="email" required placeholder="Email" />
        </div>
        <div class="login-field">
          <label>Mot de passe</label>
          <input v-model="password" type="password" required placeholder="••••••••" />
        </div>
        <button type="submit" class="login-btn" :disabled="loading">
          {{ loading ? 'Connexion…' : 'Se connecter' }}
        </button>
        <div v-if="error" class="login-error">{{ error }}</div>
      </form>
      <NuxtLink to="/forgot-password" style="font-size:13px;color:#9CA3AF;margin-top:16px;display:inline-block;">
        Activer mon compte / Mot de passe oublié
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''
  const ok = await auth.login(email.value, password.value)
  loading.value = false
  if (ok) {
    await navigateTo('/')
  } else {
    error.value = 'Email ou mot de passe incorrect'
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(ellipse at 50% 30%, #1a2035 0%, #0C0D12 50%, #080810 100%);
  color: #E8E9ED;
}
.login-card {
  width: 100%;
  max-width: 360px;
  padding: 32px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(59,130,246,0.18);
  border-radius: 16px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.login-field {
  text-align: left;
  margin-bottom: 14px;
}
.login-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #9CA3AF;
  margin-bottom: 6px;
}
.login-field input {
  width: 100%;
  padding: 10px 12px;
  background: rgba(0,0,0,0.25);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: #E8E9ED;
  font-size: 14px;
  outline: none;
}
.login-field input:focus {
  border-color: #3B82F6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.login-btn {
  width: 100%;
  padding: 12px;
  background: linear-gradient(135deg, #3B82F6, #2563EB);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  margin-top: 8px;
  transition: all 0.15s;
}
.login-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, #60A5FA, #3B82F6);
  transform: translateY(-1px);
}
.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.login-error {
  margin-top: 12px;
  font-size: 13px;
  color: #FCA5A5;
}
</style>
