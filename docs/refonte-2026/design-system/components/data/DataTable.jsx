import React from 'react';

/* Dense list of records. Header row on white, hairline separators, every
   other row white. Columns declare their own alignment and renderer; a
   selected-row or total row is a plain `tone` on the row object. */
export function DataTable({ columns = [], rows = [], rowKey, caption, footer, style, ...rest }) {
  const template = columns.map((c) => c.width || '1fr').join(' ');
  const cellBase = { fontSize: 13, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' };
  return (
    <div {...rest} style={{
      background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)',
      display: 'flex', flexDirection: 'column', overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)', ...style,
    }}>
      {caption ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>{caption}</span>
        </div>
      ) : null}
      <div style={{
        display: 'grid', gridTemplateColumns: template, alignItems: 'center', gap: 12,
        padding: '8px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)',
        fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)',
      }}>
        {columns.map((c) => <span key={c.key} style={{ textAlign: c.align || 'left' }}>{c.header}</span>)}
      </div>
      {rows.map((row, i) => (
        <div key={rowKey ? rowKey(row, i) : i} style={{
          display: 'grid', gridTemplateColumns: template, alignItems: 'center', gap: 12,
          padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)',
          background: row.tone === 'flagged' ? 'var(--pk-error-surface)' : i % 2 ? 'var(--pk-surface-raised)' : 'transparent',
        }}>
          {columns.map((c) => (
            <span key={c.key} style={{ ...cellBase, textAlign: c.align || 'left', fontWeight: c.strong ? 600 : 400, color: c.quiet ? 'var(--pk-ink-quiet)' : 'inherit' }}>
              {c.render ? c.render(row) : row[c.key]}
            </span>
          ))}
        </div>
      ))}
      {footer ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 16px', borderTop: '1px solid var(--pk-border)', marginTop: 'auto' }}>{footer}</div>
      ) : null}
    </div>
  );
}
