<script setup lang="ts">
interface NavPage {
  icon: string
  name: string
  desc: string
  path: string
  status: 'ok' | 'wip'
}

const pages: NavPage[] = [
  { icon: '🔐', name: 'Login', desc: 'Authentification', path: '/login', status: 'ok' },
  { icon: '📊', name: 'Dashboard', desc: 'Vue d\'ensemble atelier', path: '/', status: 'ok' },
  { icon: '📅', name: 'Planning Grille', desc: 'Vue calendrier grille', path: '/planning', status: 'wip' },
  { icon: '📋', name: 'Planning Liste', desc: 'Vue liste des RDVs', path: '/planning?tab=liste', status: 'wip' },
  { icon: '🔧', name: 'Espace Mécano', desc: 'Vue intervention mécano', path: '/mecano/r1', status: 'wip' },
  { icon: '👤', name: 'Clients', desc: 'Liste des clients', path: '/clients', status: 'wip' },
  { icon: '📋', name: 'Fiche Client', desc: 'Détail client', path: '/clients/1', status: 'wip' },
  { icon: '✚', name: 'Création Client', desc: 'Nouveau client', path: '/clients/nouveau', status: 'wip' },
  { icon: '📅', name: 'Wizard RDV', desc: 'Création rendez-vous', path: '/rdv/nouveau', status: 'wip' },
  { icon: '📋', name: 'Détail RDV', desc: 'Vue rendez-vous', path: '/rdv/r1', status: 'wip' },
  { icon: '🏍', name: 'Pipeline VO', desc: 'Ventes occasion', path: '/vo', status: 'wip' },
  { icon: '📦', name: 'Stock', desc: 'Gestion des pièces', path: '/stock', status: 'ok' },
  { icon: '📦', name: 'Inventaire', desc: 'Inventaire physique', path: '/stock/inventaire', status: 'wip' },
  { icon: '💶', name: 'Devis', desc: 'Devis clients', path: '/devis', status: 'ok' },
  { icon: '🧾', name: 'Factures', desc: 'Facturation', path: '/factures', status: 'ok' },
  { icon: '🔐', name: 'Admin Users', desc: 'Gestion utilisateurs', path: '/admin/users', status: 'wip' },
  { icon: '⚙️', name: 'Paramètres', desc: 'Configuration atelier', path: '/params', status: 'ok' },
  { icon: '🎨', name: 'Design System', desc: 'Tokens & composants', path: '/design-system', status: 'wip' },
  { icon: '📭', name: 'Empty States', desc: 'États vides', path: '/cover#empty', status: 'wip' },
  { icon: '✓', name: 'Checklist', desc: 'Validation prototype', path: '/design-system#checklist', status: 'wip' },
]

const designTokens = [
  { label: 'Accent', color: '#e85913', text: 'white' },
  { label: 'Header', color: '#141428', text: 'white' },
  { label: 'Body', color: '#f8f7f4', text: '#141428' },
  { label: 'Page', color: '#e4e3de', text: '#141428' },
  { label: 'Success', color: '#10b981', text: 'white' },
  { label: 'Danger', color: '#ef4444', text: 'white' },
]
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <!-- Hero -->
    <div class="bg-gradient-to-r from-header to-[#2a2a4e] text-white py-16 px-6 lg:px-8">
      <div class="max-w-[1920px] mx-auto">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-4xl">🏍️</span>
          <div class="px-3 py-1 bg-accent/20 text-accent-light text-xs font-extrabold uppercase tracking-widest rounded-full">
            Prototype complet
          </div>
        </div>
        <h1 class="text-4xl lg:text-5xl font-black tracking-tight">
          AtelierPaddock — Prototype v7 PRO
        </h1>
        <p class="text-lg text-white/70 mt-3 max-w-xl">
          22 écrans · Design System · Interactif
        </p>
        <div class="flex items-center gap-4 mt-6 text-sm text-white/60">
          <span>Stack: Nuxt 4 · Vue 3 · TS · Tailwind</span>
          <span>·</span>
          <span>Pinia · Paddock UI</span>
        </div>
      </div>
    </div>

    <!-- Grid de navigation -->
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-8">
      <div>
        <h2 class="text-lg font-bold text-text-primary mb-4">Navigation prototype</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <NuxtLink
            v-for="page in pages"
            :key="page.path"
            :to="page.path"
            class="bg-white border border-border-light rounded-xl p-4 shadow-xs hover:shadow-md hover:-translate-y-0.5 hover:border-accent transition-all cursor-pointer group"
          >
            <div class="flex items-start justify-between">
              <span class="text-2xl">{{ page.icon }}</span>
              <PaddockBadge
                :variant="page.status === 'ok' ? 'success' : 'warning'"
                size="sm"
              >
                {{ page.status === 'ok' ? 'OK' : 'En cours' }}
              </PaddockBadge>
            </div>
            <div class="mt-3 font-bold text-text-primary text-sm group-hover:text-accent transition-colors">
              {{ page.name }}
            </div>
            <div class="mt-1 text-xs text-text-tertiary">
              {{ page.desc }}
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Design System -->
      <div>
        <h2 class="text-lg font-bold text-text-primary mb-4">Design System</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
          <!-- Couleurs -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Couleurs</div>
            <div class="space-y-2">
              <div
                v-for="token in designTokens"
                :key="token.label"
                class="flex items-center gap-2"
              >
                <div
                  class="w-6 h-6 rounded-md border border-black/10 shrink-0"
                  :style="{ backgroundColor: token.color }"
                />
                <span class="text-xs text-text-secondary">{{ token.label }}</span>
              </div>
            </div>
          </div>

          <!-- Typo -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Typo</div>
            <div class="space-y-1">
              <div class="text-2xl font-black text-text-primary">Aa</div>
              <div class="text-sm font-bold text-text-primary">Inter Bold</div>
              <div class="text-sm font-medium text-text-secondary">Inter Medium</div>
              <div class="text-sm text-text-secondary">Inter Regular</div>
              <div class="text-xs font-mono text-text-tertiary">monospace</div>
            </div>
          </div>

          <!-- Composants -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Composants</div>
            <div class="space-y-2">
              <PaddockButton variant="primary" size="sm">Button</PaddockButton>
              <PaddockBadge variant="success" size="sm">Badge</PaddockBadge>
              <div class="w-8 h-8 rounded-lg bg-body-gray border border-border-light" />
            </div>
          </div>

          <!-- Spacing -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Spacing</div>
            <div class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="w-1 h-1 bg-accent rounded-full" />
                <span class="text-xs text-text-secondary">4px</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-accent rounded-full" />
                <span class="text-xs text-text-secondary">8px</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-accent rounded-full" />
                <span class="text-xs text-text-secondary">16px</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-accent rounded-full" />
                <span class="text-xs text-text-secondary">24px</span>
              </div>
            </div>
          </div>

          <!-- Shadows -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Shadows</div>
            <div class="space-y-3">
              <div class="h-8 bg-white rounded-lg shadow-xs border border-border-light flex items-center px-2">
                <span class="text-[10px] text-text-tertiary">shadow-xs</span>
              </div>
              <div class="h-8 bg-white rounded-lg shadow-card border border-border-light flex items-center px-2">
                <span class="text-[10px] text-text-tertiary">shadow-card</span>
              </div>
              <div class="h-8 bg-white rounded-lg shadow-md border border-border-light flex items-center px-2">
                <span class="text-[10px] text-text-tertiary">shadow-md</span>
              </div>
            </div>
          </div>

          <!-- Layout -->
          <div class="bg-white border border-border-light rounded-xl p-4 shadow-xs">
            <div class="text-xs font-bold uppercase text-text-tertiary tracking-wider mb-3">Layout</div>
            <div class="space-y-2">
              <div class="h-6 bg-header rounded flex items-center px-2">
                <span class="text-[10px] text-white/70">Header 64px</span>
              </div>
              <div class="h-12 bg-body-page rounded border border-border-light flex items-center px-2">
                <span class="text-[10px] text-text-tertiary">Page content</span>
              </div>
              <div class="h-4 bg-body-gray rounded flex items-center px-2">
                <span class="text-[10px] text-text-tertiary">Card</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
