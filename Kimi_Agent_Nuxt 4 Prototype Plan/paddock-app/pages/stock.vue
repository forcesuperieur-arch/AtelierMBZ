<script setup lang="ts">
import { ref, computed } from 'vue'

interface Piece {
  id: number
  reference: string
  designation: string
  stock: number
  seuil: number
  prix: number
  fournisseur: string
}

const pieces = ref<Piece[]>([
  { id: 1, reference: 'FIL-Z900', designation: 'Filtre à huile Z900', stock: 12, seuil: 5, prix: 14.90, fournisseur: 'K&N' },
  { id: 2, reference: 'PLQ-MT07', designation: 'Plaquettes frein MT-07', stock: 4, seuil: 6, prix: 42.50, fournisseur: 'Brembo' },
  { id: 3, reference: 'PN-AV-MIC', designation: 'Pneu AV Michelin Road 5', stock: 8, seuil: 4, prix: 189.00, fournisseur: 'Michelin' },
  { id: 4, reference: 'BAT-YTX', designation: 'Batterie YTX9-BS', stock: 0, seuil: 3, prix: 79.90, fournisseur: 'Yuasa' },
  { id: 5, reference: 'CH-DID520', designation: 'Chaîne DID 520 VX3', stock: 6, seuil: 4, prix: 129.00, fournisseur: 'DID' },
  { id: 6, reference: 'BK-NGK-CR9', designation: 'Bougie NGK CR9EIX', stock: 20, seuil: 10, prix: 12.50, fournisseur: 'NGK' },
])

const search = ref('')

const filteredPieces = computed(() => {
  if (!search.value) return pieces.value
  const q = search.value.toLowerCase()
  return pieces.value.filter(
    p =>
      p.reference.toLowerCase().includes(q) ||
      p.designation.toLowerCase().includes(q) ||
      p.fournisseur.toLowerCase().includes(q)
  )
})

function getStatus(p: Piece) {
  if (p.stock === 0) return { label: 'Rupture', variant: 'danger' as const }
  if (p.stock <= p.seuil) return { label: 'Seuil', variant: 'warning' as const }
  return { label: 'OK', variant: 'success' as const }
}

const alertes = computed(() =>
  pieces.value.filter(p => p.stock <= p.seuil)
)

const commandesEnCours = ref([
  { id: 'CMD-2024-089', fournisseur: 'Michelin', articles: 3, total: 567.00, dateLivraison: '2024-06-15' },
  { id: 'CMD-2024-090', fournisseur: 'Yuasa', articles: 5, total: 399.50, dateLivraison: '2024-06-18' },
])
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-text-primary">
          📦 Stock & Pièces
        </h1>
        <div class="flex items-center gap-3">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Rechercher une pièce..."
              class="w-64 pl-3 pr-3 py-2 text-sm bg-white border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/30 text-text-primary placeholder:text-text-tertiary"
            />
          </div>
          <PaddockButton variant="primary" size="md">
            + Commander
          </PaddockButton>
        </div>
      </div>

      <!-- Table -->
      <PaddockCard title="Inventaire des pièces" elevated>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Référence
                </th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Désignation
                </th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Stock
                </th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Seuil
                </th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Statut
                </th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="p in filteredPieces"
                :key="p.id"
                class="border-b border-border/50 hover:bg-body-gray/60 transition-colors"
              >
                <td class="px-4 py-3 font-mono text-text-primary font-medium">
                  {{ p.reference }}
                </td>
                <td class="px-4 py-3 text-text-primary">
                  {{ p.designation }}
                </td>
                <td class="px-4 py-3 text-right font-semibold" :class="p.stock === 0 ? 'text-danger' : p.stock <= p.seuil ? 'text-warning' : 'text-text-primary'">
                  {{ p.stock }}
                </td>
                <td class="px-4 py-3 text-right text-text-secondary">
                  {{ p.seuil }}
                </td>
                <td class="px-4 py-3">
                  <PaddockBadge :variant="getStatus(p).variant" size="sm">
                    {{ getStatus(p).label }}
                  </PaddockBadge>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <PaddockButton variant="ghost" size="sm">
                      ✎
                    </PaddockButton>
                    <PaddockButton variant="ghost" size="sm">
                      🗑
                    </PaddockButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PaddockCard>

      <!-- Bottom cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Alertes stock -->
        <PaddockCard title="Alertes stock" elevated>
          <div v-if="alertes.length" class="space-y-3">
            <div
              v-for="p in alertes"
              :key="p.id"
              class="flex items-center justify-between p-3 bg-body-gray rounded-lg border border-border"
            >
              <div>
                <div class="text-sm font-semibold text-text-primary">
                  {{ p.designation }}
                </div>
                <div class="text-xs text-text-tertiary mt-0.5">
                  {{ p.reference }} — Fournisseur : {{ p.fournisseur }}
                </div>
              </div>
              <PaddockBadge :variant="p.stock === 0 ? 'danger' : 'warning'" size="sm" :pulse="p.stock === 0">
                {{ p.stock === 0 ? 'Rupture' : p.stock + ' restant' }}
              </PaddockBadge>
            </div>
          </div>
          <div v-else class="text-sm text-text-tertiary py-4">
            Aucune alerte stock pour le moment.
          </div>
        </PaddockCard>

        <!-- Commandes en cours -->
        <PaddockCard title="Commandes en cours" elevated>
          <div class="space-y-3">
            <div
              v-for="cmd in commandesEnCours"
              :key="cmd.id"
              class="flex items-center justify-between p-3 bg-body-gray rounded-lg border border-border"
            >
              <div>
                <div class="text-sm font-semibold text-text-primary">
                  {{ cmd.id }}
                </div>
                <div class="text-xs text-text-tertiary mt-0.5">
                  {{ cmd.fournisseur }} — {{ cmd.articles }} article(s) — Livraison : {{ cmd.dateLivraison }}
                </div>
              </div>
              <div class="text-sm font-bold text-text-primary">
                {{ cmd.total.toFixed(2) }} €
              </div>
            </div>
          </div>
        </PaddockCard>
      </div>
    </div>
  </div>
</template>
