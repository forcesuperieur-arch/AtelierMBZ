#!/usr/bin/env node
/**
 * Contrôle WCAG des tokens de texte et de bordure du design system, thème par thème.
 *
 * Sans dépendance : on résout les `var()` à la main depuis tokens.css. Les
 * fonds teintés (`--*-soft`) étant translucides, une paire peut être décrite
 * comme une PILE : ['--error-content', '--error-soft', '--surface-1'] compose
 * le fond sur la surface avant de mesurer.
 *
 * Seuils : 4,5:1 pour du texte courant (AA 1.4.3), 3:1 pour les limites de
 * composants et les éléments graphiques porteurs de sens (AA 1.4.11).
 */
import { readFileSync } from 'node:fs'

// Les commentaires doivent partir AVANT le découpage : un `/* … */` en fin de
// déclaration avalerait sinon la déclaration suivante.
const CSS = readFileSync(new URL('../../frontend/assets/css/tokens.css', import.meta.url), 'utf8')
  .replace(/\/\*[\s\S]*?\*\//g, '')

/** Déclarations des blocs dont le sélecteur satisfait `match`. */
function declarations(match) {
  const out = {}
  const re = /([^{}]+)\{([^}]*)\}/g
  let m
  while ((m = re.exec(CSS))) {
    const selector = m[1].trim()
    if (!match(selector)) continue
    for (const decl of m[2].split(';')) {
      const i = decl.indexOf(':')
      if (i < 0) continue
      const name = decl.slice(0, i).trim()
      if (!name.startsWith('--')) continue
      out[name] = decl.slice(i + 1).trim()
    }
  }
  return out
}

const isDark = (s) => s.includes("data-theme='dark'")
const isLight = (s) => s.includes("data-theme='light'")
// Base = tout ce qui n'est pas spécifique à un thème (marque, échelles, alias).
const base = declarations((s) => !isDark(s) && !isLight(s))
const light = { ...base, ...declarations(isLight) }
const dark = { ...base, ...declarations(isDark) }

function resolve(value, scope, depth = 0) {
  if (depth > 16 || !value) return value
  const m = value.match(/var\((--[\w-]+)\)/)
  if (!m) return value
  const replacement = scope[m[1]]
  if (replacement === undefined) return null
  return resolve(value.replace(m[0], replacement), scope, depth + 1)
}

function parse(color) {
  if (!color) return null
  const c = color.trim()
  let m = c.match(/^#([0-9a-f]{6})$/i)
  if (m) {
    const n = parseInt(m[1], 16)
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255, 1]
  }
  m = c.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/)
  if (m) return [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]]
  return null
}

const lin = (v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4 }
const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)

/** Compose `fg` (éventuellement translucide) sur `bg` opaque. */
const over = (fg, bg) => fg[3] >= 1 ? fg : [0, 1, 2].map((i) => fg[i] * fg[3] + bg[i] * (1 - fg[3])).concat(1)

/** Pile de tokens, du premier plan vers le fond opaque. */
function ratio(stack, scope) {
  const colors = stack.map((t) => parse(resolve(`var(${t})`, scope)))
  if (colors.some((c) => !c)) return null
  let bg = colors[colors.length - 1]
  for (let i = colors.length - 2; i >= 1; i--) bg = over(colors[i], bg)
  const f = lum(over(colors[0], bg)), b = lum(bg)
  const [hi, lo] = f > b ? [f, b] : [b, f]
  return (hi + 0.05) / (lo + 0.05)
}

const TEXT = [
  ['--content-1', '--surface-0'], ['--content-1', '--surface-1'], ['--content-1', '--surface-2'],
  ['--content-2', '--surface-0'], ['--content-2', '--surface-1'], ['--content-2', '--surface-2'],
  ['--content-3', '--surface-0'], ['--content-3', '--surface-1'],
  // Les gris de texte doivent tenir aussi sur les surfaces en retrait : c'est
  // là qu'un libellé de puce passait sous le seuil, invisible du premier jeu.
  ['--content-3', '--surface-2'], ['--content-3', '--surface-3'],
  ['--content-2', '--surface-3'],
  ['--accent-content', '--surface-2'], ['--accent-content', '--surface-3'],
  ['--accent-content', '--accent-soft', '--surface-2'],
  ['--accent-content', '--surface-0'], ['--accent-content', '--surface-1'],
  ['--accent-ink', '--accent'],
  ['--content-inverse', '--surface-inverse'],
  ['--success-content', '--surface-1'], ['--warning-content', '--surface-1'],
  ['--error-content', '--surface-1'], ['--info-content', '--surface-1'],
  ['--success-content', '--success-soft', '--surface-1'],
  ['--error-content', '--error-soft', '--surface-1'],
  ['--warning-content', '--warning-soft', '--surface-1'],
  ['--info-content', '--info-soft', '--surface-1'],
  ['--accent-content', '--accent-soft', '--surface-1'],
  // Encre posée sur un aplat d'état : pastille de compteur, badge de statut.
  ['--on-success', '--success'],
  ['--on-warning', '--warning'],
  ['--on-error', '--error'],
  ['--on-info', '--info'],
]
const NON_TEXT = [
  ['--border-control', '--surface-0'], ['--border-control', '--surface-1'],
  ['--accent-graphic', '--surface-0'], ['--accent-graphic', '--surface-1'],
  ['--success', '--surface-1'], ['--error', '--surface-1'], ['--info', '--surface-1'],
  ['--warning', '--surface-1'],
]

let failures = 0
for (const [label, scope] of [['CLAIR', light], ['SOMBRE', dark]]) {
  console.log(`\n=== ${label} ===`)
  for (const [group, pairs, min] of [['texte (AA 4,5:1)', TEXT, 4.5], ['non textuel (AA 3,0:1)', NON_TEXT, 3]]) {
    console.log(`  -- ${group}`)
    for (const stack of pairs) {
      const r = ratio(stack, scope)
      const label2 = `${stack[0]} sur ${stack.slice(1).join(' sur ')}`
      if (r === null) { console.log(`     ????  ${label2} : non résolu`); failures++; continue }
      const ok = r >= min
      if (!ok) failures++
      console.log(`     ${ok ? 'ok   ' : 'ÉCHEC'} ${r.toFixed(2).padStart(5)}:1  ${label2}`)
    }
  }
}
console.log(`\n${failures === 0 ? 'Tous les seuils sont tenus.' : `${failures} paire(s) sous le seuil.`}`)
process.exit(failures === 0 ? 0 : 1)
