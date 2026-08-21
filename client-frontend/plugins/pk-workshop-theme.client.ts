/**
 * Fait suivre la couche `--pk-*` du design system au thème de l'application.
 *
 * Le design system porte son thème atelier sur la classe `.pk-workshop`, parce
 * que pour lui le sombre désigne un LIEU — le poste de pointage, la tablette de
 * réception, l'écran mural — et non une préférence. L'application, elle,
 * bascule sur `[data-theme='dark']`, et la bascule manuelle reste offerte
 * partout (décision du 21 août, amendant le tour 45b).
 *
 * Sans ce pont, les tokens `--pk-*` resteraient sur leurs valeurs claires dans
 * une interface passée en sombre : les surfaces de statut d'un bloc de planning
 * s'afficheraient en pastel sur un fond noir. On pose donc la classe du design
 * system en même temps que l'attribut de l'application, plutôt que de recopier
 * ses vingt déclarations dans une feuille d'ici — une copie finirait par
 * diverger de sa source à la première resynchronisation.
 */
export default defineNuxtPlugin(() => {
  const racine = document.documentElement

  function accorder() {
    racine.classList.toggle('pk-workshop', racine.dataset.theme === 'dark')
  }

  accorder()

  // `@nuxtjs/color-mode` réécrit l'attribut après l'hydratation et à chaque
  // bascule : on observe l'attribut plutôt que d'écouter un événement que le
  // module ne garantit pas.
  const observateur = new MutationObserver(accorder)
  observateur.observe(racine, { attributes: true, attributeFilter: ['data-theme'] })
})
