import React from 'react';

const TONES = {
  info: { surface: 'var(--pk-info-surface)', line: 'var(--pk-info-line)', ink: 'var(--pk-info-ink)', icon: 'ri-information-line' },
  success: { surface: 'var(--pk-success-surface)', line: 'var(--pk-success-line)', ink: 'var(--pk-success-ink)', icon: 'ri-checkbox-circle-line' },
  warning: { surface: 'var(--pk-warning-surface)', line: 'var(--pk-warning-line)', ink: 'var(--pk-warning-ink)', icon: 'ri-alert-line' },
  error: { surface: 'var(--pk-error-surface)', line: 'var(--pk-error-line)', ink: 'var(--pk-error-ink)', icon: 'ri-error-warning-line' },
  accent: { surface: 'var(--pk-accent-soft)', line: 'var(--pk-accent)', ink: 'var(--pk-accent-ink)', icon: 'ri-lightbulb-line' },
};

/* A persistent explanation attached to the thing it concerns. Flat tinted
   surface, 1px line in the tone colour, glyph + text — never colour alone. */
export function Callout({ children, tone = 'info', icon, title, edge = false, style, ...rest }) {
  const t = TONES[tone] || TONES.info;
  const frame = edge
    ? { background: 'var(--pk-surface)', borderLeft: '3px solid ' + t.line }
    : { background: t.surface, border: '1px solid ' + t.line };
  return (
    <div {...rest} style={{
      display: 'flex', gap: 10, padding: '11px 14px', borderRadius: edge ? 0 : 'var(--pk-radius-card)',
      fontFamily: 'var(--mb-font-montserrat)', fontSize: 13, lineHeight: 1.5,
      color: edge ? 'var(--pk-ink-quiet)' : t.ink, ...frame, ...style,
    }}>
      <i className={icon || t.icon} style={{ fontSize: 17, flexShrink: 0, color: edge ? t.ink : 'inherit' }} />
      <div style={{ minWidth: 0 }}>
        {title ? <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 3, color: t.ink }}>{title}</div> : null}
        {children}
      </div>
    </div>
  );
}
