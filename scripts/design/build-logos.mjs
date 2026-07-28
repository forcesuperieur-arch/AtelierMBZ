#!/usr/bin/env node
/**
 * Décline les logos Paddock pour le design system.
 *
 * Deux problèmes à régler :
 *
 * 1. Les fichiers portent la mention « Place on dark background » et écrivent le
 *    mot-symbole en blanc cassé (#f2efe8). Sur le thème clair il DISPARAÎT.
 *    On produit donc une variante `-light.svg` dont le mot-symbole est en noir.
 *
 * 2. Le doré du tracé (#D4A843) est un TROISIÈME jaune, distinct du jaune
 *    Motoblouz. Il est aligné sur la couleur de marque.
 *
 * La typographie du mot-symbole passe de `system-ui` à Montserrat, la police
 * d'affichage du design system.
 *
 *   node scripts/design/build-logos.mjs           # régénère
 *   node scripts/design/build-logos.mjs --check   # échoue si une sortie a dérivé
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

const ROOT = new URL('../../', import.meta.url).pathname
const CHECK = process.argv.includes('--check')

/** Couleurs d'origine des fichiers de marque. */
const GOLD_SOURCE = /#D4A843/g
const WORDMARK_SOURCE = /#f2efe8/g
const TAGLINE_SOURCE = /#6b6b5e/g
const FONT_SOURCE = /font-family="system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif"/g

/** Cibles du design system. */
const GOLD = '#F1AB00'                 // jaune Motoblouz
const WORDMARK_ON_LIGHT = '#111111'    // encre du DS sur fond clair
const TAGLINE_ON_LIGHT = '#5E5E5E'     // grey-800
const FONT = 'font-family="Montserrat, system-ui, -apple-system, sans-serif"'

/** Fichiers déclinés : ceux affichés DANS l'application. Les visuels de
 *  partage social et le favicon gardent leur fond sombre et ne bougent pas. */
const VARIANTS = ['paddock-logo-horizontal.svg', 'paddock-logo-stacked.svg']

/** Le portail client n'embarque que le logo empilé. */
const TARGET_DIRS = [
  { dir: 'frontend/public/branding', files: VARIANTS },
  { dir: 'client-frontend/public/branding', files: ['paddock-logo-stacked.svg'] },
]

/** Tous les fichiers de marque voient leur doré aligné. */
const GOLD_ONLY = ['paddock-logo-favicon.svg', 'paddock-logo-social.svg', 'paddock-logo-symbol.svg']

let drift = 0
let written = 0

function emit(path, content) {
  const absolute = join(ROOT, path)
  const current = existsSync(absolute) ? readFileSync(absolute, 'utf8') : null
  if (current === content) return
  if (CHECK) {
    console.error(`dérive : ${path}`)
    drift++
    return
  }
  writeFileSync(absolute, content)
  written++
  console.log(`écrit ${path}`)
}

for (const { dir, files } of TARGET_DIRS) {
  for (const file of files) {
    const sourcePath = join(ROOT, dir, file)
    if (!existsSync(sourcePath)) continue
    const source = readFileSync(sourcePath, 'utf8')

    // Variante sombre : le mot-symbole reste clair, seuls le doré et la police
    // sont alignés sur le design system.
    const dark = source.replace(GOLD_SOURCE, GOLD).replace(FONT_SOURCE, FONT)
    emit(join(dir, file), dark)

    // Variante claire : mot-symbole et signature encrés.
    const light = dark
      .replace(WORDMARK_SOURCE, WORDMARK_ON_LIGHT)
      .replace(TAGLINE_SOURCE, TAGLINE_ON_LIGHT)
      .replace(
        '<!-- Background: transparent. Place on dark background (#0c0c0c) for best result -->',
        '<!-- Background: transparent. Variante THÈME CLAIR (générée par scripts/design/build-logos.mjs) -->',
      )
    emit(join(dir, file.replace('.svg', '-light.svg')), light)
  }
}

for (const file of GOLD_ONLY) {
  const path = join('frontend/public/branding', file)
  const absolute = join(ROOT, path)
  if (!existsSync(absolute)) continue
  emit(path, readFileSync(absolute, 'utf8').replace(GOLD_SOURCE, GOLD).replace(FONT_SOURCE, FONT))
}

if (CHECK) {
  if (drift) {
    console.error(`\n${drift} fichier(s) de marque à régénérer : node scripts/design/build-logos.mjs`)
    process.exit(1)
  }
  console.log('Les logos sont à jour.')
} else {
  console.log(`\n${written} fichier(s) écrit(s).`)
}
