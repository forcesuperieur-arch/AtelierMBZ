import React from 'react';

const STATES = {
  received: { surface: 'var(--pk-success-surface)', line: 'var(--pk-success-line)', ink: 'var(--pk-success-ink)', cap: true },
  running: { surface: 'var(--pk-success-surface)', line: 'var(--pk-success-line)', ink: 'var(--pk-success-ink)', cap: true },
  ready: { surface: 'var(--pk-surface-raised)', line: 'var(--pk-success-line)', ink: 'var(--pk-success-ink)', cap: true },
  confirmed: { surface: 'var(--pk-info-surface)', line: 'var(--pk-info-line)', ink: 'var(--pk-info-ink)', cap: true },
  waiting: { surface: 'var(--pk-warning-surface)', line: 'var(--pk-warning-line)', ink: 'var(--pk-warning-ink)', cap: true },
  open: { surface: 'var(--pk-warning-surface)', line: 'var(--pk-accent)', ink: 'var(--pk-warning-ink)', frame: 2 },
  conflict: { surface: 'var(--pk-error-surface)', line: 'var(--pk-error-line)', ink: 'var(--pk-error-ink)', cap: true },
  unassigned: { surface: 'transparent', line: 'var(--pk-border-control)', ink: 'var(--pk-ink-muted)', dashed: true },
  done: { surface: 'var(--pk-neutral-surface)', line: 'var(--pk-border)', ink: 'var(--pk-ink-quiet)' },
};

/* One appointment in the grid. The state is read from the coloured edge, the
   written status line and the icon together. Placement is by grid column
   (bay) and row span (duration). */
export function AppointmentBlock({ state = 'confirmed', statusLabel, icon, vehicle, detail, detailTone, note, column, row, span = 2, onClick, style, ...rest }) {
  const s = STATES[state] || STATES.confirmed;
  const border = s.frame ? '2px solid ' + s.line : (s.dashed ? '1px dashed ' + s.line : '1px solid ' + s.line);
  return (
    <div role={onClick ? 'button' : undefined} onClick={onClick} {...rest} style={{
      gridColumn: column, gridRow: row ? row + ' / span ' + span : undefined,
      margin: 3, padding: '6px 8px', background: s.surface, border,
      borderLeft: s.cap ? '3px solid ' + s.line : border,
      borderRadius: 'var(--pk-radius-block)', display: 'flex', flexDirection: 'column', gap: 2,
      overflow: 'hidden', cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <span style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: s.ink }}>
        {icon ? <i className={icon} style={{ fontSize: 13 }} /> : null}{statusLabel}
      </span>
      <span style={{ fontSize: 12, fontWeight: 600 }}>{vehicle}</span>
      {detail ? <span style={{ fontSize: 11, color: detailTone === 'error' ? 'var(--pk-error-ink)' : detailTone === 'warning' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-quiet)' }}>{detail}</span> : null}
      {note ? <><div style={{ flex: 1 }} /><span style={{ fontSize: 11, fontWeight: 600, color: s.ink }}>{note}</span></> : null}
    </div>
  );
}
