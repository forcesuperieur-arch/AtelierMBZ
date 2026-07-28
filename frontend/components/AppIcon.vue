<!--
  Icône de l'interface. Point de passage UNIQUE vers le jeu tracé RemixIcon,
  que le design system Motoblouz impose à la place des emoji.

  Côté staff, la résolution est déléguée à `UIcon` (module `@nuxt/icon` fourni
  par Nuxt UI), alimenté par la collection `@iconify-json/ri` embarquée en
  dépendance : aucune requête à l'API Iconify au moment de l'affichage, ce qui
  serait fatal sur un poste d'atelier sans accès sortant.

  Le portail client possède SA PROPRE version de ce composant, au même nom et
  avec la même interface, qui rend du SVG en ligne — il n'a pas de module
  d'icônes et lui en ajouter un alourdirait une application volontairement
  légère. Les deux fronts écrivent donc exactement le même balisage.

  L'icône est DÉCORATIVE par défaut : elle double toujours un texte visible.
  Quand elle est le seul contenu d'un bouton, ce n'est pas elle qu'il faut
  nommer mais le bouton, avec un `aria-label` — c'est lui que le lecteur
  d'écran annonce.
-->
<template>
  <UIcon :name="name" class="app-icon" aria-hidden="true" />
</template>

<script setup lang="ts">
defineProps<{ name: string }>()
</script>
