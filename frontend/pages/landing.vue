<template>
  <div class="landing-page">
    <div class="landing-card">
      <div class="landing-brand">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#FFD200" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="5.5" cy="17.5" r="3.5"/>
          <circle cx="18.5" cy="17.5" r="3.5"/>
          <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 2h2"/>
        </svg>
        <h1>Mon Atelier</h1>
        <p>Votre atelier moto en ligne</p>
      </div>

      <div class="landing-actions">
        <NuxtLink to="/public/booking" class="landing-btn landing-btn-primary">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          Prendre un rendez-vous
        </NuxtLink>
        <NuxtLink to="/client" class="landing-btn landing-btn-secondary">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Accéder à mon espace client
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({ layout: false })
</script>

<style scoped>
.landing-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0C0D12;
  color: #E8E9ED;
  font-family: system-ui, -apple-system, sans-serif;
}
.landing-card {
  text-align: center;
  padding: 48px 32px;
  max-width: 420px;
  width: 100%;
}
.landing-brand {
  margin-bottom: 48px;
}
.landing-brand h1 {
  font-size: 28px;
  font-weight: 800;
  margin: 16px 0 8px;
  color: #FFD200;
}
.landing-brand p {
  font-size: 15px;
  color: #9CA3AF;
  margin: 0;
}
.landing-actions {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.landing-btn {
  display: block;
  padding: 14px 20px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  text-decoration: none;
  text-align: center;
  transition: transform 0.15s, opacity 0.15s;
  cursor: pointer;
}
.landing-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}
.landing-btn-primary {
  background: #FFD200;
  color: #111;
}
.landing-btn-secondary {
  background: rgba(255,255,255,0.06);
  color: #E8E9ED;
  border: 1px solid rgba(255,255,255,0.1);
}
</style>
