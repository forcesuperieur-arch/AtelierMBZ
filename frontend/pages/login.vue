<template>
  <div class="login-box">
    <div class="login-brand">
      <img src="/branding/paddock-logo-horizontal.svg" alt="Paddock" class="login-logo" />
    </div>
    <h2 class="login-title">Paddock</h2>
    <p class="login-sub">Connexion à votre espace atelier</p>

    <form @submit.prevent="handleLogin" class="login-form">
      <input
        v-model="email"
        type="email"
        placeholder="admin@atelier.local"
        autocomplete="username"
        required
        autofocus
      />
      <input
        v-model="password"
        type="password"
        placeholder="Mot de passe"
        autocomplete="current-password"
        required
      />
      <button type="submit" :disabled="loading || googleLoading">
        {{ loading ? 'Connexion...' : 'Se connecter' }}
      </button>
    </form>

    <div v-if="info" class="login-info">{{ info }}</div>
    <div v-if="error" class="login-error">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const route = useRoute()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const info = ref('')

const auth = useAuth()

function formatAuthError(e: any) {
  const message = String(e?.message || '')

  if (/pending admin validation/i.test(message)) {
    return 'Votre compte Google a bien été créé, mais il doit encore être validé par un administrateur.'
  }
  if (/atelier assignment pending/i.test(message)) {
    return 'Votre compte est validé, mais l’atelier n’a pas encore été attribué.'
  }
  if (/account disabled|disabled/i.test(message)) {
    return 'Votre compte est désactivé.'
  }
  if (/invalid credentials/i.test(message)) {
    return 'Email ou mot de passe incorrect.'
  }
  return message || 'Connexion impossible.'
}

async function handleLogin() {
  loading.value = true
  error.value = ''
  info.value = ''
  try {
    await auth.login(email.value, password.value)
    await navigateTo('/')
  } catch (e: any) {
    error.value = formatAuthError(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // Clean up any OAuth query params on load
  if (process.client && (route.query.code || route.query.error || route.query.google_status)) {
    window.history.replaceState({}, '', '/login')
  }
})
</script>

<style scoped>
.login-box {
  background: rgba(19,20,27,0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 20px;
  padding: 44px 36px;
  width: 400px;
  text-align: center;
  box-shadow: 0 12px 32px rgba(0,0,0,0.4), 0 0 80px rgba(245,158,11,0.05);
  animation: loginFadeIn 0.6s ease;
  position: relative;
  z-index: 1;
}

.login-brand {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.login-logo {
  width: min(100%, 280px);
  height: auto;
  display: block;
}

.login-title {
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 6px;
  background: linear-gradient(135deg, #FFD200, #FBBF24);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}

.login-sub {
  font-size: 14px;
  color: #6B7280;
  margin-bottom: 28px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.login-form input {
  width: 100%;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  padding: 14px 16px;
  color: #eee;
  font-family: inherit;
  font-size: 15px;
  margin-bottom: 12px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.login-form input:focus {
  border-color: #FFD200;
  box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
}
.login-form input::placeholder { color: #555; }

.dev-sso-box {
  margin: 0 0 14px;
  padding: 12px;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  text-align: left;
}

.dev-sso-title {
  color: #9CA3AF;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.dev-sso-box input {
  width: 100%;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  padding: 10px 12px;
  color: #eee;
  font-family: inherit;
  font-size: 14px;
  margin-bottom: 10px;
  outline: none;
}

.dev-sso-name-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 10px;
}

.dev-sso-actions {
  display: flex;
  gap: 8px;
}

.google-btn,
.login-form button,
.dev-btn {
  width: 100%;
  border: none;
  border-radius: 10px;
  padding: 14px;
  font-family: inherit;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 0.02em;
}

.google-btn {
  background: #fff;
  color: #111;
  margin-bottom: 14px;
}

.dev-btn {
  flex: 1;
  background: rgba(255,255,255,0.08);
  color: #E5E7EB;
}

.dev-btn.secondary {
  background: rgba(255,210,0,0.12);
  color: #FFD200;
}
.google-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(255,255,255,0.12);
}

.login-divider {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6B7280;
  font-size: 12px;
  margin: 4px 0 14px;
}

.login-divider span {
  padding: 0 10px;
}

.login-form button {
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  margin-top: 4px;
}
.login-form button:hover {
  background: linear-gradient(135deg, #FBBF24, #FFD200);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(245,158,11,0.25);
}
.login-form button:active { transform: translateY(0); }
.google-btn:disabled,
.login-form button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.login-info {
  color: #93C5FD;
  font-size: 13px;
  margin-top: 12px;
}

.login-error {
  color: #EF4444;
  font-size: 13px;
  margin-top: 12px;
  min-height: 18px;
}

@keyframes loginFadeIn {
  from { opacity: 0; transform: translateY(16px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
