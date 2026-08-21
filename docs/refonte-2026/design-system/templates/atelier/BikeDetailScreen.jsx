/* Fiche moto — tour 24. Carnet d'entretien par organe + campagne constructeur
   en bandeau rouge, passages avec kilométrage à droite. */
const { Button: BdButton } = window.PaddockDesignSystem_8059f4;

const bdOverline = { fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const bdStatCard = { padding: '11px 16px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 2, minWidth: 112 };
const bdChip = { whiteSpace: 'nowrap', flexShrink: 0, display: 'flex', alignItems: 'center', gap: 6, padding: '5px 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const bdCols = { display: 'grid', gridTemplateColumns: '1.3fr 130px 1fr 1fr 130px', alignItems: 'center', padding: '9px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };

const BD_PARTS = [
  { organ: 'Plaquettes avant', note: 'Épaisseur mesurée 0,8 mm', last: 'Jamais', part: 'Origine Yamaha', due: 'Maintenant', dueColor: 'var(--pk-error-ink)', state: 'À changer', stateColor: 'var(--pk-error-ink)', line: 'var(--pk-error-line)', surface: 'var(--pk-error-surface)' },
  { organ: 'Pneu arrière', note: 'Témoin à 2,1 mm', last: 'Mars 2026', part: 'Michelin Road 6', due: 'Dans ~3 000 km', dueColor: 'var(--pk-warning-ink-soft)', state: 'À surveiller', stateColor: 'var(--pk-warning-ink-soft)', line: 'var(--pk-warning-line)' },
  { organ: 'Huile moteur et filtre', note: 'Motul 7100 10W40', last: 'Aujourd’hui', part: 'HF204 · 3,5 L', due: '38 400 km ou août 2027', state: 'Neuf', stateColor: 'var(--pk-success-ink)', raised: true },
  { organ: 'Kit chaîne', note: 'Tension réglée aujourd’hui', last: 'Origine', part: 'DID 525 VX3', due: 'Dans ~8 000 km', state: 'Bon', stateColor: 'var(--pk-success-ink)' },
  { organ: 'Bougies', note: 'Contrôlées, écartement conforme', last: 'Origine', part: 'NGK LMAR8A-9', due: '40 000 km', state: 'Bon', stateColor: 'var(--pk-success-ink)', raised: true },
  { organ: 'Liquide de frein', note: 'Échéance calendaire', last: 'Sept. 2025', part: 'DOT 4', due: 'Sept. 2027', state: 'Bon', stateColor: 'var(--pk-success-ink)' },
  { organ: 'Pneu avant', note: 'Témoin à 3,4 mm · contrôlé en mars', last: 'Origine', part: 'Michelin Road 5', due: 'Dans ~5 000 km', state: 'Bon', stateColor: 'var(--pk-success-ink)', raised: true },
];

const BD_VISITS = [
  { date: 'Aujourd’hui', km: '28 412 km', title: 'Révision 20 000 km', sub: 'En cours · pont 2 · Karim M.', live: true },
  { date: '2 mars 2026', km: '24 180 km', title: 'Pneu arrière + équilibrage', sub: '214,00 € · Atelier Principal' },
  { date: '18 sept. 2025', km: '11 620 km', title: 'Révision 10 000 km', sub: '189,00 € · Roubaix' },
];

function BikeDetailScreen({ onRecall }) {
  const [recall, setRecall] = React.useState(true);
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, background: 'var(--pk-canvas)' }}>
      {recall ? (
        <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 14, padding: '13px 22px', background: 'var(--pk-error-line)', color: '#fff' }}>
          <i className="ri-alert-fill" style={{ fontSize: 22 }} />
          <div style={{ minWidth: 0 }}>
            <div style={{ fontSize: 15, fontWeight: 600 }}>Campagne de rappel constructeur ouverte</div>
            <div style={{ fontSize: 13, opacity: 0.9 }}>Yamaha R7-2024-03 · durite de frein arrière · intervention gratuite, prise en charge constructeur</div>
          </div>
          <div style={{ flex: 1 }} />
          <button type="button" onClick={onRecall} style={{ minHeight: 40, display: 'flex', alignItems: 'center', padding: '0 16px', background: '#fff', color: 'var(--pk-error-ink)', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Planifier le rappel</button>
          <button type="button" onClick={() => setRecall(false)} style={{ minHeight: 40, display: 'flex', alignItems: 'center', padding: '0 16px', background: 'transparent', color: '#fff', border: '1px solid rgba(255,255,255,0.5)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Marquer comme fait</button>
        </div>
      ) : null}

      <div style={{ flexShrink: 0, padding: '16px 22px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'flex-start', gap: 24 }}>
        <div style={{ width: 132, height: 92, background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, color: '#a5a5a5' }}><i className="ri-image-line" style={{ fontSize: 26 }} /></div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 7, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <span style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Yamaha Tracer 9</span>
            <span style={{ padding: '4px 11px', border: '2px solid #000', borderRadius: 'var(--pk-radius-block)', fontSize: 16, fontWeight: 700, letterSpacing: '0.06em' }}>GT-908-ZK</span>
            <span style={{ padding: '4px 10px', background: 'var(--pk-accent-soft)', border: '1px solid var(--pk-accent)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-accent-ink)' }}>EN ATELIER · PONT 2</span>
          </div>
          <div style={{ display: 'flex', gap: 18, fontSize: 13, color: 'var(--pk-ink-quiet)' }}>
            <span>2022 · 890 cm³</span><span>VIN JYARN57E000012345</span><span>1re immat. 14/03/2022</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-user-line" style={{ fontSize: 15 }} />Nadia Belkacem</span>
          </div>
          <div style={{ display: 'flex', gap: 8, marginTop: 2 }}>
            <button type="button" style={bdChip}><i className="ri-add-line" style={{ fontSize: 15 }} />Nouveau RDV</button>
            <button type="button" style={bdChip}><i className="ri-draft-line" style={{ fontSize: 15 }} />Devis d’entretien</button>
            <button type="button" style={bdChip}><i className="ri-printer-line" style={{ fontSize: 15 }} />Carnet d’entretien</button>
          </div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', gap: 12 }}>
          <div style={bdStatCard}><span style={bdOverline}>Compteur</span><span style={{ fontSize: 20, fontWeight: 700, lineHeight: 1 }}>28 412</span><span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>km · relevé ce matin</span></div>
          <div style={bdStatCard}><span style={bdOverline}>Usage annuel</span><span style={{ fontSize: 20, fontWeight: 700, lineHeight: 1 }}>9 400</span><span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>km par an</span></div>
          <div style={bdStatCard}><span style={bdOverline}>Entretien cumulé</span><span style={{ fontSize: 20, fontWeight: 700, lineHeight: 1 }}>671 €</span><span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>sur 4 passages</span></div>
        </div>
      </div>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ flex: 1, padding: '16px 22px', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
          <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 15, fontWeight: 600 }}>Carnet d’entretien par organe</span>
              <div style={{ flex: 1 }} />
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-error-ink)', fontWeight: 600 }}><i className="ri-error-warning-line" style={{ fontSize: 15 }} />2 organes à échéance</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1.3fr 130px 1fr 1fr 130px', padding: '8px 16px', borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-surface-raised)', ...bdOverline }}>
              <span>Organe</span><span>Dernier change.</span><span>Pièce posée</span><span>Prochaine échéance</span><span style={{ textAlign: 'right' }}>État</span>
            </div>
            {BD_PARTS.map((p) => (
              <div key={p.organ} style={{ ...bdCols, background: p.surface || (p.raised ? 'var(--pk-surface-raised)' : 'transparent'), borderLeft: p.line ? '3px solid ' + p.line : 'none' }}>
                <div><div style={{ fontWeight: 600 }}>{p.organ}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{p.note}</div></div>
                <span style={{ color: 'var(--pk-ink-quiet)' }}>{p.last}</span>
                <span style={{ color: 'var(--pk-ink-quiet)' }}>{p.part}</span>
                <span style={{ fontWeight: p.dueColor ? 600 : 400, color: p.dueColor }}>{p.due}</span>
                <span style={{ textAlign: 'right', fontWeight: p.state === 'À changer' || p.state === 'À surveiller' ? 700 : 600, color: p.stateColor }}>{p.state}</span>
              </div>
            ))}
            <div style={{ flex: 1 }} />
          </div>
        </div>

        <aside style={{ width: 356, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Passages</span>
            <span style={{ minWidth: 20, height: 20, padding: '0 6px', borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', fontSize: 11, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>4</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>avec kilométrage</span>
          </div>
          {BD_VISITS.map((v) => (
            <div key={v.date} style={{ padding: '11px 18px', borderBottom: '1px solid var(--pk-border-quiet)', borderLeft: v.live ? '3px solid var(--pk-accent)' : 'none', background: v.live ? 'var(--pk-accent-soft)' : 'transparent', display: 'flex', flexDirection: 'column', gap: 3 }}>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}><span style={{ fontSize: 12, fontWeight: 700 }}>{v.date}</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{v.km}</span></div>
              <div style={{ fontSize: 13, fontWeight: 600 }}>{v.title}</div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{v.sub}</div>
            </div>
          ))}
          <div style={{ padding: '9px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
            <i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>1 passage plus ancien</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Mai 2025 · 96 €</span>
          </div>
          <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ ...bdOverline, fontSize: 11 }}>Dégâts connus</span>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 9, fontSize: 13, lineHeight: 1.45 }}>
              <span style={{ width: 17, height: 17, border: '1.5px solid var(--pk-ink)', borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 10, fontWeight: 700, flexShrink: 0, marginTop: 1 }}>1</span>
              <div><div style={{ fontWeight: 600 }}>Rayure réservoir droite</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Relevée à l’entrée du 15 août · 6 cm</div></div>
            </div>
          </div>
          <div style={{ padding: '12px 18px', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ ...bdOverline, fontSize: 11 }}>Prochain entretien conseillé</span>
            <div style={{ padding: '11px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 4 }}>
              <div style={{ fontSize: 13, fontWeight: 600 }}>Révision 30 000 km</div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Vers avril 2027 au rythme actuel · estimation 245 à 275 €</div>
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)' }}>
            <BdButton variant="secondary" size="large" startIcon="ri-printer-line" style={{ width: '100%' }}>Imprimer le carnet pour le client</BdButton>
          </div>
        </aside>
      </div>
    </div>
  );
}
Object.assign(window, { BikeDetailScreen });
