<template>
  <div class="login-box">
    <h2 class="login-title">Atelier Moto Pro</h2>
    <p class="login-sub">Connexion à votre espace atelier</p>

    <form @submit.prevent="handleLogin" class="login-form">
      <input
        v-model="email"
        type="email"
        placeholder="admin@atelier.local"
        required
        autofocus
      />
      <input
        v-model="password"
        type="password"
        placeholder="Mot de passe"
        required
      />
      <button type="submit" :disabled="loading">
        {{ loading ? 'Connexion...' : 'Se connecter' }}
      </button>
      <div v-if="error" class="login-error">{{ error }}</div>
    </form>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

const auth = useAuth()

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    navigateTo('/')
  } catch (e: any) {
    error.value = 'Email ou mot de passe incorrect'
  } finally {
    loading.value = false
  }
}
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

.login-form button {
  width: 100%;
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  border: none;
  border-radius: 10px;
  padding: 14px;
  font-family: inherit;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 4px;
  letter-spacing: 0.02em;
}
.login-form button:hover {
  background: linear-gradient(135deg, #FBBF24, #FFD200);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(245,158,11,0.25);
}
.login-form button:active { transform: translateY(0); }
.login-form button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

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
