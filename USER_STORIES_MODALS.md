# User Stories — Modal Design System Modernization & Select v3 Migration

## Context
Après refonte complète du design system des modales (`AppModal`, `PitModalFooter`) et migration des composants `USelect`/`USelectMenu` vers la syntaxe Nuxt UI v3 (`:items`, `label-key`, `value-key`), ces user stories valident la non-régression et le bon fonctionnement de l'ensemble.

---

## US-1 — AppModal renders without SSR error when bound data is null
**As a** developer  
**I want** the modal component to handle null/undefined data gracefully during SSR  
**So that** no 500 error occurs on pages with conditional modals

### Acceptance Criteria
- [ ] Page `/motos` loads with HTTP 200 (no SSR crash)
- [ ] `AppModal` `:title` and `:description` props accept expressions with null guards
- [ ] Modal opens correctly when `selectedModel` becomes non-null

### Test Steps
1. Navigate to `/motos` as authenticated admin
2. Verify page renders without error
3. Click on a motorcycle card to open detail modal
4. Verify modal title shows `"Marque Modèle"` and description shows category

---

## US-2 — Dropdown / SelectMenu inside modal is fully visible
**As a** user  
**I want** dropdown options to appear above the modal overlay when opened  
**So that** I can select values without the dropdown being clipped

### Acceptance Criteria
- [ ] `USelectMenu` opened inside an `AppModal` shows all options
- [ ] Dropdown z-index is higher than modal overlay (z-250 vs z-240)
- [ ] Backdrop blur does not affect dropdown readability

### Test Steps
1. Navigate to `/admin/users`
2. Click "Nouvel utilisateur" to open modal
3. Click on "Profil d'accès" `USelectMenu`
4. Verify dropdown options are fully visible above modal
5. Repeat on `/facturation`, `/absences`, `/roles-metier`

---

## US-3 — Modal animations and interactions work correctly
**As a** user  
**I want** modals to open/close with smooth animations and trap focus  
**So that** the experience feels polished and accessible

### Acceptance Criteria
- [ ] Modal opens with scale + translateY animation
- [ ] Pressing `Escape` closes the modal
- [ ] Clicking backdrop closes the modal
- [ ] Body scroll is locked while modal is open
- [ ] Focus cycles within modal on Tab key

### Test Steps
1. Open any modal (e.g., `/admin/users` → "Nouvel utilisateur")
2. Press `Escape` → modal closes
3. Re-open, click outside the card → modal closes
4. Verify body cannot scroll while modal is open
5. Press Tab multiple times → focus stays inside modal

---

## US-4 — PitModalFooter standardizes form submission
**As a** user  
**I want** consistent Cancel / Submit buttons across all modals  
**So that** I know exactly how to save or discard my changes

### Acceptance Criteria
- [ ] All modales using `PitModalFooter` show Cancel + Submit buttons
- [ ] Submit button triggers form submission via `formId`
- [ ] Loading state disables submit button
- [ ] Cancel button closes modal without submitting

### Test Steps
1. Open `/admin/users` modal → see "Annuler" and "Créer" buttons
2. Fill form, click "Créer" → form submits, modal closes
3. Re-open, click "Annuler" → modal closes, no API call
4. Check `/tarifs`, `/prestations`, `/ponts` modals for same pattern

---

## US-5 — USelectMenu v3 syntax works on all modernized pages
**As a** developer  
**I want** all `USelectMenu` components to use `:items` + `label-key`/`value-key`  
**So that** Nuxt UI v3 renders them correctly without deprecation warnings

### Acceptance Criteria
- [ ] No `:options` prop remains on any `USelectMenu` in modernized files
- [ ] No `option-attribute` or `value-attribute` props remain
- [ ] Selects bind correctly and show the right labels

### Pages to validate
- `/motos`
- `/facturation`
- `/absences`
- `/roles-metier`
- `/public/booking`
- `/admin/users`

### Test Steps
1. Open each page above
2. Interact with every `USelectMenu`
3. Verify selected value is correct and label displays properly

---

## US-6 — No inline modal styles or UCard wrappers remain
**As a** developer  
**I want** all modals to use the standardized `AppModal` + `PitModalFooter`  
**So that** the codebase is maintainable and consistent

### Acceptance Criteria
- [ ] No `<UCard>` inside modals in modernized files
- [ ] No inline `:ui` blocks for modal styling
- [ ] All modals use `icon`, `iconColor`, `title`, `description` props

### Test Steps
1. Grep codebase for `UCard` inside modal contexts
2. Grep for `:ui=` near modal components
3. Visually inspect modals on all 17 modernized pages

---

## Run Automated Tests

```bash
# From frontend directory
npx playwright test tests/e2e/modals-selects-modernization.spec.mjs --headed

# Or headless
npx playwright test tests/e2e/modals-selects-modernization.spec.mjs
```

## Manual Regression Checklist

| Page | Modal opens? | Select inside modal? | Footer buttons? | Screenshot |
|------|-------------|----------------------|-----------------|------------|
| `/admin/users` | ☐ | ☐ (role) | ☐ | |
| `/admin/ponts` | ☐ | ☐ | ☐ | |
| `/admin/prestations` | ☐ | ☐ | ☐ | |
| `/admin/roles-metier` | ☐ | ☐ | ☐ | |
| `/tarifs` | ☐ | ☐ | ☐ | |
| `/facturation` | ☐ | ☐ (statut) | ☐ | |
| `/absences` | ☐ | ☐ (type, user) | ☐ | |
| `/motos` | ☐ | ☐ | ☐ | |
| `/public/booking` | ☐ | ☐ (modèle, atelier) | ☐ | |

