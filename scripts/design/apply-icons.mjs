#!/usr/bin/env node
/**
 * Remplace les emoji-icônes par des icônes RemixIcon dans les gabarits Vue.
 *
 * Sur le modèle d'`apply-tokens.mjs`, l'outil est CONSCIENT DU CONTEXTE : le
 * même emoji ne se traite pas de la même façon selon l'endroit où il tombe.
 *
 *   texte de gabarit   <button>🔧 Régler</button>
 *                      → <AppIcon …/> devant le libellé ;
 *   <option>           <option>🏖 Congé</option>
 *                      → SUPPRIMÉ. Un `<option>` natif ne peut pas contenir
 *                        d'élément : y injecter une icône afficherait le
 *                        balisage en clair dans la liste déroulante ;
 *   décoratif          « Tout est terminé 🎉 »
 *                      → SUPPRIMÉ, il n'apporte aucune information ;
 *   attribut, {{ … }},
 *   bloc <script>      → NON TOUCHÉ, seulement signalé. Ces cas demandent de
 *                        changer aussi la façon dont la valeur est rendue :
 *                        une clé `icon: '📊'` d'un tableau de navigation doit
 *                        devenir un NOM d'icône, et le gabarit qui affichait
 *                        `{{ item.icon }}` doit passer à `<AppIcon :name>`.
 *
 * L'outil signale aussi les éléments interactifs qui se retrouvent SANS
 * libellé après remplacement (un bouton dont l'emoji était l'unique contenu) :
 * il leur faut un `aria-label`, faute de quoi le bouton devient muet pour un
 * lecteur d'écran.
 *
 * Idempotent : rejouable sans dégât sur des fichiers déjà migrés.
 *
 *   node scripts/design/apply-icons.mjs [--check] [chemin…]
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

import { MOTIF_EMOJI, iconePour, normaliser } from './icon-map.mjs'

const RACINE = fileURLToPath(new URL('../..', import.meta.url))
const IGNORES = new Set(['node_modules', '.nuxt', '.output', 'dist', '.git'])
/** Éléments dont le contenu ne peut PAS accueillir de balise. */
const SANS_BALISAGE = new Set(['option', 'title', 'textarea'])
/** Éléments qui ont besoin d'un nom accessible. */
const INTERACTIFS = new Set(['button', 'a', 'nuxtlink', 'summary'])

const args = process.argv.slice(2)
const check = args.includes('--check')
const cibles = args.filter((a) => !a.startsWith('--'))

function sources(depart) {
  const trouves = []
  const explorer = (dossier) => {
    for (const entree of readdirSync(dossier)) {
      if (IGNORES.has(entree)) continue
      const chemin = join(dossier, entree)
      if (statSync(chemin).isDirectory()) explorer(chemin)
      else if (entree.endsWith('.vue')) trouves.push(chemin)
    }
  }
  const info = statSync(depart)
  if (info.isDirectory()) explorer(depart)
  else trouves.push(depart)
  return trouves
}

/**
 * Découpe un gabarit en segments, en suivant la pile des éléments ouverts.
 * Retourne les zones de TEXTE seulement : ce qui est hors balise et hors
 * interpolation `{{ … }}`.
 */
function zonesDeTexte(html) {
  const zones = []
  const pile = []
  let i = 0
  let debutTexte = 0

  const clore = (fin) => {
    if (fin > debutTexte) {
      zones.push({ debut: debutTexte, fin, parent: pile[pile.length - 1] ?? null })
    }
  }

  while (i < html.length) {
    // Commentaire : ni texte, ni balise.
    if (html.startsWith('<!--', i)) {
      clore(i)
      const fin = html.indexOf('-->', i)
      i = fin === -1 ? html.length : fin + 3
      debutTexte = i
      continue
    }
    // Interpolation : traitée comme du code, pas comme du texte.
    if (html.startsWith('{{', i)) {
      clore(i)
      const fin = html.indexOf('}}', i)
      i = fin === -1 ? html.length : fin + 2
      debutTexte = i
      continue
    }
    if (html[i] === '<') {
      clore(i)
      const fermante = html[i + 1] === '/'
      // Nom de l'élément.
      let j = i + (fermante ? 2 : 1)
      let nom = ''
      while (j < html.length && /[\w-]/.test(html[j])) nom += html[j++]
      nom = nom.toLowerCase()
      // Fin de la balise, en sautant les valeurs d'attribut entre guillemets.
      //
      // Un guillemet n'ouvre une valeur que s'il SUIT un `=`. Sans cette
      // condition, un guillemet égaré dans un NOM d'attribut fait croire à une
      // valeur qui ne se referme jamais, et l'analyse avale le reste du
      // gabarit en silence. Le cas existe dans l'application :
      // `@keydown.",.prevent="addQuickCommande"` (planning.vue).
      let guillemet = null
      while (j < html.length) {
        const c = html[j]
        if (guillemet) {
          if (c === guillemet) guillemet = null
        } else if (c === '"' || c === "'") {
          let k = j - 1
          while (k >= 0 && /\s/.test(html[k])) k--
          if (html[k] === '=') guillemet = c
        } else if (c === '>') break
        j++
      }
      const autoFermante = html[j - 1] === '/'
      if (fermante) pile.pop()
      else if (!autoFermante && nom) pile.push({ nom, balise: html.slice(i, j + 1) })
      i = j + 1
      debutTexte = i
      continue
    }
    i++
  }
  clore(html.length)
  return zones
}

const rapport = { remplaces: 0, supprimes: 0, muets: [], horsGabarit: [] }

function migrer(chemin) {
  const original = readFileSync(chemin, 'utf8')
  const relatif = relative(RACINE, chemin)

  // On ne réécrit que le premier bloc <template>. Le reste (script, style)
  // est signalé mais laissé intact.
  const debutTpl = original.indexOf('<template>')
  if (debutTpl === -1) return original
  const finTpl = original.lastIndexOf('</template>')
  if (finTpl <= debutTpl) return original

  const avant = original.slice(0, debutTpl + '<template>'.length)
  const gabarit = original.slice(debutTpl + '<template>'.length, finTpl)
  const apres = original.slice(finTpl)

  // Emoji restés hors des zones de texte : attributs, interpolations, script.
  const zones = zonesDeTexte(gabarit)
  const dansTexte = new Set()
  for (const z of zones) for (let k = z.debut; k < z.fin; k++) dansTexte.add(k)

  for (const t of gabarit.matchAll(MOTIF_EMOJI)) {
    if (!dansTexte.has(t.index)) {
      rapport.horsGabarit.push({ fichier: relatif, emoji: t[0], ou: 'attribut ou {{ }}' })
    }
  }
  for (const t of apres.matchAll(MOTIF_EMOJI)) {
    rapport.horsGabarit.push({ fichier: relatif, emoji: t[0], ou: 'script' })
  }

  // Réécriture à l'envers pour ne pas décaler les index restants.
  let sortie = gabarit
  for (const zone of [...zones].reverse()) {
    const texte = sortie.slice(zone.debut, zone.fin)
    if (!MOTIF_EMOJI.test(texte)) {
      MOTIF_EMOJI.lastIndex = 0
      continue
    }
    MOTIF_EMOJI.lastIndex = 0

    const sansBalisage = SANS_BALISAGE.has(zone.parent?.nom ?? '')
    let nbIcones = 0
    const remplace = texte.replace(MOTIF_EMOJI, (brut) => {
      const icone = iconePour(brut)
      if (icone === undefined) return brut // inconnu : on n'invente rien
      if (icone === null || sansBalisage) {
        rapport.supprimes++
        return ''
      }
      rapport.remplaces++
      nbIcones++
      return `<AppIcon name="${icone}" />`
    })

    // L'élément interactif perd-il tout libellé ? Un `aria-label` ou un
    // `title` déjà posé sur la balise ouvrante suffit à le nommer.
    if (nbIcones && INTERACTIFS.has(zone.parent?.nom ?? '')) {
      const reste = remplace.replace(/<AppIcon[^>]*\/>/g, '').trim()
      const nomme = /\b(aria-label|:?title)\s*=/.test(zone.parent.balise)
      if (!reste && !nomme) {
        rapport.muets.push({ fichier: relatif, extrait: zone.parent.balise.trim() })
      }
    }

    sortie = sortie.slice(0, zone.debut) + remplace + sortie.slice(zone.fin)
  }

  const resultat = avant + sortie + apres
  if (resultat !== original && !check) writeFileSync(chemin, resultat, 'utf8')
  return resultat
}

const depart = cibles.length
  ? cibles.map((c) => join(RACINE, c))
  : [join(RACINE, 'frontend'), join(RACINE, 'client-frontend')]

let touches = 0
for (const d of depart) {
  for (const fichier of sources(d)) {
    const original = readFileSync(fichier, 'utf8')
    if (migrer(fichier) !== original) touches++
  }
}

console.log(
  check
    ? `${rapport.remplaces} icône(s) et ${rapport.supprimes} suppression(s) dans ${touches} fichier(s) — aucune écriture.`
    : `${rapport.remplaces} icône(s) posée(s), ${rapport.supprimes} suppression(s), ${touches} fichier(s) modifié(s).`,
)

if (rapport.muets.length) {
  console.log(`\n${rapport.muets.length} élément(s) interactif(s) sans libellé — ajoutez un aria-label :`)
  for (const m of rapport.muets) console.log(`  ${m.fichier}\n      ${m.extrait}`)
}

if (rapport.horsGabarit.length) {
  const parFichier = new Map()
  for (const h of rapport.horsGabarit) {
    const cle = `${h.fichier} (${h.ou})`
    if (!parFichier.has(cle)) parFichier.set(cle, [])
    parFichier.get(cle).push(normaliser(h.emoji))
  }
  console.log(`\n${rapport.horsGabarit.length} emoji hors texte de gabarit — à reprendre à la main :`)
  for (const [cle, emojis] of parFichier) {
    console.log(`  ${cle}\n      ${[...new Set(emojis)].join(' ')}`)
  }
}
