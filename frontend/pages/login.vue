<template>
  <div class="login-box">
    <div class="login-brand">
      <img :src="brandLogo.horizontal.value" alt="Paddock" class="login-logo" />
    </div>
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
// Le mot-symbole est en blanc cassé : il faut la variante encrée en thème clair.
const brandLogo = useBrandLogo()

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
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 20px;
  padding: 44px 36px;
  width: min(400px, 100%);
  text-align: center;
  box-shadow: 0 12px 32px rgba(0,0,0,0.4), 0 0 80px rgba(217,101,0,0.05);
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
  /* Titre en encre franche : le dégradé clippé sur le texte n'existe pas au
     design system, et il rendait le titre illisible en thème clair. */
  color: var(--content-1);
  letter-spacing: -0.015em;
}

.login-sub {
  font-size: 14px;
  color: var(--content-3);
  margin-bottom: 28px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.login-form input {
  width: 100%;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 10px;
  padding: 14px 16px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 15px;
  margin-bottom: 12px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.login-form input:focus {
  border-color: var(--accent-graphic);
  box-shadow: 0 0 0 3px rgba(217,101,0,0.15);
}
.login-form input::placeholder { color: var(--content-3); }

.dev-sso-box {
  margin: 0 0 14px;
  padding: 12px;
  border: 1px solid var(--border-2);
  border-radius: 12px;
  background: var(--overlay-soft);
  text-align: left;
}

.dev-sso-title {
  color: var(--content-3);
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.dev-sso-box input {
  width: 100%;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 10px;
  padding: 10px 12px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 14px;
  margin-bottom: 10px;
  outline: none;
}

.dev-sso-name-grid {
  display: grid;
  grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
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
  background: var(--surface-1);
  color: var(--accent-ink);
  margin-bottom: 14px;
}

.dev-btn {
  flex: 1;
  background: var(--overlay-hover);
  color: var(--content-1);
}

.dev-btn.secondary {
  background: var(--accent-soft);
  color: var(--accent-content);
}
.google-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(255,255,255,0.12);
}

.login-divider {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--content-3);
  font-size: 12px;
  margin: 4px 0 14px;
}

.login-divider span {
  padding: 0 10px;
}

/* Bouton primaire : aplat de marque, encre noire, états par la teinte. */
.login-form button {
  background: var(--accent);
  color: var(--accent-ink);
  margin-top: 4px;
}
.login-form button:hover {
  background: var(--accent-hover);
}
.login-form button:active {
  background: var(--accent-active);
}
.google-btn:disabled,
.login-form button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.login-info {
  color: var(--info-content);
  font-size: 13px;
  margin-top: 12px;
}

.login-error {
  color: var(--error-content);
  font-size: 13px;
  margin-top: 12px;
  min-height: 18px;
}

@keyframes loginFadeIn {
  from { opacity: 0; transform: translateY(16px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
