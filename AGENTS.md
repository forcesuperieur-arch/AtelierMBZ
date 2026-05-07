# AGENTS.md — Protocole multi-agents AtelierMBZ

Ce fichier décrit les règles de coordination quand **plusieurs agents IA** (ou plusieurs sessions humaines en parallèle) travaillent sur le même dépôt.

## Principe directeur

**Un agent = une branche dédiée. Jamais deux agents sur la même branche en même temps.**

La branche par défaut de travail est `cleanup/printemps-2026` pendant la phase POC. La branche `main` est gelée jusqu'à validation de la version POC.

---

## Règle 1 — Tag de sauvegarde automatique avant toute opération destructive

Avant toute opération qui peut faire perdre du code (`git reset --hard`, `git rebase`, `git checkout` sur fichier modifié, suppression de fichier non commité, restauration de DB), créer un **tag local** :

```bash
git tag local-backup-$(date +%Y-%m-%d-%H%M%S)
```

Ce tag est **local** (pas pushé), il sert de filet de sécurité pour cherry-pick si besoin.

Référence historique : `local-backup-2026-05-07` a sauvé 3 commits critiques (numérotation atomique, timeout session, tolérance backward-compat) après un reset accidentel.

---

## Règle 2 — Branches dédiées par type de travail

| Préfixe branche | Usage | Exemple |
|---|---|---|
| `feat/LOT-XX-…` | Implémentation d'un lot de la roadmap POC | `feat/LOT-1-fronts-physiques` |
| `fix/…` | Correctif ciblé sur la branche active | `fix/photo-upload-pda` |
| `cleanup/…` | Refactor / dette technique | `cleanup/printemps-2026` |
| `agent/<nom>/…` | Branche d'un agent IA spécifique | `agent/copilot/L0.1-numerotation` |

**Règle absolue** : chaque agent IA travaille sur sa propre branche `agent/<nom>/<tâche>` et ne pousse jamais directement sur `main` ni `cleanup/printemps-2026` sans validation explicite de l'utilisateur.

---

## Règle 3 — Protocole de cherry-pick depuis un tag de backup

Si du code a été perdu lors d'un reset/rebase et qu'un tag `local-backup-*` contient les commits :

```bash
# 1. Lister les commits du tag absents de la branche actuelle
git log --oneline cleanup/printemps-2026..local-backup-2026-05-07

# 2. Cherry-pick commit par commit (jamais en bloc)
git cherry-pick <sha1>

# 3. Si conflit : résoudre, puis
git cherry-pick --continue

# 4. Si le commit est obsolète/inadapté
git cherry-pick --skip
```

**Ne jamais utiliser `git reset --hard <tag>` pour récupérer** — risque d'écraser les commits faits depuis. Toujours cherry-pick chirurgicalement.

---

## Règle 4 — Coordination entre sessions

Avant d'ouvrir une nouvelle session de travail :

1. `git fetch --all --tags` pour voir l'état distant
2. `git status` + `git log --oneline -5` pour situer la branche
3. Lire `.github/PROJECT_HISTORY.md` (mémoire partagée) pour connaître l'état du POC
4. Si une autre session travaille (commits récents non poussés ou branches `agent/*` actives) → coordonner avec l'utilisateur avant de modifier les mêmes fichiers

---

## Règle 5 — Commits liés à un agent IA

Un commit fait par un agent doit :

- Suivre la convention `[BLOC-XX]` ou `[LOT-XX]` (cf. `.github/copilot-instructions.md`)
- Inclure une **preuve d'exécution** dans le message (sortie curl/SQL, exit code, etc.) si vérification end-to-end possible
- Être atomique (un commit = une unité logique)
- Ne **jamais** contenir de tests qui ne passent pas sans le signaler explicitement dans le message

---

## Règle 6 — Pas de push automatique

**Aucun agent ne pousse de commits sans demande explicite de l'utilisateur.**

À la fin d'une session, l'agent fournit la commande `git push` à exécuter. L'utilisateur reste maître du moment où le code part sur le remote.

---

## État actuel du dépôt (2026-05-07)

- Branche active : `cleanup/printemps-2026`
- Phase : **POC en consolidation** (LOT 0 puis LOT 1 → 12)
- Modules en réécriture (NE PAS TOUCHER) : Stock, Facturation
- Tag de référence : `local-backup-2026-05-07` (3 commits critiques préservés)

Pour la roadmap détaillée du POC : voir `.github/PROJECT_HISTORY.md`.
