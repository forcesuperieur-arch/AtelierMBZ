# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: business-flows.spec.mjs >> Core Business Flows >> Public booking: can submit and get tracking token
- Location: tests/e2e/business-flows.spec.mjs:45:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('button').filter({ hasText: /^\d{2}:\d{2}$/ }).first()
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 15000ms
  - waiting for locator('button').filter({ hasText: /^\d{2}:\d{2}$/ }).first()

```

# Page snapshot

```yaml
- generic [ref=e4]:
  - generic [ref=e6]:
    - generic [ref=e7]:
      - generic [ref=e8]: 📅
      - heading "Réserver un rendez-vous" [level=1] [ref=e9]
      - paragraph [ref=e10]: Parcours public complet avec estimation et créneaux réels
    - generic [ref=e11]: Impossible de charger les créneaux disponibles.
    - generic [ref=e12]:
      - generic [ref=e13]:
        - generic [ref=e14]: 1. Vos coordonnées
        - generic [ref=e15]:
          - generic [ref=e16]:
            - generic [ref=e19]: Prénom
            - textbox "Prénom" [ref=e22]: Test
          - generic [ref=e23]:
            - generic [ref=e26]: Nom
            - textbox "Nom" [ref=e29]: Migration
          - generic [ref=e30]:
            - generic [ref=e33]: Téléphone
            - textbox "Téléphone" [ref=e36]: "0606569277"
          - generic [ref=e37]:
            - generic [ref=e40]: Email
            - textbox "Email" [ref=e43]: migration@example.com
        - generic [ref=e44]: Votre demande de créneau sera confirmée par email.
      - generic [ref=e45]:
        - generic [ref=e46]: 2. Votre moto
        - generic [ref=e47]:
          - generic [ref=e48]:
            - generic [ref=e51]: Marque
            - textbox "Marque" [ref=e54]: Yamaha
          - generic [ref=e55]:
            - generic [ref=e58]: Modèle
            - textbox "Modèle" [ref=e61]: MT-07
          - generic [ref=e62]:
            - generic [ref=e65]: Plaque
            - textbox "Plaque" [ref=e68]: BB-456-CC
          - generic [ref=e69]:
            - generic [ref=e72]: Type d'intervention
            - generic [ref=e73]:
              - combobox "Type d'intervention" [ref=e74]:
                - generic: entretien
              - combobox [ref=e77]
      - generic [ref=e78]:
        - generic [ref=e79]: 3. Prestations
        - generic [ref=e80]:
          - button "✓ Entretien / Vidange Révision courante et contrôle général 89,00 € 60 min" [ref=e81] [cursor=pointer]:
            - generic [ref=e82]: ✓
            - generic [ref=e83]:
              - generic [ref=e84]: Entretien / Vidange
              - generic [ref=e85]: Révision courante et contrôle général
            - generic [ref=e86]:
              - generic [ref=e87]: 89,00 €
              - generic [ref=e88]: 60 min
          - button "✓ Révision complète Contrôle sécurité et consommables 149,00 € 120 min" [ref=e89] [cursor=pointer]:
            - generic [ref=e90]: ✓
            - generic [ref=e91]:
              - generic [ref=e92]: Révision complète
              - generic [ref=e93]: Contrôle sécurité et consommables
            - generic [ref=e94]:
              - generic [ref=e95]: 149,00 €
              - generic [ref=e96]: 120 min
          - button "✓ Diagnostic atelier Recherche de panne et essai 59,00 € 45 min" [ref=e97] [cursor=pointer]:
            - generic [ref=e98]: ✓
            - generic [ref=e99]:
              - generic [ref=e100]: Diagnostic atelier
              - generic [ref=e101]: Recherche de panne et essai
            - generic [ref=e102]:
              - generic [ref=e103]: 59,00 €
              - generic [ref=e104]: 45 min
          - button "✓ Pneus / montage Montage et équilibrage 79,00 € 90 min" [ref=e105] [cursor=pointer]:
            - generic [ref=e106]: ✓
            - generic [ref=e107]:
              - generic [ref=e108]: Pneus / montage
              - generic [ref=e109]: Montage et équilibrage
            - generic [ref=e110]:
              - generic [ref=e111]: 79,00 €
              - generic [ref=e112]: 90 min
      - generic [ref=e113]:
        - generic [ref=e114]: 4. Créneau
        - generic [ref=e115]:
          - generic [ref=e118]: Date souhaitée
          - textbox "Date souhaitée" [active] [ref=e121]: 2026-04-18
        - paragraph [ref=e123]: Aucun créneau disponible ce jour.
      - generic [ref=e124]:
        - generic [ref=e125]: Récapitulatif estimatif
        - generic [ref=e126]:
          - generic [ref=e127]: Intervention
          - generic [ref=e128]: entretien
        - generic [ref=e129]:
          - generic [ref=e130]: Durée estimée
          - generic [ref=e131]: 60 min
        - generic [ref=e132]:
          - generic [ref=e133]: Total estimé
          - generic [ref=e134]: 0,00 €
      - generic [ref=e135]:
        - generic [ref=e138]: Description du problème
        - textbox "Description du problème" [ref=e141]:
          - /placeholder: "Exemple : vidange, frein avant bruyant, révision avant départ…"
      - button "Confirmer le rendez-vous" [ref=e142] [cursor=pointer]
  - contentinfo [ref=e143]:
    - link "Mentions légales" [ref=e144] [cursor=pointer]:
      - /url: /public/mentions-legales
    - text: "|"
    - link "Politique de confidentialité" [ref=e145] [cursor=pointer]:
      - /url: /public/politique-confidentialite
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | import { loginAsAdmin } from './helpers.mjs';
  3   | 
  4   | function nextOpenDate() {
  5   |   const d = new Date();
  6   |   d.setDate(d.getDate() + 1);
  7   |   if (d.getDay() === 0) d.setDate(d.getDate() + 1);
  8   |   return d.toISOString().slice(0, 10);
  9   | }
  10  | 
  11  | test.describe('Core Business Flows', () => {
  12  |   test.beforeEach(async ({ page }, testInfo) => {
  13  |     if (!/Public booking/i.test(testInfo.title)) {
  14  |       await loginAsAdmin(page);
  15  |     }
  16  |   });
  17  | 
  18  |   test('RDV wizard: step navigation', async ({ page }) => {
  19  |     await page.goto('/rdv/new');
  20  |     await page.waitForLoadState('networkidle');
  21  | 
  22  |     await expect(page.locator('body')).toContainText(/prise de rdv|identification moto/i);
  23  | 
  24  |     const manualEntry = page.locator('button:has-text("Saisie manuelle")').first();
  25  |     if (await manualEntry.count() > 0) {
  26  |       await manualEntry.click();
  27  |     }
  28  | 
  29  |     await page.locator('input[placeholder="Ex: KAWASAKI"]').fill('Honda');
  30  |     await page.locator('input[placeholder="Ex: Z900"]').fill('CB500F');
  31  |     await page.locator('input[placeholder="AB-123-CD"]').fill('ZZ-500-AA');
  32  | 
  33  |     await page.getByRole('button', { name: /suivant/i }).click();
  34  |     await expect(page.locator('body')).toContainText(/sélection des prestations|récapitulatif/i);
  35  | 
  36  |     const prestationCard = page.locator('[data-testid="prestation-card"]').first();
  37  |     await prestationCard.waitFor({ state: 'visible', timeout: 15000 });
  38  |     await prestationCard.click();
  39  |     const nextBtn = page.getByRole('button', { name: /suivant/i }).last();
  40  |     await expect(nextBtn).toBeEnabled({ timeout: 10000 });
  41  |     await nextBtn.click();
  42  |     await expect(page.locator('body')).toContainText(/choix du créneau|créneau sélectionné|planning/i);
  43  |   });
  44  | 
  45  |   test('Public booking: can submit and get tracking token', async ({ page }) => {
  46  |     await page.goto('/public/booking');
  47  |     await page.waitForLoadState('networkidle');
  48  | 
  49  |     await page.getByLabel('Prénom', { exact: true }).fill('Test');
  50  |     await page.getByLabel('Nom', { exact: true }).fill('Migration');
  51  |     await page.getByLabel('Téléphone', { exact: true }).fill(`06${Date.now().toString().slice(-8)}`);
  52  |     await page.getByLabel('Email', { exact: true }).fill('migration@example.com');
  53  |     await page.getByLabel(/Marque/i).fill('Yamaha');
  54  |     await page.getByLabel(/Modèle/i).fill('MT-07');
  55  |     await page.getByLabel('Plaque', { exact: true }).fill('BB-456-CC');
  56  | 
  57  |     await page.getByLabel('Date souhaitée', { exact: true }).fill(nextOpenDate());
  58  |     await page.getByLabel('Date souhaitée', { exact: true }).dispatchEvent('change');
  59  |     await page.waitForTimeout(1200);
  60  | 
  61  |     const slotButton = page.locator('button').filter({ hasText: /^\d{2}:\d{2}$/ }).first();
> 62  |     await expect(slotButton).toBeVisible({ timeout: 15000 });
      |                              ^ Error: expect(locator).toBeVisible() failed
  63  |     await slotButton.click();
  64  | 
  65  |     const confirmButton = page.getByRole('button', { name: /confirmer le rendez-vous/i });
  66  |     await expect(confirmButton).toBeEnabled({ timeout: 10000 });
  67  |     const bookingResponse = page.waitForResponse(res => res.url().includes('/api/public/booking') && res.status() === 201);
  68  |     await confirmButton.click();
  69  |     await bookingResponse;
  70  |     await expect(page.locator('body')).toContainText(/rendez-vous confirmé|code de suivi/i, { timeout: 15000 });
  71  |   });
  72  | 
  73  |   test('Client list: search and view', async ({ page }) => {
  74  |     await page.goto('/clients');
  75  |     await page.waitForLoadState('networkidle');
  76  | 
  77  |     // Search field should be present
  78  |     const searchInput = page.locator('input[placeholder*="herch"]');
  79  |     if (await searchInput.count() > 0) {
  80  |       await searchInput.fill('test');
  81  |       await page.waitForTimeout(500);
  82  |     }
  83  | 
  84  |     // Table should be visible
  85  |     await expect(page.locator('body')).toContainText(/client/i);
  86  |   });
  87  | 
  88  |   test('Dashboard: KPIs and pont grid visible', async ({ page }) => {
  89  |     await page.goto('/');
  90  |     await page.waitForLoadState('networkidle');
  91  | 
  92  |     // Should see KPI section
  93  |     await expect(page.locator('body')).toContainText(/rdv aujourd|occupation|pont/i);
  94  |   });
  95  | 
  96  |   test('Planning: grid and filters visible', async ({ page }) => {
  97  |     await page.goto('/planning');
  98  |     await page.waitForLoadState('networkidle');
  99  | 
  100 |     await expect(page.locator('body')).toContainText(/planning/i);
  101 |   });
  102 | 
  103 |   test('Facturation: list visible', async ({ page }) => {
  104 |     await page.goto('/facturation');
  105 |     await page.waitForLoadState('networkidle');
  106 | 
  107 |     await expect(page.locator('body')).toContainText(/factur/i);
  108 |   });
  109 | 
  110 |   test('Workshop: tabs work', async ({ page }) => {
  111 |     await page.goto('/workshop');
  112 |     await page.waitForLoadState('networkidle');
  113 | 
  114 |     // Ponts tab should be visible by default
  115 |     await expect(page.locator('body')).toContainText(/pont/i);
  116 | 
  117 |     // Click mecas tab
  118 |     const mecaTab = page.locator('button:has-text("Mécaniciens")');
  119 |     if (await mecaTab.count() > 0) {
  120 |       await mecaTab.click();
  121 |       await page.waitForTimeout(500);
  122 |     }
  123 |   });
  124 | 
  125 |   test('Admin: hub cards visible', async ({ page }) => {
  126 |     await page.goto('/admin');
  127 |     await page.waitForLoadState('networkidle');
  128 | 
  129 |     await expect(page.locator('body')).toContainText(/utilisateur/i);
  130 |     await expect(page.locator('body')).toContainText(/pont/i);
  131 |     await expect(page.locator('body')).toContainText(/prestation/i);
  132 |   });
  133 | 
  134 |   test('Stock: list and search', async ({ page }) => {
  135 |     await page.goto('/stock');
  136 |     await page.waitForLoadState('networkidle');
  137 | 
  138 |     await expect(page.locator('body')).toContainText(/stock|pièce/i);
  139 |   });
  140 | 
  141 |   test('Devis: list visible', async ({ page }) => {
  142 |     await page.goto('/devis');
  143 |     await page.waitForLoadState('networkidle');
  144 | 
  145 |     await expect(page.locator('body')).toContainText(/devis/i);
  146 |   });
  147 | 
  148 |   test('Sidebar navigation links work', async ({ page }) => {
  149 |     await page.goto('/');
  150 |     await page.waitForLoadState('networkidle');
  151 | 
  152 |     // Test sidebar links
  153 |     const sidebarLinks = [
  154 |       { text: /planning/i, url: '/planning' },
  155 |       { text: /rdv|rendez/i, url: '/rdv' },
  156 |       { text: /client/i, url: '/clients' },
  157 |     ];
  158 | 
  159 |     for (const link of sidebarLinks) {
  160 |       const el = page.locator(`a[href="${link.url}"]`).first();
  161 |       if (await el.count() > 0) {
  162 |         await el.click();
```