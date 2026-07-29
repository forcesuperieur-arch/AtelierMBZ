<template>
  <footer class="legal-footer">
    <NuxtLink to="/mentions-legales">Mentions légales</NuxtLink>
    <span>|</span>
    <NuxtLink to="/politique-confidentialite">Politique de confidentialité</NuxtLink>
  </footer>
</template>

<style scoped>
.legal-footer {
  text-align: center;
  padding: 16px;
  font-size: 12px;
  color: var(--content-3);
}
.legal-footer a {
  color: var(--content-3);
  text-decoration: none;
}
.legal-footer a:hover {
  color: var(--accent-content);
}
.legal-footer span {
  margin: 0 8px;
}
</style>
