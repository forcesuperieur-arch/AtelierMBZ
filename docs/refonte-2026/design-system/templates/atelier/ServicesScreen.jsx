/* Admin › Prestations (tour 37a). Liste à gauche groupée par famille, forfait
   sélectionné à droite : main d'œuvre, pièces incluses, prix et marge. Les
   forfaits mal calibrés portent un liseré — vert si le temps vendu est trop
   large, rouge s'il est trop court. */
const svRow = { display: 'flex', alignItems: 'center', gap: 12, padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)', cursor: 'pointer' };
const svHead = { padding: '8px 16px 6px', background: 'var(--pk-canvas)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const svPartGrid = { display: 'grid', gridTemplateColumns: '1fr 90px 96px 96px 30px', alignItems: 'center', gap: 12, padding: '9px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };

const SV_FAMILIES = [
  { name: 'Entretien', count: 9, items: [
    { id: 'rev20', name: 'Révision 20 000 km', sub: '1 h 40 · 4 pièces liées', price: '268,00 €' },
    { id: 'rev10', name: 'Révision 10 000 km', sub: '1 h 20 · 3 pièces liées', price: '189,00 €' },
    { id: 'vid', name: 'Vidange simple', sub: '0 h 40 · 2 pièces liées', price: '96,00 €' },
  ] },
  { name: 'Freinage', count: 5, items: [
    { id: 'plaq', name: 'Plaquettes avant', sub: '0 h 30 · 1 pièce liée', price: '74,90 €' },
    { id: 'purge', name: 'Purge circuit de frein', sub: '0 h 45 · 1 pièce liée', price: '88,00 €' },
  ] },
  { name: 'Pneumatiques', count: 4, items: [
    { id: 'pneu', name: 'Pneu arrière + équilibrage', price: '214,00 €', flag: 'under', sub: '1 h 00 vendue, 0 h 42 réelle' },
  ] },
  { name: 'Diagnostic', count: 3, items: [
    { id: 'diagel', name: 'Diagnostic électrique', price: '108,00 €', flag: 'over', sub: '1 h 00 vendue, 1 h 38 réelle' },
    { id: 'diagin', name: 'Diagnostic injection', sub: 'Retirée du catalogue en mars', retired: true },
  ] },
];

const SV_DETAIL = {
  rev20: { title: 'Révision 20 000 km', meta: 'Forfait constructeur · 61 passages sur 6 mois', time: '1 h 40', labour: '120,00 €', check: { tone: 'ok', text: 'Temps réel moyen 1 h 44, soit + 4 % — dans la marge de 10 %.' }, price: '268,00 €', margin: '62 %', parts: [
    { name: 'Huile Motul 7100 10W40', ref: 'MOT7100-4', qty: '3,5 L', buy: '9,80', sell: '14,90' },
    { name: 'Filtre à huile HF204', ref: 'HF204', qty: '1', buy: '6,70', sell: '11,40' },
    { name: 'Joint de vidange', ref: 'JV-M14', qty: '1', buy: '0,90', sell: '2,40' },
    { name: 'Filtre à air', ref: 'FA-YT9 · propre au modèle', qty: '1', buy: '18,40', sell: '31,20' },
  ] },
  diagel: { title: 'Diagnostic électrique', meta: 'Forfait atelier · 22 passages sur 6 mois', time: '1 h 00', labour: '72,00 €', check: { tone: 'bad', text: 'Temps réel moyen 1 h 38, soit + 63 %. Le forfait perd de l’argent à chaque passage.' }, price: '108,00 €', margin: '18 %', parts: [
    { name: 'Consommables diagnostic', ref: 'CONS-DIAG', qty: '1', buy: '4,20', sell: '9,00' },
  ] },
  pneu: { title: 'Pneu arrière + équilibrage', meta: 'Forfait atelier · 84 passages sur 6 mois', time: '1 h 00', labour: '72,00 €', check: { tone: 'warn', text: 'Temps réel moyen 0 h 42, soit − 30 %. Le forfait est vendu plus large que nécessaire.' }, price: '214,00 €', margin: '41 %', parts: [
    { name: 'Pneu Michelin Road 6 180/55', ref: 'MI-R6-18055', qty: '1', buy: '118,00', sell: '164,00' },
    { name: 'Masses d’équilibrage', ref: 'EQ-MASS', qty: '1', buy: '1,10', sell: '4,00' },
  ] },
};

function ServicesScreen() {
  const [sel, setSel] = React.useState('rev20');
  const d = SV_DETAIL[sel] || SV_DETAIL.rev20;
  const tone = d.check.tone;
  const checkStyle = tone === 'ok'
    ? { background: 'var(--pk-success-surface)', icon: 'ri-check-line', color: 'var(--pk-success-ink)' }
    : tone === 'warn'
      ? { background: 'var(--pk-warning-surface)', icon: 'ri-arrow-down-line', color: 'var(--pk-warning-ink)' }
      : { background: 'var(--pk-error-surface)', icon: 'ri-error-warning-line', color: 'var(--pk-error-ink)' };
  return (
    <React.Fragment>
      <div style={{ width: 420, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 32, padding: '0 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, flex: 1 }}><i className="ri-search-line" style={{ fontSize: 15 }} />Chercher une prestation</div>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>31</span>
        </div>
        <div style={{ flex: 1, overflow: 'hidden' }}>
          {SV_FAMILIES.map((f) => (
            <React.Fragment key={f.name}>
              <div style={svHead}>{f.name} · {f.count}</div>
              {f.items.map((it) => {
                const active = sel === it.id;
                const edge = it.flag === 'under' ? 'var(--pk-success-line)' : it.flag === 'over' ? 'var(--pk-error-line)' : null;
                const flagColor = it.flag === 'under' ? 'var(--pk-success-ink)' : 'var(--pk-error-ink)';
                return (
                  <div key={it.id} onClick={() => (it.retired ? null : setSel(it.id))}
                    style={{ ...svRow, background: active ? 'var(--pk-accent-soft)' : 'transparent', borderLeft: active ? '3px solid var(--pk-accent)' : edge ? '3px solid ' + edge : '3px solid transparent', color: it.retired ? 'var(--pk-ink-muted)' : 'inherit', cursor: it.retired ? 'default' : 'pointer' }}>
                    {it.retired ? <i className="ri-eye-off-line" style={{ fontSize: 16 }} /> : null}
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 14, fontWeight: 600 }}>{it.name}</div>
                      {it.flag ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, color: flagColor, fontWeight: 600 }}>
                          <i className={it.flag === 'under' ? 'ri-arrow-down-line' : 'ri-arrow-up-line'} style={{ fontSize: 13 }} />{it.sub}
                        </div>
                      ) : <div style={{ fontSize: 12, color: it.retired ? 'inherit' : 'var(--pk-ink-muted)' }}>{it.sub}</div>}
                    </div>
                    {it.price ? <span style={{ fontSize: 14, fontWeight: active ? 700 : 600 }}>{it.price}</span> : null}
                  </div>
                );
              })}
            </React.Fragment>
          ))}
        </div>
      </div>

      <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.015em' }}>{d.title}</div>
          <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>{d.meta}</div>
        </div>

        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ padding: '10px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 14, fontWeight: 600 }}>Main d’œuvre</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '13px 16px' }}>
            <span style={{ fontSize: 13, width: 130 }}>Temps vendu</span>
            <span style={{ minWidth: 96, minHeight: 38, display: 'flex', alignItems: 'center', padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 6, fontSize: 15 }}>{d.time}</span>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>× 72,00 €/h</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 16, fontWeight: 700 }}>{d.labour}</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 16px', borderTop: '1px solid var(--pk-border-quiet)', background: checkStyle.background }}>
            <i className={checkStyle.icon} style={{ fontSize: 16, color: checkStyle.color, flexShrink: 0 }} />
            <span style={{ fontSize: 13, color: 'var(--pk-ink)' }}>{d.check.text}</span>
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 14, fontWeight: 600 }}>Pièces incluses</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-link)', whiteSpace: 'nowrap' }}><i className="ri-add-line" style={{ fontSize: 15 }} />Ajouter</span>
          </div>
          <div style={{ ...svPartGrid, padding: '8px 16px', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span>Référence</span><span style={{ textAlign: 'right' }}>Qté</span><span style={{ textAlign: 'right' }}>Achat</span><span style={{ textAlign: 'right' }}>Vente</span><span />
          </div>
          {d.parts.map((p, i) => (
            <div key={p.ref} style={{ ...svPartGrid, background: i % 2 ? 'var(--pk-surface-raised)' : 'transparent' }}>
              <div><div style={{ fontWeight: 600 }}>{p.name}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{p.ref}</div></div>
              <span style={{ textAlign: 'right' }}>{p.qty}</span>
              <span style={{ textAlign: 'right', color: 'var(--pk-ink-quiet)' }}>{p.buy}</span>
              <span style={{ textAlign: 'right', fontWeight: 600 }}>{p.sell}</span>
              <i className="ri-close-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 20, padding: '14px 16px', background: '#000', color: '#f6f6f6' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Prix de vente</span>
            <span style={{ fontSize: 24, fontWeight: 700, lineHeight: 1 }}>{d.price}</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Marge</span>
            <span style={{ fontSize: 20, fontWeight: 700, lineHeight: 1, marginTop: 3, color: '#4dbb3a' }}>{d.margin}</span>
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 16px', border: '1px solid rgba(255,255,255,0.45)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}>Retirer du catalogue</span>
          <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 18px', background: 'var(--pk-accent)', color: '#000', borderRadius: 'var(--pk-radius-pill)', fontSize: 14, fontWeight: 600, whiteSpace: 'nowrap' }}>Enregistrer</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
          <i className="ri-shield-check-line" style={{ fontSize: 16, color: 'var(--pk-success-ink)' }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Les 11 devis en attente et les OR en cours gardent le forfait sous lequel ils ont été établis.</span>
        </div>
      </div>
    </React.Fragment>
  );
}
Object.assign(window, { ServicesScreen });
