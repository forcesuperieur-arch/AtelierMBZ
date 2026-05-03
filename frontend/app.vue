<template>
  <div class="dark">
    <NuxtLayout>
      <NuxtErrorBoundary>
        <NuxtPage />
        <template #error="{ error, clearError }">
          <div class="error-boundary-fallback">
            <div class="error-boundary-content">
              <div class="error-boundary-icon">⚠️</div>
              <h1 class="error-boundary-title">Oups, quelque chose s'est mal passé</h1>
              <p class="error-boundary-desc">
                Une erreur inattendue a empêché cette page de s'afficher correctement.
              </p>
              <div class="error-boundary-actions">
                <button class="error-boundary-btn btn-primary" @click="clearError">
                  Réessayer
                </button>
                <NuxtLink to="/" class="error-boundary-btn btn-ghost" @click="clearError">
                  Retour à l'accueil
                </NuxtLink>
              </div>
              <pre v-if="process.dev" class="error-boundary-trace">{{ error }}</pre>
            </div>
          </div>
        </template>
      </NuxtErrorBoundary>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
const colorMode = useColorMode()
colorMode.preference = 'dark'
colorMode.value = 'dark'
</script>

<style>
.error-boundary-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 24px;
  background: #0b0d12;
}
.error-boundary-content {
  max-width: 480px;
  text-align: center;
}
.error-boundary-icon {
  font-size: 48px;
  margin-bottom: 16px;
}
.error-boundary-title {
  font-size: 20px;
  font-weight: 700;
  color: #E8E9ED;
  margin-bottom: 8px;
}
.error-boundary-desc {
  font-size: 14px;
  color: #9CA3AF;
  margin-bottom: 24px;
}
.error-boundary-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}
.error-boundary-btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
}
.error-boundary-btn.btn-primary {
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  border: none;
}
.error-boundary-btn.btn-ghost {
  background: transparent;
  color: #9CA3AF;
  border: 1px solid rgba(255,255,255,0.1);
}
.error-boundary-trace {
  margin-top: 24px;
  padding: 16px;
  border-radius: 8px;
  background: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.2);
  color: #FCA5A5;
  font-size: 12px;
  text-align: left;
  overflow-x: auto;
}
</style>
