/* FilterEmptyState — maquette 29b.
 *
 * An empty screen and a screen filtered to nothing look alike and do not mean
 * the same thing: here the rows EXIST, the filters hide them. So the template
 * says how many filters are on and, whenever the calling screen can compute it,
 * what one precise removal would bring back — with the figure. Without the
 * figure the user pulls filters at random.
 *
 * Solid border, not dashed: nothing is missing. React only, no other dependency.
 */

import React from 'react';

const ROOT = {
  display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left',
  background: 'var(--pk-surface)', border: '1px solid var(--pk-border)',
  borderRadius: 'var(--pk-radius-card)',
};
const HEAD = { display: 'flex', alignItems: 'center', gap: 10 };
const ICON = { fontSize: 20, flexShrink: 0, color: 'var(--pk-ink-muted)' };
const TITLE = { fontSize: 15, fontWeight: 600, color: 'var(--pk-ink)' };
const TEXT = { margin: 0, maxWidth: '62ch', fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' };
const ACTIONS = { display: 'flex', flexWrap: 'wrap', gap: 'var(--pk-target-gap)', marginTop: 4 };

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({ tone, onClick, children }) {
  const [ring, setRing] = React.useState(false);
  const accent = tone === 'accent';
  return (
    <button
      type="button"
      onClick={onClick}
      onFocus={(e) => setRing(e.currentTarget.matches(':focus-visible'))}
      onBlur={() => setRing(false)}
      style={{
        minHeight: 'var(--pk-target-desk)', padding: '0 16px',
        display: 'inline-flex', alignItems: 'center',
        borderRadius: 'var(--pk-radius-pill)',
        border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
        background: accent ? 'var(--pk-accent)' : 'transparent',
        color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
        fontFamily: 'inherit', fontSize: 13, fontWeight: 600, cursor: 'pointer',
        outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
        outlineOffset: 'var(--pk-focus-offset)',
      }}
    >
      {children}
    </button>
  );
}

export function FilterEmptyState({
  title = 'Aucun résultat ne correspond',
  filterCount = 0,
  suggestion = null,
  onRemove,
  onClear,
  style,
  ...rest
}) {
  const activeLine = filterCount > 1 ? filterCount + ' filtres sont actifs.' : filterCount + ' filtre est actif.';
  const gainLine = suggestion
    ? ' En retirant « ' + suggestion.filter + ' », ' + suggestion.count + ' ' + suggestion.noun
      + ' apparaîtrai' + (suggestion.count > 1 ? 'ent' : 't') + '.'
    : '';

  return (
    <div {...rest} style={{ ...ROOT, ...style }}>
      <div style={HEAD}>
        <i className="ri-filter-off-line" aria-hidden="true" style={ICON} />
        <div style={TITLE}>{title}</div>
      </div>

      <p style={TEXT}>{activeLine + gainLine}</p>

      <div style={ACTIONS}>
        {suggestion ? (
          <StateAction tone="accent" onClick={() => onRemove && onRemove(suggestion.filter)}>
            {'Retirer « ' + suggestion.filter + ' »'}
          </StateAction>
        ) : null}
        <StateAction onClick={onClear}>Tout effacer</StateAction>
      </div>
    </div>
  );
}
