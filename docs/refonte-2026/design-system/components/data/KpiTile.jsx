import React from 'react';

/* A counter, and where it leads. Every Paddock tile is a link and the
   destination arrives already filtered on what the number said — a counter
   that leads nowhere is a dead counter. Hover shows the border, never an
   underline: the number must stay a number. */
export function KpiTile({ label, value, unit, note, ratio, tone = 'neutral', onClick, style, ...rest }) {
  const alert = tone === 'error';
  const warn = tone === 'warning';
  return (
    <div role={onClick ? 'button' : undefined} tabIndex={onClick ? 0 : undefined} onClick={onClick} {...rest} style={{
      background: warn ? 'var(--pk-warning-surface)' : 'var(--pk-surface)',
      border: '1px solid ' + (alert ? 'var(--pk-error-line)' : warn ? 'var(--pk-accent)' : 'var(--pk-border)'),
      borderRadius: 'var(--pk-radius-card)', padding: '14px 16px',
      display: 'flex', flexDirection: 'column', gap: 8, cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)',
      transition: 'border-color var(--pk-duration-state) var(--pk-easing)', ...style,
    }}>
      <div style={{ fontSize: 11, fontWeight: 600, letterSpacing: '0.08em', textTransform: 'uppercase', color: warn ? 'var(--pk-warning-ink)' : 'var(--pk-ink-muted)' }}>{label}</div>
      <div style={{ fontSize: 32, fontWeight: 700, lineHeight: 1, color: alert ? 'var(--pk-error-ink)' : warn ? 'var(--pk-warning-ink)' : 'inherit' }}>
        {value}
        {unit ? <span style={{ fontSize: 18, fontWeight: 500, color: 'var(--pk-ink-muted)' }}>{unit}</span> : null}
      </div>
      {ratio != null ? (
        <div style={{ height: 4, background: 'var(--pk-border-quiet)', borderRadius: 'var(--pk-radius-pill)', overflow: 'hidden' }}>
          <div style={{ width: Math.round(ratio * 100) + '%', height: '100%', background: ratio > 0.8 ? 'var(--pk-accent)' : '#000' }} />
        </div>
      ) : <div style={{ height: 4 }} />}
      {note ? <div style={{ fontSize: 12, fontWeight: alert ? 600 : 400, color: alert ? 'var(--pk-error-ink)' : warn ? 'var(--pk-warning-ink)' : 'var(--pk-ink-quiet)', display: 'flex', alignItems: 'center', gap: 5 }}>
        {alert ? <i className="ri-error-warning-line" style={{ fontSize: 14 }} /> : null}{note}
      </div> : null}
    </div>
  );
}
