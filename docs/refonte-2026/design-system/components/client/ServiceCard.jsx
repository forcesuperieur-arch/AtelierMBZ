import React from 'react';

/* A service, with its price, on the customer front. The price is announced
   before the slot is chosen — never after. Selected state is a 2px black
   frame; a recommendation is written, not implied. */
export function ServiceCard({ title, price, description, recommendation, selected = false, children, onClick, style, ...rest }) {
  return (
    <div role={onClick ? 'button' : undefined} onClick={onClick} {...rest} style={{
      background: 'var(--pk-surface-raised)', border: selected ? '2px solid #000' : '1px solid var(--pk-border)',
      padding: 16, display: 'flex', flexDirection: 'column', gap: 6, cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
        <span style={{ flex: 1, fontSize: 17, fontWeight: 600 }}>{title}</span>
        {price ? <span style={{ fontSize: selected ? 19 : 15, fontWeight: selected ? 700 : 600, color: selected ? 'inherit' : 'var(--pk-ink-quiet)' }}>{price}</span> : null}
      </div>
      {description ? <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>{description}</div> : null}
      {recommendation ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 13, fontWeight: 600, color: 'var(--pk-success-ink)', marginTop: 2 }}>
          <i className="ri-star-line" style={{ fontSize: 15 }} />{recommendation}
        </div>
      ) : null}
      {children}
    </div>
  );
}

/* Real slots the planning can actually hold. A full slot stays visible and
   says "complet" — hiding it would make the week look emptier than it is. */
export function SlotGrid({ days = [], selected, onSelect, style, ...rest }) {
  return (
    <div {...rest} style={{ display: 'flex', flexDirection: 'column', gap: 10, fontFamily: 'var(--mb-font-montserrat)', ...style }}>
      {days.map((d) => (
        <div key={d.label} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ width: 96, flex: 'none', fontSize: 15, fontWeight: 600, color: 'var(--pk-ink)' }}>{d.label}</span>
          {d.slots.map((s) => {
            const id = d.label + ' ' + s.time;
            const on = selected === id;
            const full = s.full;
            return (
              <button key={id} type="button" disabled={full} onClick={onSelect ? () => onSelect(id) : undefined} style={{
                flex: 1, minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: full ? 'var(--pk-neutral-surface)' : on ? '#000' : 'var(--pk-surface-raised)',
                border: full || !on ? '1px solid var(--pk-border)' : '1px solid #000',
                color: full ? '#a5a5a5' : on ? 'var(--pk-accent)' : 'var(--pk-ink)',
                fontSize: full ? 15 : 16, fontWeight: on ? 700 : full ? 400 : 600,
                cursor: full ? 'default' : 'pointer', fontFamily: 'inherit',
              }}>{full ? 'complet' : s.time}</button>
            );
          })}
        </div>
      ))}
    </div>
  );
}
