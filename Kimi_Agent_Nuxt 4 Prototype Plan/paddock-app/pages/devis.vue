<script setup lang="ts">
import { ref, computed } from 'vue'

interface LigneArticle {
  id: number
  reference: string
  designation: string
  description: string
  quantite: number
  prixUnitaireHT: number
}

const articles = ref<LigneArticle[]>([
  {
    id: 1,
    reference: 'REV-12K',
    designation: 'Révision 12 000 km',
    description: 'Vidange + filtres + contrôles',
    quantite: 1,
    prixUnitaireHT: 145.00,
  },
  {
    id: 2,
    reference: 'FIL-Z900',
    designation: 'Filtre à huile KAWASAKI Z900',
    description: 'OEM · KN-204',
    quantite: 1,
    prixUnitaireHT: 25.00,
  },
  {
    id: 3,
    reference: 'JOI-VG',
    designation: 'Joint de vidange',
    description: 'Aluminium Ø 14mm',
    quantite: 1,
    prixUnitaireHT: 3.50,
  },
])

const totalHT = computed(() => articles.value.reduce((sum, a) => sum + a.quantite * a.prixUnitaireHT, 0))
const tva = computed(() => totalHT.value * 0.20)
const totalTTC = computed(() => totalHT.value + tva.value)
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-text-primary">
          💶 Devis
        </h1>
        <div class="flex items-center gap-3">
          <PaddockButton variant="secondary" size="sm">
            ← Retour liste
          </PaddockButton>
          <PaddockButton variant="primary" size="sm">
            + Nouveau devis
          </PaddockButton>
        </div>
      </div>

      <!-- En-tête de devis -->
      <PaddockCard elevated>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Colonne gauche -->
          <div class="space-y-3">
            <div class="inline-block px-3 py-1 bg-accent/10 text-accent text-xs font-extrabold uppercase tracking-widest rounded-full">
              DEVIS
            </div>
            <div class="text-2xl font-black text-text-primary font-mono">
              N° D-2025-0042
            </div>
            <div class="text-sm text-text-secondary">
              Émis le 29 avril 2025 · Validité 30 jours
            </div>
          </div>
          <!-- Colonne droite -->
          <div class="space-y-1 lg:text-right">
            <div class="text-sm font-bold text-text-primary">Jean Dupont</div>
            <div class="text-sm text-text-secondary">06 12 34 56 78</div>
            <div class="text-sm text-text-secondary">jean.dupont@email.fr</div>
          </div>
        </div>

        <!-- Section véhicule -->
        <div class="mt-6 pt-6 border-t border-border-light">
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-lg">🏍</span>
            <span class="text-sm font-bold text-text-primary">KAWASAKI Z900</span>
            <span class="text-sm text-text-secondary">·</span>
            <span class="text-sm font-mono text-text-secondary">AA-123-AA</span>
            <span class="text-sm text-text-secondary">·</span>
            <span class="text-sm text-text-secondary">2022</span>
            <span class="text-sm text-text-secondary">·</span>
            <span class="text-sm text-text-secondary">12 450 km</span>
            <span class="text-sm text-text-secondary">·</span>
            <span class="text-sm text-text-secondary">Révision 12 000 km</span>
            <div class="ml-auto">
              <PaddockBadge variant="warning" size="sm">
                En attente
              </PaddockBadge>
            </div>
          </div>
        </div>
      </PaddockCard>

      <!-- Tableau des articles -->
      <PaddockCard title="Articles" elevated>
        <div class="overflow-x-auto mt-2">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">#</th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">Référence</th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">Désignation</th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">Qté</th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">P.U. HT</th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">Total HT</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="a in articles"
                :key="a.id"
                class="border-b border-border/50 hover:bg-body-gray/60 transition-colors"
              >
                <td class="px-4 py-3 text-text-tertiary font-mono">{{ a.id }}</td>
                <td class="px-4 py-3 font-mono text-text-primary font-medium">{{ a.reference }}</td>
                <td class="px-4 py-3">
                  <div class="text-text-primary font-medium">{{ a.designation }}</div>
                  <div class="text-xs text-text-tertiary">{{ a.description }}</div>
                </td>
                <td class="px-4 py-3 text-right font-mono text-text-primary">{{ a.quantite }}</td>
                <td class="px-4 py-3 text-right font-mono text-text-primary">{{ a.prixUnitaireHT.toFixed(2) }} €</td>
                <td class="px-4 py-3 text-right font-mono font-bold text-text-primary">{{ (a.quantite * a.prixUnitaireHT).toFixed(2) }} €</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totaux -->
        <div class="mt-6 flex justify-end">
          <div class="w-full max-w-xs space-y-2">
            <div class="flex justify-between text-sm text-text-secondary">
              <span>Total HT</span>
              <span class="font-mono">{{ totalHT.toFixed(2) }} €</span>
            </div>
            <div class="flex justify-between text-sm text-text-secondary">
              <span>TVA 20%</span>
              <span class="font-mono">{{ tva.toFixed(2) }} €</span>
            </div>
            <div class="flex justify-between text-base font-bold text-text-primary pt-2 border-t border-border-light">
              <span>Total TTC</span>
              <span class="font-mono">{{ totalTTC.toFixed(2) }} €</span>
            </div>
          </div>
        </div>
      </PaddockCard>

      <!-- Actions du devis -->
      <div class="flex flex-wrap items-center gap-3">
        <PaddockButton variant="secondary" size="sm">
          📄 Générer PDF
        </PaddockButton>
        <PaddockButton variant="secondary" size="sm">
          ✉️ Envoyer par email
        </PaddockButton>
        <PaddockButton variant="ghost" size="sm">
          ✏️ Modifier
        </PaddockButton>
        <PaddockButton variant="primary" size="sm" class="ml-auto">
          ✅ Valider
        </PaddockButton>
      </div>
    </div>
  </div>
</template>
