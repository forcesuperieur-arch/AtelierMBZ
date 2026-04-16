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
  background: radial-gradient(ellipse at 50% 40%, #1a1530 0%, #0C0D12 60%, #080810 100%);
  position: relative;
  overflow: hidden;
}
.public-layout::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(600px circle at 50% 30%, rgba(245,158,11,0.06), transparent 60%),
    radial-gradient(400px circle at 30% 70%, rgba(139,92,246,0.04), transparent 60%);
  pointer-events: none;
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
  color: #6B7280;
}
.public-footer a {
  color: #9CA3AF;
  text-decoration: none;
}
.public-footer a:hover {
  color: #FFD200;
}
.public-footer span {
  margin: 0 8px;
}
</style>
