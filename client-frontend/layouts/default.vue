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
        <ThemeToggle />
        <button class="nav-logout" @click="auth.logout">Déconnexion</button>
      </div>
    </nav>
    <!-- Hors session (connexion, réinitialisation) la barre de navigation est
         masquée : le réglage de thème reste joignable en coin d'écran. -->
    <ThemeToggle v-else floating />
    <main class="client-main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

// Monte la gestion de thème au niveau de la mise en page : c'est elle qui
// applique l'attribut `data-theme` et suit la préférence système.
useTheme()
</script>

<style>
.client-layout {
  min-height: 100vh;
  color: var(--pad-text, var(--content-1));
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
  background: var(--surface-1);
  border-bottom: 1px solid var(--pad-border, var(--border-2));
}
.client-nav::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  height: 3px;
  width: 140px;
  background: linear-gradient(90deg, var(--accent) 60%, transparent);
  clip-path: polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
}

.nav-brand {
  display: flex;
  align-items: center;
  color: var(--accent-content);
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
  color: var(--content-3);
  text-decoration: none;
  padding: 10px 4px;
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
  background: var(--accent);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.22s var(--pad-ease, ease-out);
}
.nav-links a:hover, .nav-links a.router-link-active {
  color: var(--accent-content);
}
.nav-links a.router-link-active::after,
.nav-links a:hover::after {
  transform: scaleX(1);
}

.nav-logout {
  background: none;
  border: 1px solid var(--error);
  color: var(--error-content);
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.15s, border-color 0.15s;
}
.nav-logout:hover {
  background: var(--error-soft);
  border-color: var(--error);
}

.client-main {
  max-width: 960px;
  margin: 0 auto;
  padding: 28px 24px 56px;
}

/* Mobile : un client consulte surtout son RDV sur téléphone. */
@media (max-width: 640px) {
  .client-nav {
    padding: 12px 16px;
    align-items: flex-start;
  }
  .nav-links {
    margin-left: 0;
    width: 100%;
    gap: 4px 16px;
  }
  .brand-text {
    font-size: 17px;
  }
  .client-main {
    padding: 20px 16px 48px;
  }
}
</style>
