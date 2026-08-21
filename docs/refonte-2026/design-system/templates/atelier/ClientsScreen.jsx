const { PageHeading, StatStrip, FilterPill, Button } = window.PaddockDesignSystem_8059f4;

const COLS = '1.4fr 1fr 1.4fr 0.9fr 1fr 0.9fr 70px';

/* Clients — the four stat cards become a one-line band and the search moves
   into the table header: the list starts on the first screen instead of the
   second. */
function ClientsScreen() {
  const [filter, setFilter] = React.useState('tous');
  return (
    <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
      <PageHeading title="Clients">
        <StatStrip items={[
          { label: 'Total clients', value: '1 284' },
          { label: 'Avec RDV', value: '742', suffix: '58 %' },
          { label: 'Véhicules', value: '1 611', suffix: '1,3/client' },
          { label: 'CA total', value: '418 260 €' },
        ]} />
      </PageHeading>

      <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, width: 300 }}>
            <i className="ri-search-line" style={{ fontSize: 16 }} />Nom, téléphone, e-mail, plaque…
          </div>
          <FilterPill label="Tous" selected={filter === 'tous'} onClick={() => setFilter('tous')} />
          <FilterPill label="Avec RDV à venir" selected={filter === 'rdv'} onClick={() => setFilter('rdv')} />
          <FilterPill label="Devis en attente" selected={filter === 'devis'} onClick={() => setFilter('devis')} />
          <FilterPill label="Inactifs > 18 mois" selected={filter === 'inactifs'} onClick={() => setFilter('inactifs')} />
          <div style={{ flex: 1 }} />
          <Button variant="secondary" size="small" shape="square" startIcon="ri-download-2-line" style={{ borderColor: 'var(--pk-border-control)', fontSize: 12 }}>Exporter</Button>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: COLS, padding: '9px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
          <span>Client</span><span>Téléphone</span><span>E-mail</span><span>Véhicules</span><span>Dernier passage</span><span style={{ textAlign: 'right' }}>CA</span><span />
        </div>

        {window.CLIENTS.map((c, i) => (
          <div key={c.name} style={{
            display: 'grid', gridTemplateColumns: COLS, alignItems: 'center', padding: '11px 16px',
            borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13,
            background: i % 2 ? 'var(--pk-surface-raised)' : 'transparent',
          }}>
            <span style={{ fontWeight: 600 }}>{c.name}</span>
            <span style={{ color: 'var(--pk-ink-quiet)', fontFamily: 'var(--mb-font-inter)', fontSize: 12 }}>{c.phone}</span>
            <span style={{ color: 'var(--pk-ink-quiet)', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.email}</span>
            <span>{c.vehicles}</span>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>{c.last}</span>
            <span style={{ textAlign: 'right', fontWeight: 600 }}>{c.ca}</span>
            <span style={{ textAlign: 'right', fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Voir →</span>
          </div>
        ))}

        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 16px', borderTop: '1px solid var(--pk-border)' }}>
          <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>10 sur 1 284 fiches</span>
          <div style={{ flex: 1 }} />
          <Button variant="secondary" size="small" style={{ borderColor: 'var(--pk-border-control)', fontSize: 12 }}>Page suivante</Button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { ClientsScreen });
