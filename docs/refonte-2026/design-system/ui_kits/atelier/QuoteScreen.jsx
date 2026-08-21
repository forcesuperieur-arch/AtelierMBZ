/* Composer de devis — tour 25. Packs, lignes avec marge, recherche pièce
   inline avec ses correspondances, colonne total et envoi. */
const { Button: QuButton } = window.PaddockDesignSystem_8059f4;

const quOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const QU_GRID = '26px 1fr 92px 78px 100px 100px 92px 34px';
const quRow = { display: 'grid', gridTemplateColumns: QU_GRID, alignItems: 'center', gap: 10, padding: '9px 14px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const quChip = { whiteSpace: 'nowrap', flexShrink: 0, display: 'flex', alignItems: 'center', gap: 6, minHeight: 36, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const quPack = { whiteSpace: 'nowrap', flexShrink: 0, display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const quSum = { display: 'flex', justifyContent: 'space-between', fontSize: 13 };
const quGhost = { flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' };

const QU_LINES = [
  { name: 'Révision 20 000 km · main d’œuvre', sub: 'Forfait constructeur', time: '1 h 35', qty: '1', pu: '109,25', total: '109,25', margin: '100 %' },
  { name: 'Huile Motul 7100 10W40', sub: 'MOT7100-4 · 12 en stock', time: '—', qty: '3,5 L', pu: '14,90', total: '52,15', margin: '34 %', raised: true },
  { name: 'Filtre à huile HF204', sub: 'HF204 · 1 en stock', time: '—', qty: '1', pu: '11,40', total: '11,40', margin: '41 %' },
  { name: 'Kit chaîne DID 525 VX3', late: 'Hors stock · livraison 48 h · repousse la sortie au 19 août', time: '—', qty: '1', pu: '168,00', total: '168,00', margin: '28 %' },
  { name: 'Pose kit chaîne · main d’œuvre', sub: 'Dépose, remplacement, tension', time: '1 h 10', qty: '1', pu: '80,50', total: '80,50', margin: '100 %' },
];

const QU_MATCHES = [
  { name: 'Plaquettes avant Brembo 07BB19', stock: '2 en stock', pose: 'pose 0 h 30', price: '62,42 HT', best: true },
  { name: 'Plaquettes arrière Brembo 07BB20', stock: '4 en stock', pose: 'pose 0 h 25', price: '48,90 HT' },
  { name: 'Plaquettes avant origine Yamaha', stock: '0 en stock', stockOut: true, pose: 'pose 0 h 30', price: '79,10 HT' },
];

function QuoteScreen({ onSend }) {
  const [discount, setDiscount] = React.useState('Aucune');
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0, background: 'var(--pk-canvas)' }}>
      <div style={{ flex: 1, padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            <div style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Devis DV-2447</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Ludovic Renard · Yamaha MT-09 · EX-421-QR · 19 780 km</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="button" style={quChip}><i className="ri-file-copy-line" style={{ fontSize: 15 }} />Repartir d’un devis existant</button>
            <button type="button" style={quChip}><i className="ri-history-line" style={{ fontSize: 15 }} />Ce qu’il a payé la dernière fois</button>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
          <span style={{ ...quOverline, fontSize: 12, letterSpacing: '0.06em' }}>Packs</span>
          {['Révision 20 000', 'Préparation hivernage', 'Remise en route printemps', 'Contrôle avant grand trajet'].map((p) => (
            <button type="button" key={p} style={quPack}><i className="ri-add-line" style={{ fontSize: 14 }} />{p}</button>
          ))}
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Un pack ajoute ses lignes, modifiables ensuite</span>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'grid', gridTemplateColumns: QU_GRID, alignItems: 'center', gap: 10, padding: '8px 14px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span /><span>Désignation</span><span>Temps</span><span>Qté</span><span style={{ textAlign: 'right' }}>P.U. HT</span><span style={{ textAlign: 'right' }}>Total HT</span><span style={{ textAlign: 'right' }}>Marge</span><span />
          </div>
          {QU_LINES.map((l) => (
            <div key={l.name} style={{ ...quRow, background: l.late ? 'var(--pk-accent-soft)' : l.raised ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: l.late ? '3px solid var(--pk-accent)' : 'none' }}>
              <i className="ri-draggable" style={{ fontSize: 16, color: '#a5a5a5' }} />
              <div>
                <div style={{ fontWeight: 600 }}>{l.name}</div>
                {l.late
                  ? <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, color: 'var(--pk-accent-ink)', fontWeight: 600 }}><i className="ri-truck-line" style={{ fontSize: 14 }} />{l.late}</div>
                  : <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{l.sub}</div>}
              </div>
              <span style={{ color: l.time === '—' ? 'var(--pk-ink-muted)' : 'var(--pk-ink-quiet)' }}>{l.time}</span>
              <span style={{ color: 'var(--pk-ink-quiet)' }}>{l.qty}</span>
              <span style={{ textAlign: 'right' }}>{l.pu}</span>
              <span style={{ textAlign: 'right', fontWeight: 600 }}>{l.total}</span>
              <span style={{ textAlign: 'right', color: 'var(--pk-success-ink)', fontWeight: 600 }}>{l.margin}</span>
              <i className="ri-close-line" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            </div>
          ))}

          <div style={{ ...quRow, borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
            <i className="ri-add-line" style={{ fontSize: 17, color: 'var(--pk-accent-ink)' }} />
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, minHeight: 32, padding: '0 10px', background: 'var(--pk-surface-raised)', border: '2px solid var(--pk-accent)', borderRadius: 'var(--pk-radius-tile)' }}>
              <span style={{ fontWeight: 500 }}>plaqu</span>
              <span style={{ width: 1, height: 16, background: 'var(--pk-ink)' }} />
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>3 correspondances</span>
            </div>
            <span style={{ color: 'var(--pk-ink-muted)' }}>—</span><span style={{ color: 'var(--pk-ink-muted)' }}>—</span>
            <span style={{ textAlign: 'right', color: 'var(--pk-ink-muted)' }}>—</span><span style={{ textAlign: 'right', color: 'var(--pk-ink-muted)' }}>—</span><span style={{ textAlign: 'right', color: 'var(--pk-ink-muted)' }}>—</span><span />
          </div>
          {QU_MATCHES.map((m, i) => (
            <div key={m.name} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '9px 14px 9px 50px', borderBottom: i === QU_MATCHES.length - 1 ? 'none' : '1px solid var(--pk-border-quiet)', background: m.best ? 'var(--pk-accent-soft)' : 'transparent', fontSize: 13 }}>
              <i className="ri-corner-down-right-line" style={{ fontSize: 15, color: m.best ? 'var(--pk-accent-ink)' : '#a5a5a5' }} />
              <span style={{ fontWeight: m.best ? 600 : 400, width: 300 }}>{m.name}</span>
              <span style={{ color: m.stockOut ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)', width: 130 }}>{m.stock}</span>
              <span style={{ color: 'var(--pk-ink-quiet)', width: 110 }}>{m.pose}</span>
              <span style={{ flex: 1 }} />
              <span style={{ fontWeight: m.best ? 600 : 400 }}>{m.price}</span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Total et envoi</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>5 lignes</span>
        </div>

        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={quSum}><span style={{ color: 'var(--pk-ink-quiet)' }}>Main d’œuvre · 2 h 45</span><span style={{ fontWeight: 600 }}>189,75 €</span></div>
          <div style={quSum}><span style={{ color: 'var(--pk-ink-quiet)' }}>Pièces et consommables</span><span style={{ fontWeight: 600 }}>231,55 €</span></div>
          <div style={quSum}><span style={{ color: 'var(--pk-ink-quiet)' }}>Total HT</span><span style={{ fontWeight: 600 }}>421,30 €</span></div>
          <div style={quSum}><span style={{ color: 'var(--pk-ink-quiet)' }}>TVA 20 %</span><span style={{ fontWeight: 600 }}>84,26 €</span></div>
          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', paddingTop: 7, borderTop: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 14, fontWeight: 600 }}>Total TTC</span><span style={{ fontSize: 22, fontWeight: 700 }}>505,56 €</span>
          </div>
        </div>

        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={quOverline}>Remise</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>votre plafond : 15 %</span>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            {['Aucune', '5 %', '10 %', 'Montant'].map((d) => (
              <button type="button" key={d} onClick={() => setDiscount(d)}
                style={{ whiteSpace: 'nowrap', flex: 1, minHeight: 36, display: 'flex', alignItems: 'center', justifyContent: 'center', background: discount === d ? '#000' : 'transparent', color: discount === d ? '#fff' : 'inherit', border: discount === d ? 'none' : '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-tile)', fontSize: 13, fontWeight: discount === d ? 600 : 400, cursor: 'pointer' }}>{d}</button>
            ))}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
            <i className="ri-pie-chart-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />
            <span style={{ fontSize: 13 }}>Marge du devis</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--pk-success-ink)' }}>{discount === '10 %' ? '49 %' : discount === '5 %' ? '52 %' : '54 %'}</span>
          </div>
          <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Une remise de 10 % ramènerait la marge à 49 % et le total à 455 €.</div>
        </div>

        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={quOverline}>Validité et acompte</span>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13 }}><i className="ri-calendar-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />Valable 30 jours<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-quiet)' }}>jusqu’au 14 sept.</span></div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13 }}><i className="ri-percent-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />Acompte 30 %<div style={{ flex: 1 }} /><span style={{ fontWeight: 600 }}>151,67 €</span></div>
          <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Demandé automatiquement au-delà de 400 € TTC, réglable à la signature.</div>
        </div>

        <div style={{ flex: 1 }} />
        <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', fontSize: 13 }}>
            <i className="ri-message-2-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />SMS + e-mail
            <div style={{ flex: 1 }} />
            <span style={{ color: 'var(--pk-ink-quiet)', fontSize: 12 }}>07 88 · l.renard@mail.fr</span>
          </div>
          <QuButton variant="primary" tone="accent" size="medium" fullWidth onClick={onSend}>Envoyer pour signature</QuButton>
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="button" style={quGhost}>Aperçu PDF</button>
            <button type="button" style={quGhost}>Imprimer</button>
          </div>
        </div>
      </aside>
    </div>
  );
}
Object.assign(window, { QuoteScreen });
