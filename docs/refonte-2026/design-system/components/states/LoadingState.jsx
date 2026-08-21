

import * as React from 'react';

/* 29c — on dessine la forme du tableau attendu, à la taille qu'il prendra.
 Rien ne tourne, rien ne clignote : un disque qui tourne n'apprend rien et
 la page saute quand les données arrivent. Les rangées s'éteignent vers le
 bas une fois pour toutes, sans animation. */

const SR_ONLY = {
position: 'absolute',
width: 1,
height: 1,
overflow: 'hidden',
clipPath: 'inset(50%)',
whiteSpace: 'nowrap',
};

/* Largeur de la première colonne, variée d'une rangée à l'autre : une pile de
 barres identiques ne ressemble à aucune liste réelle. */
const FIRST_COLUMN_FLEX = [1.6, 1.15, 1.85, 1.35];

const FADE_STEP = 0.16;
const FADE_FLOOR = 0.34;

function columnFlex(columnIndex, columnCount, rowIndex) {
if (columnIndex === 0) return FIRST_COLUMN_FLEX[rowIndex % FIRST_COLUMN_FLEX.length];
if (columnIndex === columnCount - 1) return 0.6;
return 1;
}

function range(count) {
return Array.from({ length: Math.max(1, count) }, (_, index) => index);
}

export function LoadingState({
title = 'Chargement en cours',
caption,
columns = 5,
rows = 6,
compact = false,
style,
...rest
}) {
const columnCount = Math.max(1, columns);
const columnIndexes = range(columnCount);
const rowIndexes = range(rows);

return (
  <div
    role="status"
    aria-busy="true"
    style={{
      position: 'relative',
      display: 'flex',
      flexDirection: 'column',
      gap: 1,
      background: 'var(--pk-border-quiet)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)',
      overflow: 'hidden',
      ...style,
    }}
    {...rest}
  >
    {caption ? (
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', background: 'var(--pk-surface)', fontSize: 14, fontWeight: 600, color: 'var(--pk-ink)' }}>
        {caption}
      </div>
    ) : null}

    {compact ? null : (
      <div style={{ display: 'flex', alignItems: 'center', gap: 16, padding: '12px 16px', background: 'var(--pk-surface-raised)' }} aria-hidden="true">
        {columnIndexes.map((columnIndex) => (
          <span
            key={`head-${columnIndex}`}
            style={{
              flex: columnIndex === 0 ? 1.6 : columnFlex(columnIndex, columnCount, 0),
              maxWidth: 90,
              height: 8,
              borderRadius: 'var(--pk-radius-block)',
              background: 'var(--pk-neutral-surface)',
            }}
          />
        ))}
      </div>
    )}

    {rowIndexes.map((rowIndex) => (
      <div
        key={`row-${rowIndex}`}
        style={{ display: 'flex', alignItems: 'center', gap: 16, padding: '13px 16px', background: 'var(--pk-surface)' }}
        aria-hidden="true"
      >
        {columnIndexes.map((columnIndex) => (
          <span
            key={`cell-${rowIndex}-${columnIndex}`}
            style={{
              flex: columnFlex(columnIndex, columnCount, rowIndex),
              height: 10,
              borderRadius: 'var(--pk-radius-block)',
              background: 'var(--pk-neutral-surface)',
              opacity: Math.max(FADE_FLOOR, 1 - rowIndex * FADE_STEP),
            }}
          />
        ))}
      </div>
    ))}

    <span style={SR_ONLY}>{title}</span>
  </div>
);
}
