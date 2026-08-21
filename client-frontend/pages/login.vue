<template>
  <div class="login-page">
    <!-- Ces pages sont en `layout: false` : la bascule de thème de la mise en
         page ne s'y monte pas, il faut la poser ici. -->
    <ThemeToggle floating />
    <div class="login-card">
      <img :src="logo" alt="Paddock" class="login-logo" />
      <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">Espace Client</h1>
      <p style="font-size:13px;color:var(--content-3);margin-bottom:20px;">Connexion à votre espace client</p>

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
      <NuxtLink to="/forgot-password" style="font-size:13px;color:var(--content-3);margin-top:16px;display:inline-block;">
        Activer mon compte / Mot de passe oublié
      </NuxtLink>
    </div>
    <LegalFooter />
  </div>
</template>

<script setup lang="ts">
const { stacked: logo } = useBrandLogo()

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
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 24px;
  background:
    radial-gradient(700px 360px at 50% 18%, var(--accent-soft), transparent 70%),
    repeating-linear-gradient(135deg, var(--overlay-soft) 0 2px, transparent 2px 6px),
    var(--surface-0);
  color: var(--content-1);
}
.login-logo {
  width: min(100%, 220px);
  height: auto;
  margin: 0 auto 12px;
  display: block;
}
.login-card {
  position: relative;
  width: 100%;
  max-width: 380px;
  padding: 40px 32px 32px;
  background: linear-gradient(180deg, var(--surface-2), var(--surface-1));
  border: 1px solid var(--border-2);
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
  background: linear-gradient(90deg, var(--accent) 70%, transparent);
  clip-path: polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
}
.login-card h1 {
  font-family: var(--pad-font-display, sans-serif);
  letter-spacing: 0.05em;
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
  color: var(--content-3);
  margin-bottom: 6px;
}
.login-field input {
  width: 100%;
  padding: 11px 13px;
  background: rgba(0,0,0,0.3);
  border: 1px solid var(--border-1);
  border-radius: 9px;
  color: var(--content-1);
  font-size: 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.login-field input:focus {
  border-color: var(--accent-graphic);
  box-shadow: 0 0 0 3px rgba(241,171,0,0.12);
}
.login-btn {
  width: 100%;
  padding: 13px;
  /* Aplat franc : le design system n'a aucun dégradé. Encre noire sur le
     jaune de marque, comme le prescrit `.mb-btn--accent`. */
  background: var(--accent);
  color: var(--accent-ink);
  border: none;
  border-radius: 9px;
  font-family: var(--pad-font-display, sans-serif);
  font-weight: 800;
  font-size: 15px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  cursor: pointer;
  margin-top: 8px;
  transition: background var(--dur-fast) var(--ease);
}
/* Le DS obtient ses états par la TEINTE, sans déplacement ni halo. */
.login-btn:hover:not(:disabled) {
  background: var(--accent-hover);
}
.login-btn:active:not(:disabled) {
  background: var(--accent-active);
}
.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.login-error {
  margin-top: 12px;
  font-size: 13px;
  color: var(--error-content);
}
</style>
