<template>
  <div style="max-width:800px;margin:40px auto;padding:0 20px;color:var(--content-2);">
    <h1 style="color:var(--content-1);font-size:24px;margin-bottom:24px;">Mentions Légales</h1>

    <h2 style="color:var(--accent-content);font-size:16px;margin-top:24px;">1. Éditeur du site</h2>
    <p>Le présent site est édité par l'atelier moto dont les coordonnées figurent sur la page d'accueil de l'application. Le responsable de la publication est le gérant de l'atelier.</p>

    <h2 style="color:var(--accent-content);font-size:16px;margin-top:24px;">2. Hébergement</h2>
    <p>Ce site est hébergé sur une infrastructure dédiée. Les coordonnées de l'hébergeur sont disponibles sur demande auprès du responsable de l'atelier.</p>

    <h2 style="color:var(--accent-content);font-size:16px;margin-top:24px;">3. Propriété intellectuelle</h2>
    <p>L'ensemble des contenus (textes, images, logos) présents sur ce site sont protégés par le droit de la propriété intellectuelle. Toute reproduction est interdite sans autorisation préalable.</p>

    <h2 style="color:var(--accent-content);font-size:16px;margin-top:24px;">4. Protection des données personnelles</h2>
    <p>Pour toute information relative au traitement de vos données personnelles, veuillez consulter notre <NuxtLink to="/public/politique-confidentialite" style="color:var(--accent-content);">Politique de confidentialité</NuxtLink>.</p>

    <h2 style="color:var(--accent-content);font-size:16px;margin-top:24px;">5. Cookies</h2>
    <p>Ce site utilise uniquement des cookies techniques strictement nécessaires au fonctionnement de l'application (authentification). Aucun cookie de traçage publicitaire n'est utilisé.</p>

    <div style="margin-top:40px;padding-top:20px;border-top:1px solid var(--border-2);font-size:12px;color:var(--content-3);">
      Dernière mise à jour : avril 2026
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
</script>
