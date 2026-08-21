/* PermissionCallout — maquette 29g.
 *
 * A refusal that only forbids leaves nobody to ask, and the user will phone the
 * boss anyway. So the template states the CEILING ("vous pouvez accorder
 * jusqu'à 15 %"), names who decides above it, and opens both roads: ask, or
 * come back inside the limit.
 *
 * The note says what asking sets in motion — without it, nobody knows whether
 * something has just left their hands.
 *
 * Warning tint, not error: nothing broke, the limit is working as designed.
 * No decorative illustration, no centring. React only, no other dependency.
 */
(function () {
  const NS = (window.PaddockDesignSystem_8059f4 = window.PaddockDesignSystem_8059f4 || {});

  const ROOT = {
    display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left',
    background: 'var(--pk-warning-surface)', border: '1px solid var(--pk-warning-line)',
    borderRadius: 'var(--pk-radius-card)',
  };
  const HEAD = { display: 'flex', alignItems: 'center', gap: 10 };
  const ICON = { fontSize: 20, flexShrink: 0, color: 'var(--pk-warning-line)' };
  const TITLE = { fontSize: 15, fontWeight: 600, color: 'var(--pk-warning-ink)' };
  const TEXT = { margin: 0, maxWidth: '62ch', fontSize: 13, lineHeight: 1.5, color: 'var(--pk-warning-ink)' };
  const ACTIONS = { display: 'flex', flexWrap: 'wrap', gap: 'var(--pk-target-gap)', marginTop: 4 };
  const NOTE = { margin: 0, maxWidth: '62ch', fontSize: 12, color: 'var(--pk-ink-quiet)' };

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

  function PermissionCallout({
    title = 'Cette action dépasse vos droits',
    description = '',
    requestLabel = '',
    complyLabel = '',
    note = '',
    onRequest,
    onComply,
    style,
    ...rest
  }) {
    return (
      <div role="alert" {...rest} style={{ ...ROOT, ...style }}>
        <div style={HEAD}>
          <i className="ri-shield-keyhole-line" aria-hidden="true" style={ICON} />
          <div style={TITLE}>{title}</div>
        </div>

        {description ? <p style={TEXT}>{description}</p> : null}

        {requestLabel || complyLabel ? (
          <div style={ACTIONS}>
            {requestLabel ? <StateAction tone="accent" onClick={onRequest}>{requestLabel}</StateAction> : null}
            {complyLabel ? <StateAction onClick={onComply}>{complyLabel}</StateAction> : null}
          </div>
        ) : null}

        {note ? <p style={NOTE}>{note}</p> : null}
      </div>
    );
  }

  NS.PermissionCallout = PermissionCallout;
})();
