import React from 'react';

const HEIGHT = { small: 41, medium: 52, large: 60 };
const FONT = { small: 13, medium: 15, large: 16 };
const PAD = { small: '0 16px', medium: '0 22px', large: '0 26px' };

function skin(variant, tone) {
  if (variant === 'primary') {
    if (tone === 'accent') return { background: 'var(--pk-accent)', color: '#000', border: '1px solid transparent' };
    if (tone === 'error') return { background: 'var(--pk-error-line)', color: '#fff', border: '1px solid transparent' };
    return { background: '#000', color: '#fff', border: '1px solid transparent' };
  }
  if (variant === 'secondary') {
    if (tone === 'accent') return { background: 'transparent', color: 'var(--pk-accent-ink)', border: '1px solid var(--pk-accent)' };
    if (tone === 'error') return { background: 'transparent', color: 'var(--pk-error-ink)', border: '1px solid var(--pk-error-line)' };
    return { background: 'transparent', color: 'var(--pk-ink)', border: '1px solid var(--pk-border-strong)' };
  }
  return { background: 'transparent', color: tone === 'error' ? 'var(--pk-error-ink)' : 'var(--pk-link)', border: '1px solid transparent' };
}

export function Button({
  children, variant = 'primary', tone = 'neutral', size = 'medium', shape = 'rounded',
  fullWidth = false, disabled = false, startIcon, endIcon, type = 'button', onClick, style, ...rest
}) {
  const s = skin(variant, tone);
  return (
    <button
      type={type} disabled={disabled} onClick={onClick} {...rest}
      style={{
        minHeight: HEIGHT[size] || HEIGHT.medium, padding: PAD[size] || PAD.medium,
        display: fullWidth ? 'flex' : 'inline-flex', width: fullWidth ? '100%' : undefined,
        alignItems: 'center', justifyContent: 'center', gap: 8,
        fontFamily: 'var(--mb-font-montserrat)', fontSize: FONT[size] || FONT.medium,
        fontWeight: variant === 'primary' ? 700 : 600, lineHeight: 1.2,
        borderRadius: shape === 'rounded' ? 'var(--pk-radius-pill)' : 'var(--pk-radius-card)',
        whiteSpace: 'nowrap', cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.45 : 1,
        transition: 'background var(--pk-duration-state) var(--pk-easing), color var(--pk-duration-state) var(--pk-easing)',
        ...s, ...style,
      }}>
      {startIcon ? <i className={startIcon} style={{ fontSize: FONT[size] + 4 }} /> : null}
      {children}
      {endIcon ? <i className={endIcon} style={{ fontSize: FONT[size] + 4 }} /> : null}
    </button>
  );
}
