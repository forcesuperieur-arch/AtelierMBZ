import React from 'react';

export function FilterPill({ label, count, selected = false, dashed = false, tone = 'neutral', icon, onClick, style, ...rest }) {
  const toneSkin = tone === 'warning'
    ? { background: 'var(--pk-warning-surface)', border: '1px solid var(--pk-warning-line)', color: 'var(--pk-warning-ink)' }
    : selected
      ? { background: '#000', border: '1px solid #000', color: '#fff' }
      : { background: 'transparent', border: (dashed ? '1px dashed ' : '1px solid ') + 'var(--pk-border-control)', color: 'var(--pk-ink)' };
  return (
    <button type="button" onClick={onClick} {...rest} style={{
      display: 'flex', alignItems: 'center', gap: 7, padding: '6px 12px',
      borderRadius: 'var(--pk-radius-pill)', fontFamily: 'var(--mb-font-montserrat)',
      fontSize: 12, fontWeight: selected || tone === 'warning' ? 600 : 400, lineHeight: 1.2,
      whiteSpace: 'nowrap', cursor: 'pointer', transition: 'background var(--pk-duration-state) var(--pk-easing)',
      ...toneSkin, ...style,
    }}>
      {icon ? <i className={icon} style={{ fontSize: 15 }} /> : null}
      {label}
      {count != null ? <span style={{ fontWeight: 700 }}>{count}</span> : null}
    </button>
  );
}
