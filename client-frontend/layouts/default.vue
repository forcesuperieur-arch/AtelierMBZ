<template>
  <div class="client-layout">
    <nav v-if="auth.isAuthenticated" class="client-nav">
      <div class="nav-brand"><LogoIcon style="vertical-align:middle;margin-right:6px;" /> Mon Atelier</div>
      <div class="nav-links">
        <NuxtLink to="/">Tableau de bord</NuxtLink>
        <NuxtLink to="/rdvs">Mes RDV</NuxtLink>
        <NuxtLink to="/historique">Historique</NuxtLink>
        <NuxtLink to="/motos">Mes motos</NuxtLink>
        <NuxtLink to="/profil">Mon profil</NuxtLink>
        <button class="nav-logout" @click="auth.logout">Déconnexion</button>
      </div>
    </nav>
    <main class="client-main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
</script>

<style>
.client-layout {
  min-height: 100vh;
  background: #0C0D12;
  color: #E8E9ED;
  font-family: system-ui, -apple-system, sans-serif;
}
.client-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 24px;
  background: rgba(255,255,255,0.03);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.nav-brand {
  font-weight: 800;
  font-size: 18px;
  color: #FFD200;
}
.nav-links {
  display: flex;
  gap: 16px;
  align-items: center;
  font-size: 14px;
  margin-left: 24px;
}
.nav-links a {
  color: #9CA3AF;
  text-decoration: none;
  transition: color 0.2s;
}
.nav-links a:hover, .nav-links a.router-link-active {
  color: #FFD200;
}
.nav-logout {
  background: none;
  border: 1px solid rgba(239,68,68,0.3);
  color: #FCA5A5;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
}
.client-main {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px;
}
</style>
