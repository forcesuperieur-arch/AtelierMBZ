import React from 'react';

/* « À traiter » — the queue that follows the user across every screen.
   Expanded (320px) where there is room, collapsed to a 52px counter rail
   otherwise. It is never absent: the file is the job. */
export function QueuePanel({ items = [], count, collapsed = false, onToggle, footer = 'Voir toute la file →', style, ...rest }) {
  if (collapsed) {
    return (
      <aside {...rest} style={{
        width: 52, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)',
        display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '12px 0', gap: 14,
        fontFamily: 'var(--mb-font-montserrat)', ...style,
      }}>
        <button type="button" onClick={onToggle} aria-label="Déplier la file à traiter" style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--pk-ink-quiet)', display: 'flex' }}>
          <i className="ri-sidebar-unfold-line" style={{ fontSize: 18 }} />
        </button>
        <div style={{ width: 28, height: 1, background: 'var(--pk-border-quiet)' }} />
        {items.map((it) => (
          <div key={it.title} title={it.kind} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
            <i className={it.icon} style={{ fontSize: 18, color: 'var(--pk-ink-quiet)' }} />
            <span style={{
              minWidth: 18, height: 18, padding: '0 5px', borderRadius: 'var(--pk-radius-pill)',
              background: it.level === 'critical' ? 'var(--pk-error-line)' : 'var(--pk-neutral-surface)',
              color: it.level === 'critical' ? '#fff' : 'var(--pk-ink)',
              fontSize: 10, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>{it.count}</span>
          </div>
        ))}
        <div style={{ writingMode: 'vertical-rl', marginTop: 6, fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>À traiter · {count}</div>
      </aside>
    );
  }
  return (
    <aside {...rest} style={{
      width: 320, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)',
      display: 'flex', flexDirection: 'column', overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '14px 16px', borderBottom: '1px solid var(--pk-border)' }}>
        <span style={{ fontSize: 15, fontWeight: 600 }}>À traiter</span>
        <span style={{ minWidth: 20, height: 20, padding: '0 6px', background: 'var(--pk-error-line)', color: '#fff', fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{count}</span>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={onToggle} aria-label="Replier la file à traiter" style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--pk-ink-muted)', display: 'flex' }}>
          <i className="ri-sidebar-fold-line" style={{ fontSize: 17 }} />
        </button>
      </div>
      {items.map((it) => (
        <div key={it.title} style={{
          padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)',
          borderLeft: '3px solid ' + (it.level === 'critical' ? 'var(--pk-error-line)' : it.level === 'watch' ? 'var(--pk-warning-line)' : 'var(--pk-border)'),
          display: 'flex', flexDirection: 'column', gap: 5,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <i className={it.icon} style={{ fontSize: 15, color: 'var(--pk-ink-quiet)' }} />
            <span style={{
              fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em',
              color: it.level === 'critical' ? 'var(--pk-error-ink)' : it.level === 'watch' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-muted)',
            }}>{it.kind}</span>
          </div>
          <div style={{ fontSize: 13, fontWeight: 600 }}>{it.title}</div>
          {it.detail ? <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{it.detail}</div> : null}
          {it.actions ? (
            <div style={{ display: 'flex', gap: 6, marginTop: 4 }}>
              {it.actions.map((a, i) => (
                <span key={a} style={{
                  padding: '4px 10px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12,
                  background: i === 0 ? 'var(--pk-accent)' : 'transparent', color: i === 0 ? '#000' : 'var(--pk-ink)',
                  border: i === 0 ? 'none' : '1px solid var(--pk-border-control)', fontWeight: i === 0 ? 600 : 400,
                }}>{a}</span>
              ))}
            </div>
          ) : null}
        </div>
      ))}
      <div style={{ flex: 1 }} />
      <div style={{ padding: '12px 16px', borderTop: '1px solid var(--pk-border)', fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>{footer}</div>
    </aside>
  );
}
