const { KpiTile, QueueRow, BayCard, PageHeading, PillTabs, Callout } = window.PaddockDesignSystem_8059f4;

function StatScreen({ onOpenPlanning }) {
  const [tab, setTab] = React.useState('atelier');
  return (
    <div style={{ flex: 1, overflow: 'hidden', padding: 20, display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
      <PageHeading title="Stat" description="L’état de l’atelier maintenant et ce qu’il y a à traiter.">
        <PillTabs value={tab} onChange={setTab} items={[
          { value: 'atelier', label: 'Atelier', count: 7 },
          { value: 'periode', label: 'Période' },
          { value: 'analyse', label: 'Analyse' },
          { value: 'explorer', label: 'Explorer' },
        ]} />
      </PageHeading>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
        <KpiTile label="Ponts occupés" value="5" unit="/6" ratio={0.83} note="1 libre maintenant · 83 % de la capacité" />
        <KpiTile label="Interventions en cours" value="4" note="3 mécaniciens au travail" />
        <KpiTile label="Charge du jour" value="6 h 20" ratio={0.61} note="3 h 50 pointé · 61 % du planifié" onClick={onOpenPlanning} />
        <KpiTile label="À traiter" value="7" tone="error" note="dont 4 critiques" />
      </div>

      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1.35fr 1fr', gap: 16, minHeight: 0 }}>
        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>File à traiter</span>
            <span style={{ minWidth: 20, height: 20, padding: '0 6px', background: '#000', color: '#fff', fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>7</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Tout ouvrir</span>
          </div>
          {window.QUEUE.map((q) => <QueueRow key={q.title} {...q} />)}
          <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10, background: 'var(--pk-surface-raised)' }}>
            <i className="ri-alert-line" style={{ fontSize: 16, color: 'var(--pk-warning-line)' }} />
            <span style={{ fontSize: 13, fontWeight: 600 }}>4 pièces sous le seuil de stock</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Voir la liste</span>
          </div>
          <div style={{ flex: 1, padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
            <div style={{ fontSize: 11, fontWeight: 600, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Mécaniciens au travail</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {window.MECHANICS.map((m) => (
                <div key={m.initials} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <div style={{ width: 26, height: 26, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-neutral-surface)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 600 }}>{m.initials}</div>
                  <span style={{ fontSize: 13, flex: 1 }}>{m.name}</span>
                  <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{m.task}</span>
                  <span style={{ fontSize: 12, fontWeight: 600 }}>{m.time}</span>
                </div>
              ))}
            </div>
            <div style={{ flex: 1 }} />
            <Callout tone="accent" edge>Le temps calibre les forfaits, pas les gens : l’écart vendu/pointé s’analyse par prestation, jamais nominativement.</Callout>
          </div>
        </div>

        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Ponts</span>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>6 actifs · occupation en direct</span>
          </div>
          <div style={{ flex: 1, padding: 12, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, alignContent: 'start' }}>
            {window.BAY_STATE.map((b) => <BayCard key={b.name} {...b} />)}
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { StatScreen });
