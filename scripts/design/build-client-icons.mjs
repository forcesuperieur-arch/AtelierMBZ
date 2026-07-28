#!/usr/bin/env node
/**
 * Génère `client-frontend/components/AppIcon.vue`.
 *
 * Le portail client n'a ni Nuxt UI, ni module d'icônes : y ajouter une
 * dépendance alourdirait une application publique volontairement légère, dont
 * le temps de premier affichage compte. Il reçoit donc un composant AppIcon de
 * même nom et de même interface que celui du front staff, mais qui rend du SVG
 * EN LIGNE.
 *
 * Pour ne pas embarquer les 3 244 icônes de RemixIcon, le script lit les
 * gabarits du portail, relève les noms réellement employés et n'inscrit que
 * ceux-là. Le fichier produit est donc à regénérer quand le portail gagne une
 * icône — ce que `--check` signale.
 *
 *   node scripts/design/build-client-icons.mjs [--check]
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const RACINE = fileURLToPath(new URL('../..', import.meta.url))
const PORTAIL = join(RACINE, 'client-frontend')
const SORTIE = join(PORTAIL, 'components/AppIcon.vue')
const IGNORES = new Set(['node_modules', '.nuxt', '.output', 'dist'])
const check = process.argv.includes('--check')

function gabarits(dossier, trouves = []) {
  for (const entree of readdirSync(dossier)) {
    if (IGNORES.has(entree)) continue
    const chemin = join(dossier, entree)
    if (statSync(chemin).isDirectory()) gabarits(chemin, trouves)
    else if (chemin.endsWith('.vue')) trouves.push(chemin)
  }
  return trouves
}

// Noms d'icônes employés par le portail, qu'ils soient écrits en dur
// (`name="i-ri-…"`) ou passés par une expression (`:name="x ? 'i-ri-…' : …"`).
const employes = new Set()
for (const fichier of gabarits(PORTAIL)) {
  if (fichier === SORTIE) continue
  for (const m of readFileSync(fichier, 'utf8').matchAll(/i-ri-[a-z0-9-]+/g)) {
    employes.add(m[0])
  }
}

const require_ = createRequire(join(RACINE, 'frontend/package.json'))
const collection = require_('@iconify-json/ri/icons.json')
const taille = collection.width ?? 24

/** Corps SVG d'une icône, alias résolus. */
function corps(nom) {
  let cle = nom.replace(/^i-ri-/, '')
  for (let i = 0; i < 8; i++) {
    if (collection.icons[cle]) return collection.icons[cle].body
    const alias = collection.aliases?.[cle]
    if (!alias) return null
    cle = alias.parent
  }
  return null
}

const entrees = []
for (const nom of [...employes].sort()) {
  const b = corps(nom)
  if (!b) {
    console.error(`Icône inconnue dans le portail client : ${nom}`)
    process.exit(1)
  }
  entrees.push(`  '${nom}': '${b.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}',`)
}

const contenu = `<!--
  FICHIER GÉNÉRÉ — ne pas modifier à la main.
  Régénérer avec : node scripts/design/build-client-icons.mjs

  Jumeau, pour le portail client, du composant AppIcon du front staff. Même
  nom et même interface, mais le tracé est embarqué en dur : le portail n'a pas
  de module d'icônes, et n'a pas à en gagner un pour ${entrees.length} icônes.

  Le \`v-html\` ne présente pas de risque : il ne reçoit jamais que les chaînes
  du tableau ci-dessous, figées à la génération depuis la collection RemixIcon.
-->
<template>
  <svg
    v-if="corps"
    class="app-icon"
    viewBox="0 0 ${taille} ${taille}"
    fill="currentColor"
    aria-hidden="true"
    v-html="corps"
  />
</template>

<script setup>
const TRACES = {
${entrees.join('\n')}
}

const props = defineProps({
  name: { type: String, required: true },
})

const corps = computed(() => TRACES[props.name] ?? '')
</script>
`

const actuel = (() => {
  try {
    return readFileSync(SORTIE, 'utf8')
  } catch {
    return null
  }
})()

if (actuel === contenu) {
  console.log(`Les icônes du portail client sont à jour (${entrees.length}).`)
  process.exit(0)
}

if (check) {
  console.error(
    `AppIcon du portail client à regénérer (${entrees.length} icône(s) employée(s)) :\n` +
      '  node scripts/design/build-client-icons.mjs',
  )
  process.exit(1)
}

writeFileSync(SORTIE, contenu, 'utf8')
console.log(`AppIcon du portail client généré : ${entrees.length} icône(s).`)
