/* Liste des devis — tour 35a, avec la barre de relance en masse et le
   panneau de refus (35b) en surcouche. */
const { Button: QlButton } = window.PaddockDesignSystem_8059f4;

const QL_GRID = '34px 108px 1fr 190px 96px 108px 96px';
const qlHead = { display: 'grid', gridTemplateColumns: QL_GRID, alignItems: 'center', gap: 12, padding: '8px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const qlRow = { display: 'grid', gridTemplateColumns: QL_GRID, alignItems: 'center', gap: 12, padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13, cursor: 'pointer' };
const qlKpi = { flex: 1, padding: '14px 22px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 };
const qlOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const qlReason = { minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 14px', border: '1px solid var(--pk-border-control)', fontSize: 14, background: 'transparent', color: 'inherit', cursor: 'pointer', textAlign: 'left' };

const QL_ROWS = [
  { id: 'DV-2418', client: 'Ludovic Renard', bike: 'Yamaha MT-09 · EX-421-QR', object: 'Kit chaîne + pneus', amount: '412,50 €', wait: '6 jours', waitLevel: 'late', chases: '1' },
  { id: 'DV-2422', client: 'Sabrina Amrani', bike: 'Kawasaki Z650 · BJ-778-LM', object: 'Amortisseur arrière', amount: '586,00 €', wait: '5 jours', waitLevel: 'late', chases: '0' },
  { id: 'DV-2440', client: 'Céline Marchand', bike: 'Suzuki SV650 · CD-119-PT', object: 'Révision 30 000', amount: '184,90 €', wait: '4 jours', waitLevel: 'warn', chases: '1' },
  { id: 'DV-2443', client: 'Hugo Lacroix', bike: 'Triumph Trident · FG-902-VN', object: 'Plaquettes AV/AR', amount: '148,00 €', wait: '3 jours', waitLevel: 'warn', chases: '0' },
  { id: 'DV-2447', client: 'Ludovic Renard', bike: 'Yamaha MT-09 · EX-421-QR', object: 'Révision + kit chaîne', amount: '505,56 €', wait: '1 jour', chases: '0', open: true },
  { id: 'DV-2449', client: 'Marc Delaunay', bike: 'Honda CB650R · AV-908-RT', object: 'Diagnostic électrique', amount: '108,00 €', wait: 'à l’instant', chases: '0' },
];

function QlToggle({ on }) {
  return (
    <span style={{ display: 'inline-flex', width: 46, height: 26, padding: 3, background: on ? 'var(--pk-accent)' : 'var(--pk-border)', flexShrink: 0, borderRadius: 'var(--pk-radius-pill)' }}>
      <span style={{ width: 20, height: 20, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: on ? 'auto' : 0 }} />
    </span>
  );
}

function QuotesListScreen({ onOpenQuote }) {
  const [tab, setTab] = React.useState('En attente · 11');
  const [picked, setPicked] = React.useState(['DV-2418', 'DV-2422']);
  const [refused, setRefused] = React.useState(null);
  const [reason, setReason] = React.useState('Montant trop élevé');
  const toggle = (id) => setPicked((p) => (p.indexOf(id) === -1 ? p.concat(id) : p.filter((x) => x !== id)));
  const sum = QL_ROWS.filter((r) => picked.indexOf(r.id) !== -1).reduce((t, r) => t + parseFloat(r.amount.replace(/\s/g, '').replace(',', '.')), 0);

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0, background: 'var(--pk-canvas)', position: 'relative' }}>
      <div style={{ flexShrink: 0, display: 'flex', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        <div style={qlKpi}><span style={qlOverline}>En attente de réponse</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>11</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>3 214 € engagés</span></div>
        <div style={{ ...qlKpi, background: 'var(--pk-error-surface)' }}><span style={{ ...qlOverline, color: 'var(--pk-error-ink)' }}>À relancer</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1, color: 'var(--pk-error-ink)' }}>4</span><span style={{ fontSize: 12, color: 'var(--pk-error-ink)' }}>au-delà du seuil de 2 jours</span></div>
        <div style={qlKpi}><span style={qlOverline}>Acceptés ce mois</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>68 %</span><span style={{ fontSize: 12, color: 'var(--pk-warning-ink-soft)' }}>− 5 pts contre août 2025</span></div>
        <div style={{ ...qlKpi, borderRight: 'none' }}><span style={qlOverline}>Délai moyen de réponse</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>3,4 j</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>2,1 j l’an dernier</span></div>
      </div>

      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 8, padding: '11px 22px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        {['En attente · 11', 'Acceptés · 24', 'Refusés · 7', 'Expirés · 3'].map((t) => (
          <button type="button" key={t} onClick={() => setTab(t)}
            style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'inherit', border: tab === t ? 'none' : '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: tab === t ? 600 : 400, cursor: 'pointer' }}>{t}</button>
        ))}
        <div style={{ flex: 1 }} />
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-quiet)' }}><i className="ri-sort-desc" style={{ fontSize: 15 }} />Du plus ancien au plus récent</span>
      </div>

      <div style={{ flex: 1, padding: '16px 22px', display: 'flex', flexDirection: 'column', minHeight: 0 }}>
        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={qlHead}><span /><span>Numéro</span><span>Client et moto</span><span>Objet</span><span style={{ textAlign: 'right' }}>Montant</span><span style={{ textAlign: 'right' }}>Attente</span><span style={{ textAlign: 'right' }}>Relances</span></div>
          {QL_ROWS.map((r, i) => {
            const on = picked.indexOf(r.id) !== -1;
            return (
              <div key={r.id} onClick={() => (r.open && onOpenQuote ? onOpenQuote() : toggle(r.id))}
                style={{ ...qlRow, background: on ? 'var(--pk-accent-soft)' : i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: r.waitLevel === 'late' ? '3px solid var(--pk-error-line)' : r.waitLevel === 'warn' ? '3px solid var(--pk-warning-line)' : 'none' }}>
                <span onClick={(e) => { e.stopPropagation(); toggle(r.id); }}
                  style={{ width: 20, height: 20, background: on ? '#000' : 'transparent', border: on ? 'none' : '1.5px solid var(--pk-border-control)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  {on ? <i className="ri-check-line" style={{ fontSize: 15, color: '#fff' }} /> : null}
                </span>
                <span style={{ fontWeight: 600 }}>{r.id}</span>
                <div><div style={{ fontWeight: 600 }}>{r.client}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{r.bike}</div></div>
                <span style={{ color: 'var(--pk-ink-quiet)' }}>{r.object}</span>
                <span style={{ textAlign: 'right', fontWeight: 600 }}>{r.amount}</span>
                <span style={{ textAlign: 'right', fontWeight: r.waitLevel ? 700 : 400, color: r.waitLevel === 'late' ? 'var(--pk-error-ink)' : r.waitLevel === 'warn' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-quiet)' }}>{r.wait}</span>
                <span style={{ textAlign: 'right', color: 'var(--pk-ink-quiet)' }}>{r.chases}</span>
              </div>
            );
          })}
          <div style={{ flex: 1 }} />
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 16px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>DV-2447 ouvre le devis en cours de rédaction.</span>
            <div style={{ flex: 1 }} />
            <button type="button" onClick={() => setRefused('DV-2422')} style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)', background: 'transparent', border: 'none', cursor: 'pointer' }}>Enregistrer un refus sur DV-2422</button>
          </div>
        </div>
      </div>

      {picked.length ? (
        <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 16, padding: '14px 22px', background: '#000', color: 'var(--pk-ink-on-dark)' }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>{picked.length} devis sélectionné{picked.length > 1 ? 's' : ''}</span>
          <span style={{ fontSize: 14, color: '#d4d4d4' }}>{sum.toFixed(2).replace('.', ',')} € · relance par SMS et e-mail, texte du modèle « Relance de devis »</span>
          <div style={{ flex: 1 }} />
          <button type="button" style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 16px', border: '1px solid rgba(255,255,255,0.45)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Aperçu du message</button>
          <button type="button" onClick={() => setPicked([])} style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 18px', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 14, fontWeight: 600, cursor: 'pointer' }}>Relancer les {picked.length}</button>
        </div>
      ) : null}

      {refused ? (
        <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'flex-start', justifyContent: 'flex-end', padding: 20 }}>
          <div style={{ width: 560, maxHeight: '100%', background: 'var(--pk-surface)', border: '1px solid var(--pk-border-control)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 15, fontWeight: 600 }}>DV-2422 refusé</span>
              <div style={{ flex: 1 }} />
              <button type="button" onClick={() => setRefused(null)} style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--pk-ink-muted)' }}><i className="ri-close-line" style={{ fontSize: 22 }} /></button>
            </div>
            <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 4 }}>
              <div style={{ fontSize: 18, fontWeight: 600 }}>Sabrina Amrani · Kawasaki Z650</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Amortisseur arrière · 586,00 € · refusé au téléphone il y a 2 min</div>
            </div>
            <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 10 }}>
              <span style={{ ...qlOverline }}>Pourquoi · à choisir, c’est ce qui sert plus tard</span>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
                {['Montant trop élevé', 'Reporté à plus tard, pas urgent', 'Fera faire ailleurs', 'Va vendre la moto', 'Fera lui-même'].map((r) => (
                  <button type="button" key={r} onClick={() => setReason(r)}
                    style={{ ...qlReason, background: reason === r ? '#000' : 'transparent', color: reason === r ? '#fff' : 'inherit', border: reason === r ? 'none' : '1px solid var(--pk-border-control)', fontWeight: reason === r ? 600 : 400 }}>{r}</button>
                ))}
              </div>
            </div>
            <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 10, background: 'var(--pk-accent-soft)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                <i className="ri-lightbulb-line" style={{ fontSize: 18, color: 'var(--pk-accent-ink)' }} />
                <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-accent-ink)' }}>Une offre partielle reste possible</span>
              </div>
              <div style={{ fontSize: 14, lineHeight: 1.5 }}>L’amortisseur seul, sans la révision qui l’accompagnait, tombe à 398 €. C’est le geste qui sauve le plus souvent un refus sur le prix.</div>
              <button type="button" style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', border: 'none', fontSize: 14, fontWeight: 600, cursor: 'pointer' }}>Proposer 398 € maintenant</button>
            </div>
            <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 12 }}>
              <span style={qlOverline}>Ce qui se passe ensuite</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Noter le défaut au carnet</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Amortisseur HS, signalé et refusé le 15/08</div></div>
                <QlToggle on />
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Relancer dans 6 mois</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Février 2027 · une seule fois, puis on n’en parle plus</div></div>
                <QlToggle on />
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Envoyer un accusé au client</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Sans insistance : trace écrite du refus, rien d’autre</div></div>
                <QlToggle />
              </div>
            </div>
            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 11, background: 'var(--pk-error-surface)' }}>
              <i className="ri-error-warning-fill" style={{ fontSize: 18, color: 'var(--pk-error-ink)', flexShrink: 0 }} />
              <div style={{ fontSize: 13, lineHeight: 1.5 }}>La moto repart avec un amortisseur hors service. Le défaut est inscrit sur le document de restitution et le client en signe la mention — c’est ce qui protège l’atelier.</div>
            </div>
            <div style={{ flex: 1 }} />
            <div style={{ padding: '16px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
              <QlButton variant="primary" tone="accent" size="medium" fullWidth onClick={() => setRefused(null)}>Enregistrer le refus</QlButton>
              <button type="button" onClick={() => setRefused(null)} style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Revenir au devis</button>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>Le devis reste consultable et duplicable pendant 3 ans.</span>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
Object.assign(window, { QuotesListScreen });
