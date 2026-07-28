<template>
  <!-- Logo de marque Paddock (atelier MOTO). Remplace l'ancienne icône « vélo »
       Lucide, contresens pour un atelier moto et incohérente avec le vrai logo
       affiché sur la page de connexion. -->
  <img :src="logo" alt="Paddock" width="28" height="28" style="display:block;object-fit:contain;" />
</template>

<script setup lang="ts">
const { stacked: logo } = useBrandLogo()
</script>
