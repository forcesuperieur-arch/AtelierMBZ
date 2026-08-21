const { PageHeading, StatStrip, BayControlCard } = window.PaddockDesignSystem_8059f4;

const TABS = [
  { id: 'ponts', label: 'Ponts', icon: 'ri-tools-line' },
  { id: 'meca', label: 'Mécaniciens', icon: 'ri-user-line' },
  { id: 'temps', label: 'Temps par type', icon: 'ri-timer-line' },
  { id: 'absences', label: 'Absences', icon: 'ri-calendar-line' },
];

/* Ponts & Méca — the four existing tabs stay. Every bay card carries its
   state, its configuration and its day, without going through the admin. */
function BaysScreen({ onOpenPlanning }) {
  const [tab, setTab] = React.useState('ponts');
  return (
    <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
      <PageHeading title="Atelier" description="Pilotage des ponts, affectations mécaniciens et charge du jour.">
        <StatStrip items={[
          { label: 'Occupation', value: '83 %' },
          { label: 'RDV du jour', value: '11' },
          { label: 'Mécaniciens actifs', value: '3' },
          { label: 'Conflits', value: '1', tone: 'error' },
        ]} />
      </PageHeading>

      <div style={{ display: 'flex', alignItems: 'center', gap: 20, borderBottom: '1px solid var(--pk-border)' }}>
        {TABS.map((t) => {
          const on = t.id === tab;
          return (
            <button key={t.id} type="button" onClick={() => setTab(t.id)} style={{
              display: 'flex', alignItems: 'center', gap: 7, padding: '10px 2px', background: 'transparent',
              border: 'none', borderBottom: on ? '2px solid var(--pk-ink)' : '2px solid transparent',
              cursor: 'pointer', fontFamily: 'inherit', fontSize: 14, fontWeight: on ? 600 : 400,
              color: on ? 'var(--pk-ink)' : 'var(--pk-ink-quiet)',
            }}>
              <i className={t.icon} style={{ fontSize: 16 }} />{t.label}
            </button>
          );
        })}
        <div style={{ flex: 1 }} />
        <button type="button" onClick={onOpenPlanning} style={{ background: 'transparent', border: 'none', cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Voir le planning →</button>
      </div>

      {tab === 'ponts' ? (
        <div style={{ flex: 1, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gridTemplateRows: '1fr 1fr', gap: 12, minHeight: 0 }}>
          {window.BAY_CONTROL.map((b) => <BayControlCard key={b.name} {...b} />)}
        </div>
      ) : (
        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
          <div style={{ maxWidth: 420, textAlign: 'center', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <i className={TABS.find((t) => t.id === tab).icon} style={{ fontSize: 26, color: 'var(--pk-ink-muted)' }} />
            <div style={{ fontSize: 15, fontWeight: 600 }}>{TABS.find((t) => t.id === tab).label}</div>
            <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>
              Cet onglet existe dans l’app et n’est pas dessiné dans le prototype de refonte. Laissé vide volontairement plutôt que réinventé — voir le README du kit.
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

Object.assign(window, { BaysScreen });
