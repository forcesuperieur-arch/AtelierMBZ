<template>
  <div class="landing-page">
    <!-- `layout: false` : la bascule de thème de la mise en page ne s'y
         monte pas, il faut la poser ici (même pattern que login.vue). -->
    <ThemeToggle floating />

    <section class="hero">
      <img :src="logo" alt="Paddock" class="hero-logo" />
      <p class="hero-tagline">Votre atelier moto en ligne</p>

      <div class="hero-actions">
        <!--
          Prise de RDV : page publique servie par le front STAFF (nuxt:3000),
          une application Nuxt séparée de ce portail (client-nuxt:3001). Un
          <NuxtLink> ferait une navigation SPA interne à CE front, qui n'a pas
          cette route → 404 silencieux. Ancre native pour forcer une vraie
          navigation HTTP, que Caddy route vers la bonne app.
        -->
        <a href="/public/booking" class="hero-btn hero-btn-primary">
          <AppIcon name="i-ri-calendar-line" />
          Prendre un rendez-vous
        </a>
        <NuxtLink to="/login" class="hero-btn hero-btn-secondary">
          <AppIcon name="i-ri-user-line" />
          Accéder à mon espace client
        </NuxtLink>
      </div>
    </section>

    <section class="features">
      <div v-for="f in features" :key="f.title" class="feature-card">
        <AppIcon :name="f.icon" class="feature-icon" />
        <h2 class="feature-title">{{ f.title }}</h2>
        <p class="feature-text">{{ f.text }}</p>
      </div>
    </section>

    <LegalFooter />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const { stacked: logo } = useBrandLogo()

const features = [
  {
    icon: 'i-ri-calendar-line',
    title: 'Prise de rendez-vous en ligne',
    text: 'Réservez un créneau atelier en quelques clics, à l’heure qui vous convient.',
  },
  {
    icon: 'i-ri-radar-line',
    title: 'Suivi en temps réel',
    text: 'Suivez l’avancement de votre intervention, de la réception à la restitution.',
  },
  {
    icon: 'i-ri-folder-history-line',
    title: 'Historique & documents',
    text: 'Retrouvez vos motos, vos interventions passées et vos documents dans votre espace client.',
  },
  {
    icon: 'i-ri-notification-3-line',
    title: 'Notifications automatiques',
    text: 'Confirmations et rappels par email ou SMS, sans rien à faire.',
  },
]
</script>

<style scoped>
.landing-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 56px 24px 24px;
  background:
    radial-gradient(700px 360px at 50% 0%, var(--accent-soft), transparent 70%),
    repeating-linear-gradient(135deg, var(--overlay-soft) 0 2px, transparent 2px 6px),
    var(--surface-0);
  color: var(--content-1);
}

.hero {
  text-align: center;
  max-width: 420px;
  width: 100%;
}
.hero-logo {
  width: min(100%, 240px);
  height: auto;
  display: block;
  margin: 0 auto 16px;
}
.hero-tagline {
  font-size: 15px;
  color: var(--content-3);
  margin: 0 0 32px;
}
.hero-actions {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hero-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  border-radius: 9px;
  font-weight: 700;
  font-size: 15px;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s var(--pad-ease, ease), border-color 0.15s var(--pad-ease, ease);
}
.hero-btn-primary {
  background: var(--accent);
  color: var(--accent-ink);
}
.hero-btn-primary:hover {
  background: var(--accent-hover);
}
.hero-btn-secondary {
  background: var(--overlay-hover);
  color: var(--content-1);
  border: 1px solid var(--border-1);
}
.hero-btn-secondary:hover {
  background: var(--overlay-soft);
}

.features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  max-width: 920px;
  width: 100%;
  margin: 56px 0 40px;
}
.feature-card {
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 14px;
  padding: 24px 20px;
  text-align: left;
}
.feature-icon {
  width: 28px;
  height: 28px;
  color: var(--accent-content);
  margin-bottom: 12px;
}
.feature-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 6px;
}
.feature-text {
  font-size: 13px;
  color: var(--content-3);
  margin: 0;
  line-height: 1.5;
}

@media (max-width: 640px) {
  .landing-page {
    padding: 40px 16px 16px;
  }
}
</style>
