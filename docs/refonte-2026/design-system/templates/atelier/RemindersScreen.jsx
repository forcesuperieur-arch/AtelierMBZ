/* Rappels d'entretien — la file de pilotage.

   Le déclencheur est une date : douze mois après l'intervention. Posé à la
   clôture de l'OR (`ReceptionScreen`, champ « prochain entretien »), consultable
   sur la fiche moto, piloté ici.

   Cette file n'existe ni dans le code ni dans le prototype : c'est une surface
   décidée avec l'utilisateur, pas une recréation. Elle est ordonnée par échéance
   parce qu'un rappel a une seule question — est-ce qu'il est parti. */

const { Button: RaButton } = window.PaddockDesignSystem_8059f4;

const raKpi = { flex: 1, padding: '14px 22px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 };
const raOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const raTab = { whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer', border: '1px solid transparent' };
const raAct = { whiteSpace: 'nowrap', minHeight: 30, display: 'flex', alignItems: 'center', gap: 6, padding: '0 12px', fontSize: 12, fontWeight: 600, border: '1px solid var(--pk-border-control)', background: 'transparent', color: 'inherit', cursor: 'pointer' };
const raCols = { display: 'grid', gridTemplateColumns: '150px 1.4fr 1fr 200px 130px 190px', alignItems: 'center', gap: 14, padding: '11px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };

/* Les états d'un rappel. « Coupé » vient du client : il a désactivé les rappels
   dans son espace. L'atelier le voit, il ne peut pas le forcer. */
const RA_ROWS = [
  { ech: '24 août 2026', dans: 'dans 3 jours', client: 'Sabrina Amrani', bike: 'Kawasaki Z650 · BJ-778-LM', dernier: 'Révision 12 000 km · 24 août 2025', canal: 'SMS', etat: 'a_partir', proche: true },
  { ech: '2 sept. 2026', dans: 'dans 12 jours', client: 'Marc Delaunay', bike: 'Honda CB650R · AV-908-RT', dernier: 'Vidange + filtres · 2 sept. 2025', canal: 'E-mail', etat: 'a_partir' },
  { ech: '18 août 2026', dans: 'il y a 3 jours', client: 'Ludovic Renard', bike: 'Yamaha MT-09 · EX-421-QR', dernier: 'Révision 20 000 km · 18 août 2025', canal: 'E-mail', etat: 'parti', suite: 'Ouvert le 19 août, pas de RDV pris' },
  { ech: '11 août 2026', dans: 'il y a 10 jours', client: 'Céline Marchand', bike: 'Suzuki SV650 · CD-119-PT', dernier: 'Plaquettes + disques · 11 août 2025', canal: 'SMS', etat: 'converti', suite: 'RDV pris pour le 4 septembre' },
  { ech: '29 juil. 2026', dans: 'il y a 23 jours', client: 'Amine Ouali', bike: 'KTM Duke 390 · HK-330-BC', dernier: 'Pneus + chaîne · 29 juil. 2025', canal: '—', etat: 'coupe', suite: 'Le client a désactivé ses rappels le 3 août' },
  { ech: '15 juil. 2026', dans: 'il y a 37 jours', client: 'Hugo Lacroix', bike: 'Triumph Trident · FG-902-VN', dernier: 'Révision 10 000 km · 15 juil. 2025', canal: 'E-mail', etat: 'sans_suite', suite: 'Relancé une fois, sans réponse' },
];

const RA_ETAT = {
  a_partir: { label: 'À partir', bg: 'var(--pk-accent-soft)', fg: 'var(--pk-accent-ink)' },
  parti: { label: 'Parti', bg: 'var(--pk-surface-raised)', fg: 'var(--pk-ink-quiet)' },
  converti: { label: 'RDV pris', bg: 'var(--pk-ok-soft)', fg: 'var(--pk-ok-ink)' },
  coupe: { label: 'Coupé par le client', bg: 'var(--pk-surface-raised)', fg: 'var(--pk-ink-muted)' },
  sans_suite: { label: 'Sans suite', bg: 'var(--pk-warn-soft)', fg: 'var(--pk-warn-ink)' },
};

function RaBadge({ etat }) {
  const e = RA_ETAT[etat];
  return <span style={{ whiteSpace: 'nowrap', flexShrink: 0, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 'var(--pk-radius-pill)', background: e.bg, color: e.fg }}>{e.label}</span>;
}

function RemindersScreen() {
  const [tab, setTab] = React.useState('À venir · 2');
  const [partis, setPartis] = React.useState([]);

  const tabs = ['À venir · 2', 'Toutes · 6', 'Parties', 'RDV pris', 'Sans suite', 'Coupées'];
  const filtre = (r) => {
    if (tab === 'Toutes · 6') return true;
    if (tab === 'À venir · 2') return r.etat === 'a_partir';
    if (tab === 'Parties') return r.etat === 'parti';
    if (tab === 'RDV pris') return r.etat === 'converti';
    if (tab === 'Sans suite') return r.etat === 'sans_suite';
    return r.etat === 'coupe';
  };
  const rows = RA_ROWS.filter(filtre);

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0, background: 'var(--pk-canvas)' }}>
      <div style={{ flexShrink: 0, display: 'flex', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        <div style={raKpi}><span style={raOverline}>À partir ce mois</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>2</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>la plus proche dans 3 jours</span></div>
        <div style={raKpi}><span style={raOverline}>Parties, en attente</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>1</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>ouverte, pas de RDV</span></div>
        <div style={raKpi}><span style={raOverline}>Converties ce trimestre</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>14</span><span style={{ fontSize: 12, color: 'var(--pk-ok-ink)' }}>31 % des rappels envoyés</span></div>
        <div style={{ ...raKpi, borderRight: 'none' }}><span style={raOverline}>Coupées par le client</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>3</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>sur 214 clients suivis</span></div>
      </div>

      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', overflowX: 'auto' }}>
        {tabs.map((t) => (
          <span key={t} onClick={() => setTab(t)} style={{ ...raTab, background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'var(--pk-ink-quiet)', borderColor: tab === t ? '#000' : 'var(--pk-border-control)' }}>{t}</span>
        ))}
        <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}>Échéance = douze mois après l’intervention, posée à la clôture de l’OR</span>
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        <div style={{ ...raCols, position: 'sticky', top: 0, zIndex: 1, background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', fontSize: 11, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
          <span>Échéance</span><span>Client et moto</span><span>Dernier passage</span><span>Suite</span><span>Canal</span><span></span>
        </div>
        {rows.map((r) => (
          <div key={r.client} style={{ ...raCols, background: r.proche ? 'var(--pk-accent-soft)' : 'transparent', borderLeft: r.proche ? '3px solid var(--pk-accent)' : 'none' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{r.ech}</span>
              <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{r.dans}</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
              <span style={{ fontWeight: 600 }}>{r.client}</span>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.bike}</span>
            </div>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textWrap: 'pretty' }}>{r.dernier}</span>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'flex-start' }}>
              <RaBadge etat={partis.indexOf(r.client) !== -1 ? 'parti' : r.etat} />
              {r.suite ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', textWrap: 'pretty' }}>{r.suite}</span> : null}
            </div>
            <span style={{ fontSize: 12, color: r.canal === '—' ? 'var(--pk-ink-muted)' : 'var(--pk-ink-quiet)' }}>{r.canal}</span>
            <div style={{ display: 'flex', gap: 7, justifyContent: 'flex-end' }}>
              {r.etat === 'a_partir' ? (
                partis.indexOf(r.client) !== -1
                  ? <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-ok-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />Envoyé</span>
                  : <RaButton size="sm" onClick={() => setPartis((v) => v.concat(r.client))}><i className="ri-send-plane-line" style={{ fontSize: 14 }} />Envoyer</RaButton>
              ) : r.etat === 'parti' || r.etat === 'sans_suite' ? (
                <button style={raAct}><i className="ri-mail-send-line" style={{ fontSize: 14 }} />Relancer</button>
              ) : r.etat === 'coupe' ? (
                <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', textAlign: 'right' }}>Rien à faire</span>
              ) : (
                <button style={raAct}><i className="ri-calendar-line" style={{ fontSize: 14 }} />Voir le RDV</button>
              )}
            </div>
          </div>
        ))}
        {rows.length === 0 ? (
          <div style={{ padding: 56, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10, textAlign: 'center' }}>
            <i className="ri-notification-off-line" style={{ fontSize: 26, color: 'var(--pk-ink-muted)' }} />
            <span style={{ fontSize: 15, fontWeight: 700 }}>Rien dans cette file</span>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)', maxWidth: 380, textWrap: 'pretty' }}>Une échéance apparaît ici dès qu’un OR est clôturé avec une date de prochain entretien.</span>
          </div>
        ) : null}
      </div>
    </div>
  );
}

Object.assign(window, { RemindersScreen });
