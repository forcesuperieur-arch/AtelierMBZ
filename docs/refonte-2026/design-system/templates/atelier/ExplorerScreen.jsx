const { DataTable, UnderlineTabs, Button, KpiTile } = window.PaddockDesignSystem_8059f4;

function Facet({ label, count, checked }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13 }}>
      <span style={{ width: 17, height: 17, flex: 'none', background: checked ? 'var(--pk-accent)' : 'transparent', border: checked ? 'none' : '1px solid var(--pk-border-control)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        {checked ? <i className="ri-check-line" style={{ fontSize: 13, color: '#000' }} /> : null}
      </span>
      {label}
      <div style={{ flex: 1 }} />
      {count != null ? <span style={{ color: 'var(--pk-ink-muted)' }}>{count}</span> : null}
    </div>
  );
}

function FacetGroup({ label, children }) {
  return (
    <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
      <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{label}</span>
      {children}
    </div>
  );
}

function ExplorerScreen() {
  const columns = [
    { key: 'client', header: 'Client', width: '1.3fr', strong: true },
    { key: 'moto', header: 'Moto', width: '1.2fr', quiet: true },
    { key: 'last', header: 'Dernier passage', width: '130px', quiet: true },
    { key: 'spend', header: 'Dépensé', width: '120px', align: 'right', strong: true },
    { key: 'due', header: 'Entretien dû', width: '110px', align: 'right', render: (r) => <span style={{ fontWeight: r.overdue ? 700 : 400, color: r.overdue ? 'var(--pk-error-ink)' : 'var(--pk-ink-muted)' }}>{r.due}</span> },
  ];
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
      <UnderlineTabs value="explorer" items={[{ value: 'atelier', label: 'Atelier' }, { value: 'periode', label: 'Période' }, { value: 'analyse', label: 'Analyse' }, { value: 'explorer', label: 'Explorer' }]}>
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-muted)' }}><i className="ri-save-line" style={{ fontSize: 15 }} />3 recherches enregistrées</span>
      </UnderlineTabs>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <aside style={{ width: 280, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
            <span style={{ fontSize: 14, fontWeight: 600 }}>Facettes</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Tout effacer</span>
          </div>
          <FacetGroup label="Période">
            <div style={{ display: 'flex', alignItems: 'center', gap: 9, minHeight: 34, padding: '0 11px', background: '#000', color: '#fff', fontSize: 13 }}>
              12 derniers mois<div style={{ flex: 1 }} /><i className="ri-arrow-down-s-line" style={{ fontSize: 15 }} />
            </div>
          </FacetGroup>
          <FacetGroup label="Type de prestation">
            <Facet label="Révision" count={318} checked />
            <Facet label="Pneus" count={164} />
            <Facet label="Freinage" count={98} />
            <Facet label="Diagnostic" count={61} />
          </FacetGroup>
          <FacetGroup label="Client">
            <Facet label="Première visite" count={142} />
            <Facet label="Pas revenu depuis 12 mois" count={87} checked />
            <Facet label="Plus de 3 passages" count={204} />
          </FacetGroup>
          <FacetGroup label="Marque de moto">
            <Facet label="Yamaha" count={198} />
            <Facet label="Honda" count={153} />
          </FacetGroup>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '12px 16px', borderTop: '1px solid var(--pk-border)' }}>
            <Button variant="secondary" size="small" fullWidth startIcon="ri-save-line">Enregistrer cette recherche</Button>
          </div>
        </aside>

        <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
          <div style={{ padding: '14px 16px', background: '#000', color: '#f6f6f6', display: 'flex', flexDirection: 'column', gap: 5 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Votre question</span>
            <span style={{ fontSize: 17, fontWeight: 500, lineHeight: 1.35 }}>
              Les clients venus pour une <strong style={{ fontWeight: 700, color: 'var(--pk-accent)' }}>révision</strong> et qui ne sont <strong style={{ fontWeight: 700, color: 'var(--pk-accent)' }}>pas revenus depuis 12 mois</strong>.
            </span>
          </div>
          <div style={{ display: 'flex', gap: 14 }}>
            <KpiTile style={{ flex: 1 }} label="Clients concernés" value="64" note="sur 433 clients au total" />
            <KpiTile style={{ flex: 1 }} label="Chiffre passé" value="14 200 €" note="222 € en moyenne par client" />
            <KpiTile style={{ flex: 1 }} label="Entretien dû" value="41" tone="warning" note="d’après leur carnet et leur kilométrage" />
          </div>
          <DataTable style={{ flex: 1 }} columns={columns} rows={window.EXPLORER_ROWS} rowKey={(r) => r.client}
            footer={<>
              <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>59 autres clients</span>
              <div style={{ flex: 1 }} />
              <Button variant="secondary" size="small">Exporter en CSV</Button>
              <Button variant="primary" tone="accent" size="small">Créer une campagne de rappel</Button>
            </>} />
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { ExplorerScreen });
