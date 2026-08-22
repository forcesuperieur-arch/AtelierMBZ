import React from 'react';

const EDGE = { critical: 'var(--pk-error-line)', watch: 'var(--pk-warning-line)', normal: 'var(--pk-border)' };
const INK = { critical: 'var(--pk-error-ink)', watch: 'var(--pk-warning-line)', normal: 'var(--pk-ink-muted)' };
const LABEL = { critical: 'Critique', watch: 'À surveiller', normal: 'Normal' };

/* One line of the "file à traiter" queue: what it is, since when, how bad,
   how many. The severity is carried by the left edge AND the written label —
   never by colour alone. */
export function QueueRow({ icon, title, detail, level = 'normal', count, statusLabel, onClick, style, ...rest }) {
  return (
    <div role={onClick ? 'button' : undefined} onClick={onClick} {...rest} style={{
      display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px',
      borderBottom: '1px solid var(--pk-border-quiet)', borderLeft: '3px solid ' + (EDGE[level] || EDGE.normal),
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', cursor: onClick ? 'pointer' : 'default', ...style,
    }}>
      {icon ? <i className={icon} style={{ fontSize: 18, color: 'var(--pk-ink-quiet)' }} /> : null}
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 600 }}>{title}</div>
        {detail ? <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{detail}</div> : null}
      </div>
      <span style={{ fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em', color: INK[level] }}>
        {statusLabel || LABEL[level]}
      </span>
      {count != null ? <span style={{ fontSize: 20, fontWeight: 700, minWidth: 24, textAlign: 'right' }}>{count}</span> : null}
      <i className="ri-arrow-right-s-line" style={{ fontSize: 18, color: 'var(--pk-ink-muted)' }} />
    </div>
  );
}
