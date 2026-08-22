import React from 'react';

/* The work panel. Reception, hand-back and appointment detail are moments of
   an appointment, not pages: they open to the right of the planning, which
   stays readable behind. Black header, yellow left edge, sticky footer with
   the one action that closes the moment. */
export function SidePanel({ icon, title, subtitle, children, footer, onClose, width = 'var(--pk-panel-width)', style, ...rest }) {
  return (
    <aside {...rest} style={{
      width, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '2px solid var(--pk-accent)',
      display: 'flex', flexDirection: 'column', overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ padding: '13px 18px', background: '#000', color: '#f6f6f6', display: 'flex', alignItems: 'center', gap: 10 }}>
        {icon ? <i className={icon} style={{ fontSize: 18, color: 'var(--pk-accent)' }} /> : null}
        <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>{title}</span>
          {subtitle ? <span style={{ fontSize: 12, color: '#d4d4d4' }}>{subtitle}</span> : null}
        </div>
        <div style={{ flex: 1 }} />
        <button type="button" aria-label="Fermer le panneau" onClick={onClose} style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: '#a5a5a5', display: 'flex' }}>
          <i className="ri-close-line" style={{ fontSize: 20 }} />
        </button>
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: 'auto', display: 'flex', flexDirection: 'column' }}>{children}</div>
      {footer ? (
        <div style={{ padding: '14px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>{footer}</div>
      ) : null}
    </aside>
  );
}

/* A block inside the panel: overline + content, separated by a hairline. */
export function PanelSection({ label, children, aside, style, ...rest }) {
  return (
    <div {...rest} style={{
      padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)',
      display: 'flex', flexDirection: 'column', gap: 9, ...style,
    }}>
      {label ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{label}</span>
          <div style={{ flex: 1 }} />
          {aside ? <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{aside}</span> : null}
        </div>
      ) : null}
      {children}
    </div>
  );
}
