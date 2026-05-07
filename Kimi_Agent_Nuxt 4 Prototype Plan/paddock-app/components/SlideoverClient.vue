<template>
  <Teleport to="body">
    <!-- Overlay -->
    <Transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 bg-black/45 z-drawer"
        @click="close"
      />
    </Transition>

    <!-- Slideover -->
    <Transition name="slide-right">
      <div
        v-if="modelValue"
        class="fixed top-0 right-0 w-[480px] max-w-full h-full bg-white shadow-xl z-drawer flex flex-col rounded-l-xl"
      >
        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-border-light">
          <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
            <span>👤</span>
            Nouveau client
          </h2>
          <button
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-text-muted hover:text-text-primary hover:bg-body-gray transition-colors"
            @click="close"
          >
            ✕
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <!-- Informations client -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Prénom</label>
              <input
                v-model="form.firstName"
                type="text"
                class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                placeholder="Jean"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Nom</label>
              <input
                v-model="form.lastName"
                type="text"
                class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                placeholder="Dupont"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Téléphone</label>
              <input
                v-model="form.phone"
                type="tel"
                class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                placeholder="06 12 34 56 78"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
              <input
                v-model="form.email"
                type="email"
                class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                placeholder="jean.dupont@email.com"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Adresse</label>
              <textarea
                v-model="form.address"
                rows="3"
                class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors resize-none"
                placeholder="12 rue de la Moto, 75000 Paris"
              />
            </div>
          </div>

          <!-- Separator -->
          <div class="border-t border-border-light pt-4">
            <h3 class="text-sm font-bold text-text-primary mb-4 flex items-center gap-2">
              <span>🏍</span>
              Véhicule
            </h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Marque</label>
                <select
                  v-model="form.brand"
                  class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors appearance-none"
                >
                  <option value="" disabled>Sélectionner une marque</option>
                  <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Modèle</label>
                <input
                  v-model="form.model"
                  type="text"
                  class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                  placeholder="MT-07"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Immatriculation</label>
                <input
                  v-model="form.licensePlate"
                  type="text"
                  class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors uppercase"
                  placeholder="AA-123-BB"
                >
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-text-secondary mb-1">Année</label>
                  <input
                    v-model.number="form.year"
                    type="number"
                    min="1900"
                    :max="new Date().getFullYear()"
                    class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                    placeholder="2023"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-secondary mb-1">Kilométrage</label>
                  <input
                    v-model.number="form.mileage"
                    type="number"
                    min="0"
                    class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-colors"
                    placeholder="15000"
                  >
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-border-light bg-body-gray flex justify-end gap-2 rounded-bl-xl">
          <PaddockButton variant="ghost" @click="close">
            Annuler
          </PaddockButton>
          <PaddockButton variant="primary" @click="save">
            💾 Enregistrer
          </PaddockButton>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
interface Props {
  modelValue: boolean
}

const props = defineProps<Props>()

const brands = ['YAMAHA', 'KAWASAKI', 'HONDA', 'DUCATI', 'BMW', 'TRIUMPH', 'KTM']

const form = ref({
  firstName: '',
  lastName: '',
  phone: '',
  email: '',
  address: '',
  brand: '',
  model: '',
  licensePlate: '',
  year: null as number | null,
  mileage: null as number | null,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'save', payload: typeof form.value): void
}>()

function close() {
  emit('update:modelValue', false)
}

function save() {
  emit('save', { ...form.value })
  close()
}

// Reset form on open
watch(() => props.modelValue, (open) => {
  if (open) {
    form.value = {
      firstName: '',
      lastName: '',
      phone: '',
      email: '',
      address: '',
      brand: '',
      model: '',
      licensePlate: '',
      year: null,
      mileage: null,
    }
  }
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}
.slide-right-enter-from,
.slide-right-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
