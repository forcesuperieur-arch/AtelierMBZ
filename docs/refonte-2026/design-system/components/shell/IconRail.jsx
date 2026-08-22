import React from 'react';

/* The 64px icon rail. Fixed width, no hover expansion: the icons never move.
   A section the workshop does not use leaves the rail — it never becomes a
   greyed entry. */
export function IconRail({ items = [], active, onSelect, logo = null, footer, user, style, ...rest }) {
  return (
    <nav {...rest} style={{
      width: 'var(--pk-rail-width)', flexShrink: 0, background: 'var(--pk-surface)',
      borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column',
      alignItems: 'center', padding: '12px 0', gap: 2, ...style,
    }}>
      {logo ? <img src={logo} alt="Paddock" style={{ width: 40, height: 40, display: 'block', flex: 'none', marginBottom: 14 }} /> : null}
      {items.map((it) => {
        const on = it.id === active;
        return (
          <button key={it.id} type="button" title={it.label} aria-label={it.label} aria-current={on ? 'page' : undefined}
            onClick={onSelect ? () => onSelect(it.id) : undefined}
            style={{
              position: 'relative', width: 'var(--pk-rail-item)', height: 'var(--pk-rail-item)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              background: on ? 'var(--pk-accent)' : 'transparent', color: on ? '#000' : 'var(--pk-ink-quiet)',
              borderRadius: 'var(--pk-radius-card)', border: 'none', cursor: 'pointer',
              transition: 'background var(--pk-duration-state) var(--pk-easing)',
            }}>
            <i className={it.icon} style={{ fontSize: 20 }} />
            {it.badge ? (
              <span style={{
                position: 'absolute', top: 6, right: 5, minWidth: 16, height: 16, padding: '0 4px',
                background: 'var(--pk-error-line)', color: '#fff', fontSize: 10, fontWeight: 700,
                borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>{it.badge}</span>
            ) : null}
          </button>
        );
      })}
      <div style={{ flex: 1 }} />
      {footer}
      {user ? (
        <div style={{
          width: 32, height: 32, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-info-line)',
          color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: 12, fontWeight: 600, marginTop: 8,
        }}>{user}</div>
      ) : null}
    </nav>
  );
}
