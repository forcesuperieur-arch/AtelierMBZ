<template>
  <div class="legal-container">
    <h1 class="legal-title">Politique de Confidentialité</h1>

    <h2 class="legal-section-title">1. Responsable du traitement</h2>
    <p>Le responsable du traitement des données est le gérant de l'atelier moto. Contactez-nous via les coordonnées disponibles sur la page d'accueil.</p>

    <h2 class="legal-section-title">2. Données collectées</h2>
    <p>Nous collectons uniquement les données nécessaires à la gestion de votre véhicule et de vos rendez-vous :</p>
    <ul class="legal-list">
      <li>Nom, prénom</li>
      <li>Numéro de téléphone, adresse email</li>
      <li>Adresse postale (optionnelle)</li>
      <li>Informations véhicule (plaque, marque, modèle, année)</li>
      <li>Historique des interventions et factures</li>
    </ul>

    <h2 class="legal-section-title">3. Finalités du traitement</h2>
    <ul class="legal-list">
      <li>Gestion des rendez-vous et interventions</li>
      <li>Établissement de devis et factures</li>
      <li>Suivi de l'avancement des réparations</li>
      <li>Rappels de rendez-vous par email</li>
    </ul>

    <h2 class="legal-section-title">4. Base légale</h2>
    <ul class="legal-list">
      <li><strong>Exécution du contrat</strong> : traitement de votre véhicule, facturation</li>
      <li><strong>Obligation légale</strong> : conservation des factures (10 ans), ordres de réparation (5 ans)</li>
      <li><strong>Consentement</strong> : prise de rendez-vous en ligne, envoi de rappels par email</li>
    </ul>

    <h2 class="legal-section-title">5. Durée de conservation</h2>
    <table class="legal-table">
      <thead>
        <tr class="legal-table-header">
          <th class="legal-table-th">Donnée</th>
          <th class="legal-table-th">Durée</th>
        </tr>
      </thead>
      <tbody>
        <tr class="legal-table-row"><td class="legal-table-td">Factures</td><td class="legal-table-td">10 ans (obligation légale)</td></tr>
        <tr class="legal-table-row"><td class="legal-table-td">Ordres de réparation</td><td class="legal-table-td">5 ans</td></tr>
        <tr class="legal-table-row"><td class="legal-table-td">Devis refusés/expirés</td><td class="legal-table-td">1 an</td></tr>
        <tr class="legal-table-row"><td class="legal-table-td">Données client (sans facture active)</td><td class="legal-table-td">3 ans après dernière activité</td></tr>
        <tr class="legal-table-row"><td class="legal-table-td">Logs d'audit (IP)</td><td class="legal-table-td">6 mois</td></tr>
        <tr><td class="legal-table-td">Rappels email</td><td class="legal-table-td">1 an</td></tr>
      </tbody>
    </table>

    <h2 class="legal-section-title">6. Destinataires</h2>
    <p>Vos données ne sont jamais transmises à des tiers. Elles sont accessibles uniquement au personnel de l'atelier dans le cadre de leurs fonctions.</p>

    <h2 class="legal-section-title">7. Vos droits (RGPD Art. 15-21)</h2>
    <p>Vous disposez des droits suivants :</p>
    <ul class="legal-list">
      <li><strong>Droit d'accès</strong> : obtenir une copie de vos données</li>
      <li><strong>Droit de rectification</strong> : corriger des données inexactes</li>
      <li><strong>Droit à l'effacement</strong> : demander la suppression de vos données (sauf obligations légales)</li>
      <li><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré (JSON)</li>
      <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données</li>
    </ul>
    <p class="legal-paragraph">Pour exercer ces droits, contactez le responsable de l'atelier. Nous répondrons dans un délai de 30 jours.</p>

    <h2 class="legal-section-title">8. Sécurité</h2>
    <p>Les données sont protégées par :</p>
    <ul class="legal-list">
      <li>Authentification par jeton JWT (cookies HttpOnly)</li>
      <li>Isolation des données par atelier (multi-tenant)</li>
      <li>Connexion chiffrée (HTTPS)</li>
      <li>Audit de toutes les opérations sensibles</li>
    </ul>

    <h2 class="legal-section-title">9. Réclamation</h2>
    <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de la <a href="https://www.cnil.fr" target="_blank" rel="noopener" class="link-yellow">CNIL</a>.</p>

    <div class="legal-footer">
      Dernière mise à jour : avril 2026
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
</script>

<style scoped>
.legal-container { max-width:800px; margin:40px auto; padding:0 20px; color:#D1D5DB; }
.legal-title { color:#E8E9ED; font-size:24px; margin-bottom:24px; }
.legal-section-title { color:#FFD200; font-size:16px; margin-top:24px; }
.legal-list { margin-left:20px; margin-top:8px; }
.legal-table { width:100%; border-collapse:collapse; margin-top:8px; }
.legal-table-header { border-bottom:1px solid rgba(255,255,255,0.1); }
.legal-table-th { text-align:left; padding:8px; color:#FFD200; }
.legal-table-row { border-bottom:1px solid rgba(255,255,255,0.06); }
.legal-table-td { padding:8px; }
.link-yellow { color:#FFD200; }
.legal-paragraph { margin-top:8px; }
.legal-footer { margin-top:40px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.06); font-size:12px; color:#6B7280; }
</style>
