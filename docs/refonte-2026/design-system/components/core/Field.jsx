import React from 'react';

/* Label above the field, never a placeholder standing in for it. The field
   height is a min-height so enlarged text is not clipped: 52px on the
   customer front, 38px in a dense workshop panel. A filled-and-focused
   field carries a black border; an untouched one a grey control border. */
export function Field({ label, value, placeholder, hint, dense = false, focused = false, error, endIcon, style, ...rest }) {
  return (
    <div {...rest} style={{ display: 'flex', flexDirection: 'column', gap: dense ? 4 : 6, fontFamily: 'var(--mb-font-montserrat)', ...style }}>
      {label ? (
        <span style={dense
          ? { fontSize: 12, color: 'var(--pk-ink-quiet)' }
          : { fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{label}</span>
      ) : null}
      <div style={{
        minHeight: dense ? 38 : 52, display: 'flex', alignItems: 'center', gap: 8,
        padding: dense ? '0 11px' : '0 15px', background: 'var(--pk-surface-raised)',
        border: '1px solid ' + (error ? 'var(--pk-error-line)' : focused ? 'var(--pk-border-strong)' : 'var(--pk-border-control)'),
        borderRadius: dense ? 'var(--pk-radius-card)' : 0,
        fontSize: dense ? 14 : 16, color: value ? 'var(--pk-ink)' : 'var(--pk-ink-muted)',
      }}>
        {value || placeholder}
        {endIcon ? <i className={endIcon} style={{ fontSize: 16, color: 'var(--pk-ink-muted)', marginLeft: 'auto' }} /> : null}
      </div>
      {error ? (
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: 'var(--pk-error-ink)' }}>
          <i className="ri-error-warning-line" style={{ fontSize: 15 }} />{error}
        </span>
      ) : hint ? <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>{hint}</span> : null}
    </div>
  );
}
