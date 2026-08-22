import React from 'react';

const DOT = { done: 'var(--pk-success-ink)', current: 'var(--pk-accent)', pending: 'transparent' };

/* Where is my motorcycle. One line per event, newest last, the pending step
   left hollow. No date maths: the workshop writes the hour it happened. */
export function StatusTimeline({ steps = [], style, ...rest }) {
  return (
    <div {...rest} style={{ display: 'flex', flexDirection: 'column', fontFamily: 'var(--mb-font-montserrat)', ...style }}>
      {steps.map((s, i) => {
        const last = i === steps.length - 1;
        const pending = s.state === 'pending';
        return (
          <div key={s.title} style={{ display: 'flex', gap: 14 }}>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 'none' }}>
              <span style={{
                width: 12, height: 12, borderRadius: 'var(--pk-radius-pill)', display: 'block',
                background: DOT[s.state] || DOT.pending,
                border: pending ? '2px solid #a5a5a5' : 'none',
              }} />
              {last ? null : <span style={{ flex: 1, width: 2, background: 'var(--pk-border)' }} />}
            </div>
            <div style={{ paddingBottom: last ? 0 : 11 }}>
              <div style={{ fontSize: 15, fontWeight: 600, color: pending ? 'var(--pk-ink-muted)' : 'var(--pk-ink)' }}>{s.title}</div>
              {s.detail ? <div style={{ fontSize: 14, color: pending ? 'var(--pk-ink-muted)' : 'var(--pk-ink-quiet)', marginTop: 2 }}>{s.detail}</div> : null}
            </div>
          </div>
        );
      })}
    </div>
  );
}
