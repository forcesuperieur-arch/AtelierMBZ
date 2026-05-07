<script setup lang="ts">
import { ref, computed } from 'vue'

interface Facture {
  id: number
  numero: string
  date: string
  client: string
  total: number
  paiement: 'Carte' | 'Virement' | 'Espèces' | 'Chèque'
  statut: 'Payé' | 'En attente' | 'Retard'
}

const factures = ref<Facture[]>([
  { id: 1, numero: 'FAC-2024-203', date: '2024-06-02', client: 'Jean Dupont', total: 312.50, paiement: 'Carte', statut: 'Payé' },
  { id: 2, numero: 'FAC-2024-204', date: '2024-06-03', client: 'Marie Lefebvre', total: 487.00, paiement: 'Virement', statut: 'En attente' },
  { id: 3, numero: 'FAC-2024-205', date: '2024-05-20', client: 'Lucas Bernard', total: 189.90, paiement: 'Chèque', statut: 'Retard' },
  { id: 4, numero: 'FAC-2024-206', date: '2024-06-05', client: 'Sophie Martin', total: 520.00, paiement: 'Carte', statut: 'Payé' },
  { id: 5, numero: 'FAC-2024-207', date: '2024-05-28', client: 'Thomas Petit', total: 145.00, paiement: 'Espèces', statut: 'Payé' },
])

const search = ref('')

const filteredFactures = computed(() => {
  if (!search.value) return factures.value
  const q = search.value.toLowerCase()
  return factures.value.filter(
    f =>
      f.numero.toLowerCase().includes(q) ||
      f.client.toLowerCase().includes(q) ||
      f.paiement.toLowerCase().includes(q)
  )
})

function getStatutBadge(statut: Facture['statut']) {
  switch (statut) {
    case 'Payé': return 'success' as const
    case 'En attente': return 'warning' as const
    case 'Retard': return 'danger' as const
    default: return 'gray' as const
  }
}

const caDuMois = computed(() =>
  factures.value
    .filter(f => {
      const d = new Date(f.date)
      return d.getMonth() === new Date().getMonth() && d.getFullYear() === new Date().getFullYear()
    })
    .reduce((s, f) => s + f.total, 0)
)

const encours = computed(() =>
  factures.value
    .filter(f => f.statut === 'En attente' || f.statut === 'Retard')
    .reduce((s, f) => s + f.total, 0)
)

const tauxRecouvrement = computed(() => {
  const total = factures.value.reduce((s, f) => s + f.total, 0)
  if (!total) return 0
  const paye = factures.value.filter(f => f.statut === 'Payé').reduce((s, f) => s + f.total, 0)
  return Math.round((paye / total) * 100)
})
</script>

<template>
  <div class="bg-body-page min-h-screen">
    <div class="p-6 lg:p-8 max-w-[1920px] mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-text-primary">
          🧾 Facturation
        </h1>
        <div class="flex items-center gap-3">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Rechercher une facture..."
              class="w-64 pl-3 pr-3 py-2 text-sm bg-white border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/30 text-text-primary placeholder:text-text-tertiary"
            />
          </div>
        </div>
      </div>

      <!-- Table -->
      <PaddockCard title="Liste des factures" elevated>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  N°
                </th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Date
                </th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Client
                </th>
                <th class="text-right px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Total
                </th>
                <th class="text-left px-4 py-3 text-[10px] font-extrabold uppercase text-text-tertiary tracking-widest">
                  Paiement
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
                v-for="f in filteredFactures"
                :key="f.id"
                class="border-b border-border/50 hover:bg-body-gray/60 transition-colors"
              >
                <td class="px-4 py-3 font-mono text-text-primary font-medium">
                  {{ f.numero }}
                </td>
                <td class="px-4 py-3 text-text-secondary">
                  {{ f.date }}
                </td>
                <td class="px-4 py-3 text-text-primary font-medium">
                  {{ f.client }}
                </td>
                <td class="px-4 py-3 text-right font-bold text-text-primary">
                  {{ f.total.toFixed(2) }} €
                </td>
                <td class="px-4 py-3 text-text-secondary">
                  {{ f.paiement }}
                </td>
                <td class="px-4 py-3">
                  <PaddockBadge :variant="getStatutBadge(f.statut)" size="sm">
                    {{ f.statut }}
                  </PaddockBadge>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <PaddockButton variant="ghost" size="sm">
                      👁
                    </PaddockButton>
                    <PaddockButton variant="ghost" size="sm">
                      🖨
                    </PaddockButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PaddockCard>

      <!-- Récap cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <PaddockCard title="CA du mois" flat>
          <div class="text-3xl font-extrabold text-accent">
            {{ caDuMois.toFixed(2) }} €
          </div>
          <div class="text-xs text-text-tertiary mt-1">
            Chiffre d'affaires facturé ce mois
          </div>
        </PaddockCard>

        <PaddockCard title="Encours" flat>
          <div class="text-3xl font-extrabold text-warning">
            {{ encours.toFixed(2) }} €
          </div>
          <div class="text-xs text-text-tertiary mt-1">
            Factures en attente ou en retard
          </div>
        </PaddockCard>

        <PaddockCard title="Taux de recouvrement" flat>
          <div class="text-3xl font-extrabold text-success">
            {{ tauxRecouvrement }}%
          </div>
          <div class="text-xs text-text-tertiary mt-1">
            factures payées / total facturé
          </div>
        </PaddockCard>
      </div>
    </div>
  </div>
</template>
