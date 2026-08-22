import React from 'react';

/* Four bars on black, one per step. The customer comes twice a year: nothing
   can be learnt, so the step is also written out in words underneath. */
export function StepBar({ total = 4, current = 1, label, style, ...rest }) {
  return (
    <div {...rest} style={{ display: 'flex', flexDirection: 'column', gap: 12, fontFamily: 'var(--mb-font-montserrat)', ...style }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {Array.from({ length: total }).map((_, i) => (
          <span key={i} style={{ flex: 1, height: 4, background: i < current ? 'var(--pk-accent)' : '#4a4a4a' }} />
        ))}
      </div>
      {label ? <span style={{ fontSize: 12, color: '#a5a5a5' }}>{label}</span> : null}
    </div>
  );
}
