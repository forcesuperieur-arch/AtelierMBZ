<!--
  Bouton de l'application. Une seule façon d'écrire un bouton.

  Il y en avait trois, toutes rendues à l'identique depuis l'alignement des
  tokens mais écrites différemment : les classes `.btn` de la feuille, la
  classe concurrente `.topbar-new-btn`, et le `UButton` de Nuxt UI. Les deux
  dernières ont disparu ; ce composant est la forme à employer désormais.

  Il ne réinvente rien : il POSE les classes `.btn` de `main.css`, qui restent
  la définition visuelle unique. Les appels existants en classes nues
  continuent donc de fonctionner à l'identique — c'est le même bouton.

  Rend un `<button>`, ou un `<NuxtLink>` dès qu'une destination `to` est
  donnée : un élément qui navigue doit être un lien, pas un bouton, sinon il
  n'est ni ouvrable dans un nouvel onglet ni annoncé comme un lien.
-->
<template>
  <component
    :is="to ? 'NuxtLink' : 'button'"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : (disabled || loading)"
    :aria-busy="loading ? 'true' : undefined"
    :aria-disabled="to && (disabled || loading) ? 'true' : undefined"
    :class="classes"
  >
    <AppIcon v-if="icon" :name="icon" />
    <slot>{{ label }}</slot>
  </component>
</template>

<script setup lang="ts">
type Variante = 'primary' | 'secondary' | 'ghost' | 'link'

const props = withDefaults(
  defineProps<{
    /** `secondary` par défaut : c'est ce que rend `.btn` seule (contour). */
    variant?: Variante
    /** `sm` réduit la hauteur pour les barres d'outils denses. */
    size?: 'md' | 'sm'
    /** Nom d'icône RemixIcon, posé avant le libellé. */
    icon?: string
    label?: string
    type?: 'button' | 'submit' | 'reset'
    /** Destination : le composant rend alors un lien. */
    to?: string
    disabled?: boolean
    /** Désactive et signale l'attente aux technologies d'assistance. */
    loading?: boolean
    block?: boolean
  }>(),
  { variant: 'secondary', size: 'md', type: 'button' },
)

const classes = computed(() => [
  'btn',
  `btn-${props.variant}`,
  props.size === 'sm' ? 'btn-sm' : null,
  props.block ? 'btn-block' : null,
])
</script>
