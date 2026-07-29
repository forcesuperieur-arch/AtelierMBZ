<template>
  <div class="public-layout">
    <div class="public-content">
      <slot />
    </div>
    <footer class="public-footer">
      <NuxtLink to="/public/mentions-legales">Mentions légales</NuxtLink>
      <span>|</span>
      <NuxtLink to="/public/politique-confidentialite">Politique de confidentialité</NuxtLink>
    </footer>
  </div>
</template>

<style scoped>
.public-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  width: 100%;
  /* Le design system pose des fonds PROPRES : « No gradients in the system ».
     Les trois lueurs jaunes superposées ici délavaient le thème clair. */
  background: var(--surface-0);
  position: relative;
  overflow: hidden;
}
.public-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
}
.public-footer {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 16px;
  font-size: 12px;
  color: var(--content-3);
}
.public-footer a {
  color: var(--content-3);
  text-decoration: none;
}
.public-footer a:hover {
  color: var(--accent-content);
}
.public-footer span {
  margin: 0 8px;
}
</style>
