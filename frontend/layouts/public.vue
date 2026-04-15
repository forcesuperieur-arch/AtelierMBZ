<template>
  <div class="login-screen">
    <slot />
  </div>
</template>

<style scoped>
.login-screen {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  width: 100%;
  background: radial-gradient(ellipse at 50% 40%, #1a1530 0%, #0C0D12 60%, #080810 100%);
  position: relative;
  overflow: hidden;
}
.login-screen::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(600px circle at 50% 30%, rgba(245,158,11,0.06), transparent 60%),
    radial-gradient(400px circle at 30% 70%, rgba(139,92,246,0.04), transparent 60%);
  pointer-events: none;
}
</style>
