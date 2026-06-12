<template>
  <div class="client-layout">
    <nav v-if="auth.isAuthenticated" class="client-nav">
      <div class="nav-brand">
        <LogoIcon style="vertical-align:middle;margin-right:8px;" />
        <span class="brand-text">Mon Atelier</span>
      </div>
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
  color: var(--pad-text, #E8E9ED);
}

/* Barre de nav : surface translucide + bande de course en tête */
.client-nav {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 14px 28px;
  background: rgba(16, 17, 23, 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--pad-border, rgba(255,255,255,0.07));
}
.client-nav::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  height: 3px;
  width: 140px;
  background: linear-gradient(90deg, #FFD200 60%, transparent);
  clip-path: polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
}

.nav-brand {
  display: flex;
  align-items: center;
  color: #FFD200;
}
.brand-text {
  font-family: var(--pad-font-display, sans-serif);
  font-weight: 800;
  font-size: 20px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.nav-links {
  display: flex;
  gap: 20px;
  align-items: center;
  font-size: 14px;
  margin-left: 24px;
  flex-wrap: wrap;
}

/* Liens : soulignement course qui se déploie */
.nav-links a {
  position: relative;
  color: #9CA3AF;
  text-decoration: none;
  padding: 4px 0;
  font-weight: 500;
  transition: color 0.18s;
}
.nav-links a::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: -2px;
  height: 2px;
  width: 100%;
  background: #FFD200;
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.22s var(--pad-ease, ease-out);
}
.nav-links a:hover, .nav-links a.router-link-active {
  color: #FFD200;
}
.nav-links a.router-link-active::after,
.nav-links a:hover::after {
  transform: scaleX(1);
}

.nav-logout {
  background: none;
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #FCA5A5;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.15s, border-color 0.15s;
}
.nav-logout:hover {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.5);
}

.client-main {
  max-width: 960px;
  margin: 0 auto;
  padding: 28px 24px 56px;
}
</style>
