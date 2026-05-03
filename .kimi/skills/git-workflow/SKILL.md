# Git Workflow — Skill Projet

## Configuration locale
- Git installé à : `C:\Program Files\Git\bin\git.exe`
- PowerShell ne reconnaît pas `git` dans le PATH → toujours utiliser le chemin absolu
- Branche active : `cleanup/printemps-2026`
- Remote : `origin` (GitHub)

## Commandes PowerShell
```powershell
# Git avec chemin absolu
& 'C:\Program Files\Git\bin\git.exe' status
& 'C:\Program Files\Git\bin\git.exe' add -A
& 'C:\Program Files\Git\bin\git.exe' commit -m "type(scope): description"
& 'C:\Program Files\Git\bin\git.exe' push origin cleanup/printemps-2026
```

## Convention de commits
Format : `type(scope): description concise`

| Type | Quand l'utiliser |
|---|---|
| `feat` | Nouvelle feature |
| `fix` | Correction de bug |
| `refactor` | Refactoring sans changement de comportement |
| `test` | Ajout/modification de tests |
| `docs` | Documentation |
| `chore` | Tâches de maintenance |

Exemples :
- `feat(stock): ajout du filtre par catégorie`
- `fix(facturation): correction calcul TVA sur avoirs`
- `test(stock): tests fonctionnels StockController`

## Règles strictes
- **Jamais** `git push --force`
- **Jamais** de rebase sur `main` sans autorisation explicite
- Ne pas toucher `main` directement
- Si erreur `NativeCommandError` sur push → vérifier avec `git log origin/cleanup/printemps-2026..HEAD` que le commit est bien en avance, puis ignorer l'erreur PowerShell

## Workflow autonome
1. `git add -A`
2. `git commit -m "type(scope): ..."`
3. `git push origin cleanup/printemps-2026`
4. Si push refuse (divergence) → `git pull origin cleanup/printemps-2026 --no-rebase` puis repush
