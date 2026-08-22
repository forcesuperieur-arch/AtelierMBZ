import React from 'react';

/* The planning is the workstation: hours down the left, one column per bay,
   appointments placed as blocks. The grid never animates on a day change —
   it is read, not watched. */
export function PlanningGrid({ hours = [], bays = [], children, style, ...rest }) {
  const template = '54px repeat(' + bays.length + ', 1fr)';
  return (
    <div {...rest} style={{
      background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)',
      display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0, flex: 1,
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'grid', gridTemplateColumns: template, borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
        <div style={{ padding: '9px 6px', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-muted)', borderRight: '1px solid var(--pk-border-quiet)' }}>Heure</div>
        {bays.map((b, i) => (
          <div key={b.name} style={{ padding: '9px 10px', borderRight: i < bays.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none' }}>
            <div style={{ fontSize: 12, fontWeight: 700 }}>{b.name}</div>
            <div style={{ fontSize: 11, color: b.tone === 'success' ? 'var(--pk-success-ink)' : 'var(--pk-ink-quiet)' }}>{b.assignee}</div>
          </div>
        ))}
      </div>
      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: template, gridTemplateRows: 'repeat(' + hours.length + ', 1fr)', minHeight: 0 }}>
        {hours.map((h, i) => (
          <div key={h} style={{
            gridColumn: 1, gridRow: i + 1, borderRight: '1px solid var(--pk-border-quiet)',
            borderBottom: i < hours.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none',
            padding: '3px 6px', fontSize: 11, color: 'var(--pk-ink-muted)',
          }}>{h}</div>
        ))}
        {children}
      </div>
    </div>
  );
}
