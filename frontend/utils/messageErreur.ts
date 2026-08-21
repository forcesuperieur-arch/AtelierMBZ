/**
 * Traduit une erreur d'appel serveur en message conforme à la règle 5 du
 * design system : « dire ce qui s'est passé, ce que ça empêche, et la seule
 * action qui sert — puis laisser une issue légitime plutôt que bloquer ».
 *
 * Le repli générique « Erreur inconnue » viole cette règle deux fois : il ne
 * nomme pas la cause, et il laisse croire que quelque chose a peut-être été
 * enregistré. Or c'est la première question de celui qui voit le message.
 *
 * Quand le serveur a parlé, on le cite : il en sait plus que nous. Quand il
 * s'est tu, on décrit le silence et on dit ce qui n'a pas bougé.
 */

/** Message renvoyé par l'API, sous les deux formes qu'elle emploie. */
function messageDuServeur(erreur: any): string {
  const brut = erreur?.data?.error || erreur?.data?.message || erreur?.message
  if (typeof brut !== 'string') return ''
  const propre = brut.trim()
  // « Failed to fetch » et consorts ne sont pas des phrases pour un comptoir.
  if (!propre || /^(failed to fetch|networkerror|load failed|typeerror)/i.test(propre)) return ''
  return propre
}

/**
 * @param erreur  L'objet levé par l'appel.
 * @param effet   Ce que l'échec empêche, à la première personne du pluriel du
 *                métier. Ex. « le rendez-vous n'a pas été déplacé ».
 */
export function messageErreur(erreur: any, effet: string): string {
  const duServeur = messageDuServeur(erreur)
  if (duServeur) return duServeur

  const statut = Number(erreur?.statusCode ?? erreur?.status ?? 0)

  if (statut === 403) return `Votre profil ne permet pas cette action — ${effet}.`
  if (statut === 404) return `L'élément visé n'existe plus — ${effet}.`
  if (statut === 409) return `Quelqu'un d'autre a modifié ce dossier entre-temps — ${effet}. Rechargez pour voir son état actuel.`
  if (statut >= 500) return `Le serveur n'a pas pu traiter la demande — ${effet}. Rien n'a été modifié.`
  if (statut === 0) return `Le serveur n'a pas répondu — ${effet}. Rien n'a été modifié : réessayez, ou vérifiez la connexion du poste.`

  return `${effet.charAt(0).toUpperCase()}${effet.slice(1)}. Rien n'a été modifié.`
}
