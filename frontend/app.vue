<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
/**
 * Le thème n'est plus imposé ici.
 *
 * Cette racine forçait `colorMode.preference = 'dark'` à chaque démarrage et
 * enveloppait l'application dans un `<div class="dark">` : la préférence de
 * l'utilisateur était réécrite au chargement et les composants Nuxt UI
 * restaient en variante sombre quoi qu'il arrive. C'est @nuxtjs/color-mode,
 * configuré dans `nuxt.config.ts`, qui pose désormais la classe et l'attribut
 * `data-theme` sur <html>.
 */
</script>
