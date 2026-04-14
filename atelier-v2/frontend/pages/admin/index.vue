<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Administration</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/users')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-users" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Utilisateurs</p>
            <p class="text-sm text-gray-500">Gérer les comptes et rôles</p>
          </div>
        </div>
      </UCard>
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/config')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-cog-6-tooth" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Configuration</p>
            <p class="text-sm text-gray-500">Paramètres de l'atelier</p>
          </div>
        </div>
      </UCard>
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/absences')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-calendar" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Absences</p>
            <p class="text-sm text-gray-500">Gérer les congés mécaniciens</p>
          </div>
        </div>
      </UCard>
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/ponts')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-wrench-screwdriver" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Ponts</p>
            <p class="text-sm text-gray-500">Gérer les postes de travail</p>
          </div>
        </div>
      </UCard>
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/prestations')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-clipboard-document-list" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Prestations</p>
            <p class="text-sm text-gray-500">Tarifs et grilles</p>
          </div>
        </div>
      </UCard>
      <UCard class="cursor-pointer hover:ring-2 ring-primary" @click="navigateTo('/admin/audit')">
        <div class="flex items-center gap-3">
          <UIcon name="i-heroicons-document-magnifying-glass" class="text-2xl text-primary" />
          <div>
            <p class="font-semibold">Audit</p>
            <p class="text-sm text-gray-500">Journal des actions</p>
          </div>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
</script>
