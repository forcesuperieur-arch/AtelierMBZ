#!/usr/bin/env node
/**
 * Garde-fou du chantier « icônes ».
 *
 * Quatre contrôles, dans cet ordre :
 *   1. chaque nom de la table existe VRAIMENT dans la collection RemixIcon —
 *      un nom inventé ne lève aucune erreur au runtime, il rend un carré vide ;
 *   2. aucun emoji ne subsiste dans les sources des deux fronts ;
 *   3. tout emoji encore présent est signalé avec l'icône qui lui correspond,
 *      pour que la migration sache quoi poser ;
 *   4. aucun bouton ni lien n'a pour SEUL contenu une icône sans être nommé.
 *      Une icône est décorative (`aria-hidden`) : sans `aria-label` ni `title`
 *      sur la commande, un lecteur d'écran annonce « bouton », rien de plus.
 *      C'est le contrôle qui empêche la migration de dégrader l'accessibilité.
 *
 * Sort en erreur si un contrôle échoue, de façon à pouvoir être branché sur un
 * hook ou une CI au même titre que `check-contrast.mjs`.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

import { ICONES, DECORATIFS, MOTIF_EMOJI, normaliser } from './icon-map.mjs'

const RACINE = fileURLToPath(new URL('../..', import.meta.url))
const FRONTS = ['frontend', 'client-frontend']
const EXTENSIONS = ['.vue', '.ts', '.js', '.mjs']
const IGNORES = new Set(['node_modules', '.nuxt', '.output', 'dist', '.git'])

function sources(depart) {
  const trouves = []
  const explorer = (dossier) => {
    let entrees
    try {
      entrees = readdirSync(dossier)
    } catch {
      return
    }
    for (const entree of entrees) {
      if (IGNORES.has(entree)) continue
      const chemin = join(dossier, entree)
      if (statSync(chemin).isDirectory()) explorer(chemin)
      else if (EXTENSIONS.some((e) => entree.endsWith(e))) trouves.push(chemin)
    }
  }
  explorer(depart)
  return trouves
}

let echec = false

// 1. La table pointe-t-elle sur des icônes qui existent ?
const require_ = createRequire(join(RACINE, 'frontend/package.json'))
let collection
try {
  collection = require_('@iconify-json/ri/icons.json')
} catch {
  console.error(
    "La collection @iconify-json/ri est absente. Lancez `npm install` dans frontend/.",
  )
  process.exit(1)
}
const disponibles = new Set([
  ...Object.keys(collection.icons),
  ...Object.keys(collection.aliases ?? {}),
])

const introuvables = Object.entries(ICONES).filter(
  ([, nom]) => !disponibles.has(nom.replace(/^i-ri-/, '')),
)
if (introuvables.length) {
  echec = true
  console.error(`\n${introuvables.length} nom(s) absent(s) de RemixIcon :`)
  for (const [emoji, nom] of introuvables) console.error(`  ${emoji}  ${nom}`)
} else {
  console.log(`Table : ${Object.keys(ICONES).length} correspondances, toutes résolues.`)
}

// 2 et 3. Reste-t-il des emoji dans les sources ?
const restants = new Map()
for (const front of FRONTS) {
  for (const fichier of sources(join(RACINE, front))) {
    const contenu = readFileSync(fichier, 'utf8')
    for (const trouve of contenu.matchAll(MOTIF_EMOJI)) {
      const cle = normaliser(trouve[0])
      if (!restants.has(cle)) restants.set(cle, [])
      restants.get(cle).push(relative(RACINE, fichier))
    }
  }
}

if (restants.size === 0) {
  console.log('Sources : aucun emoji résiduel.')
} else {
  echec = true
  const total = [...restants.values()].reduce((n, l) => n + l.length, 0)
  console.error(`\n${total} emoji dans ${restants.size} forme(s) distincte(s) :`)
  for (const [emoji, fichiers] of [...restants].sort((a, b) => b[1].length - a[1].length)) {
    const cible = DECORATIFS.has(emoji) ? '(à supprimer)' : (ICONES[emoji] ?? '(NON MAPPÉ)')
    const uniques = [...new Set(fichiers)]
    console.error(
      `  ${emoji.padEnd(3)} ×${String(fichiers.length).padStart(3)}  ${cible}` +
        `\n        ${uniques.slice(0, 4).join(', ')}${uniques.length > 4 ? `, +${uniques.length - 4}` : ''}`,
    )
  }
}

// 4. Une commande réduite à une icône est-elle nommée ?
//
// On ne cherche que les commandes SANS balise imbriquée : dès qu'un élément
// s'intercale, le contenu textuel n'est plus décidable par expression
// régulière, et mieux vaut ne rien dire que crier à tort.
const COMMANDES = /<(button|a|NuxtLink)\b([^>]*)>((?:(?!<\/?(?:button|a|NuxtLink)\b)[\s\S])*?)<\/\1>/g
const muets = []
for (const front of FRONTS) {
  for (const fichier of sources(join(RACINE, front))) {
    if (!fichier.endsWith('.vue')) continue
    const contenu = readFileSync(fichier, 'utf8')
    for (const m of contenu.matchAll(COMMANDES)) {
      const [, balise, attributs, interieur] = m
      if (!/<AppIcon\b/.test(interieur)) continue
      if (/\b(aria-label|:aria-label|title|:title)\s*=/.test(attributs)) continue
      // Reste-t-il autre chose que des icônes une fois celles-ci retirées ?
      const reste = interieur.replace(/<AppIcon\b[^>]*\/>/g, '').trim()
      if (reste) continue
      muets.push({
        fichier: relative(RACINE, fichier),
        ligne: contenu.slice(0, m.index).split('\n').length,
        balise,
      })
    }
  }
}

if (muets.length === 0) {
  console.log('Accessibilité : toute commande réduite à une icône est nommée.')
} else {
  echec = true
  console.error(`\n${muets.length} commande(s) sans nom accessible — ajoutez un aria-label :`)
  for (const c of muets) console.error(`  ${c.fichier}:${c.ligne}  <${c.balise}>`)
}

process.exit(echec ? 1 : 0)
