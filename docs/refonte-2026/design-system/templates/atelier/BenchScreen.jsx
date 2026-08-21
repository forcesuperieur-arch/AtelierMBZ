const { Button, Callout, StatusBadge } = window.PaddockDesignSystem_8059f4;

const JOBS = [
  { or: 'OR 2418', vehicle: 'Triumph Tiger 900', bay: 'Pont 5', task: 'Distribution', sold: '2 h 00', done: '1 h 40', state: 'running' },
  { or: 'OR 2421', vehicle: 'Yamaha MT-07', bay: 'Pont 4', task: 'Pneus montés équilibrés', sold: '1 h 00', done: '0 h 45', state: 'running' },
  { or: 'OR 2424', vehicle: 'Honda CB500F', bay: 'Pont 3', task: 'Diagnostic panne', sold: '1 h 00', done: '—', state: 'queued' },
];

/* The bench screen: dark because it lives in the workshop, standing, under
   neon. 56px targets — gloves on. Only two actions, and the time never types
   itself: it is clocked. */
function BenchScreen() {
  const [active, setActive] = React.useState('OR 2418');
  return (
    <div style={{ flex: 1, background: 'var(--pk-canvas)', color: 'var(--pk-ink)', display: 'flex', flexDirection: 'column', minHeight: 0, fontFamily: 'var(--mb-font-montserrat)' }}>
      <div style={{ padding: '18px 24px', display: 'flex', alignItems: 'center', gap: 16, borderBottom: '1px solid var(--pk-border)' }}>
        <img src="../../assets/paddock-logo-favicon.svg" alt="Paddock" style={{ width: 40, height: 40, display: 'block' }} />
        <div>
          <div style={{ fontSize: 18, fontWeight: 600 }}>Poste de Karim M.</div>
          <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Pont 2 et 5 · 3 h 25 pointées aujourd’hui</div>
        </div>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 26, fontWeight: 700, color: 'var(--pk-accent)' }}>15:34</span>
      </div>

      <div style={{ flex: 1, padding: 24, display: 'flex', flexDirection: 'column', gap: 14, minHeight: 0, overflow: 'auto' }}>
        {JOBS.map((j) => {
          const on = j.or === active;
          return (
            <div key={j.or} style={{
              background: 'var(--pk-surface-raised)', border: on ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border-quiet)',
              padding: '16px 18px', display: 'flex', alignItems: 'center', gap: 20,
            }}>
              <div style={{ minWidth: 0, flex: 1 }}>
                <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
                  <span style={{ fontSize: 17, fontWeight: 600 }}>{j.or} — {j.vehicle}</span>
                  <StatusBadge tone={on ? 'warning' : 'neutral'}>{on ? 'En cours' : j.state === 'queued' ? 'À prendre' : 'En pause'}</StatusBadge>
                </div>
                <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>{j.bay} · {j.task}</div>
              </div>
              <div style={{ textAlign: 'right', flex: 'none' }}>
                <div style={{ fontSize: 20, fontWeight: 700 }}>{j.done}</div>
                <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>vendu {j.sold}</div>
              </div>
              <div style={{ display: 'flex', gap: 8, flex: 'none' }}>
                {on ? <>
                  <Button variant="primary" tone="accent" size="medium" shape="square" style={{ minHeight: 56, minWidth: 132 }}>Terminer</Button>
                  <Button variant="secondary" size="medium" shape="square" style={{ minHeight: 56, minWidth: 108, borderColor: 'var(--pk-border-control)', color: 'var(--pk-ink)' }}>Pause</Button>
                </> : (
                  <Button variant="secondary" size="medium" shape="square" onClick={() => setActive(j.or)} style={{ minHeight: 56, minWidth: 132, borderColor: 'var(--pk-border-control)', color: 'var(--pk-ink)' }}>Pointer</Button>
                )}
              </div>
            </div>
          );
        })}
        <Callout tone="warning">Le disque arrière de la MT-09 attend l’accord du client. Rien à faire ici : le comptoir rappelle, le pointage reprend quand la réponse arrive.</Callout>
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>L’écart vendu / pointé s’analyse par prestation, jamais par mécanicien. Cette règle est écrite dans l’interface, pas seulement dans la doc.</div>
      </div>
    </div>
  );
}

Object.assign(window, { BenchScreen });
