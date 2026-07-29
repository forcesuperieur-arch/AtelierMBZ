/**
 * Correspondance emoji → RemixIcon, source de vérité du chantier « icônes ».
 *
 * Le design system Motoblouz proscrit les emoji comme icônes et impose un jeu
 * tracé : RemixIcon. Les noms sont ceux de la collection `@iconify-json/ri`,
 * embarquée en devDependency du front staff pour que `@nuxt/icon` les résolve
 * SANS appeler l'API Iconify au runtime — un serveur d'atelier sans accès
 * sortant afficherait sinon des icônes vides.
 *
 * Piège : un nom absent de la collection ne lève AUCUNE erreur, il rend un
 * carré vide. Toute entrée ajoutée ici doit être vérifiée par
 * `node scripts/design/check-icons.mjs`, qui contrôle la table contre la
 * collection installée.
 *
 * Les emoji portent souvent un sélecteur de variante U+FE0F invisible
 * (« 🛠️ » = 🛠 + FE0F). Les clés sont écrites SANS ce sélecteur ; les
 * consommateurs normalisent avant de chercher.
 */

/** Emoji (sans sélecteur de variante) → nom d'icône Iconify complet. */
export const ICONES = {
  // Actions et contrôles
  '✕': 'i-ri-close-line',
  '✗': 'i-ri-close-line',
  '✖': 'i-ri-close-line',
  '✓': 'i-ri-check-line',
  '☰': 'i-ri-menu-line',
  '✏': 'i-ri-pencil-line',
  '🗑': 'i-ri-delete-bin-line',
  '💾': 'i-ri-save-line',
  '🔄': 'i-ri-refresh-line',
  '🔍': 'i-ri-search-line',
  '🔎': 'i-ri-search-line',
  '📌': 'i-ri-pushpin-line',
  '🔗': 'i-ri-links-line',
  '☑': 'i-ri-checkbox-line',
  '☐': 'i-ri-checkbox-blank-line',
  '⬜': 'i-ri-checkbox-blank-line',
  '↻': 'i-ri-refresh-line',
  '↺': 'i-ri-eraser-line',
  '↩': 'i-ri-arrow-go-back-line',
  '↪': 'i-ri-arrow-go-forward-line',
  '←': 'i-ri-arrow-left-line',
  '◀': 'i-ri-arrow-left-line',
  '▶': 'i-ri-play-line',
  '⏸': 'i-ri-pause-line',
  '⏭': 'i-ri-skip-forward-line',
  '⏻': 'i-ri-shut-down-line',
  '□': 'i-ri-square-line',

  // Chevrons de repli et indicateurs de tendance. Le triangle plein « ▲ » et
  // la flèche « ↑ » disent la même chose dans deux composants différents
  // (StatsCard et StatTile) : ils reçoivent la même icône, faute de quoi le
  // même indicateur n'aurait pas la même allure d'un écran à l'autre.
  '▲': 'i-ri-arrow-up-s-fill',
  '▼': 'i-ri-arrow-down-s-fill',
  '↑': 'i-ri-arrow-up-s-fill',
  '↓': 'i-ri-arrow-down-s-fill',

  // Alignement de texte du composeur de documents : ces trois glyphes n'ont
  // pas d'autre emploi dans l'application, ils désignent bien un alignement
  // et non une direction.
  '⬅': 'i-ri-align-left',
  '↔': 'i-ri-align-center',
  '➡': 'i-ri-align-right',

  // États
  '✅': 'i-ri-checkbox-circle-line',
  '❌': 'i-ri-close-circle-line',
  '⚠': 'i-ri-error-warning-line',
  '🟠': 'i-ri-error-warning-fill',
  '🔴': 'i-ri-alarm-warning-fill',
  '🚫': 'i-ri-forbid-line',
  '⛔': 'i-ri-forbid-2-line',
  'ℹ': 'i-ri-information-line',

  // Métier atelier
  '🏍': 'i-ri-motorbike-line',
  '🔧': 'i-ri-tools-line',
  '🛠': 'i-ri-hammer-line',
  '⚡': 'i-ri-flashlight-line',
  '🏥': 'i-ri-hospital-line',
  '🏪': 'i-ri-store-2-line',
  '🏢': 'i-ri-building-line',
  '🚚': 'i-ri-truck-line',
  '🏖': 'i-ri-sun-line',

  // Documents
  '📋': 'i-ri-clipboard-line',
  '📄': 'i-ri-file-text-line',
  '📝': 'i-ri-draft-line',
  '🧾': 'i-ri-receipt-line',
  '📚': 'i-ri-book-2-line',
  '📕': 'i-ri-book-line',
  '🗂': 'i-ri-folders-line',
  '🗄': 'i-ri-database-2-line',
  '📦': 'i-ri-archive-line',
  '🖼': 'i-ri-image-line',
  '🪪': 'i-ri-id-card-line',
  '⚖': 'i-ri-scales-3-line',

  // Signature et saisie
  '✍': 'i-ri-quill-pen-line',
  '📸': 'i-ri-camera-line',
  '📷': 'i-ri-camera-line',

  // Communication
  '📧': 'i-ri-mail-line',
  '✉': 'i-ri-mail-line',
  '📨': 'i-ri-mail-send-line',
  '📭': 'i-ri-mail-open-line',
  '📤': 'i-ri-upload-line',
  '📥': 'i-ri-inbox-line',
  '📞': 'i-ri-phone-line',
  '📱': 'i-ri-smartphone-line',
  '💬': 'i-ri-chat-3-line',
  '📡': 'i-ri-broadcast-line',
  '🔔': 'i-ri-notification-3-line',
  '🔕': 'i-ri-notification-off-line',
  '🌐': 'i-ri-global-line',

  // Personnes et accès
  '👤': 'i-ri-user-line',
  '👥': 'i-ri-group-line',
  '👁': 'i-ri-eye-line',
  '🔒': 'i-ri-lock-line',
  '🔓': 'i-ri-lock-unlock-line',
  '🔑': 'i-ri-key-2-line',
  '🛡': 'i-ri-shield-line',

  // Temps
  '📅': 'i-ri-calendar-line',
  '🗓': 'i-ri-calendar-2-line',
  '📆': 'i-ri-calendar-check-line',

  // Argent
  '💳': 'i-ri-bank-card-line',
  '💶': 'i-ri-money-euro-circle-line',
  '💰': 'i-ri-money-euro-box-line',
  '🏷': 'i-ri-price-tag-3-line',

  // Pilotage
  '📊': 'i-ri-bar-chart-2-line',
  '📉': 'i-ri-line-chart-line',
  '🎯': 'i-ri-focus-3-line',
  '🔮': 'i-ri-magic-line',
  '💡': 'i-ri-lightbulb-line',
  '⚙': 'i-ri-settings-3-line',
  '📍': 'i-ri-map-pin-line',
  '⏱': 'i-ri-timer-line',
  '⏳': 'i-ri-hourglass-line',
  '⏰': 'i-ri-alarm-line',
  '🅿': 'i-ri-parking-box-line',
}

/**
 * Emoji purement décoratifs : ils disparaissent au lieu de devenir une icône.
 * Poser une icône ici n'ajouterait aucune information et alourdirait la phrase.
 */
export const DECORATIFS = new Set(['🎉'])

/** Retire les sélecteurs de variante et les jointures d'emoji. */
export function normaliser(emoji) {
  return emoji.replace(/[︎️‍]/g, '')
}

/**
 * Tout caractère employé comme icône, sélecteur de variante compris.
 *
 * Aux plages emoji s'ajoutent les glyphes techniques que l'application
 * détourne en icônes : commandes de lecture (« ▶ Activer », « ⏸ Pause »),
 * flèches de bouton (« ↻ Actualiser », « ← Retour »), chevrons de repli et
 * indicateurs de tendance.
 *
 * Deux exclusions volontaires :
 *   « → » (U+2192) sépare deux étapes DANS une phrase (« Réception → Atelier »).
 *     C'est de la typographie, pas une icône ; le remplacer couperait la phrase.
 *   « ② ③ » (U+2460-24FF) numérotent des gardes dans les commentaires de test.
 *
 * Piège : la plage emoji commence à U+1F000 et non à U+1F300, sinon « 🅿 »
 * (U+1F17F) passerait au travers.
 */
export const MOTIF_EMOJI =
  /[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{2300}-\u{23FF}\u{25A0}-\u{25FF}\u{2139}\u{2190}\u{2191}\u{2193}\u{2194}\u{21A9}\u{21AA}\u{21BA}\u{21BB}][︎️‍]*/gu

/** Icône associée à un emoji, ou `null` s'il doit simplement disparaître. */
export function iconePour(emoji) {
  const cle = normaliser(emoji)
  if (DECORATIFS.has(cle)) return null
  return ICONES[cle] ?? undefined
}
