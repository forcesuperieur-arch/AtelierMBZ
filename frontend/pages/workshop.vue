<template>
  <div class="pk-atelier">
    <!-- Bandeau de tête — maquette 2c : le titre à gauche, les quatre mesures
         du jour à droite, dans un seul bloc filet plutôt que quatre cartes. -->
    <header class="pk-heading">
      <div class="pk-heading-titles">
        <h1 class="pk-heading-title">Atelier</h1>
        <span class="pk-heading-rule" aria-hidden="true" />
        <p class="pk-heading-sub">Pilotage des ponts, affectations mécaniciens et charge du jour.</p>
      </div>

      <!-- Règle 3 : un chiffre mène quelque part. Les mesures qui ont une
           destination réelle sont des contrôles ; l'occupation n'en a pas dans
           l'app, elle reste du texte plutôt qu'un faux lien. -->
      <div v-if="!loading && !errorMessage" class="pk-strip" data-testid="workshop-mesures">
        <div class="pk-strip-cell">
          <span class="pk-overline">Occupation</span>
          <span class="pk-strip-value">{{ pourcent(kpis.occupation) }}</span>
        </div>

        <NuxtLink class="pk-strip-cell pk-strip-cell--link" to="/planning" data-testid="workshop-mesure-rdv">
          <span class="pk-overline">RDV du jour</span>
          <span class="pk-strip-value">{{ kpis.rdvsToday }}</span>
        </NuxtLink>

        <button type="button" class="pk-strip-cell pk-strip-cell--link" data-testid="workshop-mesure-mecas" @click="activeTab = 'mecas'">
          <span class="pk-overline">Mécaniciens actifs</span>
          <span class="pk-strip-value">{{ kpis.activeMecas }}</span>
        </button>

        <NuxtLink v-if="kpis.conflicts" class="pk-strip-cell pk-strip-cell--link pk-strip-cell--erreur" to="/planning" data-testid="workshop-mesure-conflits">
          <span class="pk-overline">Conflits</span>
          <span class="pk-strip-value">{{ kpis.conflicts }}</span>
        </NuxtLink>
        <div v-else class="pk-strip-cell" data-testid="workshop-mesure-conflits">
          <span class="pk-overline">Conflits</span>
          <span class="pk-strip-value">0</span>
        </div>
      </div>
    </header>

    <AppLoadingState
      v-if="loading"
      title="Chargement de l’atelier"
      description="Les statuts des ponts et de l’équipe sont en cours de récupération."
      :colonnes="3"
      :lignes="4"
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="L’atelier n’a pas répondu"
      :description="errorMessage"
      consequence="Rien n’a été modifié : les ponts gardent leur affectation et leur programme."
      action-label="Réessayer le chargement"
      issue-label="Ouvrir le planning"
      @retry="loadWorkshop"
      @issue="router.push('/planning')"
    />

    <template v-else>
      <div class="pk-toolbar">
        <button class="btn pk-toolbar-btn" :disabled="refreshing" @click="refreshWorkshop">
          <AppIcon v-if="!refreshing" name="i-ri-refresh-line" />{{ refreshing ? 'Actualisation…' : 'Relire l’état des ponts' }}
        </button>
        <NuxtLink class="btn btn-primary pk-toolbar-btn" :to="buildPlanningCreateLink()" data-testid="workshop-poser-rdv">
          Poser un rendez-vous · aujourd’hui
        </NuxtLink>
        <span class="pk-toolbar-stamp">Mis à jour à {{ lastUpdatedAt || 'l’instant' }}</span>
      </div>

      <nav class="pk-tabs" aria-label="Vues de l’atelier">
        <button
          type="button"
          class="pk-tab"
          :class="{ 'pk-tab--on': activeTab === 'ponts' }"
          :aria-current="activeTab === 'ponts' ? 'true' : undefined"
          data-testid="workshop-tab-ponts"
          @click="activeTab = 'ponts'"
        >
          <AppIcon name="i-ri-tools-line" />Ponts
        </button>
        <button
          type="button"
          class="pk-tab"
          :class="{ 'pk-tab--on': activeTab === 'mecas' }"
          :aria-current="activeTab === 'mecas' ? 'true' : undefined"
          data-testid="workshop-tab-mecas"
          @click="activeTab = 'mecas'"
        >
          <AppIcon name="i-ri-user-line" />Mécaniciens
        </button>
        <button
          type="button"
          class="pk-tab"
          :class="{ 'pk-tab--on': activeTab === 'absences' }"
          :aria-current="activeTab === 'absences' ? 'true' : undefined"
          data-testid="workshop-tab-absences"
          @click="activeTab = 'absences'"
        >
          <AppIcon name="i-ri-calendar-line" />Absences
        </button>
        <NuxtLink class="pk-tabs-aside" to="/planning">Voir le planning<AppIcon name="i-ri-arrow-right-line" /></NuxtLink>
      </nav>

      <!-- ═══ PONTS ═══════════════════════════════════════════════════════ -->
      <section v-if="activeTab === 'ponts'" class="pk-view">
        <div class="pk-view-head">
          <p class="pk-lede">Le pont se pilote sur sa carte : mise en service, mécanicien rattaché, programme du jour. Ce qui est enregistré ici part au planning tout de suite.</p>
          <button v-if="peutConfigurerPonts" class="btn btn-primary btn-sm pk-view-action" data-testid="pont-nouveau" @click="ouvrirFichePont(null)">
            Créer un nouveau pont
          </button>
          <p v-else class="pk-note">Créer, renommer ou archiver un pont demande un profil d’administration. La mise en service et le mécanicien rattaché restent pilotables ici.</p>
        </div>

        <div v-if="enrichedPonts.length" class="pk-bays">
          <article
            v-for="pont in enrichedPonts"
            :key="pont.id"
            class="pont-card pk-bay"
            :class="`pk-bay--${etatPont(pont).ton}`"
            :data-testid="`pont-card-${pont.id}`"
          >
            <header class="pk-bay-head">
              <span class="pk-bay-name">{{ pont.nom }}</span>
              <span class="pk-tag" :class="`pk-tag--${etatPont(pont).ton}`">{{ etatPont(pont).mot }}</span>
              <span class="pk-bay-spec">{{ specPont(pont) }}</span>
            </header>

            <div class="pk-bay-config">
              <div class="pk-bay-config-head">
                <span class="pk-overline">Configuration</span>
                <button
                  type="button"
                  class="pk-pillbtn"
                  :class="{ 'pk-pillbtn--accent': !pontSettings[pont.id]?.is_active }"
                  :disabled="pontSettingSaving[pont.id]"
                  :data-testid="`pont-activation-${pont.id}`"
                  @click="togglePontActivation(pont)"
                >
                  {{ pontSettings[pont.id]?.is_active ? 'Mettre hors service' : 'Remettre en service' }}
                </button>
              </div>

              <label class="pk-field-label" :for="`pont-meca-${pont.id}`">Mécanicien rattaché</label>
              <select :id="`pont-meca-${pont.id}`" v-model="pontSettings[pont.id].mecanicien_id" class="pk-field">
                <option :value="null">Aucun mécanicien rattaché</option>
                <option v-for="m in activeMecaniciens" :key="`pont-meca-${pont.id}-${m.id}`" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>

              <!-- Règle 1 : montrer l'effet avant d'enregistrer. Tant que le
                   serveur n'a rien reçu, la carte dit ce que le bouton va faire
                   au planning, pas « modifications en attente ». -->
              <p v-if="affectationModifiee(pont)" class="pk-bay-pending" role="status">
                Pas encore enregistré · {{ resumeModification(pont) }}
              </p>

              <button
                class="btn btn-primary btn-sm pk-bay-save"
                :disabled="pontSettingSaving[pont.id]"
                :data-testid="`pont-enregistrer-${pont.id}`"
                @click="savePontSettings(pont)"
              >
                {{ pontSettingSaving[pont.id] ? 'Enregistrement…' : `Enregistrer l’affectation · ${pont.nom}` }}
              </button>

              <div v-if="peutConfigurerPonts" class="pk-bay-admin">
                <button type="button" class="pk-linkbtn" @click="ouvrirFichePont(pont)">
                  <AppIcon name="i-ri-pencil-line" />Modifier la fiche
                </button>
                <button type="button" class="pk-linkbtn pk-linkbtn--erreur" :disabled="pontSettingSaving[pont.id]" @click="archiverPont(pont)">
                  <AppIcon name="i-ri-archive-line" />Archiver le pont
                </button>
              </div>
            </div>

            <div v-if="pont.current_rdv" class="pk-bay-now">
              <span class="pk-overline">Travaux en cours</span>
              <p class="pk-bay-now-title">{{ libelleProgramme(pont.current_rdv) }}</p>
              <p class="pk-bay-now-line">{{ pont.current_rdv.type_intervention || 'atelier' }} · {{ rdvVehicleLabel(pont.current_rdv) }}</p>
              <div class="pk-bay-now-badge"><StatusBadge :status="pont.current_rdv.status ?? pont.current_rdv.statut" /></div>

              <div v-if="pont.current_rdv.temps_estime" class="pk-gauge">
                <div class="pk-gauge-track">
                  <div
                    class="pk-gauge-fill"
                    :class="{ 'pk-gauge-fill--over': pontProgress(pont) > 100 }"
                    :style="{ width: Math.min(pontProgress(pont), 100) + '%' }"
                  />
                </div>
                <span class="pk-gauge-label">{{ pourcent(pontProgress(pont)) }} du temps vendu · {{ formatDuree(pont.current_rdv.temps_estime) }} annoncées</span>
              </div>

              <button type="button" class="pk-linkbtn" @click="openRdvDetail(pont.current_rdv)">
                Ouvrir le dossier<AppIcon name="i-ri-arrow-right-line" />
              </button>
            </div>

            <div v-else-if="pont.next_rdv" class="pk-bay-now">
              <span class="pk-overline">Prochain passage</span>
              <p class="pk-bay-now-title">{{ formatHourLabel(pont.next_rdv.heure_rdv) }} · {{ libelleProgramme(pont.next_rdv) }}</p>
              <p class="pk-bay-now-line">{{ pont.next_rdv.type_intervention || 'atelier' }} · {{ rdvVehicleLabel(pont.next_rdv) }}</p>
            </div>

            <div v-if="pont.day_schedule.length" class="pk-bay-prog">
              <span class="pk-overline">Programme du jour</span>
              <div
                v-for="rdv in pont.day_schedule.slice(0, 3)"
                :key="rdv.id"
                class="pk-prog-row"
                :class="{ 'pk-prog-row--erreur': statutProgramme(rdv, pont).ton === 'erreur' }"
              >
                <span class="pk-prog-time">{{ formatHourLabel(rdv.heure_rdv) }}</span>
                <span class="pk-prog-label">{{ libelleProgramme(rdv) }}</span>
                <span class="pk-prog-status" :class="`pk-prog-status--${statutProgramme(rdv, pont).ton}`">{{ statutProgramme(rdv, pont).mot }}</span>
              </div>
              <p v-if="pont.day_schedule.length > 3" class="pk-bay-idle">
                {{ pont.day_schedule.length - 3 }} autres rendez-vous sur ce pont aujourd’hui.
              </p>
            </div>

            <!-- Un pont désactivé doit dire ce qu'il advient de sa capacité :
                 c'est la règle 1 appliquée à une bascule (contrat BayControlCard). -->
            <div v-else-if="!isActiveFlag(pont.is_active ?? pont.est_actif)" class="pk-bay-prog">
              <span class="pk-overline">Hors capacité</span>
              <p class="pk-bay-idle">Le pont est exclu du taux d’occupation et du planning tant qu’il est hors service.</p>
            </div>

            <div v-else class="pk-bay-prog">
              <span class="pk-overline">Programme du jour</span>
              <p class="pk-bay-idle">Aucun rendez-vous posé sur ce pont aujourd’hui.</p>
            </div>

            <div class="pk-bay-act">
              <button
                v-if="getPontQuickAction(pont)?.transition"
                class="btn btn-primary pk-bay-act-btn"
                :disabled="actioningByPont[pont.id] === getPontQuickAction(pont)?.transition"
                @click="runPontQuickAction(pont)"
              >
                {{ actioningByPont[pont.id] === getPontQuickAction(pont)?.transition ? 'Enregistrement…' : getPontQuickAction(pont)?.label }}
              </button>
              <button
                v-else-if="getPontQuickAction(pont)?.action"
                class="btn btn-primary pk-bay-act-btn"
                @click="getPontQuickAction(pont)!.action!()"
              >
                {{ getPontQuickAction(pont)?.label }}
              </button>
              <NuxtLink
                v-else-if="getPontQuickAction(pont)?.to"
                :to="getPontQuickAction(pont)!.to"
                class="btn btn-primary pk-bay-act-btn"
              >
                {{ getPontQuickAction(pont)?.label }}
              </NuxtLink>
            </div>

            <footer class="pk-bay-foot">{{ chargeDuJour(pont) }}</footer>
          </article>
        </div>

        <AppEmptyState
          v-else
          icon="i-ri-tools-line"
          title="Aucun pont sur cet atelier"
          description="Un pont se crée ici même, sur cet écran. Tant qu’aucun n’existe, aucun rendez-vous ne peut être posé et le taux d’occupation reste à zéro."
          :action-label="peutConfigurerPonts ? 'Créer un nouveau pont' : ''"
          @action="ouvrirFichePont(null)"
        />
      </section>

      <!-- ═══ MÉCANICIENS ═════════════════════════════════════════════════ -->
      <section v-else-if="activeTab === 'mecas'" class="pk-view">
        <div class="pk-view-head">
          <p class="pk-lede">Qui est là aujourd’hui, qui tient un pont, et sur quoi. L’écart entre temps vendu et temps pointé se lit par prestation, pas par personne.</p>
        </div>

        <div v-if="enrichedMecas.length" class="pk-mecas">
          <article v-for="m in enrichedMecas" :key="m.id" class="pk-meca" :data-testid="`meca-card-${m.id}`">
            <header class="pk-meca-head">
              <span class="pk-meca-initials" :class="`pk-meca-initials--${m.statut.ton}`">{{ initiales(m) }}</span>
              <div class="pk-meca-id">
                <div class="pk-meca-name-row">
                  <span class="pk-meca-name">{{ m.prenom }} {{ m.nom }}</span>
                  <span class="pk-tag" :class="`pk-tag--${m.statut.ton}`">{{ m.statut.mot }}</span>
                </div>
                <span class="pk-meca-role">{{ m.specialite ?? 'Mécanicien' }}</span>
              </div>
            </header>

            <div v-if="m.specialites?.length" class="pk-chips">
              <span v-for="s in m.specialites" :key="s" class="pk-chip">{{ s }}</span>
            </div>

            <div v-if="m.currentRdv" class="pk-meca-now">
              <span class="pk-overline">Sur le pont</span>
              <p class="pk-meca-now-line">{{ libelleProgramme(m.currentRdv) }}</p>
              <p class="pk-meca-now-quiet">{{ m.currentRdv.type_intervention || 'atelier' }}</p>

              <div v-if="m.currentRdv.temps_estime" class="pk-gauge">
                <div class="pk-gauge-track">
                  <div class="pk-gauge-fill" :class="{ 'pk-gauge-fill--over': m.progressPct > 100 }" :style="{ width: Math.min(m.progressPct, 100) + '%' }" />
                </div>
                <span class="pk-gauge-label">{{ pourcent(m.progressPct) }} du temps annoncé</span>
              </div>
            </div>

            <footer class="pk-meca-foot">
              <span class="pk-meca-mail"><AppIcon name="i-ri-mail-line" />{{ m.email ?? 'Adresse non renseignée' }}</span>
              <span>{{ chargeMeca(m) }}</span>
            </footer>
          </article>
        </div>

        <AppEmptyState
          v-else
          icon="i-ri-user-line"
          title="Aucun mécanicien sur cet atelier"
          description="Un mécanicien se crée dans l’administration ; il apparaît ici dès qu’il est rattaché à l’atelier. Sans lui, aucun pont ne peut recevoir d’affectation."
        />
      </section>

      <!-- ═══ ABSENCES ════════════════════════════════════════════════════ -->
      <section v-else class="pk-view">
        <div class="pk-view-head">
          <p class="pk-lede">L’absence se déclare ici, là où le manque se constate. La charge des ponts en tient compte dès l’enregistrement.</p>
          <button v-if="peutConfigurerPonts" class="btn btn-primary btn-sm pk-view-action" data-testid="absence-nouvelle" @click="ouvrirFicheAbsence(null)">
            Déclarer une nouvelle absence
          </button>
          <p v-else class="pk-note">Déclarer ou retirer une absence demande un profil d’administration.</p>
        </div>

        <div v-if="absences.length" class="pk-table" data-testid="absences-table">
          <UTable :data="absences" :columns="absenceCols">
            <template #mecanicien_nom-cell="{ row }">
              <span class="pk-cell-strong">{{ row.original.mecanicien_nom || 'Mécanicien non renseigné' }}</span>
            </template>
            <template #date_debut-cell="{ row }">
              <div class="pk-cell-stack">
                <span>{{ formatDateFr(row.original.date_debut ?? row.original.dateDebut) }}</span>
                <span class="pk-cell-quiet">{{ plageAbsence(row.original) }}</span>
              </div>
            </template>
            <template #date_fin-cell="{ row }">
              <span>{{ formatDateFr(row.original.date_fin ?? row.original.dateFin) }}</span>
            </template>
            <template #motif-cell="{ row }">
              <div class="pk-cell-row">
                <span class="pk-tag" :class="`pk-tag--${motifAbsence(row.original).ton}`">{{ motifAbsence(row.original).label }}</span>
                <span v-if="detailMotifAbsence(row.original)" class="pk-cell-quiet">{{ detailMotifAbsence(row.original) }}</span>
              </div>
            </template>
            <template #actions-cell="{ row }">
              <div v-if="peutConfigurerPonts" class="pk-cell-row">
                <button type="button" class="pk-linkbtn" @click="ouvrirFicheAbsence(row.original)">
                  <AppIcon name="i-ri-pencil-line" />Modifier l’absence
                </button>
                <button type="button" class="pk-linkbtn pk-linkbtn--erreur" @click="supprimerAbsence(row.original)">
                  <AppIcon name="i-ri-close-line" />Retirer l’absence
                </button>
              </div>
            </template>
          </UTable>
        </div>

        <AppNothingToDo
          v-else
          title="Équipe au complet"
          description="Aucune absence n’est déclarée : la charge des ponts est calculée avec toute l’équipe."
        />
      </section>
    </template>

    <!-- Fiche d'un pont — venue d'Administration › Ponts (fusion 8a).
         Activer un pont et lui rattacher un mécanicien est du pilotage
         quotidien : la carte porte son état, elle porte donc sa fiche. -->
    <AppModal v-model:open="fichePontOuverte" size="lg">
      <template #content>
        <div class="pk-form" data-testid="fiche-pont">
          <header class="pk-form-head">
            <h2 class="pk-form-title">{{ fichePontId ? 'Modifier le pont' : 'Nouveau pont' }}</h2>
            <button type="button" class="pk-form-close" aria-label="Fermer la fiche du pont" @click="fichePontOuverte = false">
              <AppIcon name="i-ri-close-line" />
            </button>
          </header>

          <form class="pk-form-body" @submit.prevent="enregistrerFichePont">
            <div class="form-group">
              <label class="form-label" for="pont-nom">Nom du pont</label>
              <input id="pont-nom" v-model="fichePont.nom" class="form-input pk-field" required placeholder="Pont 3" />
              <p class="pk-help">C’est le nom lu au planning et annoncé au comptoir. Sans lui, le pont n’est identifiable nulle part.</p>
            </div>

            <div class="pk-form-duo">
              <div class="form-group">
                <label class="form-label" for="pont-type">Type de pont</label>
                <select id="pont-type" v-model="fichePont.type_pont" class="form-input pk-field">
                  <option value="moto">Moto</option>
                  <option value="diagnostic">Diagnostic</option>
                  <option value="lavage">Lavage</option>
                  <option value="livraison">Livraison</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="pont-capacite">Capacité</label>
                <input id="pont-capacite" v-model.number="fichePont.capacite_kg" type="number" min="100" step="10" class="form-input pk-field" />
                <p class="pk-help">En kilogrammes. Une moto plus lourde que la capacité ne se pose pas sur ce pont.</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="pont-description">Description</label>
              <input id="pont-description" v-model="fichePont.description" class="form-input pk-field" placeholder="Pont principal, atelier rapide" />
            </div>

            <div class="form-group">
              <label class="form-label" for="pont-meca">Mécanicien rattaché</label>
              <select id="pont-meca" v-model="fichePont.mecanicien_id" class="form-input pk-field">
                <option :value="null">Aucun</option>
                <option v-for="m in activeMecaniciens" :key="`fiche-meca-${m.id}`" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>
              <p class="pk-help">Un mécanicien ne tient qu’un pont à la fois : s’il en occupe déjà un, le serveur refuse la fiche et rien n’est enregistré.</p>
            </div>

            <div class="pk-check">
              <input id="pont-actif" v-model="fichePont.est_actif" type="checkbox" class="pk-check-box" />
              <label for="pont-actif" class="pk-check-label">Pont en service</label>
            </div>
            <p class="pk-help">Hors service, le pont quitte le taux d’occupation et le planning. Les rendez-vous déjà passés le gardent en historique.</p>

            <footer class="pk-form-foot">
              <button type="button" class="btn" @click="fichePontOuverte = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="fichePontSaving">
                {{ fichePontSaving ? 'Enregistrement…' : fichePontId ? 'Modifier le pont' : 'Créer le pont' }}
              </button>
            </footer>
          </form>
        </div>
      </template>
    </AppModal>

    <!-- Absence d'un mécanicien — venue d'Administration › Absences (15a).
         La saisie se fait là où on constate le manque, sur l'écran des ponts. -->
    <AppModal v-model:open="ficheAbsenceOuverte" size="lg">
      <template #content>
        <div class="pk-form" data-testid="fiche-absence">
          <header class="pk-form-head">
            <h2 class="pk-form-title">{{ ficheAbsenceId ? 'Modifier l’absence' : 'Nouvelle absence' }}</h2>
            <button type="button" class="pk-form-close" aria-label="Fermer la fiche d’absence" @click="ficheAbsenceOuverte = false">
              <AppIcon name="i-ri-close-line" />
            </button>
          </header>

          <form class="pk-form-body" @submit.prevent="enregistrerFicheAbsence">
            <div class="form-group">
              <label class="form-label" for="abs-meca">Mécanicien</label>
              <select id="abs-meca" v-model="ficheAbsence.mecanicien_id" class="form-input pk-field" required>
                <option :value="null">Choisir…</option>
                <option v-for="m in activeMecaniciens" :key="`abs-meca-${m.id}`" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>
              <p class="pk-help">Sans mécanicien nommé, l’absence ne retire aucun créneau de la charge du jour.</p>
            </div>

            <div class="pk-form-duo">
              <div class="form-group">
                <label class="form-label" for="abs-debut">Date de début</label>
                <input id="abs-debut" v-model="ficheAbsence.date_debut" type="date" class="form-input pk-field" required />
              </div>
              <div class="form-group">
                <label class="form-label" for="abs-fin">Date de fin</label>
                <input id="abs-fin" v-model="ficheAbsence.date_fin" type="date" class="form-input pk-field" required />
              </div>
            </div>

            <div class="pk-form-duo">
              <div class="form-group">
                <label class="form-label" for="abs-hd">Heure de début</label>
                <input id="abs-hd" v-model="ficheAbsence.heure_debut" type="time" class="form-input pk-field" />
              </div>
              <div class="form-group">
                <label class="form-label" for="abs-hf">Heure de fin</label>
                <input id="abs-hf" v-model="ficheAbsence.heure_fin" type="time" class="form-input pk-field" />
              </div>
            </div>
            <p class="pk-help">Heures laissées vides : l’absence court sur la journée entière. Les deux renseignées : seule cette plage bloque le mécanicien — un rendez-vous médical le matin, par exemple.</p>

            <div class="form-group">
              <label class="form-label" for="abs-type">Type de motif</label>
              <select id="abs-type" v-model="ficheAbsence.type_motif" class="form-input pk-field">
                <option value="conge">Congé</option>
                <option value="maladie">Maladie</option>
                <option value="formation">Formation</option>
                <option value="autre">Autre</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="abs-motif">Détail du motif</label>
              <input id="abs-motif" v-model="ficheAbsence.motif" class="form-input pk-field" placeholder="Précisions, si elles servent au planning" />
            </div>

            <footer class="pk-form-foot">
              <button type="button" class="btn" @click="ficheAbsenceOuverte = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="ficheAbsenceSaving">
                {{ ficheAbsenceSaving ? 'Enregistrement…' : ficheAbsenceId ? 'Modifier l’absence' : 'Déclarer l’absence' }}
              </button>
            </footer>
          </form>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const auth = useAuth()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')
// L'onglet « Temps par type » ne portait qu'un renvoi vers la grille tarifaire,
// elle-même supprimée (8a) : les temps se lisent et se règlent dans
// Administration › Prestations, au même endroit que les prix.
const validTabs = ['ponts', 'mecas', 'absences']
const activeTab = ref('ponts')
const ponts = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const absences = ref<any[]>([])
const rdvs = ref<any[]>([])
const lastUpdatedAt = ref('')
const actioningByPont = reactive<Record<number, string>>({})
const pontSettings = reactive<Record<number, { mecanicien_id: number | null; is_active: boolean }>>({})
const pontSettingSaving = reactive<Record<number, boolean>>({})

/** Les cinq tons du design system : un statut se dit en MOT, jamais en couleur seule. */
type TonStatut = 'neutre' | 'succes' | 'attention' | 'erreur' | 'info'

const absenceCols = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
  { key: 'actions', label: '' },
]

function isActiveFlag(value: any): boolean {
  return value !== false && Number(value ?? 1) !== 0
}

function extractDateKey(value: any): string {
  if (!value) return ''
  if (typeof value === 'string') return value.slice(0, 10)
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10)
}

function formatHourLabel(value: any): string {
  if (!value) return '--:--'
  if (typeof value === 'string') {
    const match = value.match(/(\d{2}):(\d{2})/)
    if (match) return `${match[1]}:${match[2]}`
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '--:--' : date.toISOString().slice(11, 16)
}

/**
 * Écriture française des grandeurs, imposée par le design system : espace
 * insécable avant l'unité, durée en « 6 h 20 » et non en « 06:20 » — un
 * horaire et une durée ne se lisent pas de la même façon sur un planning.
 */
function pourcent(value: any): string {
  return `${Math.round(Number(value) || 0)}\u00A0%`
}

function nombreFr(value: any): string {
  return Number(value || 0).toLocaleString('fr-FR')
}

function formatDuree(minutes: any): string {
  const total = Math.max(0, Math.round(Number(minutes ?? 0)))
  const h = Math.floor(total / 60)
  const m = total % 60
  if (!h) return `${m}\u00A0min`
  if (!m) return `${h}\u00A0h`
  return `${h}\u00A0h\u00A0${String(m).padStart(2, '0')}`
}

function formatDateFr(value: any): string {
  const cle = extractDateKey(value)
  if (!cle) return '–'
  const [annee, mois, jour] = cle.split('-')
  return `${jour}/${mois}/${annee}`
}

function getRdvStatus(rdv: any): string {
  return String(rdv?.status ?? rdv?.statut ?? '').toLowerCase()
}

function isFinalStatus(status: string): boolean {
  return ['termine', 'restitue', 'annule', 'facture', 'paye'].includes(status)
}

function rdvClientName(rdv: any): string {
  if (rdv?.client_nom) return rdv.client_nom
  const prenom = rdv?.client?.prenom ?? ''
  const nom = rdv?.client?.nom ?? ''
  return `${prenom} ${nom}`.trim() || 'Client non renseigné'
}

function rdvVehicleLabel(rdv: any): string {
  if (rdv?.vehicule_info) return rdv.vehicule_info
  const parts = [rdv?.vehicule?.marque, rdv?.vehicule?.modele, rdv?.vehicule_plaque ?? rdv?.vehicule?.plaque].filter(Boolean)
  return parts.join(' • ') || 'Véhicule non renseigné'
}

/**
 * « MT-09 · Renard » — la moto d'abord, c'est elle qu'on voit entrer dans
 * l'atelier ; le point médian sépare la machine de son propriétaire.
 */
function libelleProgramme(rdv: any): string {
  const moto = [rdv?.vehicule?.marque, rdv?.vehicule?.modele].filter(Boolean).join(' ')
    || rdv?.vehicule_info
    || rdv?.vehicule_plaque
    || rdv?.vehicule?.plaque
    || ''
  return [moto, rdvClientName(rdv)].filter(Boolean).join(' · ')
}

function asId(value: any): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null
  }
  if (value && typeof value === 'object') {
    return asId(value.id ?? value['@id'])
  }
  return null
}

function normalizeSpecialites(value: any): string[] {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (typeof value === 'string') {
    return value.split(/[;,]/).map((item: string) => item.trim()).filter(Boolean)
  }
  return []
}

const activeMecaniciens = computed(() => {
  return mecaniciens.value
    .filter((m: any) => isActiveFlag(m.is_active ?? m.isActive))
    .sort((a: any, b: any) => `${a.prenom ?? ''} ${a.nom ?? ''}`.localeCompare(`${b.prenom ?? ''} ${b.nom ?? ''}`))
})

function nomMecanicien(id: number | null): string {
  if (!id) return ''
  const meca = mecaniciens.value.find((m: any) => Number(m.id) === Number(id))
  return meca ? `${meca.prenom ?? ''} ${meca.nom ?? ''}`.trim() : ''
}

function syncPontSettings() {
  for (const pont of ponts.value) {
    pontSettings[pont.id] = {
      mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
      is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
    }
  }
}

/** Vrai tant que la carte porte un choix que le serveur n'a pas encore reçu. */
function affectationModifiee(pont: any): boolean {
  const local = pontSettings[pont?.id]
  if (!local) return false
  return asId(local.mecanicien_id) !== asId(pont?.mecanicien?.id ?? pont?.mecanicien_id)
    || local.is_active !== isActiveFlag(pont?.is_active ?? pont?.est_actif)
}

/** Ce que le bouton d'enregistrement va produire, en conséquence et non en catégorie. */
function resumeModification(pont: any): string {
  const local = pontSettings[pont?.id]
  if (!local) return ''
  const morceaux: string[] = []

  if (local.is_active !== isActiveFlag(pont?.is_active ?? pont?.est_actif)) {
    morceaux.push(local.is_active
      ? 'le pont revient dans le taux d’occupation et dans le planning'
      : 'le pont sort du taux d’occupation et du planning')
  }

  if (asId(local.mecanicien_id) !== asId(pont?.mecanicien?.id ?? pont?.mecanicien_id)) {
    const nom = nomMecanicien(asId(local.mecanicien_id))
    morceaux.push(nom ? `${nom} prend ${pont.nom}` : `${pont.nom} n’a plus de mécanicien rattaché`)
  }

  return morceaux.join(', ')
}

async function savePontSettings(pont: any) {
  if (!pont?.id) return
  const settings = pontSettings[pont.id] ?? {
    mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
    is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
  }

  pontSettingSaving[pont.id] = true
  try {
    const mecanicienId = asId(settings.mecanicien_id)
    await api.patch(`/ponts/${pont.id}`, {
      mecanicien_id: mecanicienId,
      mecanicien: mecanicienId ? `/api/mecaniciens/${mecanicienId}` : null,
      est_actif: settings.is_active,
      is_active: settings.is_active ? 1 : 0,
    })
    toast.add({ title: `${pont.nom} mis à jour`, color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: 'Affectation non enregistrée',
      description: messageErreur(e, `${pont.nom} garde son mécanicien et son état actuels`),
      color: 'error',
    })
  } finally {
    pontSettingSaving[pont.id] = false
  }
}

/**
 * Fiche d'un pont — reprise d'Administration › Ponts (fusion 8a).
 *
 * Créer, renommer ou archiver un pont reste réservé à l'administration : le
 * serveur le vérifie, l'interface n'affiche donc les commandes qu'à qui peut
 * les utiliser, plutôt que de laisser un bouton qui échouera.
 */
const peutConfigurerPonts = computed(() => {
  const roles = auth.user.value?.roles ?? []
  const role = String(auth.user.value?.role || '')
  return role === 'admin' || role === 'super_admin' || roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN')
})

const fichePontOuverte = ref(false)
const fichePontId = ref<number | null>(null)
const fichePontSaving = ref(false)
const fichePont = reactive({
  nom: '',
  description: '',
  mecanicien_id: null as number | null,
  est_actif: true,
  type_pont: 'moto',
  capacite_kg: 500,
})

function ouvrirFichePont(pont: any | null) {
  fichePontId.value = pont?.id ?? null
  Object.assign(fichePont, {
    nom: pont?.nom ?? '',
    description: pont?.description ?? '',
    mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
    est_actif: pont ? isActiveFlag(pont.is_active ?? pont.est_actif) : true,
    type_pont: pont?.type_pont || 'moto',
    capacite_kg: Number(pont?.capacite_kg || 500),
  })
  fichePontOuverte.value = true
}

async function enregistrerFichePont() {
  fichePontSaving.value = true
  try {
    const mecanicienId = asId(fichePont.mecanicien_id)
    const payload = {
      nom: fichePont.nom,
      description: fichePont.description,
      type_pont: fichePont.type_pont,
      capacite_kg: Number(fichePont.capacite_kg || 500),
      est_actif: fichePont.est_actif,
      is_active: fichePont.est_actif ? 1 : 0,
      mecanicien: mecanicienId ? `/api/mecaniciens/${mecanicienId}` : null,
    }

    if (fichePontId.value) await api.patch(`/ponts/${fichePontId.value}`, payload)
    else await api.post('/ponts', payload)

    fichePontOuverte.value = false
    toast.add({ title: fichePontId.value ? 'Pont modifié' : 'Pont créé', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: fichePontId.value ? 'Fiche non modifiée' : 'Pont non créé',
      description: messageErreur(e, fichePontId.value
        ? 'le pont garde sa fiche actuelle'
        : 'aucun pont n’a été ajouté à l’atelier'),
      color: 'error',
    })
  } finally {
    fichePontSaving.value = false
  }
}

async function archiverPont(pont: any) {
  if (!pont?.id) return
  if (!confirm(`Archiver le pont ${pont.nom} ? Les rendez-vous passés le gardent en historique.`)) return

  pontSettingSaving[pont.id] = true
  try {
    await api.del(`/ponts/${pont.id}`)
    toast.add({ title: 'Pont archivé', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: 'Pont non archivé',
      description: messageErreur(e, `${pont.nom} reste en service et garde ses rendez-vous`),
      color: 'error',
    })
  } finally {
    pontSettingSaving[pont.id] = false
  }
}

/**
 * Absences — reprises d'Administration › Absences (fusion 15a).
 *
 * Le motif est stocké en un seul champ « type — détail » côté API : on le
 * décompose pour l'affichage et on le recompose à l'enregistrement, comme le
 * faisait l'écran d'administration.
 */
const MOTIFS_ABSENCE: Record<string, { label: string; ton: TonStatut }> = {
  conge: { label: 'Congé', ton: 'info' },
  maladie: { label: 'Maladie', ton: 'erreur' },
  formation: { label: 'Formation', ton: 'info' },
  autre: { label: 'Autre', ton: 'neutre' },
}

function decomposerMotif(absence: any): { type: string; detail: string } {
  const brut = String(absence?.motif ?? '')
  const parts = brut.split(' — ')
  const type = parts[0] in MOTIFS_ABSENCE ? parts[0] : 'autre'
  const detail = parts[0] in MOTIFS_ABSENCE ? parts.slice(1).join(' — ') : brut
  return { type, detail }
}

function motifAbsence(absence: any) {
  return MOTIFS_ABSENCE[decomposerMotif(absence).type] ?? MOTIFS_ABSENCE.autre
}

function detailMotifAbsence(absence: any): string {
  return decomposerMotif(absence).detail
}

// Extrait HH:MM de n'importe quel format d'heure renvoyé par l'API.
function versHeureMinute(value: any): string {
  const m = String(value ?? '').match(/(\d{2}:\d{2})/)
  return m ? m[1] : ''
}

/** Sans heures, l'absence court sur la journée : le dire évite d'ouvrir la fiche. */
function plageAbsence(absence: any): string {
  const debut = versHeureMinute(absence?.heure_debut ?? absence?.heureDebut)
  const fin = versHeureMinute(absence?.heure_fin ?? absence?.heureFin)
  return debut && fin ? `${debut} – ${fin}` : 'Journée entière'
}

const ficheAbsenceOuverte = ref(false)
const ficheAbsenceId = ref<number | null>(null)
const ficheAbsenceSaving = ref(false)
const ficheAbsence = reactive({
  mecanicien_id: null as number | null,
  date_debut: '',
  date_fin: '',
  heure_debut: '',
  heure_fin: '',
  type_motif: 'conge',
  motif: '',
})

function ouvrirFicheAbsence(absence: any | null) {
  const { type, detail } = absence ? decomposerMotif(absence) : { type: 'conge', detail: '' }
  ficheAbsenceId.value = absence?.id ?? null
  Object.assign(ficheAbsence, {
    mecanicien_id: asId(absence?.mecanicien?.id ?? absence?.mecanicien_id),
    date_debut: String(absence?.date_debut ?? absence?.dateDebut ?? '').slice(0, 10),
    date_fin: String(absence?.date_fin ?? absence?.dateFin ?? '').slice(0, 10),
    heure_debut: versHeureMinute(absence?.heure_debut ?? absence?.heureDebut),
    heure_fin: versHeureMinute(absence?.heure_fin ?? absence?.heureFin),
    type_motif: type,
    motif: detail,
  })
  ficheAbsenceOuverte.value = true
}

function construirePayloadAbsence() {
  if (!ficheAbsence.mecanicien_id) throw new Error('Aucun mécanicien n’est nommé : l’absence ne retirerait aucun créneau de la charge du jour.')

  // Absence partielle : les deux heures ensemble, ou aucune (= journée entière).
  const hd = ficheAbsence.heure_debut || null
  const hf = ficheAbsence.heure_fin || null
  if ((hd && !hf) || (!hd && hf)) {
    throw new Error('Une absence partielle demande ses deux heures, début et fin. Laissées vides toutes les deux, elle court sur la journée entière.')
  }
  if (hd && hf && hf <= hd) throw new Error('L’heure de fin doit tomber après l’heure de début, sinon la plage ne bloque aucun créneau.')

  return {
    mecanicien: `/api/mecaniciens/${ficheAbsence.mecanicien_id}`,
    mecanicien_id: ficheAbsence.mecanicien_id,
    date_debut: ficheAbsence.date_debut,
    date_fin: ficheAbsence.date_fin,
    dateDebut: ficheAbsence.date_debut,
    dateFin: ficheAbsence.date_fin,
    heureDebut: hd,
    heureFin: hf,
    motif: [ficheAbsence.type_motif, ficheAbsence.motif].filter(Boolean).join(' — '),
  }
}

async function enregistrerFicheAbsence() {
  ficheAbsenceSaving.value = true
  try {
    const payload = construirePayloadAbsence()

    if (ficheAbsenceId.value) await api.patch(`/absences/${ficheAbsenceId.value}`, payload)
    else await api.post('/absences', payload)

    ficheAbsenceOuverte.value = false
    toast.add({ title: ficheAbsenceId.value ? 'Absence modifiée' : 'Absence déclarée', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: ficheAbsenceId.value ? 'Absence non modifiée' : 'Absence non déclarée',
      description: messageErreur(e, 'la charge des ponts reste calculée avec ce mécanicien'),
      color: 'error',
    })
  } finally {
    ficheAbsenceSaving.value = false
  }
}

async function supprimerAbsence(absence: any) {
  if (!absence?.id) return
  if (!confirm('Supprimer cette absence ? Le mécanicien redevient disponible sur la période.')) return

  try {
    await api.del(`/absences/${absence.id}`)
    toast.add({ title: 'Absence retirée', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: 'Absence non retirée',
      description: messageErreur(e, 'le mécanicien reste indisponible sur la période'),
      color: 'error',
    })
  }
}

async function togglePontActivation(pont: any) {
  const current = pontSettings[pont.id]
  if (!current) return
  current.is_active = !current.is_active
  await savePontSettings(pont)
}

function countConflicts(todayRdvs: any[]): number {
  const seen = new Map<string, number>()
  let conflicts = 0

  for (const rdv of todayRdvs) {
    const hour = formatHourLabel(rdv?.heure_rdv)
    const pontId = rdv?.pont?.id ?? rdv?.pont_id
    const mecaId = rdv?.mecanicien?.id ?? rdv?.mecanicien_id
    const keys = [pontId ? `pont:${pontId}:${hour}` : '', mecaId ? `meca:${mecaId}:${hour}` : ''].filter(Boolean)

    for (const key of keys) {
      const next = (seen.get(key) ?? 0) + 1
      seen.set(key, next)
      if (next === 2) conflicts += 1
    }
  }

  return conflicts
}

/**
 * Deux rendez-vous à la même heure sur le même pont : la carte le dit, plutôt
 * que de laisser le chevauchement se découvrir au planning. Même règle de
 * détection que le compteur « Conflits » du bandeau, restreinte à un pont.
 */
function heuresEnConflit(daySchedule: any[]): Set<string> {
  const vues = new Set<string>()
  const conflits = new Set<string>()
  for (const rdv of daySchedule) {
    const heure = formatHourLabel(rdv?.heure_rdv)
    if (vues.has(heure)) conflits.add(heure)
    else vues.add(heure)
  }
  return conflits
}

const enrichedPonts = computed(() => {
  const today = todayLocalISO()
  const todayRdvs = rdvs.value
    .filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
    .sort((a: any, b: any) => formatHourLabel(a?.heure_rdv).localeCompare(formatHourLabel(b?.heure_rdv)))

  return ponts.value.map((pont: any) => {
    const daySchedule = todayRdvs.filter((r: any) => (r?.pont?.id ?? r?.pont_id) === pont.id)
    const currentFromPlanning = daySchedule.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
    const currentRdv = pont.current_rdv ?? currentFromPlanning ?? null
    const nextRdv = daySchedule.find((r: any) => r.id !== currentRdv?.id) ?? null
    const plannedMinutes = daySchedule.reduce((sum: number, r: any) => sum + Number(r?.temps_estime ?? 60), 0)
    const assignedMecaId = pont?.mecanicien?.id ?? pont?.mecanicien_id ?? null
    const assignedMeca = pont?.mecanicien ?? mecaniciens.value.find((m: any) => m.id === assignedMecaId) ?? null

    return {
      ...pont,
      current_rdv: currentRdv,
      next_rdv: nextRdv,
      day_schedule: daySchedule,
      total_rdvs_today: daySchedule.length,
      planned_minutes: plannedMinutes,
      assigned_meca: assignedMeca,
      conflict_hours: heuresEnConflit(daySchedule),
    }
  })
})

const kpis = computed(() => {
  const total = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif)).length
  const occupied = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif) && (p.current_rdv || p.day_schedule.length)).length
  const occupation = total ? Math.round(occupied / total * 100) : 0
  const today = todayLocalISO()
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))
  const activeMecas = mecaniciens.value.filter((m: any) => isActiveFlag(m.is_active ?? m.isActive) && !absentIds.has(m.id)).length

  return {
    occupation,
    rdvsToday: todayRdvs.length,
    activeMecas,
    conflicts: countConflicts(todayRdvs),
  }
})

const enrichedMecas = computed(() => {
  const today = todayLocalISO()
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))

  return mecaniciens.value.map((m: any) => {
    const mecaRdvs = todayRdvs.filter((r: any) => {
      const mid = r.mecanicien?.id ?? r.mecanicien_id
      return mid === m.id
    })
    const currentRdv = mecaRdvs.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
    const isAbsent = absentIds.has(m.id)
    const isWorking = !!currentRdv
    let progressPct = 0
    if (currentRdv?.temps_estime && (currentRdv.heure_debut_travaux || currentRdv.started_at)) {
      const started = new Date(currentRdv.heure_debut_travaux || currentRdv.started_at)
      if (!isNaN(started.getTime())) {
        progressPct = Math.round((Date.now() - started.getTime()) / 60000 / currentRdv.temps_estime * 100)
      }
    }
    return {
      ...m,
      rdvCount: mecaRdvs.length,
      currentRdv,
      progressPct,
      // Le statut se dit en mot ; le ton ne fait que le doubler.
      statut: isAbsent
        ? { mot: 'Absent', ton: 'erreur' as TonStatut }
        : isWorking
          ? { mot: 'En intervention', ton: 'attention' as TonStatut }
          : { mot: 'Disponible', ton: 'succes' as TonStatut },
      specialites: normalizeSpecialites(m.specialites ?? m.competences),
    }
  })
})

function initiales(meca: any): string {
  return `${(meca?.prenom?.[0] ?? '')}${(meca?.nom?.[0] ?? '')}`.toUpperCase() || '–'
}

function chargeMeca(meca: any): string {
  const n = Number(meca?.rdvCount ?? 0)
  return n ? `${n} rendez-vous aujourd’hui` : 'Aucun rendez-vous aujourd’hui'
}

/** L'état du pont, en mot d'abord — le ton n'est là que pour le doubler. */
function etatPont(pont: any): { mot: string; ton: TonStatut } {
  if (!isActiveFlag(pont?.is_active ?? pont?.est_actif)) return { mot: 'Hors service', ton: 'attention' }
  if (pont?.conflict_hours?.size) return { mot: 'Conflit', ton: 'erreur' }
  if (pont?.current_rdv) return { mot: 'Occupé', ton: 'neutre' }
  if (pont?.day_schedule?.length) return { mot: 'Réservé', ton: 'neutre' }
  return { mot: 'Libre', ton: 'succes' }
}

/** « Type ATELIER · 350 kg » — le type en capitales, seul mot de statut autorisé. */
function specPont(pont: any): string {
  const type = String(pont?.type_pont || 'atelier').toUpperCase()
  const capacite = pont?.capacite_kg ? `${nombreFr(pont.capacite_kg)}\u00A0kg` : 'capacité non renseignée'
  return `Type ${type} · ${capacite}`
}

function statutProgramme(rdv: any, pont: any): { mot: string; ton: TonStatut } {
  if (pont?.conflict_hours?.has(formatHourLabel(rdv?.heure_rdv))) return { mot: 'À arbitrer', ton: 'erreur' }
  const statut = getRdvStatus(rdv)
  if (statut === 'en_cours') return { mot: 'En cours', ton: 'attention' }
  if (statut === 'reception') return { mot: 'Réceptionné', ton: 'attention' }
  if (statut === 'reserve' || statut === 'confirme') return { mot: 'Réservé', ton: 'neutre' }
  if (statut === 'en_attente') return { mot: 'À confirmer', ton: 'neutre' }
  return { mot: 'Non affecté', ton: 'neutre' }
}

function chargeDuJour(pont: any): string {
  const n = Number(pont?.total_rdvs_today ?? 0)
  if (!n) return 'Aucun rendez-vous posé aujourd’hui'
  const morceaux = [`${n} rendez-vous`, `${formatDuree(pont.planned_minutes)} de charge`]
  const file = Number(pont?.next_count ?? 0)
  if (file) morceaux.push(`${file} en file`)
  return morceaux.join(' · ')
}

function pontProgress(pont: any) {
  const rdv = pont.current_rdv
  if (!rdv?.temps_estime) return 0
  if (typeof rdv?.temps_ecoule_minutes === 'number') {
    return Math.round(rdv.temps_ecoule_minutes / rdv.temps_estime * 100)
  }
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((Date.now() - startTime.getTime()) / 60000 / rdv.temps_estime * 100)
}

function normalizeCollection(payload: any) {
  return Array.isArray(payload) ? payload : (payload?.['hydra:member'] ?? payload?.member ?? [])
}

function normalizePont(item: any) {
  return {
    ...item,
    is_active: item?.is_active ?? item?.est_actif ?? item?.isActive ?? 1,
    current_rdv: item?.current_rdv ?? null,
    next_count: Number(item?.next_count ?? 0),
  }
}

function addMinutesToTime(time: string, minutesToAdd: number): string {
  const [hours, minutes] = String(time || '09:00').split(':').map(Number)
  const total = ((hours || 0) * 60) + (minutes || 0) + Math.max(15, Number(minutesToAdd || 0))
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

function buildPlanningCreateLink(pont?: any): string {
  const today = todayLocalISO()
  const suggestedTime = pont?.day_schedule?.length
    ? addMinutesToTime(formatHourLabel(pont.day_schedule[pont.day_schedule.length - 1]?.heure_rdv), Number(pont.day_schedule[pont.day_schedule.length - 1]?.temps_estime ?? 60))
    : '10:00'

  const params = new URLSearchParams({ create: '1', date: today, time: suggestedTime })
  if (pont?.id) params.set('pontId', String(pont.id))
  return `/planning?${params.toString()}`
}

/**
 * L'action qui reste à faire sur ce pont. Le libellé dit le RÉSULTAT en
 * entier, avec la donnée après le point médian : « Réceptionner · Pont 2 »
 * plutôt que « Réceptionner ».
 */
function getPontQuickAction(pont: any): { label: string; transition?: string; to?: string; action?: () => void } | null {
  const status = getRdvStatus(pont?.current_rdv)
  if (status === 'reserve' || status === 'confirme') return { label: `Réceptionner et placer sur ${pont.nom}`, transition: 'reception' }
  if (status === 'reception') return { label: `Démarrer les travaux sur ${pont.nom}`, transition: 'start_travail' }
  if (status === 'en_cours') return { label: 'Ouvrir le dossier en cours', action: () => openRdvDetail(pont.current_rdv) }
  if (pont?.next_rdv?.id) return { label: `Ouvrir le passage de ${formatHourLabel(pont.next_rdv.heure_rdv)}`, action: () => openRdvDetail(pont.next_rdv) }
  return { label: `Poser un rendez-vous sur ${pont.nom}`, to: buildPlanningCreateLink(pont) }
}

async function runPontQuickAction(pont: any) {
  const action = getPontQuickAction(pont)
  if (!action?.transition || !pont?.current_rdv?.id) return

  actioningByPont[pont.id] = action.transition
  try {
    await api.post(`/rendez-vous/${pont.current_rdv.id}/transition/${action.transition}`, {
      pont_id: pont.id,
      mecanicien_id: pont.assigned_meca?.id ?? pont.mecanicien?.id ?? null,
    })
    toast.add({ title: 'Dossier avancé', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({
      title: 'Dossier non avancé',
      description: messageErreur(e, 'le rendez-vous reste dans son état actuel'),
      color: 'error',
    })
  } finally {
    delete actioningByPont[pont.id]
  }
}

async function fetchPontsWithFallback() {
  const statusPayload = await api.get('/ponts/status').catch(() => null)
  const statusPonts = normalizeCollection(statusPayload).map(normalizePont)
  if (statusPonts.length) {
    return statusPonts
  }

  const rawPayload = await api.getAll('/ponts?order[id]=asc').catch(() => [])
  return normalizeCollection(rawPayload).map((item: any) => normalizePont(item))
}

async function loadWorkshop() {
  loading.value = true
  errorMessage.value = ''

  const [p, m, a, r] = await Promise.allSettled([
    fetchPontsWithFallback(),
    api.getAll('/mecaniciens?order[id]=asc'),
    api.getAll('/absences?order[id]=asc'),
    api.getAll('/rendez-vous?order[id]=asc'),
  ])

  const issues: string[] = []

  if (p.status === 'fulfilled') {
    ponts.value = Array.isArray(p.value) ? p.value : normalizeCollection(p.value).map(normalizePont)
    if (!ponts.value.length) {
      issues.push('ponts')
    }
  } else {
    ponts.value = []
    issues.push('ponts')
  }

  if (m.status === 'fulfilled') {
    mecaniciens.value = [...new Map(normalizeCollection(m.value).map((item: any) => [Number(item.id), item])).values()]
  } else {
    mecaniciens.value = []
    issues.push('mécaniciens')
  }

  if (a.status === 'fulfilled') {
    const absRaw = normalizeCollection(a.value)
    absences.value = absRaw.map((ab: any) => ({
      ...ab,
      mecanicien_nom: ab.mecanicien ? `${ab.mecanicien.prenom ?? ''} ${ab.mecanicien.nom ?? ''}`.trim() : '–',
    }))
  } else {
    absences.value = []
    issues.push('absences')
  }

  if (r.status === 'fulfilled') {
    rdvs.value = normalizeCollection(r.value)
  } else {
    rdvs.value = []
    issues.push('rendez-vous')
  }

  if (issues.length === 4) {
    errorMessage.value = 'Le serveur n’a renvoyé ni pont, ni mécanicien, ni absence, ni rendez-vous.'
  } else if (issues.length > 0) {
    toast.add({
      title: 'Atelier affiché en partie',
      description: `Ces sections n’ont pas répondu : ${issues.join(', ')}. Le reste de l’écran est à jour ; rien n’a été modifié.`,
      color: 'warning',
    })
  }

  syncPontSettings()
  lastUpdatedAt.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  loading.value = false
}

async function refreshWorkshop() {
  refreshing.value = true
  try {
    await loadWorkshop()
  } finally {
    refreshing.value = false
  }
}

onMounted(() => {
  const queryTab = typeof route.query.tab === 'string' ? route.query.tab : ''
  if (validTabs.includes(queryTab)) {
    activeTab.value = queryTab
  }
  loadWorkshop()
})

watch(activeTab, (tab) => {
  if (route.query.tab === tab) return
  router.replace({ query: { ...route.query, tab } })
})
</script>

<style scoped>
/* Ponts & Méca — maquette 2c.
 *
 * Aucune valeur de couleur, de durée ni de cible n'est écrite ici : tout passe
 * par la couche `--pk-*`, qui suit le thème atelier quand l'app bascule en
 * sombre. Les sélecteurs doublés (`.pk-bay.pont-card`, `.pk-bay select`) ne
 * sont pas de la décoration : `main.css` porte encore les anciennes règles
 * `.pont-card` et `.content select`, et une classe seule perdrait contre elles.
 */

.pk-atelier {
  display: flex;
  flex-direction: column;
  gap: 14px;
  color: var(--pk-ink);
}

/* ---- Surtitre : le seul endroit, avec les mots de statut, où le design
   system autorise les capitales. ---- */
.pk-overline {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

/* ---- Tête de page ---- */
.pk-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.pk-heading-titles { display: flex; flex-direction: column; gap: 4px; min-width: 0; }

.pk-heading-title {
  margin: 0;
  font-size: 28px;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  color: var(--pk-ink);
}

.pk-heading-rule { width: 44px; height: 4px; background: var(--pk-accent); }

.pk-heading-sub {
  margin: 4px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pk-ink-quiet);
}

/* ---- Bandeau de mesures ---- */
.pk-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
}

.pk-strip-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 18px;
  min-height: var(--pk-target-desk);
  border-right: 1px solid var(--pk-border-quiet);
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  text-decoration: none;
}
.pk-strip-cell:last-child { border-right: none; }

.pk-strip-cell--link { cursor: pointer; border-top: none; border-bottom: none; border-left: none; }
.pk-strip-cell--link:hover { background: var(--pk-neutral-surface); }
.pk-strip-cell--link:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: calc(var(--pk-focus-offset) * -1);
}

.pk-strip-value {
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  color: var(--pk-ink);
}

.pk-strip-cell--erreur .pk-overline,
.pk-strip-cell--erreur .pk-strip-value { color: var(--pk-error-ink); }

/* ---- Barre d'actions ---- */
.pk-toolbar { display: flex; align-items: center; gap: var(--pk-target-gap); flex-wrap: wrap; }
.pk-toolbar-btn { min-height: var(--pk-target-desk); }
.pk-toolbar-btn:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-toolbar-stamp { margin-left: auto; font-size: 12px; color: var(--pk-ink-muted); }

/* ---- Onglets ---- */
.pk-tabs {
  display: flex;
  align-items: center;
  gap: 20px;
  border-bottom: 1px solid var(--pk-border);
}

.pk-tab {
  display: flex;
  align-items: center;
  gap: 7px;
  min-height: var(--pk-target-desk);
  padding: 10px 2px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  font: inherit;
  font-size: 14px;
  color: var(--pk-ink-quiet);
  cursor: pointer;
  transition: color var(--pk-duration-state) var(--pk-easing);
}
.pk-tab:hover { color: var(--pk-ink); }
.pk-tab:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-tab--on { color: var(--pk-ink); font-weight: 600; border-bottom-color: var(--pk-ink); }

.pk-tabs-aside {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  min-height: var(--pk-target-desk);
  font-size: 12px;
  font-weight: 600;
  color: var(--pk-link);
  text-decoration: none;
}
.pk-tabs-aside:hover { text-decoration: underline; }
.pk-tabs-aside:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

/* ---- Corps d'un onglet ---- */
.pk-view { display: flex; flex-direction: column; gap: 12px; }

.pk-view-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.pk-view-action { min-height: var(--pk-target-desk); }
.pk-view-action:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-lede { margin: 0; max-width: 78ch; font-size: 13px; line-height: 1.5; color: var(--pk-ink-quiet); }
.pk-note { margin: 0; max-width: 62ch; font-size: 12px; line-height: 1.5; color: var(--pk-ink-muted); }
.pk-help { margin: 0; max-width: 68ch; font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }

/* ---- Mot de statut ---- */
.pk-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border: 1px solid transparent;
  border-radius: var(--pk-radius-pill);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  white-space: nowrap;
}
.pk-tag--neutre { background: var(--pk-neutral-surface); color: var(--pk-ink); }
.pk-tag--succes { background: var(--pk-success-surface); border-color: var(--pk-success-line); color: var(--pk-success-ink); }
.pk-tag--attention { background: var(--pk-warning-surface); border-color: var(--pk-warning-line); color: var(--pk-warning-ink); }
.pk-tag--erreur { background: var(--pk-error-surface); border-color: var(--pk-error-line); color: var(--pk-error-ink); }
.pk-tag--info { background: var(--pk-info-surface); border-color: var(--pk-info-line); color: var(--pk-info-ink); }

/* ---- Cartes de pont ---- */
.pk-bays {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
  gap: 12px;
}

.pk-bay.pont-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-top: 3px solid var(--pk-accent);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
  transition: border-color var(--pk-duration-state) var(--pk-easing);
}
.pk-bay.pont-card:hover { border-color: var(--pk-border-control); }

/* Libre : la carte est en creux, pas en couleur — rien ne s'y passe. */
.pk-bay--succes.pont-card {
  background: var(--pk-surface-raised);
  border: 1px dashed var(--pk-border-control);
}
.pk-bay--succes.pont-card:hover { border-color: var(--pk-border-strong); }

/* Conflit : le filet rouge fait le tour, il ne se contente pas du haut. */
.pk-bay--erreur.pont-card {
  border: 1px solid var(--pk-error-line);
  border-top: 3px solid var(--pk-error-line);
}
.pk-bay--erreur.pont-card:hover { border-color: var(--pk-error-line); }

/* Hors service : la carte descend sur le canevas, elle sort du travail du jour. */
.pk-bay--attention.pont-card {
  background: var(--pk-canvas);
  border: 1px solid var(--pk-border);
}
.pk-bay--attention.pont-card .pk-bay-name { color: var(--pk-ink-quiet); }

.pk-bay-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pk-bay-name { font-size: 16px; font-weight: 700; }
.pk-bay-spec { margin-left: auto; font-size: 11px; color: var(--pk-ink-muted); }

.pk-bay-config {
  display: flex;
  flex-direction: column;
  gap: 7px;
  padding: 10px;
  background: var(--pk-surface-raised);
  border: 1px solid var(--pk-border-quiet);
  border-radius: var(--pk-radius-tile);
}
.pk-bay--attention .pk-bay-config { background: var(--pk-surface); border-color: var(--pk-border); }

.pk-bay-config-head { display: flex; align-items: center; gap: var(--pk-target-gap); }

.pk-pillbtn {
  margin-left: auto;
  min-height: var(--pk-target-desk);
  padding: 0 14px;
  background: transparent;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-pill);
  font: inherit;
  font-size: 11px;
  font-weight: 600;
  color: var(--pk-ink);
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing);
}
.pk-pillbtn:hover { background: var(--pk-neutral-surface); }
.pk-pillbtn:disabled { opacity: 0.55; cursor: not-allowed; }
.pk-pillbtn:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
/* Remettre en service est l'action attendue sur un pont éteint : elle porte l'accent. */
.pk-pillbtn--accent { background: var(--pk-accent); border-color: transparent; color: var(--pk-ink); }
.pk-pillbtn--accent:hover { background: var(--pk-accent); }

.pk-field-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--pk-ink-muted);
}

.pk-bay select.pk-field {
  width: 100%;
  min-height: var(--pk-target-desk);
  padding: 0 10px;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  color: var(--pk-ink);
  font: inherit;
  font-size: 12px;
}
.pk-bay select.pk-field:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-bay-pending {
  margin: 0;
  padding: 7px 9px;
  background: var(--pk-warning-surface);
  border-left: 3px solid var(--pk-warning-line);
  border-radius: var(--pk-radius-block);
  font-size: 12px;
  line-height: 1.4;
  color: var(--pk-warning-ink);
}

.pk-bay-save { width: 100%; min-height: var(--pk-target-desk); }
.pk-bay-save:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

/* Modifier et Archiver n'ont pas le même effet : le design system impose un
   écart minimal entre deux cibles voisines aux conséquences opposées. */
.pk-bay-admin {
  display: flex;
  align-items: center;
  gap: calc(var(--pk-target-gap) * 2);
  flex-wrap: wrap;
  padding-top: 3px;
  border-top: 1px solid var(--pk-border-quiet);
}

.pk-linkbtn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: var(--pk-target-desk);
  padding: 0;
  background: none;
  border: none;
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  color: var(--pk-link);
  text-decoration: none;
  cursor: pointer;
}
.pk-linkbtn:hover { text-decoration: underline; }
.pk-linkbtn:disabled { opacity: 0.55; cursor: not-allowed; text-decoration: none; }
.pk-linkbtn:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-linkbtn--erreur { color: var(--pk-error-ink); }

.pk-bay-now { display: flex; flex-direction: column; gap: 5px; }
.pk-bay-now-title { margin: 0; font-size: 14px; font-weight: 700; color: var(--pk-ink); }
.pk-bay-now-line { margin: 0; font-size: 12px; color: var(--pk-ink-quiet); }
.pk-bay-now-badge { margin-top: 2px; }

.pk-gauge { display: flex; flex-direction: column; gap: 3px; margin-top: 4px; }
.pk-gauge-track {
  height: 6px;
  background: var(--pk-neutral-surface);
  border-radius: var(--pk-radius-block);
  overflow: hidden;
}
.pk-gauge-fill {
  height: 100%;
  background: var(--pk-accent);
  border-radius: var(--pk-radius-block);
}
/* Dépasser le temps vendu n'est pas une alerte : c'est un fait, dit en rouge
   et repris en toutes lettres par le libellé sous la jauge. */
.pk-gauge-fill--over { background: var(--pk-error-line); }
.pk-gauge-label { font-size: 11px; color: var(--pk-ink-muted); }

.pk-bay-prog { display: flex; flex-direction: column; gap: 5px; }

.pk-prog-row { display: flex; align-items: center; gap: var(--pk-target-gap); font-size: 12px; }
.pk-prog-time { width: 38px; flex-shrink: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.pk-prog-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pk-prog-status { font-weight: 600; white-space: nowrap; }
.pk-prog-status--neutre { color: var(--pk-ink-quiet); }
.pk-prog-status--succes { color: var(--pk-success-ink); }
.pk-prog-status--attention { color: var(--pk-warning-ink); }
.pk-prog-status--erreur { color: var(--pk-error-ink); }
.pk-prog-row--erreur { color: var(--pk-error-ink); }

.pk-bay-idle { margin: 0; font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }

.pk-bay-act { margin-top: auto; }
.pk-bay-act-btn { width: 100%; min-height: var(--pk-target-desk); }
.pk-bay-act-btn:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-bay-foot {
  padding-top: 8px;
  border-top: 1px solid var(--pk-border-quiet);
  font-size: 11px;
  color: var(--pk-ink-muted);
}

/* ---- Cartes mécanicien ---- */
.pk-mecas {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}

.pk-meca {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
}

.pk-meca-head { display: flex; align-items: center; gap: 12px; }

.pk-meca-initials {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  border: 1px solid transparent;
  border-radius: var(--pk-radius-pill);
  font-size: 14px;
  font-weight: 700;
}
.pk-meca-initials--succes { background: var(--pk-success-surface); border-color: var(--pk-success-line); color: var(--pk-success-ink); }
.pk-meca-initials--attention { background: var(--pk-warning-surface); border-color: var(--pk-warning-line); color: var(--pk-warning-ink); }
.pk-meca-initials--erreur { background: var(--pk-error-surface); border-color: var(--pk-error-line); color: var(--pk-error-ink); }

.pk-meca-id { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.pk-meca-name-row { display: flex; align-items: center; gap: var(--pk-target-gap); flex-wrap: wrap; }
.pk-meca-name { font-size: 14px; font-weight: 700; }
.pk-meca-role { font-size: 12px; color: var(--pk-ink-quiet); }

.pk-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.pk-chip {
  padding: 2px 8px;
  background: var(--pk-info-surface);
  border-radius: var(--pk-radius-pill);
  font-size: 10px;
  color: var(--pk-info-ink);
}

.pk-meca-now {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 9px 10px;
  background: var(--pk-warning-surface);
  border: 1px solid var(--pk-warning-line);
  border-radius: var(--pk-radius-tile);
}
.pk-meca-now .pk-overline { color: var(--pk-warning-ink); }
.pk-meca-now-line { margin: 0; font-size: 12px; font-weight: 600; color: var(--pk-warning-ink); }
.pk-meca-now-quiet { margin: 0; font-size: 12px; color: var(--pk-warning-ink); }

.pk-meca-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
  padding-top: 8px;
  border-top: 1px solid var(--pk-border-quiet);
  font-size: 12px;
  color: var(--pk-ink-muted);
}
.pk-meca-mail { display: inline-flex; align-items: center; gap: 5px; min-width: 0; overflow: hidden; text-overflow: ellipsis; }

/* ---- Tableau des absences ---- */
.pk-table {
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow-x: auto;
}

.pk-cell-strong { font-weight: 600; color: var(--pk-ink); }
.pk-cell-stack { display: flex; flex-direction: column; gap: 2px; }
.pk-cell-row { display: flex; align-items: center; gap: calc(var(--pk-target-gap) * 2); flex-wrap: wrap; }
.pk-cell-quiet { font-size: 12px; color: var(--pk-ink-quiet); }

/* ---- Fiches en modale ---- */
.pk-form { display: flex; flex-direction: column; color: var(--pk-ink); }

/* La modale pose déjà son propre retrait : la fiche ne le redouble pas, sinon
   le filet sous le titre flotterait à 36 px du bord de la carte. */
.pk-form-head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--pk-border);
}

.pk-form-title { margin: 0; font-size: 17px; font-weight: 500; letter-spacing: -0.015em; }

.pk-form-close {
  margin-left: auto;
  min-width: var(--pk-target-desk);
  min-height: var(--pk-target-desk);
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: var(--pk-ink-muted);
  font-size: 20px;
  cursor: pointer;
}
.pk-form-close:hover { background: var(--pk-neutral-surface); }
.pk-form-close:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-form-body { display: flex; flex-direction: column; gap: 14px; padding-top: 16px; }
.pk-form-duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }

.pk-form .form-input.pk-field {
  min-height: var(--pk-target-desk);
  background: var(--pk-surface-raised);
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  color: var(--pk-ink);
}
.pk-form .form-input.pk-field:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
  border-color: var(--pk-border-strong);
  box-shadow: none;
}

.pk-check { display: flex; align-items: center; gap: var(--pk-target-gap); min-height: var(--pk-target-desk); }
.pk-check-box { width: 18px; height: 18px; accent-color: var(--pk-accent); }
.pk-check-box:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-check-label { font-size: 13px; color: var(--pk-ink); }

.pk-form-foot {
  display: flex;
  justify-content: flex-end;
  gap: calc(var(--pk-target-gap) * 2);
  padding-top: 4px;
}
.pk-form-foot .btn { min-height: var(--pk-target-desk); }
.pk-form-foot .btn:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

@media (max-width: 900px) {
  .pk-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pk-strip-cell:nth-child(2) { border-right: none; }
  .pk-toolbar-stamp { margin-left: 0; }
}
</style>
