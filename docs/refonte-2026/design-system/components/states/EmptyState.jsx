/* EmptyState — maquette 29a.
 *
 * "Aucune donnée disponible" answers nobody. The question in front of an empty
 * list is "how does it fill up", so the description names the DOOR the data
 * comes through — a client is created when a booking is taken, not here — and
 * the two actions offer both roads, the fast one and the manual one.
 *
 * No decorative illustration, no centring: the block is read left to right
 * like every other block on the screen. React only, no other dependency.
 */

import React from 'react';

/* Dashed border: a place waiting to be filled, not a place that broke. */
const ROOT = {
  display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left',
  background: 'var(--pk-surface)', border: '1px dashed var(--pk-border-control)',
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

export function EmptyState({
  icon = 'ri-inbox-line',
  title = 'Rien à afficher ici',
  description = '',
  actionLabel = '',
  secondaryLabel = '',
  onAction,
  onSecondary,
  style,
  ...rest
}) {
  return (
    <div {...rest} style={{ ...ROOT, ...style }}>
      <div style={HEAD}>
        <i className={icon} aria-hidden="true" style={ICON} />
        <div style={TITLE}>{title}</div>
      </div>

      {description ? <p style={TEXT}>{description}</p> : null}

      {actionLabel || secondaryLabel ? (
        <div style={ACTIONS}>
          {actionLabel ? <StateAction tone="accent" onClick={onAction}>{actionLabel}</StateAction> : null}
          {secondaryLabel ? <StateAction onClick={onSecondary}>{secondaryLabel}</StateAction> : null}
        </div>
      ) : null}
    </div>
  );
}
