<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="page-title">Configuration atelier</div>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:var(--content-3);">Chargement...</span>
    </div>

    <div v-else style="display:flex;flex-direction:column;gap:16px;max-width:1100px;">
      <!-- Sommaire : tout est sur une seule page, ces liens font défiler. -->
      <div class="sommaire">
        <a v-for="sec in sections" :key="sec.id" :href="`#${sec.id}`" class="sommaire-lien">
          {{ sec.titre }}
        </a>
      </div>

      <form @submit.prevent="saveConfig" style="display:flex;flex-direction:column;gap:16px;">
        <UCard id="sec-atelier">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Identité de l'atelier</span></template>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <UFormField label="Nom atelier"><UInput v-model="atelier.nom" /></UFormField>
            <UFormField label="Téléphone"><UInput v-model="atelier.telephone" /></UFormField>
            <UFormField label="Email"><UInput v-model="atelier.email" type="email" /></UFormField>
            <UFormField label="SIRET"><UInput v-model="atelier.siret" /></UFormField>
            <UFormField label="TVA intracom"><UInput v-model="atelier.tva_intracom" /></UFormField>
            <UFormField label="Code postal"><UInput v-model="atelier.cp" /></UFormField>
            <UFormField label="Ville"><UInput v-model="atelier.ville" /></UFormField>
            <UFormField label="Adresse" style="grid-column:1 / -1;"><UInput v-model="atelier.adresse" /></UFormField>
          </div>

          <div style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
            <UFormField label="TVA MO (%)"><UInput v-model="config.tva_mo_taux" type="number" step="0.1" /></UFormField>
            <UFormField label="TVA pièces (%)"><UInput v-model="config.tva_pieces_taux" type="number" step="0.1" /></UFormField>
            <UFormField label="Taux horaire standard"><UInput v-model="config.taux_horaire_mo_standard" type="number" step="0.1" /></UFormField>
            <UFormField label="Acompte (%)"><UInput v-model="config.accompte_pourcentage" type="number" step="1" /></UFormField>
            <UFormField label="Garantie travaux (jours)"><UInput v-model="config.garantie_travaux_jours" type="number" step="1" /></UFormField>
            <UFormField label="Gardiennage / jour (€)"><UInput v-model="config.tarif_gardiennage_journalier" type="number" step="0.1" /></UFormField>
          </div>
        </UCard>

        <UCard id="sec-logo">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Logo et image atelier</span></template>
          <div style="display:grid;grid-template-columns:240px 1fr;gap:16px;align-items:start;">
            <div style="border:1px solid var(--border-2);border-radius:12px;padding:12px;min-height:180px;display:flex;align-items:center;justify-content:center;background:var(--overlay-soft);">
              <img v-if="atelier.logo_url" :src="atelier.logo_url" alt="Logo atelier" style="max-width:100%;max-height:150px;object-fit:contain;" />
              <span v-else style="color:var(--content-3);font-size:13px;">Aucun logo</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <div style="font-size:13px;color:var(--content-2);">Ajoute ici le logo affiché sur les documents et les écrans de l'atelier.</div>
              <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input" @change="onLogoChange" />
              <div style="font-size:12px;color:var(--content-3);">Formats acceptés : PNG, JPG, WebP ou SVG.</div>
              <div v-if="uploadingLogo" style="font-size:12px;color:var(--accent-content);">Téléversement en cours…</div>
            </div>
          </div>
        </UCard>

        <UCard id="sec-horaires">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Horaires, fermetures et alertes</span></template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="h in horaires" :key="h.jour_semaine" style="display:grid;grid-template-columns:90px 1fr 1fr 1fr 1fr auto;gap:8px;align-items:center;font-size:13px;">
              <span style="font-weight:600;color:var(--content-1);">{{ jourLabel(h.jour_semaine) }}</span>
              <UInput v-model="h.heure_ouverture" type="time" :disabled="!h.is_ouvert" size="sm" />
              <UInput v-model="h.heure_fermeture" type="time" :disabled="!h.is_ouvert" size="sm" />
              <UInput v-model="h.pause_debut" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause début" />
              <UInput v-model="h.pause_fin" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause fin" />
              <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--content-3);">
                <input v-model="h.is_ouvert" type="checkbox" />
                Ouvert
              </label>
            </div>

            <div style="margin-top:12px;border:1px solid var(--border-2);border-radius:12px;padding:14px;background:var(--overlay-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--content-1);">Jours fermés hebdomadaires</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <label v-for="option in closureDayOptions" :key="option.value" style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--content-2);padding:6px 10px;border-radius:999px;background:var(--overlay-soft);">
                  <input v-model="config.jours_fermeture_hebdo" type="checkbox" :value="option.value" />
                  {{ option.label }}
                </label>
              </div>
            </div>

            <div style="border:1px solid var(--border-2);border-radius:12px;padding:14px;background:var(--overlay-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--content-1);">Fermetures exceptionnelles</div>
              <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                <UInput v-model="newClosureDate" type="date" style="max-width:220px;" />
                <button type="button" class="btn btn-ghost" @click="addExceptionalClosureDate">Ajouter la date</button>
              </div>
              <div v-if="config.dates_fermeture_exceptionnelles?.length" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <span v-for="date in config.dates_fermeture_exceptionnelles" :key="date" style="display:inline-flex;align-items:center;gap:6px;font-size:11px;padding:4px 8px;border-radius:999px;background:var(--warning-soft);color:var(--warning-content);">
                  {{ date }}
                  <button type="button" aria-label="Retirer cette date de fermeture" style="background:none;border:none;color:inherit;cursor:pointer;" @click="removeExceptionalClosureDate(date)"><AppIcon name="i-ri-close-line" /></button>
                </span>
              </div>
              <div v-else style="font-size:12px;color:var(--content-3);margin-top:10px;">Aucune fermeture exceptionnelle enregistrée.</div>
            </div>

            <div style="border:1px solid var(--border-2);border-radius:12px;padding:14px;background:var(--overlay-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--content-1);">Motos en atelier — alerte de séjour prolongé</div>
              <div style="font-size:11px;color:var(--content-3);margin-top:2px;margin-bottom:10px;">
                Au-delà de ce seuil, la moto est signalée dans l'onglet « En atelier », sur le planning et
                le tableau de bord. Le décompte est en heures <strong>ouvrées</strong> : les jours de
                fermeture (week-end, fériés, fermetures exceptionnelles) ne comptent pas.
              </div>
              <div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">
                <label style="display:block;">
                  <span style="display:block;font-size:11px;color:var(--content-3);margin-bottom:4px;">Seuil d'alerte (heures ouvrées)</span>
                  <input
                    v-model.number="config.seuil_sejour_atelier_heures"
                    type="number"
                    min="1"
                    max="8760"
                    step="1"
                    class="form-input"
                    style="max-width:160px;"
                    data-testid="seuil-sejour-atelier"
                  />
                </label>
                <div style="font-size:11px;color:var(--content-3);padding-bottom:10px;">
                  ≈ {{ seuilSejourEnJours }} — {{ seuilSejourExemple }}
                </div>
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <button
                  v-for="preset in SEUILS_SEJOUR_PRESETS"
                  :key="preset.heures"
                  type="button"
                  class="btn btn-ghost"
                  style="padding:6px 10px;font-size:11px;"
                  @click="config.seuil_sejour_atelier_heures = preset.heures"
                >
                  {{ preset.label }}
                </button>
              </div>
              <button
                type="button"
                class="btn"
                :class="isAlerteSejourActive ? 'btn-primary' : 'btn-ghost'"
                style="padding:8px 12px;font-size:12px;margin-top:12px;"
                data-testid="toggle-alerte-sejour"
                @click="toggleAlerteSejour"
              >
                {{ isAlerteSejourActive ? 'i-ri-notification-3-line' : 'i-ri-notification-off-line' }} Alerte automatique (notification + e-mail quotidien)
              </button>
              <div style="font-size:11px;color:var(--content-3);margin-top:6px;">
                Alerte coupée : l'onglet « En atelier » reste consultable, mais plus aucune notification
                ni e-mail n'est envoyé.
              </div>
            </div>
          </div>
        </UCard>

        <UCard id="sec-regles">
          <template #header>
            <div>
              <span style="font-size:15px;font-weight:700;color:var(--content-1);">Règles métier</span>
              <div style="font-size:11px;color:var(--content-3);margin-top:2px;">
                Ces règles étaient auparavant figées dans le code : elles se pilotent maintenant ici.
              </div>
            </div>
          </template>

          <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="regle">
              <div class="regle-titre">Photos d'entrée exigées</div>
              <div class="regle-aide">
                Nombre minimum de photos à prendre au dépôt pour pouvoir signer l'état des lieux
                (0 = aucune obligation). Sert de preuve en cas de litige sur l'état de la moto.
              </div>
              <input
                v-model.number="config.min_photos_entree"
                type="number" min="0" max="20" step="1"
                class="form-input regle-champ"
                data-testid="regle-min-photos"
              />
            </div>

            <div class="regle">
              <div class="regle-titre">Rappels avant rendez-vous</div>
              <div class="regle-aide">
                Combien de jours avant le RDV le client est prévenu. Deux rappels maximum
                (le plus proche du RDV utilise le message « veille »).
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <label class="regle-sous-champ">
                  <span>Rappel proche (jours avant)</span>
                  <input
                    v-model.number="rappelProche"
                    type="number" min="1" max="60" step="1"
                    class="form-input regle-champ"
                    data-testid="regle-rappel-proche"
                  />
                </label>
                <label class="regle-sous-champ">
                  <span>Rappel anticipé (jours avant, vide = aucun)</span>
                  <input
                    v-model="rappelAnticipe"
                    type="number" min="1" max="60" step="1"
                    class="form-input regle-champ"
                    data-testid="regle-rappel-anticipe"
                  />
                </label>
              </div>
            </div>

            <div class="regle">
              <div class="regle-titre">Relance des travaux supplémentaires</div>
              <div class="regle-aide">
                Délai sans réponse du client avant de le relancer automatiquement, et plage horaire
                pendant laquelle ces envois sont autorisés (pas de SMS la nuit).
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <label class="regle-sous-champ">
                  <span>Relancer après (heures)</span>
                  <input
                    v-model.number="config.relance_travaux_delai_heures"
                    type="number" min="1" max="168" step="1"
                    class="form-input regle-champ"
                    data-testid="regle-relance-delai"
                  />
                </label>
                <label class="regle-sous-champ">
                  <span>Envois à partir de (heure)</span>
                  <input
                    v-model.number="config.relance_heure_min"
                    type="number" min="0" max="23" step="1"
                    class="form-input regle-champ"
                  />
                </label>
                <label class="regle-sous-champ">
                  <span>Envois jusqu'à (heure)</span>
                  <input
                    v-model.number="config.relance_heure_max"
                    type="number" min="1" max="24" step="1"
                    class="form-input regle-champ"
                  />
                </label>
              </div>
              <div v-if="fenetreIncoherente" class="regle-alerte">
                L'heure de fin doit être postérieure à l'heure de début.
              </div>
            </div>

            <div class="regle">
              <div class="regle-titre">Validité des liens clients</div>
              <div class="regle-aide">
                Durée pendant laquelle un lien de suivi envoyé par e-mail ou SMS reste consultable
                après la clôture du dossier. Passé ce délai, le lien ne montre plus les données du client.
              </div>
              <input
                v-model.number="config.lien_public_jours"
                type="number" min="1" max="3650" step="1"
                class="form-input regle-champ"
                data-testid="regle-lien-public"
              />
              <span class="regle-unite">jours après la date du rendez-vous</span>
            </div>

            <div class="regle">
              <div class="regle-titre">Essai routier</div>
              <div class="regle-aide">
                Nombre de points de contrôle que le mécanicien doit renseigner pour valider un essai
                routier (l'essai validé est exigé avant de terminer une intervention).
              </div>
              <input
                v-model.number="config.essai_points_min"
                type="number" min="0" max="50" step="1"
                class="form-input regle-champ"
                data-testid="regle-essai-points"
              />
              <span class="regle-unite">points minimum</span>
            </div>

            <div class="regle">
              <div class="regle-titre">Rappel d'une alerte « moto en atelier »</div>
              <div class="regle-aide">
                Délai avant qu'une moto déjà signalée le soit à nouveau, pour éviter une notification
                identique chaque jour.
              </div>
              <input
                v-model.number="config.rappel_alerte_heures"
                type="number" min="1" max="720" step="1"
                class="form-input regle-champ"
                data-testid="regle-rappel-alerte"
              />
              <span class="regle-unite">heures</span>
            </div>

            <div class="regle">
              <div class="regle-titre">Intervalle de vidange</div>
              <div class="regle-aide">
                Valeurs suggérées au mécanicien à la restitution (« prochaine vidange »),
                ajustables OR par OR. Le rappel envoyé au client se déclenche dès que l'un
                des deux seuils est atteint, au premier des deux — comme sur une notice
                constructeur.
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <label class="regle-sous-champ">
                  <span>Tous les (km)</span>
                  <input
                    v-model.number="config.vidange_intervalle_km"
                    type="number" min="500" max="50000" step="500"
                    class="form-input regle-champ"
                    data-testid="regle-vidange-km"
                  />
                </label>
                <label class="regle-sous-champ">
                  <span>Ou tous les (mois)</span>
                  <input
                    v-model.number="config.vidange_intervalle_mois"
                    type="number" min="1" max="60" step="1"
                    class="form-input regle-champ"
                    data-testid="regle-vidange-mois"
                  />
                </label>
              </div>
            </div>

            <div class="regle regle--info">
              <div class="regle-titre">Non modifiable ici</div>
              <div class="regle-aide">
                Les durées de conservation RGPD (anonymisation à 3 ans, factures 10 ans), les limites
                de taille des fichiers et les durées de session restent fixées dans le code : ce sont
                des obligations légales ou des garde-fous de sécurité, pas des choix d'exploitation.
              </div>
            </div>
          </div>
        </UCard>

        <UCard id="sec-types-moto">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Types de moto activés</span></template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="font-size:13px;color:var(--content-2);">Les types déjà en base se pilotent ici en simple toggle.</div>

            <div v-if="categories.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
              <div
                v-for="cat in categories"
                :key="cat.id"
                :style="{
                  border: `1px solid ${isCategoryActive(cat) ? 'var(--accent)' : 'var(--border-2)'}`,
                  borderRadius: '10px',
                  padding: '12px',
                  background: isCategoryActive(cat) ? 'var(--accent-soft)' : 'var(--overlay-soft)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: '10px',
                  alignItems: 'start',
                }"
              >
                <div>
                  <div style="font-weight:700;color:var(--content-1);">{{ cat.nom }}</div>
                  <div v-if="cat.description" style="font-size:12px;color:var(--content-3);margin-top:4px;">{{ cat.description }}</div>
                  <div style="font-size:11px;color:var(--content-2);margin-top:8px;">
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
            <div v-else style="font-size:13px;color:var(--content-3);">Aucun type moto trouvé.</div>
          </div>
        </UCard>

        <UCard id="sec-tarifs">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Tarifs</span></template>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="font-size:13px;color:var(--content-2);">
              Les prix, temps et modes de tarification par type de moto se gèrent dans l'onglet
              <strong>Prestations</strong>, qui présente la liste complète et le même écran de configuration.
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <NuxtLink to="/admin/prestations" class="btn btn-primary" style="padding:8px 12px;font-size:12px;text-decoration:none;">
                Ouvrir l'onglet Prestations
              </NuxtLink>
              <button
                class="btn btn-ghost"
                type="button"
                style="padding:8px 12px;font-size:12px;"
                :disabled="seedLoading || !activeCategories.length || !prestations.length"
                @click="seedBaseTarifs"
              >
                {{ seedLoading ? 'Pré-remplissage…' : 'Pré-remplir les premiers tarifs' }}
              </button>
              <span v-if="!activeCategories.length" style="font-size:11px;color:var(--error-content);">
                Active d'abord au moins un type de moto ci-dessus.
              </span>
            </div>
          </div>
        </UCard>

        <UCard v-if="isSuperAdmin" id="sec-modules">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Modules de l'application</span></template>
          <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="border:1px solid var(--accent);border-radius:12px;padding:14px;background:var(--accent-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--warning-content);">Pilote ici le périmètre de l'atelier</div>
              <div style="font-size:12px;color:var(--content-1);margin-top:4px;">Quand un module est coupé, le menu, les pages et les actions associées disparaissent du workflow.</div>
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
                  <span style="font-size:20px;line-height:1;"><AppIcon :name="item.icon" /></span>
                  <span>
                    <span style="display:block;font-size:13px;font-weight:700;">{{ item.label }}</span>
                    <span style="display:block;font-size:11px;opacity:0.86;margin-top:2px;">{{ item.hint }}</span>
                  </span>
                </span>
                <span style="font-size:11px;line-height:1.45;opacity:0.88;">{{ item.impact }}</span>
                <span style="margin-top:auto;font-size:11px;font-weight:700;padding:4px 8px;border-radius:999px;background:var(--overlay-hover);">
                  {{ isFeatureModuleEnabled(item.key) ? 'Activé' : 'Désactivé' }}
                </span>
              </button>
            </div>

            <div v-if="disabledModuleLabels.length" style="border:1px solid var(--warning);border-radius:12px;padding:12px;background:var(--warning-soft);">
              <div style="font-size:12px;font-weight:700;color:var(--warning-content);">Modules actuellement masqués du workflow</div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                <span v-for="label in disabledModuleLabels" :key="label" style="font-size:11px;padding:4px 8px;border-radius:999px;background:var(--overlay-hover);color:var(--warning-content);">{{ label }}</span>
              </div>
            </div>

            <div v-else style="border:1px solid var(--success);border-radius:12px;padding:12px;background:var(--success-soft);font-size:12px;color:var(--success-content);">
              Tous les modules principaux sont actifs. Le parcours atelier reste complet.
            </div>

            <div style="border:1px solid var(--border-2);border-radius:12px;padding:14px;background:var(--overlay-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--content-1);">Notifications client par étape</div>
              <div style="font-size:11px;color:var(--content-3);margin-top:2px;margin-bottom:10px;">
                Le client reçoit un email/SMS à chaque étape activée. Couper une étape n'affecte ni le suivi en ligne ni l'historique.
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button
                  v-for="etape in notificationEtapeDefinitions"
                  :key="etape.key"
                  type="button"
                  class="btn"
                  :class="isNotificationEtapeEnabled(etape.key) ? 'btn-primary' : 'btn-ghost'"
                  style="padding:8px 12px;font-size:12px;"
                  :title="etape.hint"
                  @click="toggleNotificationEtape(etape.key)"
                >
                  {{ isNotificationEtapeEnabled(etape.key) ? 'i-ri-notification-3-line' : 'i-ri-notification-off-line' }} {{ etape.label }}
                </button>
              </div>
            </div>

            <div style="border:1px solid var(--border-2);border-radius:12px;padding:14px;background:var(--overlay-soft);">
              <div style="font-size:13px;font-weight:700;color:var(--content-1);">Check-in / état des lieux</div>
              <div style="font-size:11px;color:var(--content-3);margin-top:2px;margin-bottom:10px;">
                Quand l'option est active, la moto ne peut pas passer en réception sans un état des lieux d'entrée signé par le client.
              </div>
              <button
                type="button"
                class="btn"
                :class="isCheckinObligatoire ? 'btn-primary' : 'btn-ghost'"
                style="padding:8px 12px;font-size:12px;"
                data-testid="toggle-checkin-obligatoire"
                @click="toggleCheckinObligatoire"
              >
                {{ isCheckinObligatoire ? 'i-ri-lock-line' : 'i-ri-lock-unlock-line' }} Check-in obligatoire avant réception
              </button>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
              <div style="border:1px solid var(--border-2);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:var(--content-3);">Atelier</div>
                <div style="font-size:15px;font-weight:700;color:var(--content-1);">{{ atelier.nom || 'Non renseigné' }}</div>
                <div style="font-size:12px;color:var(--content-2);margin-top:4px;">{{ atelier.telephone || 'Téléphone manquant' }}</div>
              </div>
              <div style="border:1px solid var(--border-2);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:var(--content-3);">Modules</div>
                <div style="font-size:15px;font-weight:700;color:var(--content-1);">{{ activeModuleCount }} / {{ moduleDefinitions.length }} actifs</div>
              </div>
              <div style="border:1px solid var(--border-2);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:var(--content-3);">Horaires</div>
                <div style="font-size:15px;font-weight:700;color:var(--content-1);">{{ horaires.filter((h) => h.is_ouvert).length }} jours ouverts</div>
              </div>
              <div style="border:1px solid var(--border-2);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:var(--content-3);">Types moto</div>
                <div style="font-size:15px;font-weight:700;color:var(--content-1);">{{ activeCategories.length }} actifs</div>
              </div>
              <div style="border:1px solid var(--border-2);border-radius:10px;padding:12px;">
                <div style="font-size:12px;color:var(--content-3);">Tarifs par type</div>
                <div style="font-size:15px;font-weight:700;color:var(--content-1);">{{ grilles.filter((g) => Number(g.is_active ?? 1) === 1).length }} lignes actives</div>
              </div>
            </div>
          </div>
        </UCard>

        <div class="barre-enregistrer">
          <span style="font-size:11px;color:var(--content-3);">Les modifications ne sont appliquées qu'après enregistrement.</span>
          <AppButton variant="primary" type="submit" label="Enregistrer la configuration" :loading="saving" />
        </div>
      </form>
    </div>

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
const togglingCategoryId = ref<number | null>(null)

const isSuperAdmin = computed(() => {
  const rolesList = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || rolesList.includes('ROLE_SUPER_ADMIN')
})

// Toutes les sections sont affichées à la suite : le sommaire ne sert qu'à naviguer.
const sections = computed(() => {
  const base = [
    { id: 'sec-atelier', titre: 'Identité' },
    { id: 'sec-logo', titre: 'Logo' },
    { id: 'sec-horaires', titre: 'Horaires & alertes' },
    { id: 'sec-regles', titre: 'Règles métier' },
    { id: 'sec-types-moto', titre: 'Types de moto' },
    { id: 'sec-tarifs', titre: 'Tarifs' },
  ]

  if (isSuperAdmin.value) {
    base.push({ id: 'sec-modules', titre: 'Modules' })
  }

  return base
})

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
  notifications_etapes: {},
  checkin_obligatoire: true,
  seuil_sejour_atelier_heures: 72,
  alerte_sejour_atelier_active: true,
  min_photos_entree: 4,
  relance_travaux_delai_heures: 4,
  relance_heure_min: 8,
  relance_heure_max: 19,
  rappels_rdv_jours: [1, 3],
  lien_public_jours: 30,
  essai_points_min: 5,
  rappel_alerte_heures: 24,
  vidange_intervalle_km: 7000,
  vidange_intervalle_mois: 12,
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



const moduleDefinitions = [
  { key: 'devis', label: 'Devis', icon: 'i-ri-draft-line', hint: 'Création et suivi des devis', impact: 'Masque la création, la consultation et la conversion des devis.' },
  { key: 'facturation', label: 'Facturation', icon: 'i-ri-bank-card-line', hint: 'Factures, paiements et encaissements', impact: 'Retire la création de facture, l’encaissement et les écrans de paiement.' },
  { key: 'stock', label: 'Stock', icon: 'i-ri-archive-line', hint: 'Pièces détachées et alertes', impact: 'Supprime les alertes de stock et la gestion des pièces atelier.' },
  { key: 'suivi', label: 'Suivi live', icon: 'i-ri-eye-line', hint: 'Vue temps réel atelier', impact: 'Cache la vue live et les indicateurs temps réel de l’atelier.' },
  { key: 'motos', label: 'Catalogue motos', icon: 'i-ri-motorbike-line', hint: 'Référentiel et fiches moto', impact: 'Masque les fiches moto et le catalogue de référence.' },
  { key: 'rdv_siege', label: 'Prise de RDV par le siège', icon: 'i-ri-building-line', hint: 'Autorise le service client à prendre des RDV pour cet atelier depuis le siège', impact: 'Si désactivé, le service client ne peut ni voir ni réserver pour cet atelier hors contexte local.' },
  { key: 'vo', label: 'Véhicules d’Occasion', icon: 'i-ri-price-tag-3-line', hint: 'Rachat, dépôt-vente, livre de police et facturation VO', impact: 'Masque le menu VO et toutes les opérations d’achat-vente d’occasion.' },
]

// Lot A — chaque étape activée envoie un email/SMS au client (transparence max par défaut)
const notificationEtapeDefinitions = [
  { key: 'rdv_confirmation', label: 'RDV confirmé', hint: 'À la confirmation du rendez-vous' },
  { key: 'rdv_reception', label: 'Moto réceptionnée', hint: 'Quand la moto est prise en charge à l\'atelier' },
  { key: 'travaux_demarres', label: 'Travaux démarrés', hint: 'Quand la moto monte sur le pont (et à la reprise après pièces)' },
  { key: 'attente_pieces', label: 'Attente de pièces', hint: 'Quand les travaux sont suspendus en attente de pièces' },
  { key: 'travaux_termines', label: 'Moto prête', hint: 'Quand les travaux sont terminés' },
  { key: 'no_show', label: 'RDV manqué', hint: 'Quand le client ne s\'est pas présenté' },
  { key: 'demande_relance', label: 'Relance travaux supp (H+4)', hint: 'Relance automatique si le client n\'a pas répondu à une proposition de travaux' },
]

function isNotificationEtapeEnabled(key: string) {
  const etapes = config.value.notifications_etapes || {}
  // Absence de réglage = activé (transparence par défaut, aligné backend)
  return etapes[key] !== false
}

function toggleNotificationEtape(key: string) {
  const etapes = { ...(config.value.notifications_etapes || {}) }
  etapes[key] = !isNotificationEtapeEnabled(key)
  config.value.notifications_etapes = etapes

  const def = notificationEtapeDefinitions.find((e) => e.key === key)
  toast.add({
    title: `${def?.label || key} : ${etapes[key] ? 'notification activée' : 'notification coupée'}`,
    description: 'Prendra effet après sauvegarde.',
    color: etapes[key] ? 'success' : 'warning',
  })
}

// Lot B — garde workflow : état des lieux signé exigé avant la transition réception
const isCheckinObligatoire = computed(() => config.value.checkin_obligatoire !== false)

function toggleCheckinObligatoire() {
  config.value.checkin_obligatoire = !isCheckinObligatoire.value

  toast.add({
    title: config.value.checkin_obligatoire
      ? 'Check-in obligatoire avant réception : activé'
      : 'Check-in obligatoire avant réception : désactivé',
    description: 'Prendra effet après sauvegarde.',
    color: config.value.checkin_obligatoire ? 'success' : 'warning',
  })
}

// Suivi « moto en atelier » : seuil d'alerte en heures ouvrées + interrupteur d'alerte
const SEUILS_SEJOUR_PRESETS = [
  { heures: 24, label: '1 jour' },
  { heures: 48, label: '2 jours' },
  { heures: 72, label: '3 jours (défaut)' },
  { heures: 120, label: '5 jours' },
  { heures: 168, label: '7 jours' },
]

const seuilSejourEnJours = computed(() => {
  const heures = Number(config.value.seuil_sejour_atelier_heures) || 0
  if (heures < 24) return `${heures} h`
  const jours = Math.floor(heures / 24)
  const reste = heures % 24
  return reste ? `${jours} j ${reste} h` : `${jours} jour${jours > 1 ? 's' : ''}`
})

const seuilSejourExemple = computed(() => {
  const heures = Number(config.value.seuil_sejour_atelier_heures) || 0
  // Les jours fermés étant gelés, un seuil de 72 h reçu vendredi alerte mercredi.
  return heures <= 24
    ? 'alerte dès le lendemain ouvré'
    : `alerte après ${seuilSejourEnJours.value} d'ouverture, week-end et fériés non comptés`
})

// Le serveur stocke une liste de jours ; l'UI expose deux champs explicites.
const rappelProche = computed({
  get: () => (config.value.rappels_rdv_jours ?? [])[0] ?? 1,
  set: (valeur: number) => {
    const anticipe = (config.value.rappels_rdv_jours ?? [])[1]
    const proche = Number(valeur) || 1
    config.value.rappels_rdv_jours = anticipe ? [proche, anticipe] : [proche]
  },
})

const rappelAnticipe = computed({
  get: () => (config.value.rappels_rdv_jours ?? [])[1] ?? '',
  set: (valeur: string | number) => {
    const proche = (config.value.rappels_rdv_jours ?? [])[0] ?? 1
    const anticipe = Number(valeur)
    config.value.rappels_rdv_jours = anticipe > 0 ? [proche, anticipe] : [proche]
  },
})

const fenetreIncoherente = computed(
  () => Number(config.value.relance_heure_max) <= Number(config.value.relance_heure_min),
)

const isAlerteSejourActive = computed(() => config.value.alerte_sejour_atelier_active !== false)

function toggleAlerteSejour() {
  config.value.alerte_sejour_atelier_active = !isAlerteSejourActive.value

  toast.add({
    title: config.value.alerte_sejour_atelier_active
      ? 'Alerte séjour atelier : activée'
      : 'Alerte séjour atelier : désactivée',
    description: 'Prendra effet après sauvegarde.',
    color: config.value.alerte_sejour_atelier_active ? 'success' : 'warning',
  })
}

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
  grilles.value = normalizeGrilles(unwrapList(await api.getAll('/grille_tarifaires?order[id]=asc')))
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

<style scoped>
/* La barre d'onglets est fixée en haut : sans cette marge, un saut d'ancre
   masquerait le titre de la section visée derrière elle. */
:deep([id^='sec-']) {
  scroll-margin-top: 72px;
}

/* Sommaire de sections : la page est déroulante, ces liens ne font que défiler. */
.sommaire {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
}

.sommaire-lien {
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--overlay-soft);
  color: var(--content-2);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}

.sommaire-lien:hover {
  background: var(--accent-soft);
  color: var(--accent-content);
}

/* Le bouton d'enregistrement reste atteignable quelle que soit la section lue. */
.barre-enregistrer {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
}

.regle {
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
}

.regle--info {
  background: var(--overlay-soft);
  border-style: dashed;
}

.regle-titre {
  font-size: 13px;
  font-weight: 700;
  color: var(--content-1);
}

.regle-aide {
  margin: 2px 0 10px;
  font-size: 11px;
  color: var(--content-3);
  max-width: 70ch;
}

.regle-champ {
  max-width: 140px;
}

.regle-sous-champ {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11px;
  color: var(--content-3);
}

.regle-unite {
  margin-left: 8px;
  font-size: 11px;
  color: var(--content-3);
}

.regle-alerte {
  margin-top: 8px;
  font-size: 11px;
  color: var(--error-content);
}
</style>
