import { describe, it, expect } from 'vitest'
import { messageErreur } from '../utils/messageErreur'

/**
 * La règle 5 du design system : une erreur nomme ce qui s'est passé, ce que ça
 * empêche, et laisse une issue. Ces tests figent surtout ce qui est INTERDIT —
 * le repli générique — parce que c'est lui qui revient à chaque ajout d'écran.
 */
describe('messageErreur', () => {
  it('cite le serveur quand il a parlé', () => {
    const m = messageErreur({ data: { error: 'Le pont 2 est déjà occupé à 14:00.' } }, 'le rendez-vous n\'a pas été déplacé')
    expect(m).toBe('Le pont 2 est déjà occupé à 14:00.')
  })

  it('ignore les messages techniques du navigateur, qui ne sont pas des phrases', () => {
    const m = messageErreur({ message: 'Failed to fetch' }, 'le rendez-vous n\'a pas été déplacé')
    expect(m).not.toContain('Failed to fetch')
    expect(m).toContain('rendez-vous')
  })

  it('dit ce qui n\'a pas bougé quand le serveur se tait', () => {
    const m = messageErreur({ status: 0 }, 'le rendez-vous n\'a pas été déplacé')
    expect(m).toContain("n'a pas répondu")
    expect(m).toContain("Rien n'a été modifié")
  })

  it('nomme le conflit de version plutôt que de le masquer', () => {
    const m = messageErreur({ statusCode: 409 }, 'le devis n\'a pas été enregistré')
    expect(m).toContain('modifié ce dossier')
    expect(m).toContain('Rechargez')
  })

  it('distingue un refus de droits d\'une panne', () => {
    const m = messageErreur({ statusCode: 403 }, 'la remise n\'a pas été appliquée')
    expect(m).toContain('profil')
  })

  it('ne produit JAMAIS le repli générique proscrit', () => {
    const cas = [{}, { status: 0 }, { statusCode: 500 }, { statusCode: 404 }, { message: '' }, { data: {} }]
    for (const c of cas) {
      const m = messageErreur(c, "l'action n'a pas abouti")
      expect(m.toLowerCase()).not.toContain('erreur inconnue')
      expect(m.toLowerCase()).not.toContain('une erreur est survenue')
      expect(m.length).toBeGreaterThan(20)
    }
  })
})
