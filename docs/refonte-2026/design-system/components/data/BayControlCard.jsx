import React from 'react';

/* The bay as a control surface, not just a status: state, configuration
   (activation, mechanic attached) and the day's programme — without going
   through the admin screens. A deactivated bay leaves the occupancy rate and
   the planning, and the card says so. */
export function BayControlCard({ name, state = 'occupied', spec, mechanic, programme = [], note, style, ...rest }) {
  const free = state === 'free';
  const conflict = state === 'conflict';
  const maintenance = state === 'maintenance';
  const label = free ? 'Libre' : conflict ? 'Conflit' : maintenance ? 'Maintenance' : 'Occupé';
  const chip = free
    ? { background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)', color: 'var(--pk-success-ink)' }
    : conflict
      ? { background: 'var(--pk-error-surface)', border: '1px solid var(--pk-error-line)', color: 'var(--pk-error-ink)' }
      : maintenance
        ? { background: 'var(--pk-warning-surface)', border: '1px solid var(--pk-warning-line)', color: 'var(--pk-warning-ink)' }
        : { background: 'var(--pk-neutral-surface)', border: 'none', color: 'var(--pk-ink)' };
  return (
    <div {...rest} style={{
      background: maintenance ? 'var(--pk-canvas)' : free ? 'var(--pk-surface-raised)' : 'var(--pk-surface)',
      border: free ? '1px dashed var(--pk-border-control)' : '1px solid ' + (conflict ? 'var(--pk-error-line)' : 'var(--pk-border)'),
      borderTop: free || maintenance ? undefined : '3px solid ' + (conflict ? 'var(--pk-error-line)' : 'var(--pk-accent)'),
      borderRadius: 'var(--pk-radius-card)', padding: 14, display: 'flex', flexDirection: 'column', gap: 10,
      overflow: 'hidden', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ fontSize: 16, fontWeight: 700, color: maintenance ? 'var(--pk-ink-quiet)' : 'inherit' }}>{name}</span>
        <span style={{ display: 'flex', alignItems: 'center', gap: 4, padding: '3px 9px', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', ...chip }}>
          {conflict ? <i className="ri-error-warning-line" style={{ fontSize: 13 }} /> : maintenance ? <i className="ri-tools-fill" style={{ fontSize: 13 }} /> : null}
          {label}
        </span>
        <div style={{ flex: 1 }} />
        {spec ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{spec}</span> : null}
      </div>

      <div style={{
        padding: 10, background: maintenance ? 'var(--pk-surface)' : free ? 'var(--pk-surface)' : 'var(--pk-surface-raised)',
        border: '1px solid var(--pk-border-quiet)', borderRadius: 'var(--pk-radius-tile)',
        display: 'flex', flexDirection: 'column', gap: 7,
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: 'var(--pk-ink-muted)' }}>Configuration</span>
          <div style={{ flex: 1 }} />
          <span style={{
            padding: '4px 10px', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 600,
            background: maintenance ? 'var(--pk-accent)' : 'transparent', color: maintenance ? '#000' : 'var(--pk-ink)',
            border: maintenance ? 'none' : '1px solid var(--pk-border-control)',
          }}>{maintenance ? 'Activer' : 'Désactiver'}</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 32, padding: '0 10px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 12, color: mechanic ? 'inherit' : 'var(--pk-ink-quiet)' }}>
          <i className="ri-user-line" style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }} />
          {mechanic || 'Aucun mécanicien rattaché'}
          <i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)', marginLeft: 'auto' }} />
        </div>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
        <span style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: 'var(--pk-ink-muted)' }}>{maintenance ? 'Hors capacité' : 'Programme du jour'}</span>
        {programme.map((p) => (
          <div key={p.time + p.label} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: p.tone === 'error' ? 'var(--pk-error-ink)' : 'inherit' }}>
            <span style={{ fontWeight: 700, width: 34 }}>{p.time}</span>
            <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.label}</span>
            <span style={{
              fontWeight: 600,
              color: p.tone === 'error' ? 'var(--pk-error-ink)' : p.state === 'done' ? 'var(--pk-success-ink)' : p.state === 'running' ? 'var(--pk-link)' : 'var(--pk-ink-muted)',
            }}>{p.status}</span>
          </div>
        ))}
        {note ? <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{note}</div> : null}
      </div>
    </div>
  );
}
