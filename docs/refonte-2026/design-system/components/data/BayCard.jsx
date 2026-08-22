import React from 'react';

/* A workshop bay. Occupied bays carry a 3px yellow cap and a white surface;
   a free bay is a dashed outline — an empty bay must read as empty at a
   glance, not as a card with no content. */
export function BayCard({ name, state = 'occupied', vehicle, customer, note, style, ...rest }) {
  const free = state === 'free';
  const down = state === 'down';
  return (
    <div {...rest} style={{
      border: free ? '1px dashed var(--pk-border-control)' : '1px solid var(--pk-border)',
      borderTop: free ? '1px dashed var(--pk-border-control)' : '3px solid ' + (down ? 'var(--pk-error-line)' : 'var(--pk-accent)'),
      borderRadius: 'var(--pk-radius-tile)', padding: '10px 12px', background: free ? 'transparent' : 'var(--pk-surface-raised)',
      display: 'flex', flexDirection: 'column', gap: 6,
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
        <span style={{ fontSize: 14, fontWeight: 700 }}>{name}</span>
        <span style={{
          fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em',
          color: free ? 'var(--pk-success-ink)' : down ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)',
        }}>{free ? 'Libre' : down ? 'Hors service' : 'Occupé'}</span>
      </div>
      <div style={{ fontSize: 13, fontWeight: free ? 400 : 600, color: free ? 'var(--pk-ink-quiet)' : 'inherit' }}>{vehicle || 'Disponible'}</div>
      <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{customer || '—'}</div>
      {note ? <div style={{ fontSize: 11, color: 'var(--pk-ink-muted)', borderTop: '1px solid var(--pk-border-quiet)', paddingTop: 6 }}>{note}</div> : null}
    </div>
  );
}
