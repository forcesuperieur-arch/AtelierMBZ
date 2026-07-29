#!/usr/bin/env node
/**
 * Migration des couleurs écrites en dur vers les tokens du design system.
 *
 * Pourquoi un script et pas 2 400 éditions à la main : l'application portait
 * ses couleurs de thème sombre en dur (#E8E9ED en texte, rgba(255,255,255,.07)
 * en bordure…). En thème clair, un texte #E8E9ED sur fond blanc est invisible.
 * Chaque valeur doit donc devenir un token qui bascule.
 *
 * Le script est CONSCIENT DE LA PROPRIÉTÉ : le même #10B981 devient
 * `var(--success-content)` derrière `color:` et `var(--success)` derrière
 * `background:`. Sans ça on peindrait du texte avec une couleur de remplissage.
 *
 * Il est IDEMPOTENT : une valeur déjà sous `var()` n'est pas retouchée. On peut
 * donc le rejouer sur des fichiers arrivés après la migration (c'est prévu pour
 * le chantier dashboard/en-atelier en cours).
 *
 * Usage :
 *   node scripts/design/apply-tokens.mjs --check      # n'écrit rien, liste ce qui changerait
 *   node scripts/design/apply-tokens.mjs              # applique
 *   node scripts/design/apply-tokens.mjs frontend/pages/planning.vue   # cible précise
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const ROOT = new URL('../../', import.meta.url).pathname
const args = process.argv.slice(2)
const CHECK = args.includes('--check')
const targets = args.filter((a) => !a.startsWith('--'))

/**
 * Table de correspondance.
 *   fill    : remplissage (background, aplat, série de graphique)
 *   content : la valeur sert de couleur de TEXTE
 *   border  : la valeur sert de bordure / filet
 * Une entrée absente retombe sur `fill`.
 */
const MAP = {
  /* ---- Surfaces sombres ----
     `content: 'accent-ink'` est indispensable : un graphite employé comme
     COULEUR DE TEXTE est de l'encre posée sur un fond clair (un libellé sur un
     aplat jaune, par exemple). Sans cette entrée, le rôle « texte » retombait
     sur la surface et le libellé devenait blanc sur jaune en thème clair. */
  '#090b10': { content: 'accent-ink', fill: 'surface-0' },
  '#0b0d12': { content: 'accent-ink', fill: 'surface-0' },
  '#0c0d12': { content: 'accent-ink', fill: 'surface-0' },
  '#0f1218': { content: 'accent-ink', fill: 'surface-1' },
  '#11141b': { content: 'accent-ink', fill: 'surface-1' },
  '#14161d': { content: 'accent-ink', fill: 'surface-1' },
  '#171b24': { content: 'accent-ink', fill: 'surface-2' },
  '#171a21': { content: 'accent-ink', fill: 'surface-2' },
  '#1a1d26': { content: 'accent-ink', fill: 'surface-2' },
  '#1a1f2b': { content: 'accent-ink', fill: 'surface-2' },
  '#242936': { content: 'accent-ink', fill: 'surface-3' },
  '#2a3040': { content: 'accent-ink', fill: 'surface-3' },

  /* ---- Textes clairs (thème sombre) ---- */
  '#ffffff': { dflt: 'content-1', content: 'content-1', fill: 'surface-1', border: 'border-strong' },
  '#fff': { dflt: 'content-1', content: 'content-1', fill: 'surface-1', border: 'border-strong' },
  '#f8fafc': { dflt: 'content-1', content: 'content-1', fill: 'surface-1' },
  '#f3f4f6': { dflt: 'content-1', content: 'content-1', fill: 'surface-1' },
  '#f0f1f5': { dflt: 'content-1', content: 'content-1', fill: 'surface-1' },
  '#e8e9ed': { dflt: 'content-1', content: 'content-1', fill: 'surface-1' },
  '#e5e7eb': { dflt: 'content-1', content: 'content-1', fill: 'surface-2' },
  '#d1d5db': { dflt: 'content-2', content: 'content-2', fill: 'surface-3' },
  '#cbd5e1': { dflt: 'content-2', content: 'content-2', fill: 'surface-3' },
  '#9ca3af': { dflt: 'content-3', content: 'content-3', fill: 'surface-3', border: 'border-1' },
  '#6b7280': { dflt: 'content-3', content: 'content-3', fill: 'surface-3', border: 'border-1' },
  '#4b5563': { dflt: 'content-disabled', content: 'content-disabled', fill: 'surface-3', border: 'border-1' },
  '#111111': { dflt: 'accent-ink', content: 'accent-ink', fill: 'surface-0' },
  '#111827': { dflt: 'accent-ink', content: 'accent-ink', fill: 'surface-0' },
  '#000000': { dflt: 'accent-ink', content: 'accent-ink', fill: 'surface-inverse' },

  /* ---- Hexadécimaux à 8 chiffres (couleur + opacité), remplissages d'aire
     des graphiques. Leur opacité (0x20 ≈ 12,5 %) correspond aux fonds
     teintés `--*-soft`. ---- */
  '#ffd20020': { fill: 'accent-soft', dflt: 'accent-soft' },
  '#ffd20010': { fill: 'accent-soft', dflt: 'accent-soft' },
  '#ffd20008': { fill: 'accent-soft', dflt: 'accent-soft' },
  '#f1ab0020': { fill: 'accent-soft', dflt: 'accent-soft' },
  '#14b8a620': { fill: 'success-soft', dflt: 'success-soft' },

  /* ---- Jaune de marque ---- */
  '#ffd200': { content: 'accent-content', fill: 'accent', border: 'accent' },
  '#ffde33': { content: 'accent-content', fill: 'accent-hover', border: 'accent-hover' },
  '#ffb800': { content: 'accent-content', fill: 'accent-hover', border: 'accent-hover' },
  '#f1ab00': { content: 'accent-content', fill: 'accent', border: 'accent' },

  /* ---- Sémantique : succès ---- */
  '#10b981': { content: 'success-content', fill: 'success', border: 'success' },
  '#059669': { content: 'success-content', fill: 'success', border: 'success' },
  '#34d399': { content: 'success-content', fill: 'success', border: 'success' },
  '#6ee7b7': { content: 'success-content', fill: 'success', border: 'success' },
  '#a7f3d0': { content: 'success-content', fill: 'success-soft', border: 'success' },
  '#14b8a6': { content: 'success-content', fill: 'success', border: 'success' },
  '#2dd4bf': { content: 'success-content', fill: 'success', border: 'success' },

  /* ---- Sémantique : alerte ---- */
  '#f59e0b': { content: 'warning-content', fill: 'warning', border: 'warning' },
  '#d97706': { content: 'warning-content', fill: 'warning', border: 'warning' },
  '#fbbf24': { content: 'warning-content', fill: 'warning', border: 'warning' },
  '#fcd34d': { content: 'warning-content', fill: 'warning', border: 'warning' },
  '#fde68a': { content: 'warning-content', fill: 'warning-soft', border: 'warning' },

  /* ---- Sémantique : erreur ---- */
  '#ef4444': { content: 'error-content', fill: 'error', border: 'error' },
  '#dc2626': { content: 'error-content', fill: 'error', border: 'error' },
  '#f87171': { content: 'error-content', fill: 'error', border: 'error' },
  '#fca5a5': { content: 'error-content', fill: 'error', border: 'error' },
  '#fecaca': { content: 'error-content', fill: 'error-soft', border: 'error' },
  '#b91c1c': { content: 'error-content', fill: 'error', border: 'error' },

  /* ---- Sémantique : information (le violet et le cyan n'existent pas au DS) ---- */
  '#3b82f6': { content: 'info-content', fill: 'info', border: 'info' },
  '#2563eb': { content: 'info-content', fill: 'info', border: 'info' },
  '#60a5fa': { content: 'info-content', fill: 'info', border: 'info' },
  '#93c5fd': { content: 'info-content', fill: 'info', border: 'info' },
  '#bfdbfe': { content: 'info-content', fill: 'info-soft', border: 'info' },
  '#8b5cf6': { content: 'info-content', fill: 'info', border: 'info' },
  '#a78bfa': { content: 'info-content', fill: 'info', border: 'info' },
  '#c4b5fd': { content: 'info-content', fill: 'info', border: 'info' },
  '#7c3aed': { content: 'info-content', fill: 'info', border: 'info' },
  '#06b6d4': { content: 'info-content', fill: 'info', border: 'info' },
  '#22d3ee': { content: 'info-content', fill: 'info', border: 'info' },
  '#1d4ed8': { content: 'info-content', fill: 'info', border: 'info' },
  /* Série 3 de la palette de données (turquoise) : ce n'est PAS un vert de
     statut, c'est une identité de série — elle a sa propre échelle. */
  '#0d9488': { fill: 'viz-3', dflt: 'viz-3', content: 'viz-3', border: 'viz-3' },

  /* ---- Deuxième passe : valeurs relevées par le rapport « hors table ».
     Même logique, ces teintes étaient des variantes ponctuelles des mêmes
     rôles (un graphite de plus, un vert de plus…) plutôt qu'un choix. ---- */
  '#080810': { content: 'accent-ink', fill: 'surface-0' },
  '#0a0b0f': { content: 'accent-ink', fill: 'surface-0' },
  '#0b0e14': { content: 'accent-ink', fill: 'surface-0' },
  '#0b1120': { content: 'accent-ink', fill: 'surface-0' },
  '#0e0f15': { content: 'accent-ink', fill: 'surface-0' },
  '#0f1117': { content: 'accent-ink', fill: 'surface-1' },
  '#0f172a': { content: 'accent-ink', fill: 'surface-1' },
  '#111218': { content: 'accent-ink', fill: 'surface-1' },
  '#15161d': { content: 'accent-ink', fill: 'surface-1' },
  '#151621': { content: 'accent-ink', fill: 'surface-1' },
  '#1a1a2e': { content: 'accent-ink', fill: 'surface-2' },
  '#2a2e3a': { fill: 'surface-3', border: 'border-1' },
  '#3a4050': { fill: 'surface-3', border: 'border-1' },
  '#16150e': { fill: 'accent-soft' },
  '#111': { dflt: 'accent-ink', content: 'accent-ink', fill: 'surface-0' },
  '#555': { dflt: 'content-3', content: 'content-3', fill: 'surface-3', border: 'border-1' },
  '#eee': { dflt: 'content-1', content: 'content-1', fill: 'surface-2' },
  '#c4c5ca': { dflt: 'content-2', content: 'content-2', fill: 'surface-3' },
  '#94a3b8': { dflt: 'content-3', content: 'content-3', fill: 'surface-3', border: 'border-1' },
  '#f9fafb': { dflt: 'content-1', content: 'content-1', fill: 'surface-1' },
  '#1f2937': { dflt: 'content-1', content: 'content-1', fill: 'surface-2', border: 'border-1' },
  '#374151': { dflt: 'content-2', content: 'content-2', fill: 'surface-3', border: 'border-1' },
  /* verts */
  '#22c55e': { content: 'success-content', fill: 'success', border: 'success' },
  '#4ade80': { content: 'success-content', fill: 'success', border: 'success' },
  '#86efac': { content: 'success-content', fill: 'success', border: 'success' },
  '#bbf7d0': { content: 'success-content', fill: 'success-soft', border: 'success' },
  '#d1fae5': { content: 'success-content', fill: 'success-soft', border: 'success' },
  '#5eead4': { content: 'success-content', fill: 'success', border: 'success' },
  '#99f6e4': { content: 'success-content', fill: 'success-soft', border: 'success' },
  '#14532d': { content: 'success-content', fill: 'success-soft', border: 'success' },
  /* rouges */
  '#fb7185': { content: 'error-content', fill: 'error', border: 'error' },
  '#fef2f2': { content: 'error-content', fill: 'error-soft', border: 'error' },
  /* ambres et jaunes */
  '#f97316': { content: 'warning-content', fill: 'warning', border: 'warning' },
  '#fef3c7': { content: 'warning-content', fill: 'warning-soft', border: 'warning' },
  '#713f12': { content: 'warning-content', fill: 'warning-soft', border: 'warning' },
  '#f0b90b': { content: 'accent-content', fill: 'accent', border: 'accent' },
  '#ffe033': { content: 'accent-content', fill: 'accent-hover', border: 'accent-hover' },
}

/**
 * Teintes translucides autres que le blanc, le noir et le jaune : ce sont les
 * fonds et bordures d'état (`rgba(239,68,68,.08)` derrière un message
 * d'erreur…). Elles portent le même sens que les hexadécimaux ci-dessus, il
 * faut donc les envoyer vers les mêmes tokens, sinon un badge d'erreur garde
 * un fond rouge sombre sur une interface passée en clair.
 *
 * Clé = triplet « r,g,b » normalisé sans espace.
 */
const RGB_FAMILY = {
  /* états */
  '239,68,68': 'error',
  '248,113,113': 'error',
  '220,38,38': 'error',
  '16,185,129': 'success',
  '34,197,94': 'success',
  '20,184,166': 'success',
  '134,239,172': 'success',
  '94,234,212': 'success',
  '5,150,105': 'success',
  '245,158,11': 'warning',
  '251,191,36': 'warning',
  '252,211,77': 'warning',
  '253,224,71': 'warning',
  '217,119,6': 'warning',
  '59,130,246': 'info',
  '96,165,250': 'info',
  '147,197,253': 'info',
  '139,92,246': 'info',
  '167,139,250': 'info',
  '99,102,241': 'info',
  '124,58,237': 'info',
  '52,211,153': 'success',
}

/**
 * Réalignement de teinte pour les ombres et halos : ancien triplet → triplet
 * de la palette Motoblouz. Le noir n'y figure pas, une ombre noire reste noire.
 */
const SHADOW_HUE = {
  '255,210,0': '241,171,0',    // ancien jaune Paddock → jaune de marque
  '255,184,0': '241,171,0',
  '239,68,68': '215,3,33',     // → #D70321
  '248,113,113': '215,3,33',
  '16,185,129': '23,149,0',    // → #179500
  '34,197,94': '23,149,0',
  '20,184,166': '23,149,0',
  '245,158,11': '217,101,0',   // → #D96500
  '251,191,36': '217,101,0',
  '59,130,246': '74,141,183',  // → #4A8DB7
  '96,165,250': '74,141,183',
  '139,92,246': '74,141,183',
  '167,139,250': '74,141,183',
}

/** Graphites et gris translucides : mêmes rôles que leurs équivalents opaques. */
const RGB_NEUTRAL = {
  '3,7,18': 'surface-0',
  '12,14,20': 'surface-0',
  '15,16,20': 'surface-0',
  '17,24,39': 'surface-0',
  '15,23,42': 'surface-1',
  '16,17,23': 'surface-1',
  '17,20,27': 'surface-1',
  '19,20,27': 'surface-1',
  '23,27,36': 'surface-2',
  '71,85,105': 'surface-3',
  '107,114,128': 'content-3',
  '156,163,175': 'content-3',
  '148,163,184': 'content-3',
  '248,250,252': 'content-1',
  '17,17,17': 'accent-ink',
  '13,15,20': 'surface-1',
}

/**
 * Voiles blancs et noirs : en thème clair un `rgba(255,255,255,.07)` de bordure
 * disparaît sur blanc. On les classe par opacité, l'usage étant très régulier
 * dans ce dépôt (voiles faibles = fond, moyens = filet, forts = bordure).
 */
function mapAlphaWhite(alpha, role) {
  const a = parseFloat(alpha)
  if (role === 'border') return a <= 0.09 ? 'border-2' : 'border-1'
  if (role === 'content') return a >= 0.5 ? 'content-1' : 'content-3'
  if (a <= 0.04) return 'overlay-soft'
  if (a <= 0.08) return 'overlay-hover'
  if (a <= 0.2) return 'surface-3'
  return 'surface-2'
}

/** Ombres et voiles noirs : on renvoie null pour laisser la valeur telle quelle
 *  quand elle relève d'une ombre (déjà gérée par les tokens d'élévation). */
function mapAlphaBlack(alpha, role) {
  const a = parseFloat(alpha)
  if (role === 'border') return 'border-2'
  if (role === 'content') return 'content-1'
  if (a >= 0.4) return 'scrim'
  return null
}

/** Déduit le rôle de la couleur d'après la propriété CSS qui la précède. */
function roleFor(before) {
  // Dernière propriété `nom:` ouverte sur le fragment qui précède la couleur.
  const m = [...before.matchAll(/(?:^|[\s;{,'"([])([a-zA-Z-]+)\s*:/g)].pop()
  const prop = (m ? m[1] : '').toLowerCase()
  // 'none' = aucune propriété CSS identifiable (valeur portée par du
  // JavaScript : table de couleurs, valeur de retour). On ne peut pas déduire
  // le rôle, on retombe alors sur le défaut déclaré par la teinte.
  if (!prop) return 'none'
  if (prop === 'color' || prop === '-webkit-text-fill-color' || prop === 'caret-color') return 'content'
  if (prop === 'fill' || prop === 'stroke') return 'content'
  if (prop.startsWith('border') || prop === 'outline' || prop === 'outlinecolor') return 'border'
  if (prop.includes('shadow')) return 'shadow'
  if (prop.startsWith('background') || prop === 'accent-color' || prop === 'bg') return 'fill'
  return 'none'
}

const unmapped = new Map()

/**
 * Le bloc `@theme` de Tailwind déclare les échelles de marque pour les
 * composants Nuxt UI : comme `tokens.css`, c'est une zone où les hexadécimaux
 * sont LÉGITIMES. On l'extrait avant conversion et on le remet ensuite, sinon
 * le script se signalerait à lui-même ses propres valeurs de marque.
 */
function protectThemeBlock(source) {
  const start = source.indexOf('@theme')
  if (start < 0) return [source, null]
  const open = source.indexOf('{', start)
  if (open < 0) return [source, null]
  let depth = 0
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++
    else if (source[i] === '}') {
      depth--
      if (depth === 0) {
        const block = source.slice(start, i + 1)
        return [source.slice(0, start) + '/*__THEME_BLOCK__*/' + source.slice(i + 1), block]
      }
    }
  }
  return [source, null]
}

function convert(source, file) {
  let changed = 0
  const [protectedSource, themeBlock] = protectThemeBlock(source)
  source = protectedSource

  // 1. Hexadécimaux — on ignore ceux déjà dans un var() ou dans une URL.
  source = source.replace(/#([0-9a-fA-F]{3,8})\b/g, (match, hex, offset) => {
    const key = match.toLowerCase()
    const entry = MAP[key]
    const before = source.slice(Math.max(0, offset - 160), offset)
    // Un `#` peut être une ancre, un identifiant CSS ou une couleur dans un
    // commentaire : on n'agit que si la valeur est connue de la table.
    if (!entry) {
      if (/^([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(hex)) {
        const list = unmapped.get(key) ?? new Set()
        list.add(relative(ROOT, file))
        unmapped.set(key, list)
      }
      return match
    }
    const role = roleFor(before)
    if (role === 'shadow') return match // les ombres passent par --elevation-*
    const token = entry[role] ?? entry.dflt ?? entry.fill ?? entry.content
    if (!token) return match
    changed++
    return `var(--${token})`
  })

  // 2. rgb/rgba blancs et noirs.
  source = source.replace(
    /rgba?\(\s*(255)\s*,\s*(255)\s*,\s*(255)\s*(?:,\s*([\d.]+)\s*)?\)|rgba?\(\s*(0)\s*,\s*(0)\s*,\s*(0)\s*(?:,\s*([\d.]+)\s*)?\)/g,
    (match, ...rest) => {
      const offset = rest[rest.length - 2]
      const before = source.slice(Math.max(0, offset - 160), offset)
      const role = roleFor(before)
      if (role === 'shadow') return match
      const isWhite = rest[0] !== undefined
      const alpha = isWhite ? (rest[3] ?? '1') : (rest[7] ?? '1')
      const token = isWhite ? mapAlphaWhite(alpha, role) : mapAlphaBlack(alpha, role)
      if (!token) return match
      changed++
      return `var(--${token})`
    },
  )

  // 3. Voiles jaunes : rgba(255,210,0,x) (ancien jaune) et rgba(241,171,0,x).
  source = source.replace(
    /rgba?\(\s*(?:255\s*,\s*210\s*,\s*0|241\s*,\s*171\s*,\s*0|255\s*,\s*184\s*,\s*0)\s*(?:,\s*([\d.]+)\s*)?\)/g,
    (match, alpha, offset) => {
      const before = source.slice(Math.max(0, offset - 160), offset)
      const role = roleFor(before)
      if (role === 'shadow') return match
      changed++
      if (role === 'content') return 'var(--accent-content)'
      if (role === 'border') return 'var(--accent)'
      return 'var(--accent-soft)'
    },
  )

  // 4. Autres teintes translucides : états (rouge, vert, ambre, bleu) et
  //    graphites. Une opacité forte vaut la couleur pleine, une opacité faible
  //    vaut le fond teinté `--*-soft`.
  source = source.replace(
    /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/g,
    (match, r, g, b, alpha, offset) => {
      const key = `${r},${g},${b}`
      const before = source.slice(Math.max(0, offset - 160), offset)
      const role = roleFor(before)
      if (role === 'shadow') return match

      if (key === '13,148,136') {
        changed++
        // Teinte d'aire de la série 3 : token dédié, pas un vert de statut.
        return role === 'border' ? 'var(--viz-3)' : 'var(--viz-3-soft)'
      }

      const family = RGB_FAMILY[key]
      if (family) {
        changed++
        if (role === 'content') return `var(--${family}-content)`
        if (role === 'border') return `var(--${family})`
        return parseFloat(alpha ?? '1') >= 0.5 ? `var(--${family})` : `var(--${family}-soft)`
      }

      const neutral = RGB_NEUTRAL[key]
      if (neutral) {
        changed++
        const isTextTone = neutral.startsWith('content') || neutral === 'accent-ink'
        if (role === 'border') return 'var(--border-1)'
        if (role === 'content') return isTextTone ? `var(--${neutral})` : 'var(--content-1)'
        return isTextTone ? 'var(--surface-3)' : `var(--${neutral})`
      }

      // Le blanc, le noir et le jaune ont déjà été traités plus haut.
      if (!/^(255,255,255|0,0,0|255,210,0|241,171,0|255,184,0)$/.test(key)) {
        const list = unmapped.get(`rgb(${key})`) ?? new Set()
        list.add(relative(ROOT, file))
        unmapped.set(`rgb(${key})`, list)
      }
      return match
    },
  )

  // 5b. Hexadécimaux dans une ombre : un aplat de marque écrit là devient un
  //     token, une teinte de la palette est réalignée. Sans cette passe, un
  //     liseré `inset 0 2px 0 #FFD200` gardait l'ancien jaune.
  source = source.replace(/#([0-9a-fA-F]{3,8})\b/g, (match, hex, offset) => {
    const before = source.slice(Math.max(0, offset - 200), offset)
    if (roleFor(before) !== 'shadow') return match
    const entry = MAP[match.toLowerCase()]
    const token = entry?.border ?? entry?.fill
    if (!token) return match
    changed++
    return `var(--${token})`
  })

  // 5. Ombres. Elles gardent leur structure (une ombre n'est pas exprimable
  //    par un token de couleur : elle porte des décalages et un flou), mais
  //    leur TEINTE doit suivre la palette. Sans cette passe, un halo garderait
  //    l'ancien jaune #FFD200 ou l'ancien vert #10B981 à côté d'un aplat
  //    déjà passé au jaune de marque.
  source = source.replace(
    /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/g,
    (match, r, g, b, alpha, offset) => {
      const before = source.slice(Math.max(0, offset - 200), offset)
      if (roleFor(before) !== 'shadow') return match
      const target = SHADOW_HUE[`${r},${g},${b}`]
      if (!target) return match
      changed++
      return alpha === undefined ? `rgb(${target})` : `rgba(${target},${alpha})`
    },
  )

  if (themeBlock) source = source.replace('/*__THEME_BLOCK__*/', themeBlock)

  return { source, changed }
}

/* ---------- Parcours des fichiers ---------- */
const EXT = /\.(vue|css|ts|js|mjs)$/
const SKIP = /node_modules|\.nuxt|\.output|dist|tests\/e2e|playwright/

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)
    if (SKIP.test(full)) continue
    const st = statSync(full)
    if (st.isDirectory()) walk(full, out)
    else if (EXT.test(name)) out.push(full)
  }
  return out
}

const files = targets.length
  ? targets.map((t) => join(ROOT, t))
  : [
      ...walk(join(ROOT, 'frontend/pages')),
      ...walk(join(ROOT, 'frontend/components')),
      ...walk(join(ROOT, 'frontend/layouts')),
      ...walk(join(ROOT, 'frontend/assets/css')),
      ...walk(join(ROOT, 'client-frontend/pages')),
      ...walk(join(ROOT, 'client-frontend/components')),
      ...walk(join(ROOT, 'client-frontend/layouts')),
      ...walk(join(ROOT, 'client-frontend/assets/css')),
    ]

let totalFiles = 0
let totalChanges = 0
for (const file of files) {
  // tokens.css porte les valeurs de marque : c'est la seule feuille qui a le
  // droit d'écrire des hexadécimaux en dur.
  if (file.endsWith('tokens.css')) continue
  const original = readFileSync(file, 'utf8')
  const { source, changed } = convert(original, file)
  if (!changed || source === original) continue
  totalFiles++
  totalChanges += changed
  if (!CHECK) writeFileSync(file, source)
  console.log(`${CHECK ? 'à migrer' : 'migré  '} ${String(changed).padStart(4)}  ${relative(ROOT, file)}`)
}

console.log(`\n${totalChanges} valeur(s) dans ${totalFiles} fichier(s)${CHECK ? ' (aucune écriture)' : ''}.`)

if (unmapped.size) {
  console.log(`\n${unmapped.size} couleur(s) hors table — à trancher à la main :`)
  for (const [hex, filesSet] of [...unmapped.entries()].sort()) {
    const list = [...filesSet]
    console.log(`  ${hex}  (${list.length} fichier(s)) ex. ${list[0]}`)
  }
}
