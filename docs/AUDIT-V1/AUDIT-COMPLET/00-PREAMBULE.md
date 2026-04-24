# Audit complet AtelierMBZ v4

**Date de l'audit :** 24 avril 2026
**Périmètre :** intégralité du code source (backend Symfony + frontend Nuxt + infra)
**Objectif :** documenter exhaustivement le fonctionnement de l'application, chaque entité, chaque endpoint, chaque règle métier, chaque subtilité.

## Comment lire ce document

Ce document est constitué de 6 chapitres compilés dans cet ordre dans le PDF :

| # | Chapitre | Sujet |
|---|---|---|
| 00 | Préambule | Ce document, méthode |
| 01 | Vue d'ensemble | Ce que fait l'appli, métiers, règles non négociables, subtilités, glossaire |
| 02 | Backend — modèle | Entités Doctrine, workflows, listeners, voters, migrations |
| 03 | Backend — API | Controllers, endpoints, services, commands, crons, messages |
| 04 | Frontend Nuxt 3 | Pages, composables, stores Pinia, composants, middlewares, layouts, plugins, tests |
| 05 | Transverse | Configuration, sécurité, multi-tenant, notifications, Docker, Caddy, tests |

## Source des informations

Données collectées par lecture directe du code source à la date d'audit. Aucune information ne provient d'inférence ou de documentation externe non vérifiée. Les chemins de fichiers cités sont relatifs à la racine du dépôt.

## Mises à jour

Ce document doit être régénéré après tout changement structurel majeur (nouvelle entité, nouveau workflow, nouveau module). La commande de régénération PDF est :

```
docker compose exec php php bin/generate-audit-pdf.php
```

Le PDF de sortie est placé dans `docs/AUDIT-V1/AUDIT-COMPLET-AtelierMBZ.pdf`.
