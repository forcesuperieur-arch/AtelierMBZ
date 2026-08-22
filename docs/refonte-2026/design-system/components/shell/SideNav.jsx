import React from 'react';

/* The 224px navigation, grouped by trade (Pilotage / Atelier / Commerce).
   The 64px IconRail is this same nav collapsed — the two are one control with
   two states, not two competing models. A module the workshop does not use
   leaves the nav; it never becomes a greyed entry. */
export function SideNav({ groups = [], active, onSelect, workshop, onCollapse, logo, style, ...rest }) {
  return (
    <nav {...rest} style={{
      width: 224, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)',
      display: 'flex', flexDirection: 'column', padding: '12px 0', overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 12px 16px', padding: '8px 10px', background: '#000', borderRadius: 'var(--pk-radius-card)' }}>
        {logo ? <img src={logo} alt="Paddock" style={{ width: 32, height: 32, display: 'block', flex: 'none' }} /> : null}
        <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#fff', letterSpacing: '0.14em' }}>PADDOCK</span>
          {workshop ? <span style={{ fontSize: 11, color: '#d4d4d4' }}>{workshop}</span> : null}
        </div>
        <i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: '#a5a5a5', marginLeft: 'auto' }} />
      </div>

      {groups.map((g) => (
        <React.Fragment key={g.label}>
          <div style={{ padding: '14px 20px 6px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{g.label}</div>
          {g.items.map((it) => {
            const on = it.id === active;
            return (
              <button key={it.id} type="button" onClick={onSelect ? () => onSelect(it.id) : undefined} style={{
                display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px',
                background: on ? 'var(--pk-accent)' : 'transparent', color: on ? '#000' : 'var(--pk-ink)',
                border: 'none', borderRadius: 'var(--pk-radius-card)', cursor: 'pointer', textAlign: 'left',
                fontFamily: 'inherit', fontSize: 13, fontWeight: on ? 600 : 400,
                transition: 'background var(--pk-duration-state) var(--pk-easing)',
              }}>
                <i className={it.icon} style={{ fontSize: 17, color: on ? '#000' : 'var(--pk-ink-quiet)' }} />
                {it.label}
                {it.badge != null ? (
                  <span style={{
                    marginLeft: 'auto', minWidth: 20, height: 20, padding: '0 6px',
                    background: it.badgeTone === 'error' ? 'var(--pk-error-line)' : 'var(--pk-neutral-surface)',
                    color: it.badgeTone === 'error' ? '#fff' : 'var(--pk-ink)',
                    fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                  }}>{it.badge}</span>
                ) : null}
              </button>
            );
          })}
        </React.Fragment>
      ))}

      <div style={{ flex: 1 }} />
      <button type="button" onClick={onCollapse} style={{
        display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px',
        background: 'transparent', border: 'none', borderTop: '1px solid var(--pk-border-quiet)',
        cursor: 'pointer', fontFamily: 'inherit', fontSize: 13, color: 'var(--pk-ink)', textAlign: 'left',
      }}>
        <i className="ri-contract-left-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Replier le menu
      </button>
    </nav>
  );
}
