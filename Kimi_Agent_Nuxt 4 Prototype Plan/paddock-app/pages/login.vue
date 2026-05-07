<template>
  <div>
    <NuxtLayout name="auth">
      <div class="bg-white rounded-xl border border-border-light shadow-card p-8">
        <div class="text-center mb-8">
          <div class="text-4xl mb-3">🏍️</div>
          <h1 class="text-xl font-bold text-text-primary">Atelier Paddock</h1>
          <p class="text-sm text-text-secondary mt-1">Connectez-vous à votre atelier</p>
        </div>

        <form class="space-y-4" @submit.prevent="handleLogin">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">Email</label>
            <input
              v-model="email"
              type="email"
              class="w-full rounded-lg border border-border-light bg-body-page px-4 py-2.5 text-sm text-text-primary focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-all"
              placeholder="mecano@paddock.fr"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">Mot de passe</label>
            <input
              v-model="password"
              type="password"
              class="w-full rounded-lg border border-border-light bg-body-page px-4 py-2.5 text-sm text-text-primary focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-all"
              placeholder="••••••••"
            />
          </div>

          <PaddockButton variant="primary" class="w-full justify-center py-3" type="submit">
            Se connecter
          </PaddockButton>
        </form>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

definePageMeta({ layout: false })

const router = useRouter()
const email = ref('')
const password = ref('')

function handleLogin() {
  // Simuler un flow 2FA : rediriger vers /2fa au lieu de /
  router.push('/2fa')
}
</script>
