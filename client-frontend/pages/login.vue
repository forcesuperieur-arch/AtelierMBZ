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
  padding: 24px;
  background:
    radial-gradient(700px 360px at 50% 18%, rgba(255, 210, 0, 0.08), transparent 70%),
    repeating-linear-gradient(135deg, rgba(255,255,255,0.012) 0 2px, transparent 2px 6px),
    #0A0B0F;
  color: #E8E9ED;
}
.login-card {
  position: relative;
  width: 100%;
  max-width: 380px;
  padding: 40px 32px 32px;
  background: linear-gradient(180deg, #1A1D26, #14161D);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  text-align: center;
  box-shadow: 0 16px 48px rgba(0,0,0,0.55);
  overflow: hidden;
  animation: card-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}
/* Bande de course en tête de carte */
.login-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0;
  height: 4px; width: 96px;
  background: linear-gradient(90deg, #FFD200 70%, transparent);
  clip-path: polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
}
.login-card h1 {
  font-family: var(--pad-font-display, sans-serif);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
@keyframes card-in {
  from { opacity: 0; transform: translateY(14px) scale(0.99); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.login-field {
  text-align: left;
  margin-bottom: 14px;
}
.login-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #9CA3AF;
  margin-bottom: 6px;
}
.login-field input {
  width: 100%;
  padding: 11px 13px;
  background: rgba(0,0,0,0.3);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 9px;
  color: #E8E9ED;
  font-size: 14px;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.login-field input:focus {
  border-color: rgba(255, 210, 0, 0.6);
  box-shadow: 0 0 0 3px rgba(255, 210, 0, 0.12);
}
.login-btn {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, #FFD200, #F0B90B);
  color: #14161D;
  border: none;
  border-radius: 9px;
  font-family: var(--pad-font-display, sans-serif);
  font-weight: 800;
  font-size: 15px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  cursor: pointer;
  margin-top: 8px;
  transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
}
.login-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 22px rgba(255, 210, 0, 0.3);
  filter: brightness(1.04);
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
