/**
 * Mémorise qu'un profil SRC est entré dans un atelier depuis le cockpit réseau.
 *
 * Sans cette trace, rien à l'écran ne distingue un SRC en visite d'un compte du
 * site : la maquette 52a veut au contraire que le bandeau jaune reste affiché
 * tant qu'il est « chez » l'atelier, et qu'il nomme lequel.
 *
 * La valeur est le nom de l'atelier ouvert ; vide ou absente, il n'y a pas de
 * visite en cours et aucun bandeau n'est posé.
 */
export function useCockpitOrigin() {
  return useCookie<string | null>('src_cockpit_origin', {
    default: () => null,
    sameSite: 'lax',
    path: '/',
  })
}
