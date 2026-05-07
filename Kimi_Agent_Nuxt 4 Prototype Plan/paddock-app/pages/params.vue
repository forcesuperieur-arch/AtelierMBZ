<template>
  <div class="p-6 lg:p-8 max-w-[1920px] mx-auto bg-body-page min-h-screen">
    <h1 class="text-2xl font-bold text-text-primary mb-6">⚙️ Paramètres Atelier</h1>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-1 p-1 bg-body-gray rounded-lg mb-8 w-fit">
      <button
        v-for="tab in tabs"
        :key="tab"
        @click="activeTab = tab"
        :class="[
          'px-4 py-2 rounded-md text-sm font-medium transition-all',
          activeTab === tab
            ? 'bg-white shadow-xs text-text-primary'
            : 'text-text-secondary hover:text-text-primary',
        ]"
      >
        {{ tab }}
      </button>
    </div>

    <!-- HORAIRES -->
    <div v-if="activeTab === 'Horaires'">
      <PaddockCard class="max-w-3xl">
        <h2 class="text-lg font-semibold text-text-primary mb-4">Horaires d'ouverture</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-light">
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Jour</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Ouverture</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Fermeture</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Actif</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(h, idx) in horaires"
                :key="h.jour"
                :class="idx !== horaires.length - 1 ? 'border-b border-border-light' : ''"
              >
                <td class="py-3 px-2 font-medium text-text-primary">{{ h.jour }}</td>
                <td class="py-3 px-2">
                  <input
                    v-model="h.ouverture"
                    type="time"
                    class="bg-body-gray rounded-md px-3 py-1.5 text-sm border border-border-light focus:outline-none focus:ring-2 focus:ring-accent/20"
                  />
                </td>
                <td class="py-3 px-2">
                  <input
                    v-model="h.fermeture"
                    type="time"
                    class="bg-body-gray rounded-md px-3 py-1.5 text-sm border border-border-light focus:outline-none focus:ring-2 focus:ring-accent/20"
                  />
                </td>
                <td class="py-3 px-2">
                  <button
                    @click="h.actif = !h.actif"
                    :class="[
                      'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                      h.actif ? 'bg-accent' : 'bg-body-gray border border-border-light',
                    ]"
                  >
                    <span
                      :class="[
                        'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-xs',
                        h.actif ? 'translate-x-6' : 'translate-x-1',
                      ]"
                    />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PaddockCard>
    </div>

    <!-- PONTS -->
    <div v-if="activeTab === 'Ponts'">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <PaddockCard v-for="pont in ponts" :key="pont.id" class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-text-primary">{{ pont.nom }}</h3>
            <PaddockBadge :color="pont.type === 'lourd' ? 'orange' : 'green'">
              {{ pont.type === 'lourd' ? 'Pont lourd' : 'Pont léger' }}
            </PaddockBadge>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-text-secondary">État</span>
            <button
              @click="pont.actif = !pont.actif"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                pont.actif ? 'bg-accent' : 'bg-body-gray border border-border-light',
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-xs',
                  pont.actif ? 'translate-x-6' : 'translate-x-1',
                ]"
              />
            </button>
          </div>
        </PaddockCard>
      </div>
    </div>

    <!-- TARIFS -->
    <div v-if="activeTab === 'Tarifs'">
      <PaddockCard class="max-w-3xl">
        <h2 class="text-lg font-semibold text-text-primary mb-4">Grille tarifaire</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-light">
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Prestation</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Prix TTC</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(t, idx) in tarifs"
                :key="t.prestation"
                :class="idx !== tarifs.length - 1 ? 'border-b border-border-light' : ''"
              >
                <td class="py-3 px-2 font-medium text-text-primary">{{ t.prestation }}</td>
                <td class="py-3 px-2">
                  <div class="flex items-center gap-1">
                    <input
                      v-model="t.prix"
                      type="number"
                      min="0"
                      step="0.01"
                      class="bg-body-gray rounded-md px-3 py-1.5 text-sm border border-border-light focus:outline-none focus:ring-2 focus:ring-accent/20 w-28"
                    />
                    <span class="text-text-secondary text-sm">€</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PaddockCard>
    </div>

    <!-- UTILISATEURS -->
    <div v-if="activeTab === 'Utilisateurs'">
      <PaddockCard class="max-w-4xl">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-text-primary">Mécaniciens</h2>
          <PaddockButton variant="primary">+ Ajouter</PaddockButton>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-light">
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Nom</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Rôle</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Téléphone</th>
                <th class="text-left py-3 px-2 font-medium text-text-secondary">Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(u, idx) in utilisateurs"
                :key="u.nom"
                :class="idx !== utilisateurs.length - 1 ? 'border-b border-border-light' : ''"
              >
                <td class="py-3 px-2 font-medium text-text-primary">{{ u.nom }}</td>
                <td class="py-3 px-2">
                  <PaddockBadge :color="u.role === 'Chef d\'atelier' ? 'orange' : 'gray'">
                    {{ u.role }}
                  </PaddockBadge>
                </td>
                <td class="py-3 px-2 text-text-secondary">{{ u.telephone }}</td>
                <td class="py-3 px-2">
                  <button
                    @click="u.actif = !u.actif"
                    :class="[
                      'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                      u.actif ? 'bg-accent' : 'bg-body-gray border border-border-light',
                    ]"
                  >
                    <span
                      :class="[
                        'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-xs',
                        u.actif ? 'translate-x-6' : 'translate-x-1',
                      ]"
                    />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PaddockCard>
    </div>

    <!-- NOTIFICATIONS -->
    <div v-if="activeTab === 'Notifications'">
      <PaddockCard class="max-w-3xl">
        <h2 class="text-lg font-semibold text-text-primary mb-6">Paramètres de notification</h2>
        <div class="space-y-5">
          <div
            v-for="notif in notifications"
            :key="notif.id"
            class="flex items-center justify-between gap-4"
          >
            <div>
              <div class="text-sm font-medium text-text-primary">{{ notif.label }}</div>
              <div class="text-xs text-text-secondary mt-0.5">{{ notif.description }}</div>
            </div>
            <button
              @click="notif.actif = !notif.actif"
              :class="[
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
                notif.actif ? 'bg-accent' : 'bg-body-gray border border-border-light',
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-xs',
                  notif.actif ? 'translate-x-6' : 'translate-x-1',
                ]"
              />
            </button>
          </div>
        </div>
      </PaddockCard>
    </div>

    <!-- INTÉGRATIONS -->
    <div v-if="activeTab === 'Intégrations'">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <PaddockCard
          v-for="integ in integrations"
          :key="integ.id"
          class="flex flex-col gap-4"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <span class="text-2xl">{{ integ.icon }}</span>
              <div>
                <div class="text-sm font-bold text-text-primary">{{ integ.nom }}</div>
                <div class="text-xs text-text-secondary">{{ integ.description }}</div>
              </div>
            </div>
            <PaddockBadge
              :variant="integ.connecte ? 'success' : 'gray'"
              size="sm"
            >
              {{ integ.connecte ? 'Connecté' : 'Non connecté' }}
            </PaddockBadge>
          </div>
          <div class="flex justify-end">
            <PaddockButton variant="secondary" size="sm">
              Configurer
            </PaddockButton>
          </div>
        </PaddockCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const tabs = ['Horaires', 'Ponts', 'Tarifs', 'Utilisateurs', 'Notifications', 'Intégrations'] as const
type Tab = (typeof tabs)[number]
const activeTab = ref<Tab>('Horaires')

interface Horaire {
  jour: string
  ouverture: string
  fermeture: string
  actif: boolean
}
const horaires = ref<Horaire[]>([
  { jour: 'Lundi', ouverture: '08:00', fermeture: '18:00', actif: true },
  { jour: 'Mardi', ouverture: '08:00', fermeture: '18:00', actif: true },
  { jour: 'Mercredi', ouverture: '08:00', fermeture: '18:00', actif: true },
  { jour: 'Jeudi', ouverture: '08:00', fermeture: '18:00', actif: true },
  { jour: 'Vendredi', ouverture: '08:00', fermeture: '18:00', actif: true },
  { jour: 'Samedi', ouverture: '08:00', fermeture: '12:00', actif: true },
  { jour: 'Dimanche', ouverture: '08:00', fermeture: '18:00', actif: false },
])

interface Pont {
  id: number
  nom: string
  type: 'leger' | 'lourd'
  actif: boolean
}
const ponts = ref<Pont[]>([
  { id: 1, nom: 'Pont A', type: 'leger', actif: true },
  { id: 2, nom: 'Pont B', type: 'leger', actif: true },
  { id: 3, nom: 'Pont C', type: 'lourd', actif: true },
  { id: 4, nom: 'Pont D', type: 'leger', actif: false },
  { id: 5, nom: 'Pont E', type: 'lourd', actif: true },
])

interface Tarif {
  prestation: string
  prix: number
}
const tarifs = ref<Tarif[]>([
  { prestation: 'Forfait révision', prix: 149.0 },
  { prestation: 'Pneu avant', prix: 89.9 },
  { prestation: 'Pneu arrière', prix: 95.0 },
  { prestation: 'Vidange', prix: 79.0 },
  { prestation: 'Frein AV (la paire)', prix: 120.0 },
  { prestation: 'Frein AR (la paire)', prix: 110.0 },
  { prestation: 'Changement ampoule', prix: 15.0 },
  { prestation: 'Diagnostic électronique', prix: 55.0 },
])

interface Utilisateur {
  nom: string
  role: string
  telephone: string
  actif: boolean
}
const utilisateurs = ref<Utilisateur[]>([
  { nom: 'Thomas Martin', role: "Chef d'atelier", telephone: '06 12 34 56 78', actif: true },
  { nom: 'Lucas Bernard', role: 'Mécanicien', telephone: '06 23 45 67 89', actif: true },
  { nom: 'Sophie Petit', role: 'Mécanicien', telephone: '06 34 56 78 90', actif: true },
  { nom: 'Marc Dubois', role: 'Apprenti', telephone: '06 45 67 89 01', actif: false },
])

interface Notification {
  id: string
  label: string
  description: string
  actif: boolean
}
const notifications = ref<Notification[]>([
  { id: 'retard', label: 'Alertes retard mécano', description: 'Notifier quand un RDV dépasse la durée estimée', actif: true },
  { id: 'stock', label: 'Alertes stock critique', description: 'Avertir quand une pièce passe sous le seuil', actif: true },
  { id: 'rdv', label: 'Nouveau RDV client', description: 'Recevoir une alerte à chaque nouveau rendez-vous', actif: false },
  { id: 'revision', label: 'Rappel révision', description: 'Notifier les clients pour leurs révisions périodiques', actif: true },
  { id: 'rapport', label: 'Rapport quotidien email', description: 'Recevoir un résumé quotidien de l\'activité', actif: false },
])

interface Integration {
  id: string
  nom: string
  description: string
  icon: string
  connecte: boolean
}
const integrations = ref<Integration[]>([
  { id: 'sendgrid', nom: 'SendGrid', description: 'Email transactions', icon: '📧', connecte: true },
  { id: 'twilio', nom: 'Twilio', description: 'SMS clients', icon: '📱', connecte: false },
  { id: 'stripe', nom: 'Stripe', description: 'Paiements en ligne', icon: '💶', connecte: true },
  { id: 'gcal', nom: 'Google Calendar', description: 'Synchro RDVs', icon: '📊', connecte: false },
])
</script>
