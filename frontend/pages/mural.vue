<template>
  <div class="mural">
    <header class="mural-tete">
      <img :src="logo" alt="" class="mural-logo" />
      <div class="mural-atelier">{{ atelierNom }} · {{ dateDuJour }}</div>
      <div class="mural-espace" />
      <div class="mural-horloge">
        <span class="mural-heure">{{ heure }}</span>
        <!-- « L'heure du rafraîchissement est affichée, parce qu'un écran figé
             ressemble à un écran à jour. » Le point passe au rouge dès que la
             dernière réponse date de plus de trois minutes. -->
        <span class="mural-fraicheur" :class="{ 'est-perime': perime }">
          <span class="mural-point" />{{ perime ? `figé depuis ${minutesDepuisMaj} min` : 'à jour' }}
        </span>
      </div>
    </header>

    <div class="mural-colonnes">
      <section class="mural-colonne">
        <div class="mural-titre">
          <span class="mural-libelle">Sortent aujourd'hui</span>
          <span class="mural-compte">{{ sortent.length }}</span>
        </div>
        <div class="mural-liste">
          <div
            v-for="m in sortent"
            :key="`s-${m.rdv_id}`"
            class="mural-tuile"
            :class="[`bord-${m.ton}`, { 'est-parti': m.ton === 'parti' }]"
          >
            <div class="mural-tuile-corps">
              <div class="mural-nom">{{ m.vehicule }}<span v-if="m.plaque"> · {{ m.plaque }}</span></div>
              <div class="mural-sous">{{ m.sous }}</div>
            </div>
            <span class="mural-droite" :class="`ton-${m.ton}`">{{ m.droite }}</span>
          </div>
          <p v-if="!sortent.length" class="mural-vide">Aucune moto à sortir aujourd'hui.</p>
        </div>
      </section>

      <section class="mural-colonne">
        <div class="mural-titre">
          <span class="mural-libelle">Sur les ponts</span>
          <span class="mural-compte">{{ pontsOccupes }} / {{ ponts.length }}</span>
        </div>
        <div class="mural-liste">
          <div v-for="p in ponts" :key="`p-${p.id}`" :class="p.occupe ? 'mural-tuile mural-pont' : 'mural-pont-libre'">
            <template v-if="p.occupe">
              <div class="mural-pont-tete">
                <span class="mural-pont-nom">{{ p.nom }}</span>
                <span class="mural-nom">{{ p.vehicule }}</span>
              </div>
              <div class="mural-pont-pied">
                <!-- Le prénom reste : il dit qui appeler au comptoir. Le rapport
                     vendu/pointé, lui, ne s'affiche PAS ici — la règle 6 refuse
                     qu'un écran lu de tout l'atelier attribue un temps à
                     quelqu'un. La barre suffit à dire que ça avance. -->
                <span class="mural-qui">{{ p.qui }}</span>
              </div>
              <div class="mural-barre"><div class="mural-barre-part" :class="`ton-${p.ton}`" :style="{ width: `${p.pct}%` }" /></div>
            </template>
            <template v-else>
              <span class="mural-pont-nom est-eteint">{{ p.nom }}</span>
              <span class="mural-libre">{{ p.libre }}</span>
            </template>
          </div>
        </div>
      </section>

      <section class="mural-colonne mural-colonne--derniere">
        <div class="mural-titre">
          <span class="mural-libelle">Attente réponse</span>
          <span class="mural-compte" :class="{ 'ton-retard': attentes.length > 0 }">{{ attentes.length }}</span>
        </div>
        <div class="mural-liste">
          <div v-for="a in attentes" :key="`a-${a.id}`" class="mural-tuile" :class="`bord-${a.ton}`">
            <div class="mural-tuile-corps">
              <div class="mural-nom">{{ a.titre }}</div>
              <div class="mural-sous">{{ a.detail }}</div>
              <div class="mural-attente" :class="`ton-${a.ton}`">{{ a.depuis }}</div>
            </div>
          </div>
          <p v-if="!attentes.length" class="mural-vide">Aucune demande sans réponse.</p>
        </div>
        <div class="mural-demain">
          <span class="mural-libelle-petit">Demain</span>
          <div class="mural-ligne"><span>Rendez-vous</span><strong>{{ demain.rdv }}</strong></div>
          <div class="mural-ligne"><span>Charge des ponts</span><strong class="ton-accent">{{ demain.charge }} %</strong></div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Affichage mural — maquette 47a, 1920 × 1080.
 *
 * L'écran au-dessus du comptoir. Il n'a AUCUNE interaction : ni bouton, ni
 * lien, ni défilement. C'est ce qui remplace le « Suivi Live » supprimé au
 * tour 8a — le besoin réel derrière cette page n'était pas une page de plus
 * dans la navigation, mais un écran qu'on lit de loin.
 *
 * Rien sous 20 px, jamais : c'est le seuil que 4c a posé et que 47a tient.
 *
 * ÉCART ASSUMÉ AVEC LA MAQUETTE. 47a affiche « Karim · 1 h 40 / 2 h » sur
 * chaque pont. La règle 6 interdit qu'un écran de pilotage attribue un temps
 * nominativement, et un mur se lit de tout l'atelier. Option A du tour 51c,
 * retenue : le prénom reste — il dit qui appeler — le rapport vendu/pointé
 * part, et la barre de progression suffit à dire que ça avance.
 */
definePageMeta({ layout: false })

const api = useApi()
const atelierStore = useAtelierStore()
// Le favicon est la variante que 47a pose à 40 px en tête du mural. Elle est
// claire sur fond sombre, ce qui est exactement le cas ici.
const logo = '/branding/paddock-logo-favicon.svg'
const atelierNom = computed(() => atelierStore.branding?.nom || 'Atelier')

const heure = ref('')
const dernierMaj = ref<number>(0)
const maintenant = ref<number>(0)

const motos = ref<any[]>([])
const pontsBruts = ref<any[]>([])
const demandes = ref<any[]>([])
const demain = ref({ rdv: 0, charge: 0 })

const dateDuJour = computed(() =>
  new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }),
)

/** Trois minutes sans réponse : l'écran cesse de se prétendre à jour. */
const minutesDepuisMaj = computed(() =>
  dernierMaj.value ? Math.floor((maintenant.value - dernierMaj.value) / 60000) : 0,
)
const perime = computed(() => minutesDepuisMaj.value >= 3)

const sortent = computed(() =>
  motos.value.slice(0, 6).map((m: any) => {
    const statut = String(m.statut || '')
    if (statut === 'restitue') return { ...m, ton: 'parti', droite: 'Partie', sous: `Restituée · ${m.client_nom || ''}`.trim() }
    if (statut === 'termine' || statut === 'pret') return { ...m, ton: 'pret', droite: 'Prête', sous: `${m.client_nom || ''} · ${m.type_intervention || ''}`.trim() }
    if (m.en_depassement) return { ...m, ton: 'retard', droite: 'En retard', sous: `${m.client_nom || ''} · ${m.type_intervention || ''}`.trim() }
    return { ...m, ton: 'encours', droite: m.heure_rdv ? String(m.heure_rdv).slice(0, 5) : 'En cours', sous: `${m.client_nom || ''} · ${m.type_intervention || ''}`.trim() }
  }),
)

const ponts = computed(() =>
  pontsBruts.value.map((p: any) => {
    const rdv = p.current_rdv
    const meca = p.assigned_meca || p.mecanicien
    const prenom = meca ? String(meca.prenom || meca.nom || '').trim() : ''
    if (!rdv) {
      return { id: p.id, nom: p.nom, occupe: false, libre: p.est_actif === false ? 'hors service' : 'libre' }
    }
    const vendu = Number(rdv.temps_estime || 0)
    const ecoule = Number(rdv.temps_ecoule_minutes ?? 0)
    const pct = vendu > 0 ? Math.min(Math.round((ecoule / vendu) * 100), 100) : 0
    const depasse = vendu > 0 && ecoule > vendu
    return {
      id: p.id,
      nom: p.nom,
      occupe: true,
      vehicule: rdv.vehicule_info || rdv.vehicule_plaque || 'Moto',
      qui: prenom || 'Non assigné',
      pct,
      ton: depasse ? 'retard' : 'encours',
    }
  }),
)

const pontsOccupes = computed(() => ponts.value.filter((p: any) => p.occupe).length)

const attentes = computed(() =>
  demandes.value
    .filter((d: any) => String(d.statut || '') === 'en_attente')
    .slice(0, 4)
    .map((d: any) => {
      const urgent = String(d.urgence || '') === 'haute'
      return {
        id: d.id,
        titre: `${d.vehicule_info || 'Moto'} · ${d.client_nom || ''}`.trim(),
        detail: [d.description, d.prix_estime ? `${d.prix_estime} €` : ''].filter(Boolean).join(' · '),
        depuis: urgent ? 'réponse urgente attendue' : 'en attente de réponse',
        ton: urgent ? 'retard' : 'encours',
      }
    }),
)

async function rafraichir() {
  const [m, p, d] = await Promise.allSettled([
    api.get('/sejour-atelier/motos'),
    api.get('/ponts/status'),
    api.get('/demandes-travaux-supp'),
  ])
  const liste = (v: any) => (Array.isArray(v) ? v : v?.member ?? v?.['hydra:member'] ?? v?.motos ?? v?.items ?? [])
  if (m.status === 'fulfilled') motos.value = liste(m.value)
  if (p.status === 'fulfilled') pontsBruts.value = liste(p.value)
  if (d.status === 'fulfilled') demandes.value = liste(d.value)

  // Seul un tour COMPLÈTEMENT muet fige l'écran : une seule source qui répond
  // suffit à dire que le poste voit encore le serveur.
  if ([m, p, d].some(r => r.status === 'fulfilled')) dernierMaj.value = Date.now()
}

let horloge: ReturnType<typeof setInterval> | null = null
let sonde: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  const battre = () => {
    maintenant.value = Date.now()
    heure.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  }
  battre()
  rafraichir()
  horloge = setInterval(battre, 10_000)
  sonde = setInterval(rafraichir, 60_000)
})

onBeforeUnmount(() => {
  if (horloge) clearInterval(horloge)
  if (sonde) clearInterval(sonde)
})
</script>

<style scoped>
/* Le mural est sombre par nature : il vit dans l'atelier, sous néon ou en
   plein jour. Il ne suit donc pas la bascule de thème et pose lui-même les
   valeurs du thème atelier du design system. */
.mural {
  width: 100vw;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #141414;
  color: #f6f6f6;
  font-family: Montserrat, sans-serif;
}

.mural-tete {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px 28px;
  border-bottom: 1px solid #2f2f2f;
}
.mural-logo { width: 40px; height: 40px; display: block; flex: none; }
.mural-atelier { font-size: 30px; font-weight: 600; letter-spacing: -0.01em; }
.mural-espace { flex: 1; }
.mural-horloge { display: flex; align-items: baseline; gap: 14px; }
.mural-heure { font-size: 40px; font-weight: 700; line-height: 1; letter-spacing: -0.02em; }
.mural-fraicheur { display: flex; align-items: center; gap: 8px; font-size: 20px; color: #a5a5a5; }
.mural-point { width: 10px; height: 10px; border-radius: 999px; background: #7ee08a; display: block; }
.mural-fraicheur.est-perime { color: #ff8095; }
.mural-fraicheur.est-perime .mural-point { background: #ff8095; }

.mural-colonnes { flex: 1; display: grid; grid-template-columns: 1.15fr 1fr 0.95fr; min-height: 0; }
.mural-colonne { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid #2f2f2f; }
.mural-colonne--derniere { border-right: none; }

.mural-titre { display: flex; align-items: baseline; gap: 14px; padding: 18px 26px 12px; }
.mural-libelle { font-size: 24px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #f1ab00; }
.mural-compte { font-size: 24px; font-weight: 700; color: #a5a5a5; }
.mural-libelle-petit { font-size: 20px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #a5a5a5; }

.mural-liste { flex: 1; display: flex; flex-direction: column; gap: 10px; padding: 0 26px 20px; min-height: 0; overflow: hidden; }

.mural-tuile { background: #1f1f1f; padding: 14px 18px; display: flex; align-items: center; gap: 16px; }
.mural-tuile-corps { flex: 1; min-width: 0; }
.mural-nom { font-size: 28px; font-weight: 600; }
.mural-sous { font-size: 20px; color: #a5a5a5; margin-top: 2px; }
.mural-droite { font-size: 24px; font-weight: 700; color: #a5a5a5; }
.mural-attente { font-size: 22px; font-weight: 700; margin-top: 6px; }
.mural-vide { font-size: 22px; color: #6f6e6e; padding: 8px 0; margin: 0; }

.bord-pret    { border-left: 5px solid #7ee08a; }
.bord-encours { border-left: 5px solid #f1ab00; }
.bord-retard  { border-left: 5px solid #ff8095; }
.bord-parti   { border-left: none; }
.est-parti    { opacity: 0.75; }

.ton-pret { color: #7ee08a; }
.ton-encours { color: #f1ab00; }
.ton-retard { color: #ff8095; }
.ton-accent { color: #f1ab00; }
.ton-parti { color: #a5a5a5; }

.mural-pont { flex-direction: column; align-items: stretch; gap: 0; }
.mural-pont-tete { display: flex; align-items: baseline; gap: 12px; }
.mural-pont-nom { font-size: 22px; font-weight: 700; color: #f1ab00; }
.mural-pont-nom.est-eteint { color: #6f6e6e; }
.mural-pont-pied { display: flex; align-items: baseline; justify-content: space-between; margin-top: 6px; }
.mural-qui { font-size: 20px; color: #a5a5a5; }
.mural-barre { height: 8px; background: #333; margin-top: 8px; }
.mural-barre-part { height: 100%; }
.mural-barre-part.ton-encours { background: #7ee08a; }
.mural-barre-part.ton-retard { background: #ff8095; }

.mural-pont-libre {
  border: 2px dashed #333;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.mural-libre { font-size: 20px; color: #6f6e6e; }

.mural-demain { padding: 18px 26px; border-top: 1px solid #2f2f2f; display: flex; flex-direction: column; gap: 8px; }
.mural-ligne { display: flex; align-items: baseline; justify-content: space-between; font-size: 24px; }
.mural-ligne strong { font-size: 26px; font-weight: 700; }
</style>
