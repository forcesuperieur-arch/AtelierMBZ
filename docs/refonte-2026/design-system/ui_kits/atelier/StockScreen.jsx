/* Stock — tour 2 · 3c. La jauge stock / seuil est dans la ligne, le tri met
   les pièces sous le seuil en haut, une rupture dit l'OR qu'elle bloque. */
const SK_GRID = '130px 1.5fr 1fr 190px 100px 110px 120px';
const skOverline = { fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const skKpi = { padding: '10px 20px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 };
const skRow = { display: 'grid', gridTemplateColumns: SK_GRID, alignItems: 'center', padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const skOrder = { padding: '7px 14px', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer' };

const SK_LOW = [
  { ref: '07BB1935', name: 'Plaquettes avant Brembo', sub: 'Bloque l’OR 2418 · MT-09 sur pont 1', subError: true, loc: 'Rayon B · bac 12', pct: 0, qty: '0 / 4', level: 'out', buy: '38,20 €', sell: '74,90 €' },
  { ref: 'MOT-10W40', name: 'Huile moteur 10W40 · 4 L', sub: 'Consommable · 6 révisions prévues cette semaine', loc: 'Rayon A · bac 03', pct: 33, qty: '2 / 6', level: 'low', buy: '21,40 €', sell: '42,00 €' },
  { ref: 'FIL-HU-224', name: 'Filtre à huile HF204', sub: 'Compatible MT-07 / MT-09 / Tracer 9', loc: 'Rayon A · bac 07', pct: 60, qty: '3 / 5', level: 'low', buy: '6,80 €', sell: '14,50 €' },
  { ref: 'PN-180-55', name: 'Pneu arrière 180/55 ZR17', sub: 'Monté sur Z900 · délai fournisseur 48 h', loc: 'Réserve · mur pneus', pct: 50, qty: '1 / 2', level: 'low', buy: '92,00 €', sell: '168,00 €' },
];

const SK_OK = [
  { ref: 'BOU-NGK-9', name: 'Bougie NGK CR9EK', loc: 'Rayon A · bac 11', pct: 100, qty: '18 / 8', buy: '7,10 €', sell: '15,90 €' },
  { ref: 'CHA-DID-525', name: 'Kit chaîne DID 525 VX3', loc: 'Rayon C · bac 02', pct: 100, qty: '5 / 2', buy: '121,00 €', sell: '210,00 €' },
];

function StockScreen() {
  const [tab, setTab] = React.useState('À commander · 5');
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Stock — pièces détachées</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
            <div style={skKpi}><span style={skOverline}>Références</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>412</span></div>
            <div style={skKpi}><span style={skOverline}>Sous le seuil</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-warning-ink-soft)' }}>4</span></div>
            <div style={skKpi}><span style={skOverline}>En rupture</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-error-ink)' }}>1</span></div>
            <div style={{ ...skKpi, borderRight: 'none' }}><span style={skOverline}>Valeur du stock</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>48 910 €</span></div>
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, width: 280 }}><i className="ri-search-line" style={{ fontSize: 16 }} />Référence, désignation, marque…</div>
            {['À commander · 5', 'Tout le stock · 412', 'Consommables', 'Pneumatiques'].map((t) => (
              <button type="button" key={t} onClick={() => setTab(t)}
                style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'inherit', border: tab === t ? 'none' : '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: tab === t ? 600 : 400, cursor: 'pointer' }}>{t}</button>
            ))}
            <div style={{ flex: 1 }} />
            <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', background: '#000', color: '#fff', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}><i className="ri-shopping-cart-line" style={{ fontSize: 14 }} />Générer la commande fournisseur</button>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: SK_GRID, padding: '9px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', ...skOverline }}>
            <span>Référence</span><span>Désignation</span><span>Emplacement</span><span>Stock / seuil</span><span style={{ textAlign: 'right' }}>Achat HT</span><span style={{ textAlign: 'right' }}>Vente HT</span><span style={{ textAlign: 'right' }}>Action</span>
          </div>
          {SK_LOW.map((r, i) => (
            <div key={r.ref} style={{ ...skRow, background: i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: '3px solid ' + (r.level === 'out' ? 'var(--pk-error-line)' : 'var(--pk-warning-line)') }}>
              <span style={{ fontWeight: 600 }}>{r.ref}</span>
              <div><div style={{ fontWeight: 600 }}>{r.name}</div><div style={{ fontSize: 12, color: r.subError ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)' }}>{r.sub}</div></div>
              <span style={{ color: 'var(--pk-ink-quiet)' }}>{r.loc}</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ flex: 1, height: 8, background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', overflow: 'hidden' }}>
                  <div style={{ width: r.pct + '%', height: '100%', background: r.level === 'out' ? 'var(--pk-error-line)' : 'var(--pk-warning-line)' }} />
                </div>
                <span style={{ fontSize: 12, fontWeight: 700, color: r.level === 'out' ? 'var(--pk-error-ink)' : 'var(--pk-warning-ink-soft)', width: 44 }}>{r.qty}</span>
              </div>
              <span style={{ textAlign: 'right', color: 'var(--pk-ink-quiet)' }}>{r.buy}</span>
              <span style={{ textAlign: 'right', fontWeight: 600 }}>{r.sell}</span>
              <span style={{ textAlign: 'right' }}><button type="button" style={skOrder}>Commander</button></span>
            </div>
          ))}
          <div style={{ padding: '10px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Stock suffisant</div>
          {SK_OK.map((r, i) => (
            <div key={r.ref} style={{ ...skRow, background: i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent' }}>
              <span style={{ fontWeight: 600 }}>{r.ref}</span>
              <span style={{ fontWeight: 600 }}>{r.name}</span>
              <span style={{ color: 'var(--pk-ink-quiet)' }}>{r.loc}</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ flex: 1, height: 8, background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', overflow: 'hidden' }}><div style={{ width: '100%', height: '100%', background: 'var(--pk-success-line)' }} /></div>
                <span style={{ fontSize: 12, fontWeight: 700, width: 44 }}>{r.qty}</span>
              </div>
              <span style={{ textAlign: 'right', color: 'var(--pk-ink-quiet)' }}>{r.buy}</span>
              <span style={{ textAlign: 'right', fontWeight: 600 }}>{r.sell}</span>
              <span style={{ textAlign: 'right', fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Modifier</span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
          <div style={{ display: 'flex', alignItems: 'center', padding: '12px 16px', borderTop: '1px solid var(--pk-border)', fontSize: 12, color: 'var(--pk-ink-quiet)' }}>
            <span>412 références · 48 910 € de valeur</span>
            <div style={{ flex: 1 }} />
            <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 12, fontWeight: 600, color: 'var(--pk-ink)', background: 'transparent', cursor: 'pointer' }}><i className="ri-download-2-line" style={{ fontSize: 14 }} />Export inventaire</button>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { StockScreen });
