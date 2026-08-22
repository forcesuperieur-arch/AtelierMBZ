import React from 'react';

/* Page title, 4px yellow underline, one line saying what the page answers.
   The yellow bar is the only decoration a page header gets. */
export function PageHeading({ title, description, children, style, ...rest }) {
  return (
    <div {...rest} style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16, ...style }}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4, fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)' }}>
        <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>{title}</div>
        <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
        {description ? <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>{description}</div> : null}
      </div>
      {children}
    </div>
  );
}

/* Pill tab set used on Stat. Active state is carried by fill and colour,
   never by weight — a weight change shifts the widths of the whole set. */
export function PillTabs({ items = [], value, onChange, style, ...rest }) {
  return (
    <div {...rest} style={{
      display: 'flex', gap: 4, padding: 3, background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-pill)', ...style,
    }}>
      {items.map((it) => {
        const on = it.value === value;
        return (
          <button key={it.value} type="button" onClick={onChange ? () => onChange(it.value) : undefined} style={{
            padding: '7px 16px', borderRadius: 'var(--pk-radius-pill)', border: 'none', cursor: 'pointer',
            background: on ? '#000' : 'transparent', color: on ? '#fff' : 'var(--pk-ink-quiet)',
            fontFamily: 'var(--mb-font-montserrat)', fontSize: 13, fontWeight: 500,
            display: 'flex', alignItems: 'center', gap: 6,
          }}>
            {it.label}
            {it.count != null ? (
              <span style={{
                minWidth: 18, height: 18, padding: '0 5px', background: 'var(--pk-accent)', color: '#000',
                fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>{it.count}</span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}

/* Underlined tab set used inside a working screen (Stat › Explorer). */
export function UnderlineTabs({ items = [], value, onChange, children, style, ...rest }) {
  return (
    <div {...rest} style={{
      display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', background: 'var(--pk-surface)',
      borderBottom: '1px solid var(--pk-border)', fontFamily: 'var(--mb-font-montserrat)', ...style,
    }}>
      {items.map((it) => {
        const on = it.value === value;
        return (
          <button key={it.value} type="button" onClick={onChange ? () => onChange(it.value) : undefined} style={{
            padding: '13px 2px', background: 'transparent', border: 'none', cursor: 'pointer',
            borderBottom: on ? '2px solid ' + 'var(--pk-ink)' : '2px solid transparent',
            fontSize: 14, fontWeight: on ? 600 : 400, color: on ? 'var(--pk-ink)' : 'var(--pk-ink-quiet)',
          }}>{it.label}</button>
        );
      })}
      <div style={{ flex: 1 }} />
      {children}
    </div>
  );
}
