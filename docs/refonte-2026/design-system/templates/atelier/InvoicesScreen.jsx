/* Factures — tour 2 · 3b. Impayés en tête, échéance dépassée traitée comme
   une erreur, action par ligne (relancer / ouvrir / encaisser / PDF). */
const IV_GRID = '110px 1.2fr 1.2fr 110px 130px 110px 160px';
const ivOverline = { fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const ivKpi = { padding: '10px 20px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 };
const ivRow = { display: 'grid', gridTemplateColumns: IV_GRID, alignItems: 'center', padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const ivPill = { padding: '7px 14px', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer' };
const ivGhostPill = { padding: '7px 14px', background: 'transparent', color: 'inherit', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer' };

const IV_UNPAID = [
  { id: 'FA-1188', client: 'Sarah Amrani', bike: 'Z650 · EL-447-TY', issued: '02 juil.', due: '+ 14 jours', level: 'late', total: '1 420,00 €', action: 'Relancer' },
  { id: 'FA-1194', client: 'Camille Perrot', bike: 'SV 650 · TG-556-AA', issued: '09 juil.', due: '+ 7 jours', level: 'late', total: '860,00 €', action: 'Relancer' },
  { id: 'FA-1201', client: 'Hugo Lacroix', bike: 'Z650 · RM-341-BF', issued: '16 juil.', due: '+ 2 jours', level: 'late', total: '1 980,00 €', action: 'Relancer' },
  { id: 'FA-1216', client: 'Pierre Guérin', bike: 'CB500F · DR-118-NX', issued: '05 août', due: 'Dans 3 jours', level: 'warn', total: '240,00 €', action: 'Ouvrir' },
  { id: 'FA-1224', client: 'Céline Marchand', bike: 'Vespa GTS · FH-220-JK', issued: '12 août', due: 'Dans 12 jours', total: '184,90 €', action: 'Ouvrir' },
  { id: 'FA-1229', client: 'Julien Ravel', bike: 'Z900 · BR-742-TM', issued: '15 août', due: 'À la restitution', total: '640,00 €', action: 'Encaisser' },
];

const IV_PAID = [
  { id: 'FA-1231', client: 'Ludovic Renard', bike: 'MT-09 · EX-421-QR', issued: '15 août', due: 'Payée · CB', total: '412,50 €' },
  { id: 'FA-1230', client: 'Marc Delaunay', bike: 'MT-07 · CD-114-VF', issued: '13 août', due: 'Payée · espèces', total: '298,00 €' },
];

function InvoicesScreen() {
  const [tab, setTab] = React.useState('Impayées · 7');
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Factures</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
            <div style={ivKpi}><span style={ivOverline}>Encaissé ce mois</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>31 480 €</span></div>
            <div style={ivKpi}><span style={ivOverline}>Impayés</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-error-ink)' }}>4 260 €</span></div>
            <div style={ivKpi}><span style={ivOverline}>Échéance dépassée</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-error-ink)' }}>3</span></div>
            <div style={{ ...ivKpi, borderRight: 'none' }}><span style={ivOverline}>Panier moyen</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>386 €</span></div>
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, width: 280 }}><i className="ri-search-line" style={{ fontSize: 16 }} />N° de facture, client, plaque…</div>
            {['Impayées · 7', 'Payées · 74', 'Acomptes · 3', 'Avoirs · 1'].map((t) => (
              <button type="button" key={t} onClick={() => setTab(t)}
                style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'inherit', border: tab === t ? 'none' : '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: tab === t ? 600 : 400, cursor: 'pointer' }}>{t}</button>
            ))}
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-quiet)' }}><i className="ri-sort-desc" style={{ fontSize: 15 }} />Trié par retard de paiement</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: IV_GRID, padding: '9px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', ...ivOverline }}>
            <span>N°</span><span>Client</span><span>Moto</span><span>Émise le</span><span>Échéance</span><span style={{ textAlign: 'right' }}>Total TTC</span><span style={{ textAlign: 'right' }}>Action</span>
          </div>
          {IV_UNPAID.map((r, i) => (
            <div key={r.id} style={{ ...ivRow, background: i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: '3px solid ' + (r.level === 'late' ? 'var(--pk-error-line)' : r.level === 'warn' ? 'var(--pk-warning-line)' : 'var(--pk-border)') }}>
              <span style={{ fontWeight: 600 }}>{r.id}</span><span>{r.client}</span><span style={{ color: 'var(--pk-ink-quiet)' }}>{r.bike}</span><span style={{ color: 'var(--pk-ink-quiet)' }}>{r.issued}</span>
              {r.level === 'late'
                ? <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontWeight: 600, color: 'var(--pk-error-ink)' }}><i className="ri-error-warning-line" style={{ fontSize: 14 }} />{r.due}</span>
                : <span style={{ fontWeight: r.level === 'warn' ? 600 : 400, color: r.level === 'warn' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-quiet)' }}>{r.due}</span>}
              <span style={{ textAlign: 'right', fontWeight: 700 }}>{r.total}</span>
              <span style={{ textAlign: 'right' }}><button type="button" style={r.action === 'Ouvrir' ? ivGhostPill : ivPill}>{r.action}</button></span>
            </div>
          ))}
          <div style={{ padding: '10px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Payées — 74 ce mois</div>
          {IV_PAID.map((r, i) => (
            <div key={r.id} style={{ ...ivRow, background: i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent' }}>
              <span style={{ fontWeight: 600 }}>{r.id}</span><span>{r.client}</span><span style={{ color: 'var(--pk-ink-quiet)' }}>{r.bike}</span><span style={{ color: 'var(--pk-ink-quiet)' }}>{r.issued}</span>
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontWeight: 600, color: 'var(--pk-success-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />{r.due}</span>
              <span style={{ textAlign: 'right', fontWeight: 700 }}>{r.total}</span>
              <span style={{ textAlign: 'right' }}><button type="button" style={{ ...ivGhostPill, display: 'inline-flex', alignItems: 'center', gap: 5 }}><i className="ri-file-text-line" style={{ fontSize: 14 }} />PDF</button></span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
          <div style={{ display: 'flex', alignItems: 'center', padding: '12px 16px', borderTop: '1px solid var(--pk-border)', fontSize: 12, color: 'var(--pk-ink-quiet)' }}>
            <span>85 factures en août · 4 260 € restant à encaisser</span>
            <div style={{ flex: 1 }} />
            <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 12, fontWeight: 600, color: 'var(--pk-ink)', background: 'transparent', cursor: 'pointer' }}><i className="ri-download-2-line" style={{ fontSize: 14 }} />Export comptable</button>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { InvoicesScreen });
