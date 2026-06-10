<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Configuration atelier</div>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else style="display:flex;flex-direction:column;gap:16px;max-width:1100px;">
      <UCard>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-size:15px;font-weight:700;color:#E8E9ED;">Assistant de configuration</div>
            <div style="font-size:12px;color:#9CA3AF;">Retrouve le parcours pas à pas de l'ancienne application.</div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button
              v-for="item in steps"
              :key="item.id"
              class="btn"
              :class="step === item.id ? 'btn-primary' : 'btn-ghost'"
              style="font-size:12px;padding:6px 12px;"
              @click="step = item.id"
            >
              {{ item.id }}. {{ item.title }}
            </button>
          </div>
        </div>
      </UCard>

      <UCard v-if="isSuperAdmin" style="border-color:rgba(139,92,246,0.28);background:rgba(139,92,246,0.06);">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-size:13px;font-weight:700;color:#C4B5FD;">Onglet super admin</div>
            <div style="font-size:12px;color:#E5E7EB;margin-top:4px;">L’activation des modules atelier se pilote dans l’onglet Modules.</div>
          </div>
          <button type="button" class="btn btn-primary" style="padding:8px 12px;font-size:12px;" @click="step = 6">
            Ouvrir Modules atelier
          </button>
        </div>
      </UCard>

      <form @submit.prevent="saveConfig" style="display:flex;flex-direction:column;gap:16px;">
        <UCard v-if="step === 1">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">1. Identité de l'atelier</span></template>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <UFormField label="Nom atelier"><UInput v-model="atelier.nom" /></UFormField>
            <UFormField label="Téléphone"><UInput v-model="atelier.telephone" /></UFormField>
            <UFormField label="Email"><UInput v-model="atelier.email" type="email" /></UFormField>
            <UFormField label="SIRET"><UInput v-model="atelier.siret" /></UFormField>
            <UFormField label="TVA intracom"><UInput v-model="atelier.tva_intracom" /></UFormField>
            <UFormField label="Code postal"><UInput v-model="atelier.cp" /></UFormField>
            <UFormField label="Ville"><UInput v-model="atelier.ville" /></UFormField>
            <UFormField label="Adresse" style="grid-column:1 / -1;"><UInput v-model="atelier.adresse" /></UFormField>
          </div>

          <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
            <UFormField label="TVA MO (%)"><UInput v-model="config.tva_mo_taux" type="number" step="0.1" /></UFormField>
            <UFormField label="TVA pièces (%)"><UInput v-model="config.tva_pieces_taux" type="number" step="0.1" /></UFormField>
            <UFormField label="Taux horaire standard"><UInput v-model="config.taux_horaire_mo_standard" type="number" step="0.1" /></UFormField>
            <UFormField label="Acompte (%)"><UInput v-model="config.accompte_pourcentage" type="number" step="1" /></UFormField>
            <UFormField label="Garantie travaux (jours)"><UInput v-model="config.garantie_travaux_jours" type="number" step="1" /></UFormField>
            <UFormField label="Gardiennage / jour (€)"><UInput v-model="config.tarif_gardiennage_journalier" type="number" step="0.1" /></UFormField>
          </div>
        </UCard>

        <UCard v-if="step === 2">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">2. Logo et image atelier</span></template>
          <div style="display:grid;grid-template-columns:240px 1fr;gap:16px;align-items:start;">
            <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:12px;min-height:180px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.02);">
              <img v-if="atelier.logo_url" :src="atelier.logo_url" alt="Logo atelier" style="max-width:100%;max-height:150px;object-fit:contain;" />
              <span v-else style="color:#6B7280;font-size:13px;">Aucun logo</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <div style="font-size:13px;color:#D1D5DB;">Ajoute ici le logo affiché sur les documents et les écrans de l'atelier.</div>
              <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input" @change="onLogoChange" />
              <div style="font-size:12px;color:#9CA3AF;">Formats acceptés : PNG, JPG, WebP ou SVG.</div>
              <div v-if="uploadingLogo" style="font-size:12px;color:#FFD200;">Téléversement en cours…</div>
            </div>
          </div>
        </UCard>

        <UCard v-if="step === 3">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">3. Horaires d'ouverture</span></template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="h in horaires" :key="h.jour_semaine" style="display:grid;grid-template-columns:90px 1fr 1fr 1fr 1fr auto;gap:8px;align-items:center;font-size:13px;">
              <span style="font-weight:600;color:#E8E9ED;">{{ jourLabel(h.jour_semaine) }}</span>
              <UInput v-model="h.heure_ouverture" type="time" :disabled="!h.is_ouvert" size="sm" />
              <UInput v-model="h.heure_fermeture" type="time" :disabled="!h.is_ouvert" size="sm" />
              <UInput v-model="h.pause_debut" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause début" />
              <UInput v-model="h.pause_fin" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause fin" />
              <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#9CA3AF;">
                <input v-model="h.is_ouvert" type="checkbox" />
                Ouvert
              </label>
            </div>

            <div style="margin-top:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.02);">
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;">Jours fermés hebdomadaires</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <label v-for="option in closureDayOptions" :key="option.value" style="display:flex;align-items:center;gap:6px;font-size:12px;color:#D1D5DB;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,0.04);">
                  <input v-model="config.jours_fermeture_hebdo" type="checkbox" :value="option.value" />
                  {{ option.label }}
                </label>
              </div>
            </div>

            <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.02);">
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;">Fermetures exceptionnelles</div>
              <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                <UInput v-model="newClosureDate" type="date" style="max-width:220px;" />
                <button type="button" class="btn btn-ghost" @click="addExceptionalClosureDate">Ajouter la date</button>
              </div>
              <div v-if="config.dates_fermeture_exceptionnelles?.length" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <span v-for="date in config.dates_fermeture_exceptionnelles" :key="date" style="display:inline-flex;align-items:center;gap:6px;font-size:11px;padding:4px 8px;border-radius:999px;background:rgba(245,158,11,0.12);color:#FDE68A;">
                  {{ date }}
                  <button type="button" style="background:none;border:none;color:inherit;cursor:pointer;" @click="removeExceptionalClosureDate(date)">✕</button>
                </span>
              </div>
              <div v-else style="font-size:12px;color:#9CA3AF;margin-top:10px;">Aucune fermeture exceptionnelle enregistrée.</div>
            </div>
          </div>
        </UCard>

        <UCard v-if="step === 4">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">4. Types de moto activés</span></template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="font-size:13px;color:#D1D5DB;">Les types déjà en base se pilotent ici en simple toggle.</div>

            <div v-if="categories.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
              <div
                v-for="cat in categories"
                :key="cat.id"
                :style="{
                  border: `1px solid ${isCategoryActive(cat) ? 'rgba(255,210,0,0.35)' : 'rgba(255,255,255,0.08)'}`,
                  borderRadius: '10px',
                  padding: '12px',
                  background: isCategoryActive(cat) ? 'rgba(255,210,0,0.05)' : 'rgba(255,255,255,0.02)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: '10px',
                  alignItems: 'start',
                }"
              >
                <div>
                  <div style="font-weight:700;color:#E8E9ED;">{{ cat.nom }}</div>
                  <div v-if="cat.description" style="font-size:12px;color:#9CA3AF;margin-top:4px;">{{ cat.description }}</div>
                  <div style="font-size:11px;color:#D1D5DB;margin-top:8px;">
                    {{ isCategoryActive(cat) ? 'Disponible dans les tarifs' : 'Masqué dans les tarifs' }}
                  </div>
                </div>
                <button
                  type="button"
                  class="btn"
                  :class="isCategoryActive(cat) ? 'btn-primary' : 'btn-ghost'"
                  style="padding:6px 10px;font-size:11px;white-space:nowrap;"
                  :disabled="togglingCategoryId === cat.id"
                  @click="toggleCategory(cat)"
                >
                  {{ togglingCategoryId === cat.id ? '...' : isCategoryActive(cat) ? 'Activé' : 'Désactivé' }}
                </button>
              </div>
            </div>
            <div v-else style="font-size:13px;color:#6B7280;">Aucun type moto trouvé.</div>
          </div>
        </UCard>

        <UCard v-if="step === 5">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">5. Tarifs par prestation</span></template>
          <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
              <div style="font-size:13px;color:#D1D5DB;">Chaque prestation s'ouvre dans une pop-in avec ses prix, temps et modes par type de moto.</div>
              <button class="btn btn-primary" type="button" :disabled="seedLoading || !activeCategories.length || !prestations.length" @click="seedBaseTarifs">
                {{ seedLoading ? 'Pré-remplissage…' : 'Pré-remplir les premiers tarifs' }}
              </button>
            </div>

            <div v-if="!activeCategories.length" style="font-size:13px;color:#FCA5A5;">Active d'abord au moins un type de moto à l'étape précédente.</div>

            <div v-else-if="prestationCards.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
              <div v-for="item in prestationCards" :key="item.id" style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.02);display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:start;">
                  <div>
                    <div style="font-weight:700;color:#E8E9ED;">{{ item.nom }}</div>
                    <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ item.enabledCount }} type(s) actifs</div>
                  </div>
                  <span style="font-size:11px;padding:3px 8px;border-radius:999px;background:rgba(255,210,0,0.12);color:#FFD200;">{{ formatMinutes(item.temps_estime) }}</span>
                </div>

                <div style="font-size:12px;color:#D1D5DB;">
                  Base : {{ formatCurrency(item.prix_ttc) }}
                </div>

                <div style="display:flex;gap:6px;flex-wrap:wrap;min-height:24px;">
                  <span v-for="mode in item.modes" :key="`${item.id}-${mode}`" style="font-size:11px;padding:3px 8px;border-radius:999px;background:rgba(139,92,246,0.14);color:#C4B5FD;">
                    {{ labelTypeTarif(mode) }}
                  </span>
                  <span v-if="!item.modes.length" style="font-size:11px;color:#9CA3AF;">Pas encore configuré</span>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                  <button type="button" class="btn btn-primary" style="padding:8px 12px;font-size:12px;" @click="openTarifModal(item)">Configurer</button>
                </div>
              </div>
            </div>
            <div v-else style="font-size:13px;color:#6B7280;">Aucune prestation active trouvée.</div>
          </div>
        </UCard>

        <UCard v-if="isSuperAdmin && step === 6">
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">6. Activation des modules</span></template>
          <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="border:1px solid rgba(255,210,0,0.16);border-radius:12px;padding:14px;background:rgba(255,210,0,0.05);">
              <div style="font-size:13px;font-weight:700;color:#FDE68A;">Pilote ici le périmètre de l'atelier</div>
              <div style="font-size:12px;color:#E5E7EB;margin-top:4px;">Quand un module est coupé, le menu, les pages et les actions associées disparaissent du workflow.</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                <button type="button" class="btn btn-ghost" style="padding:7px 12px;font-size:12px;" @click="setFeaturePreset('all')">Tout activer</button>
                <button type="button" class="btn btn-ghost" style="padding:7px 12px;font-size:12px;" @click="setFeaturePreset('light')">Mode atelier léger</button>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
              <button
                v-for="item in moduleDefinitions"
                :key="item.key"
                type="button"
                class="btn"
                :class="isFeatureModuleEnabled(item.key) ? 'btn-primary' : 'btn-ghost'"
                style="display:flex;flex-direction:column;align-items:flex-start;gap:10px;padding:14px;text-align:left;min-height:148px;"
                @click="toggleFeatureModule(item.key)"
              >
                <span style="display:flex;align-items:flex-start;gap:10px;">
                  <span style="font-size:20px;line-height:1;">{{ item.icon }}</span>
                  <span>
                    <span style="display:block;font-size:13px;font-weight:700;">{{ item.label }}</span>
                    <span style="display:block;font-size:11px;opacity:0.86;margin-top:2px;">{{ item.hint }}</span>
                  </span>
                </span>
                <span style="font-size:11px;line-height:1.45;opacity:0.88;">{{ item.impact }}</span>
                <span style="margin-top:auto;font-size:11px;font-weight:700;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,0.08);">
                  {{ isFeatureModuleEnabled(item.key) ? 'Activé' : 'Désactivé' }}
                </span>
              </button>
            </div>

            <div v-if="disabledModuleLabels.length" style="border:1px solid rgba(245,158,11,0.2);border-radius:12px;padding:12px;background:rgba(245,158,11,0.06);">
              <div style="font-size:12px;font-weight:700;color:#FBBF24;">Modules actuellement masqués du workflow</div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                <span v-for="label in disabledModuleLabels" :key="label" style="font-size:11px;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,0.08);color:#FDE68A;">{{ label }}</span>
              </div>
            </div>

            <div v-else style="border:1px solid rgba(16,185,129,0.16);border-radius:12px;padding:12px;background:rgba(16,185,129,0.06);font-size:12px;color:#BBF7D0;">
              Tous les modules principaux sont actifs. Le parcours atelier reste complet.
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:#9CA3AF;">Atelier</div>
                <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ atelier.nom || 'Non renseigné' }}</div>
                <div style="font-size:12px;color:#D1D5DB;margin-top:4px;">{{ atelier.telephone || 'Téléphone manquant' }}</div>
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:#9CA3AF;">Modules</div>
                <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ activeModuleCount }} / {{ moduleDefinitions.length }} actifs</div>
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:#9CA3AF;">Horaires</div>
                <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ horaires.filter((h) => h.is_ouvert).length }} jours ouverts</div>
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:#9CA3AF;">Types moto</div>
                <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ activeCategories.length }} actifs</div>
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:#9CA3AF;">Tarifs par type</div>
                <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ grilles.filter((g) => Number(g.is_active ?? 1) === 1).length }} lignes actives</div>
              </div>
            </div>
          </div>
        </UCard>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <div style="display:flex;gap:8px;">
            <button type="button" class="btn btn-ghost" :disabled="step === steps[0]?.id" @click="goPrevStep">Précédent</button>
            <button type="button" class="btn btn-ghost" :disabled="step === lastStepId" @click="goNextStep">Suivant</button>
          </div>
          <UButton type="submit" label="Enregistrer la configuration" :loading="saving" />
        </div>
      </form>
    </div>

    <AppModal v-model:open="tarifModalOpen" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
              <div>
                <div style="font-weight:700;color:#E8E9ED;">{{ selectedPrestation?.nom || 'Tarifs prestation' }}</div>
                <div style="font-size:12px;color:#9CA3AF;">Forfait, horaire ou sur devis selon chaque type de moto.</div>
              </div>
              <button type="button" @click="tarifModalOpen = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="row in tarifRows" :key="row.categorie_id" style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(255,255,255,0.02);">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                  <div style="font-weight:700;color:#E8E9ED;">{{ row.categorie_label }}</div>
                  <div style="font-size:11px;color:#9CA3AF;">Active ou masque cette prestation pour ce type.</div>
                </div>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#D1D5DB;">
                  <input v-model="row.is_active" type="checkbox" :true-value="1" :false-value="0" />
                  Activée
                </label>
              </div>

              <div :style="{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', marginTop: '12px', opacity: Number(row.is_active) === 1 ? 1 : 0.55 }">
                <div class="form-group">
                  <label class="form-label">Mode tarif</label>
                  <select v-model="row.type_tarif" class="form-input" :disabled="Number(row.is_active) !== 1">
                    <option value="forfait">Forfait</option>
                    <option value="horaire">Horaire</option>
                    <option value="devis">Sur devis</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">{{ row.type_tarif === 'horaire' ? 'Taux horaire TTC (€)' : 'Prix TTC (€)' }}</label>
                  <input v-model.number="row.prix_ttc" type="number" step="0.01" class="form-input" :disabled="Number(row.is_active) !== 1 || row.type_tarif === 'devis'" />
                </div>
                <div class="form-group">
                  <label class="form-label">Temps (min)</label>
                  <input v-model.number="row.temps_minutes" type="number" step="1" class="form-input" :disabled="Number(row.is_active) !== 1" />
                </div>
              </div>

              <div v-if="row.type_tarif === 'devis'" style="font-size:11px;color:#9CA3AF;margin-top:8px;">Le montant sera saisi au cas par cas sur le devis.</div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
              <button type="button" class="btn btn-ghost" @click="tarifModalOpen = false">Annuler</button>
              <button type="button" class="btn btn-primary" :disabled="modalSaving" @click="saveTarifModal">{{ modalSaving ? 'Enregistrement…' : 'Enregistrer la pop-in' }}</button>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { DEFAULT_FEATURE_MODULES, normalizeFeatureModules } from '~/stores/atelier'

const api = useApi()
const toast = useToast()
const atelierStore = useAtelierStore()
const { user } = useAuth()

const loading = ref(true)
const saving = ref(false)
const uploadingLogo = ref(false)
const seedLoading = ref(false)
const modalSaving = ref(false)
const togglingCategoryId = ref<number | null>(null)
const step = ref(1)
const tarifModalOpen = ref(false)

const isSuperAdmin = computed(() => {
  const rolesList = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || rolesList.includes('ROLE_SUPER_ADMIN')
})

const steps = computed(() => {
  const baseSteps = [
    { id: 1, title: 'Atelier' },
    { id: 2, title: 'Logo' },
    { id: 3, title: 'Horaires' },
    { id: 4, title: 'Types moto' },
    { id: 5, title: 'Tarifs' },
  ]

  if (isSuperAdmin.value) {
    baseSteps.push({ id: 6, title: 'Modules · super admin' })
  }

  return baseSteps
})

const lastStepId = computed(() => steps.value[steps.value.length - 1]?.id ?? 1)

const config = ref<any>({
  tva_mo_taux: 20,
  tva_pieces_taux: 20,
  taux_horaire_mo_standard: 65,
  accompte_pourcentage: 30,
  garantie_travaux_jours: 30,
  tarif_gardiennage_journalier: 5,
  jours_fermeture_hebdo: ['sunday'],
  dates_fermeture_exceptionnelles: [],
  feature_modules: { ...DEFAULT_FEATURE_MODULES },
})

const atelier = ref<any>({
  nom: '',
  adresse: '',
  cp: '',
  ville: '',
  telephone: '',
  email: '',
  siret: '',
  tva_intracom: '',
  logo_url: '',
})

const horaires = ref<any[]>([])
const categories = ref<any[]>([])
const prestations = ref<any[]>([])
const grilles = ref<any[]>([])
const selectedPrestation = ref<any | null>(null)
const tarifRows = ref<any[]>([])

const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']
const closureDayOptions = [
  { value: 'monday', label: 'Lundi' },
  { value: 'tuesday', label: 'Mardi' },
  { value: 'wednesday', label: 'Mercredi' },
  { value: 'thursday', label: 'Jeudi' },
  { value: 'friday', label: 'Vendredi' },
  { value: 'saturday', label: 'Samedi' },
  { value: 'sunday', label: 'Dimanche' },
]
const newClosureDate = ref('')
function jourLabel(i: number) { return jours[i] || '' }

function toNumber(value: any, fallback = 0) {
  const parsed = Number(value ?? fallback)
  return Number.isFinite(parsed) ? parsed : fallback
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(toNumber(v))
}

function labelTypeTarif(v: string) {
  if (v === 'horaire') return 'Horaire'
  if (v === 'devis') return 'Sur devis'
  return 'Forfait'
}

const moduleDefinitions = [
  { key: 'devis', label: 'Devis', icon: '📝', hint: 'Création et suivi des devis', impact: 'Masque la création, la consultation et la conversion des devis.' },
  { key: 'facturation', label: 'Facturation', icon: '💳', hint: 'Factures, paiements et encaissements', impact: 'Retire la création de facture, l’encaissement et les écrans de paiement.' },
  { key: 'stock', label: 'Stock', icon: '📦', hint: 'Pièces détachées et alertes', impact: 'Supprime les alertes de stock et la gestion des pièces atelier.' },
  { key: 'suivi', label: 'Suivi live', icon: '👁', hint: 'Vue temps réel atelier', impact: 'Cache la vue live et les indicateurs temps réel de l’atelier.' },
  { key: 'motos', label: 'Catalogue motos', icon: '🏍️', hint: 'Référentiel et fiches moto', impact: 'Masque les fiches moto et le catalogue de référence.' },
  { key: 'rdv_siege', label: 'Prise de RDV par le siège', icon: '🏢', hint: 'Autorise le service client à prendre des RDV pour cet atelier depuis le siège', impact: 'Si désactivé, le service client ne peut ni voir ni réserver pour cet atelier hors contexte local.' },
  { key: 'vo', label: 'Véhicules d’Occasion', icon: '🏷️', hint: 'Rachat, dépôt-vente, livre de police et facturation VO', impact: 'Masque le menu VO et toutes les opérations d’achat-vente d’occasion.' },
]

const activeModuleCount = computed(() => {
  const modules = normalizeFeatureModules(config.value.feature_modules)
  return moduleDefinitions.filter((item) => modules[item.key] !== false).length
})

const disabledModuleLabels = computed(() => {
  return moduleDefinitions
    .filter((item) => !isFeatureModuleEnabled(item.key))
    .map((item) => item.label)
})

function isFeatureModuleEnabled(key: string) {
  const modules = normalizeFeatureModules(config.value.feature_modules)
  return modules[key] !== false
}

function setFeaturePreset(mode: 'all' | 'light') {
  const modules = normalizeFeatureModules(config.value.feature_modules)

  config.value.feature_modules = {
    ...modules,
    devis: true,
    facturation: true,
    stock: mode === 'all',
    suivi: mode === 'all',
    motos: true,
    vo: mode === 'all',
  }

  toast.add({
    title: mode === 'all' ? 'Tous les modules sont activés' : 'Preset atelier léger appliqué',
    description: mode === 'all' ? 'Le workflow complet est à nouveau disponible.' : 'Le stock et le suivi live sont coupés pour alléger l’interface.',
    color: 'success',
  })
}

function toggleFeatureModule(key: string) {
  if (!isSuperAdmin.value) return

  const modules = normalizeFeatureModules(config.value.feature_modules)
  const nextEnabled = !modules[key]
  const label = moduleDefinitions.find((item) => item.key === key)?.label || key

  if (!nextEnabled) {
    const confirmed = globalThis.confirm?.(`Désactiver ${label} pour cet atelier ? Les menus et les actions liés seront masqués après sauvegarde.`)
    if (confirmed === false) return
  }

  config.value.feature_modules = {
    ...modules,
    [key]: nextEnabled,
  }

  toast.add({
    title: `${label} ${nextEnabled ? 'activé' : 'désactivé'}`,
    description: nextEnabled ? 'Le module sera disponible dans le workflow après sauvegarde.' : 'Le module sera retiré des menus et des écrans après sauvegarde.',
    color: nextEnabled ? 'success' : 'warning',
  })
}

function unwrapList(data: any) {
  return data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
}

function extractId(value: any): number | null {
  if (value == null) return null
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const id = Number(value.split('/').pop())
    return Number.isFinite(id) ? id : null
  }
  const id = Number(value.id ?? value['@id']?.split('/').pop())
  return Number.isFinite(id) ? id : null
}

function defaultHoraires() {
  return jours.map((_, index) => ({
    jour_semaine: index,
    heure_ouverture: index < 5 ? '09:00' : '09:30',
    heure_fermeture: index < 5 ? '18:00' : '12:30',
    pause_debut: index < 5 ? '12:00' : null,
    pause_fin: index < 5 ? '14:00' : null,
    is_ouvert: index < 6,
  }))
}

function normalizeHoraires(items: any[]) {
  const source = Array.isArray(items) ? items : []
  if (!source.length) return defaultHoraires()

  return jours.map((_, index) => {
    const found = source.find((h: any) => Number(h.jour_semaine ?? h.jourSemaine) === index)
    return found
      ? {
          jour_semaine: Number(found.jour_semaine ?? found.jourSemaine ?? index),
          heure_ouverture: found.heure_ouverture ?? found.heureOuverture ?? '09:00',
          heure_fermeture: found.heure_fermeture ?? found.heureFermeture ?? '18:00',
          pause_debut: found.pause_debut ?? found.pauseDebut ?? null,
          pause_fin: found.pause_fin ?? found.pauseFin ?? null,
          is_ouvert: Boolean(found.is_ouvert ?? found.isOuvert),
        }
      : defaultHoraires()[index]
  })
}

function normalizeCategories(items: any[]) {
  return items.map((c: any) => ({
    id: Number(c.id),
    nom: c.nom,
    description: c.description ?? '',
    is_active: Number(c.is_active ?? c.isActive ?? 1),
  }))
}

function normalizePrestations(items: any[]) {
  return items
    .map((p: any) => ({
      id: Number(p.id),
      nom: p.nom,
      code: p.code,
      description: p.description ?? '',
      prix_ttc: toNumber(p.prix_ttc ?? p.prix_base_ttc ?? p.prixBaseTtc, 0),
      temps_estime: toNumber(p.temps_estime ?? p.temps_estime_minutes ?? p.tempsEstimeMinutes, 30),
      type_tarif: p.type_tarif ?? p.typeTarif ?? 'forfait',
      is_active: Number(p.is_active ?? p.isActive ?? 1),
    }))
    .filter((p: any) => p.is_active === 1)
}

function normalizeGrilles(items: any[]) {
  return items.map((g: any) => ({
    id: Number(g.id),
    prestation_id: extractId(g.prestation_id ?? g.prestation),
    categorie_id: extractId(g.categorie_moto_id ?? g.categorie_moto ?? g.categorieMoto),
    prix_ttc: toNumber(g.prix_ttc ?? g.prixTtc, 0),
    temps_minutes: toNumber(g.temps_minutes ?? g.tempsMinutes, 30),
    type_tarif: g.type_tarif ?? g.typeTarif ?? 'forfait',
    is_active: Number(g.is_active ?? g.isActive ?? 1),
  }))
}

function isCategoryActive(cat: any) {
  return Number(cat?.is_active ?? cat?.isActive ?? 1) === 1
}

const activeCategories = computed(() => categories.value.filter((cat: any) => isCategoryActive(cat)))

const prestationCards = computed(() => {
  return prestations.value
    .map((p: any) => {
      const rows = grilles.value.filter((g: any) => g.prestation_id === p.id && activeCategories.value.some((cat: any) => cat.id === g.categorie_id))
      const enabledRows = rows.filter((g: any) => Number(g.is_active ?? 1) === 1)
      const modes = Array.from(new Set(enabledRows.map((g: any) => g.type_tarif || p.type_tarif || 'forfait')))

      return {
        ...p,
        enabledCount: enabledRows.length,
        modes,
      }
    })
    .sort((a: any, b: any) => a.nom.localeCompare(b.nom))
})

function buildGrillePayload(entry: any) {
  const prixTtc = entry.type_tarif === 'devis' ? 0 : toNumber(entry.prix_ttc, 0)
  const tva = toNumber(config.value.tva_mo_taux, 20)
  const prixHt = prixTtc / (1 + (tva / 100))

  return {
    prestation: `/api/prestations/${entry.prestation_id}`,
    categorie_moto: `/api/motos/categories/${entry.categorie_id}`,
    type_vehicule: 'tous',
    prix_ht: prixHt.toFixed(2),
    prix_ttc: prixTtc.toFixed(2),
    temps_minutes: toNumber(entry.temps_minutes, 30),
    type_tarif: entry.type_tarif || 'forfait',
    delai_jours: 1,
    is_active: Number(entry.is_active ?? 1) === 1 ? 1 : 0,
  }
}

async function fetchCategories() {
  categories.value = normalizeCategories(unwrapList(await api.get('/motos/categories?itemsPerPage=200')))
}

async function fetchPrestations() {
  let data = await api.get('/prestations?itemsPerPage=200')
  prestations.value = normalizePrestations(unwrapList(data))

  if (!prestations.value.length) {
    try {
      await api.post('/config/prestations/bootstrap', {})
      data = await api.get('/prestations?itemsPerPage=200')
      prestations.value = normalizePrestations(unwrapList(data))
    } catch {
      // aucun catalogue source disponible
    }
  }
}

async function fetchGrilles() {
  grilles.value = normalizeGrilles(unwrapList(await api.get('/grille_tarifaires?itemsPerPage=400')))
}

function goPrevStep() {
  const ids = steps.value.map((item) => item.id)
  const index = ids.indexOf(step.value)
  if (index > 0) {
    step.value = ids[index - 1]
  }
}

function goNextStep() {
  const ids = steps.value.map((item) => item.id)
  const index = ids.indexOf(step.value)
  if (index >= 0 && index < ids.length - 1) {
    step.value = ids[index + 1]
  }
}

function addExceptionalClosureDate() {
  if (!newClosureDate.value) return

  const current = Array.isArray(config.value.dates_fermeture_exceptionnelles)
    ? [...config.value.dates_fermeture_exceptionnelles]
    : []

  if (!current.includes(newClosureDate.value)) {
    current.push(newClosureDate.value)
    current.sort()
    config.value.dates_fermeture_exceptionnelles = current
  }

  newClosureDate.value = ''
}

function removeExceptionalClosureDate(date: string) {
  const current = Array.isArray(config.value.dates_fermeture_exceptionnelles)
    ? [...config.value.dates_fermeture_exceptionnelles]
    : []

  config.value.dates_fermeture_exceptionnelles = current.filter((item: string) => item !== date)
}

async function saveConfig() {
  saving.value = true
  try {
    const configPayload = { ...config.value }
    if (!isSuperAdmin.value) {
      delete configPayload.feature_modules
    }

    const result = await api.put('/config', {
      config: configPayload,
      atelier: atelier.value,
      horaires: horaires.value,
    })

    config.value = {
      ...config.value,
      ...result,
      feature_modules: normalizeFeatureModules(result?.feature_modules ?? result?.featureModules ?? config.value.feature_modules),
    }
    if (result?.atelier) {
      atelier.value = { ...atelier.value, ...result.atelier }
      atelierStore.setBranding(atelier.value)
    }
    atelierStore.setModules(config.value.feature_modules)

    toast.add({ title: 'Configuration sauvegardée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Sauvegarde impossible', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function onLogoChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  uploadingLogo.value = true
  try {
    const formData = new FormData()
    formData.append('logo', file)
    const result = await api.upload('/config/logo', formData)
    atelier.value.logo_url = result?.logo_url || result?.atelier?.logo_url || atelier.value.logo_url
    atelierStore.setBranding(atelier.value)
    toast.add({ title: 'Logo enregistré', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur logo', description: e?.message || 'Téléversement impossible', color: 'error' })
  } finally {
    uploadingLogo.value = false
    target.value = ''
  }
}

async function toggleCategory(cat: any) {
  togglingCategoryId.value = cat.id
  try {
    const nextValue = isCategoryActive(cat) ? 0 : 1
    await api.put(`/motos/categories/${cat.id}`, {
      nom: cat.nom,
      description: cat.description || null,
      is_active: nextValue,
    })

    cat.is_active = nextValue
    toast.add({ title: `Type moto ${nextValue ? 'activé' : 'désactivé'}`, color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Mise à jour impossible', color: 'error' })
  } finally {
    togglingCategoryId.value = null
  }
}

function openTarifModal(prestation: any) {
  selectedPrestation.value = prestation
  const byCategorie = new Map(
    grilles.value
      .filter((g: any) => g.prestation_id === prestation.id)
      .map((g: any) => [g.categorie_id, g])
  )

  tarifRows.value = activeCategories.value.map((cat: any) => {
    const existing = byCategorie.get(cat.id)
    return {
      id: existing?.id ?? null,
      prestation_id: prestation.id,
      categorie_id: cat.id,
      categorie_label: cat.nom,
      prix_ttc: toNumber(existing?.prix_ttc ?? prestation.prix_ttc, 0),
      temps_minutes: toNumber(existing?.temps_minutes ?? prestation.temps_estime, 30),
      type_tarif: existing?.type_tarif ?? prestation.type_tarif ?? 'forfait',
      is_active: Number(existing?.is_active ?? 1),
    }
  })

  tarifModalOpen.value = true
}

async function saveTarifModal() {
  if (!selectedPrestation.value) return

  modalSaving.value = true
  try {
    await Promise.all(
      tarifRows.value.map(async (row: any) => {
        const payload = buildGrillePayload(row)
        if (row.id) {
          await api.patch(`/grille_tarifaires/${row.id}`, payload)
          return
        }

        if (Number(row.is_active ?? 1) === 1) {
          await api.post('/grille_tarifaires', payload)
        }
      })
    )

    await fetchGrilles()
    tarifModalOpen.value = false
    toast.add({ title: 'Tarifs prestation enregistrés', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Enregistrement impossible', color: 'error' })
  } finally {
    modalSaving.value = false
  }
}

async function seedBaseTarifs() {
  if (!activeCategories.value.length || !prestations.value.length) return

  seedLoading.value = true
  try {
    const result = await api.post('/config/seed-tarifs', {})
    await fetchGrilles()

    toast.add({
      title: result?.created ? `${result.created} tarifs initiaux créés` : 'Les tarifs étaient déjà présents',
      color: 'success',
    })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Pré-remplissage impossible', color: 'error' })
  } finally {
    seedLoading.value = false
  }
}

watch(tarifModalOpen, (open) => {
  if (!open) {
    selectedPrestation.value = null
    tarifRows.value = []
  }
})

watch(steps, (items) => {
  if (!items.some((item) => item.id === step.value)) {
    step.value = items[0]?.id ?? 1
  }
}, { immediate: true })

onMounted(async () => {
  try {
    const [c, h, cats, prests, grilleRows] = await Promise.all([
      api.get('/config'),
      api.get('/config/horaires'),
      api.get('/motos/categories?itemsPerPage=200'),
      api.get('/prestations?itemsPerPage=200'),
      api.get('/grille_tarifaires?itemsPerPage=400'),
    ])

    config.value = {
      ...config.value,
      ...c,
      feature_modules: normalizeFeatureModules(c?.feature_modules ?? c?.featureModules ?? config.value.feature_modules),
    }
    atelierStore.setModules(config.value.feature_modules)
    if (c?.atelier) {
      atelier.value = { ...atelier.value, ...c.atelier }
      atelierStore.setBranding(atelier.value)
    }
    horaires.value = normalizeHoraires(h)
    categories.value = normalizeCategories(unwrapList(cats))
    prestations.value = normalizePrestations(unwrapList(prests))
    grilles.value = normalizeGrilles(unwrapList(grilleRows))
  } finally {
    loading.value = false
  }
})
</script>
