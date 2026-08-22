import React from 'react';

/* 52px application header: context on the left, search and actions on the right. */
export function TopBar({ children, title, workshop, live, style, ...rest }) {
  return (
    <header {...rest} style={{
      height: 'var(--pk-header-height)', flexShrink: 0, background: 'var(--pk-surface)',
      borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center',
      gap: 14, padding: '0 20px', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      {title ? <span style={{ fontSize: 15, fontWeight: 600 }}>{title}</span> : null}
      {workshop ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, paddingRight: 16, borderRight: '1px solid var(--pk-border-quiet)' }}>
          <i className="ri-store-2-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
          <span style={{ fontSize: 13, fontWeight: 600 }}>{workshop}</span>
          <i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
        </div>
      ) : null}
      {live ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 500, color: 'var(--pk-ink-muted)' }}>
          <span style={{ width: 7, height: 7, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-success-line)' }} />
          {live}
        </div>
      ) : null}
      {children}
    </header>
  );
}

export function SearchField({ placeholder = 'Client, immat, n° d’OR…', shortcut = '⌘K', style, ...rest }) {
  return (
    <div {...rest} style={{
      display: 'flex', alignItems: 'center', gap: 8, height: 32, padding: '0 10px',
      border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)',
      color: 'var(--pk-ink-muted)', fontSize: 12, minWidth: 200, ...style,
    }}>
      <i className="ri-search-line" style={{ fontSize: 15 }} />
      {placeholder}
      {shortcut ? <span style={{ marginLeft: 'auto', fontSize: 10, border: '1px solid var(--pk-border)', borderRadius: 4, padding: '1px 4px' }}>{shortcut}</span> : null}
    </div>
  );
}

export function IconAction({ icon, badge, label, onClick, style, ...rest }) {
  return (
    <button type="button" aria-label={label} onClick={onClick} {...rest} style={{
      position: 'relative', width: 36, height: 36, display: 'flex', alignItems: 'center',
      justifyContent: 'center', background: 'transparent', border: 'none', cursor: 'pointer',
      color: 'var(--pk-ink)', ...style,
    }}>
      <i className={icon} style={{ fontSize: 18 }} />
      {badge ? (
        <span style={{
          position: 'absolute', top: 4, right: 3, minWidth: 16, height: 16, padding: '0 4px',
          background: 'var(--pk-error-line)', color: '#fff', fontSize: 10, fontWeight: 700,
          borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>{badge}</span>
      ) : null}
    </button>
  );
}
