<template>
  <div class="w-full max-w-md mx-auto">
    <UCard class="border border-white/10 shadow-2xl">
      <template #header>
        <div class="text-center space-y-2">
          <div class="mx-auto h-12 w-12 rounded-xl bg-gradient-to-br from-yellow-300 to-amber-500 text-black font-black grid place-items-center">A</div>
          <h1 class="text-2xl font-black text-white tracking-tight">Atelier Moto Pro</h1>
          <p class="text-sm text-gray-300">Connexion a votre espace atelier</p>
        </div>
      </template>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <UFormField label="Email" class="text-gray-200">
          <UInput
            v-model="email"
            type="email"
            placeholder="admin@atelier.local"
            icon="i-heroicons-envelope"
            required
            size="xl"
          />
        </UFormField>

        <UFormField label="Mot de passe" class="text-gray-200">
          <UInput
            v-model="password"
            type="password"
            placeholder="••••••••"
            icon="i-heroicons-lock-closed"
            required
            size="xl"
          />
        </UFormField>

        <UButton type="submit" block :loading="loading" label="Se connecter" color="primary" size="xl" />

        <p v-if="error" class="text-sm text-red-400 text-center">{{ error }}</p>
      </form>
    </UCard>
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
