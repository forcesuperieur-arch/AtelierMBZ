<template>
  <div class="landing-card">
    <div class="landing-brand">
      <img :src="brandLogo.stacked.value" alt="Paddock" class="landing-logo" />
      <p class="landing-sub">Votre atelier moto en ligne</p>
    </div>

    <div class="landing-actions">
      <AppButton to="/public/booking" variant="primary" icon="i-ri-calendar-line" block>
        Prendre un rendez-vous
      </AppButton>
      <!--
        Lien vers le portail client (application Nuxt séparée, servie sur
        `/client/*` par un autre conteneur — voir Caddyfile). Un <NuxtLink>
        ferait une navigation SPA à l'intérieur du front STAFF, qui n'a pas
        cette route : middleware d'auth → redirection vers /login. D'où une
        ancre native, classes `.btn` posées à la main pour rester identique à
        AppButton.
      -->
      <a href="/client" class="btn btn-secondary btn-block">
        <AppIcon name="i-ri-user-line" />
        Accéder à mon espace client
      </a>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
const brandLogo = useBrandLogo()
</script>

<style scoped>
.landing-card {
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 20px;
  padding: 44px 36px;
  width: min(420px, 100%);
  text-align: center;
  box-shadow: 0 12px 32px rgba(0,0,0,0.4);
}
.landing-brand {
  margin-bottom: 36px;
}
.landing-logo {
  width: min(100%, 320px);
  height: auto;
  display: block;
  margin: 0 auto 14px;
}
.landing-sub {
  font-size: 14px;
  color: var(--content-3);
  margin: 0;
}
.landing-actions {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
</style>
