import React from 'react';

const TONES = {
  neutral: 'var(--pk-ink-muted)',
  success: 'var(--pk-success-ink)',
  warning: 'var(--pk-warning-line)',
  error: 'var(--pk-error-ink)',
  info: 'var(--pk-info-ink)',
};

export function StatusBadge({ children, tone = 'neutral', icon, style, ...rest }) {
  return (
    <span {...rest} style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      fontFamily: 'var(--mb-font-montserrat)', fontSize: 11, fontWeight: 600,
      textTransform: 'uppercase', letterSpacing: '0.06em', color: TONES[tone] || TONES.neutral,
      ...style,
    }}>
      {icon ? <i className={icon} style={{ fontSize: 14 }} /> : null}
      {children}
    </span>
  );
}

export function Counter({ children, tone = 'neutral', style, ...rest }) {
  const skin = tone === 'accent' ? { background: 'var(--pk-accent)', color: '#000' }
    : tone === 'error' ? { background: 'var(--pk-error-line)', color: '#fff' }
      : { background: '#000', color: '#fff' };
  return (
    <span {...rest} style={{
      minWidth: 20, height: 20, padding: '0 6px', borderRadius: 'var(--pk-radius-pill)',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      fontFamily: 'var(--mb-font-montserrat)', fontSize: 11, fontWeight: 700,
      ...skin, ...style,
    }}>{children}</span>
  );
}
