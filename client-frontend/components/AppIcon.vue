<!--
  FICHIER GÉNÉRÉ — ne pas modifier à la main.
  Régénérer avec : node scripts/design/build-client-icons.mjs

  Jumeau, pour le portail client, du composant AppIcon du front staff. Même
  nom et même interface, mais le tracé est embarqué en dur : le portail n'a pas
  de module d'icônes, et n'a pas à en gagner un pour 3 icônes.

  Le `v-html` ne présente pas de risque : il ne reçoit jamais que les chaînes
  du tableau ci-dessous, figées à la génération depuis la collection RemixIcon.
-->
<template>
  <svg
    v-if="corps"
    class="app-icon"
    viewBox="0 0 24 24"
    fill="currentColor"
    aria-hidden="true"
    v-html="corps"
  />
</template>

<script setup>
const TRACES = {
  'i-ri-arrow-left-line': '<path fill="currentColor" d="M7.828 11H20v2H7.828l5.364 5.364l-1.414 1.414L4 12l7.778-7.778l1.414 1.414z"/>',
  'i-ri-file-text-line': '<path fill="currentColor" d="M21 8v12.993A1 1 0 0 1 20.007 22H3.993A.993.993 0 0 1 3 21.008V2.992C3 2.455 3.449 2 4.002 2h10.995zm-2 1h-5V4H5v16h14zM8 7h3v2H8zm0 4h8v2H8zm0 4h8v2H8z"/>',
  'i-ri-phone-line': '<path fill="currentColor" d="M9.366 10.682a10.56 10.56 0 0 0 3.952 3.952l.884-1.238a1 1 0 0 1 1.294-.296a11.4 11.4 0 0 0 4.583 1.364a1 1 0 0 1 .921.997v4.462a1 1 0 0 1-.898.995Q19.307 21 18.5 21C9.94 21 3 14.06 3 5.5q0-.807.082-1.602A1 1 0 0 1 4.077 3h4.462a1 1 0 0 1 .997.921A11.4 11.4 0 0 0 10.9 8.504a1 1 0 0 1-.296 1.294zm-2.522-.657l1.9-1.357A13.4 13.4 0 0 1 7.647 5H5.01Q5 5.25 5 5.5C5 12.956 11.044 19 18.5 19q.25 0 .5-.01v-2.637a13.4 13.4 0 0 1-3.668-1.097l-1.357 1.9a12.5 12.5 0 0 1-1.588-.75l-.058-.033a12.56 12.56 0 0 1-4.702-4.702l-.033-.058a12.4 12.4 0 0 1-.75-1.588"/>',
}

const props = defineProps({
  name: { type: String, required: true },
})

const corps = computed(() => TRACES[props.name] ?? '')
</script>
