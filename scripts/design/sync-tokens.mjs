#!/usr/bin/env node
/**
 * Recopie les tokens du design system du front staff vers le portail client.
 *
 * Les deux applications Nuxt sont des projets séparés, montés séparément dans
 * Docker : un fichier partagé hors de leur arborescence ne serait pas visible
 * du conteneur. La source de vérité est donc `frontend/assets/css/tokens.css`
 * et ce script en propage une copie identique.
 *
 *   node scripts/design/sync-tokens.mjs           # propage
 *   node scripts/design/sync-tokens.mjs --check   # échoue si la copie a dérivé
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs'

const ROOT = new URL('../../', import.meta.url).pathname
const SOURCE = `${ROOT}frontend/assets/css/tokens.css`
const TARGET = `${ROOT}client-frontend/assets/css/tokens.css`
const CHECK = process.argv.includes('--check')

const source = readFileSync(SOURCE, 'utf8')
const current = existsSync(TARGET) ? readFileSync(TARGET, 'utf8') : null

if (current === source) {
  console.log('Les tokens du portail client sont à jour.')
  process.exit(0)
}

if (CHECK) {
  console.error(
    'Les tokens du portail client ont dérivé de la source.\n' +
    'Lancer : node scripts/design/sync-tokens.mjs',
  )
  process.exit(1)
}

writeFileSync(TARGET, source)
console.log('Tokens propagés vers client-frontend/assets/css/tokens.css')
