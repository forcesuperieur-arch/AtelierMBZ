import React from 'react';

/* Four measures on one line, in a single bordered strip with hairline
   dividers. Used when the page's subject is the list underneath: the numbers
   frame it, they are not the content. Distinct from KpiTile, which is a card
   and a link. */
export function StatStrip({ items = [], style, ...rest }) {
  return (
    <div {...rest} style={{
      display: 'grid', gridTemplateColumns: 'repeat(' + items.length + ', minmax(0, 1fr))',
      background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      {items.map((it, i) => (
        <div key={it.label} style={{
          padding: '10px 20px', borderRight: i < items.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none',
          display: 'flex', flexDirection: 'column', gap: 3,
        }}>
          <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: it.tone === 'error' ? 'var(--pk-error-ink)' : 'var(--pk-ink-muted)' }}>{it.label}</span>
          <span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: it.tone === 'error' ? 'var(--pk-error-ink)' : 'inherit' }}>
            {it.value}
            {it.suffix ? <span style={{ fontSize: 12, fontWeight: 500, color: 'var(--pk-ink-muted)' }}> · {it.suffix}</span> : null}
          </span>
        </div>
      ))}
    </div>
  );
}
