const { PageHeading, StatStrip, Button, FilterPill, StatusBadge } = window.PaddockDesignSystem_8059f4;

function Plate({ children }) {
  return <span style={{ display: 'inline-block', marginLeft: 6, padding: '1px 6px', border: '1px solid var(--pk-border-control)', borderRadius: 3, fontSize: 11, fontWeight: 600, letterSpacing: '0.04em', fontFamily: 'var(--mb-font-inter)' }}>{children}</span>;
}

function Pill({ children, tone }) {
  const skin = tone === 'success' ? { background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)', color: 'var(--pk-success-ink)' }
    : tone === 'warning' ? { background: 'var(--pk-warning-surface)', border: '1px solid var(--pk-warning-line)', color: 'var(--pk-warning-ink)' }
      : tone === 'dashed' ? { border: '1px dashed var(--pk-border-control)', color: 'var(--pk-ink-quiet)' }
        : { background: 'var(--pk-neutral-surface)', color: 'var(--pk-ink-quiet)' };
  return <span style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '4px 10px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap', ...skin }}>{children}</span>;
}

/* Réception du matin — one line per expected motorcycle, the hour anchored
   left, the état des lieux and its PDF on the same line as the action. */
function ReceptionScreen({ onCheckIn }) {
  return (
    <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0, overflow: 'hidden' }}>
      <PageHeading title="Réception du matin" description="Vendredi 15 août — check-in et état des lieux d’entrée.">
        <StatStrip items={[
          { label: 'RDV du jour', value: '8' },
          { label: 'Check-ins signés', value: '3' },
          { label: 'Restants', value: '5' },
        ]} />
      </PageHeading>

      <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
          <i className="ri-inbox-line" style={{ fontSize: 17 }} />
          <span style={{ fontSize: 15, fontWeight: 600 }}>Motos attendues aujourd’hui</span>
          <span style={{ minWidth: 20, height: 20, padding: '0 6px', background: '#000', color: '#fff', fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>8</span>
          <div style={{ flex: 1 }} />
          <FilterPill label="À réceptionner" count={5} selected />
          <FilterPill label="Signés" count={3} />
          <FilterPill label="Absents" count={0} />
        </div>

        {window.CHECKINS.map((c, i) => {
          const todo = c.state === 'todo';
          return (
            <div key={c.time} style={{
              display: 'flex', alignItems: 'center', gap: 16, padding: '14px 16px',
              borderBottom: i < window.CHECKINS.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none',
              borderLeft: todo ? '3px solid var(--pk-accent)' : 'none',
              background: todo ? 'transparent' : 'var(--pk-surface-raised)',
            }}>
              <div style={{ width: 58, textAlign: 'center', flex: 'none' }}>
                <div style={{ fontSize: 18, fontWeight: 700 }}>{c.time}</div>
                <div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{c.duration}</div>
              </div>
              <div style={{ width: 1, height: 40, background: 'var(--pk-border-quiet)', flex: 'none' }} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14, fontWeight: 600 }}>{c.customer}</div>
                <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>{c.vehicle}<Plate>{c.plate}</Plate></div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{c.work}</div>
              </div>
              {c.flag ? <Pill tone="warning"><i className="ri-hammer-line" style={{ fontSize: 14 }} />{c.flag}</Pill> : null}
              {c.unassigned ? <Pill tone="dashed"><i className="ri-tools-line" style={{ fontSize: 14 }} />Sans affectation</Pill> : null}
              {todo ? <Pill>EDL à faire</Pill> : <Pill tone="success">Check-in signé</Pill>}
              {todo ? null : (
                <Button variant="secondary" size="small" startIcon="ri-file-text-line" style={{ borderColor: 'var(--pk-border-control)', fontSize: 12 }}>PDF de l’EDL</Button>
              )}
              {todo
                ? <Button variant="primary" tone="accent" size="small" onClick={onCheckIn}>Démarrer le check-in</Button>
                : <Button variant="secondary" size="small">Revoir</Button>}
            </div>
          );
        })}
      </div>
    </div>
  );
}

Object.assign(window, { ReceptionScreen, Plate, Pill });
