<template>
  <div class="min-h-screen bg-body-page">
    <header class="h-16 bg-header flex items-center px-6 lg:px-8 sticky top-0 z-50">
      <h1 class="text-white font-semibold text-lg tracking-tight">Atelier Paddock</h1>
    </header>
    <main class="p-6 lg:p-8 max-w-[1920px] mx-auto">
      <slot />
    </main>
  </div>
</template>
