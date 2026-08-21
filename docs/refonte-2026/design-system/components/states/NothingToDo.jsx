/* NothingToDo — maquette 29h.
 *
 * "Un vide obtenu se félicite ; il ne se dessine pas comme un manque." An empty
 * queue is a RESULT, not a breakdown: hence the green trio instead of the grey
 * dashed box, the enumeration of what was cleared rather than of what is
 * missing, and the hour of the last item handled — without it, zero reads like
 * a load that failed.
 *
 * React only, no other dependency.
 */
(function () {
  const NS = (window.PaddockDesignSystem_8059f4 = window.PaddockDesignSystem_8059f4 || {});

  /* Surface + line + ink: the success tokens are always used as a trio. */
  const ROOT = {
    display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left',
    background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)',
    borderRadius: 'var(--pk-radius-card)',
  };
  const HEAD = { display: 'flex', alignItems: 'center', gap: 10 };
  const ICON = { fontSize: 20, flexShrink: 0, color: 'var(--pk-success-line)' };
  const TITLE = { fontSize: 15, fontWeight: 600, color: 'var(--pk-success-ink)' };
  const TEXT = { margin: 0, maxWidth: '62ch', fontSize: 13, lineHeight: 1.5, color: 'var(--pk-success-ink)' };
  const QUIET = { display: 'block', marginTop: 2, color: 'var(--pk-ink-quiet)' };
  const ACTIONS = { display: 'flex', flexWrap: 'wrap', gap: 'var(--pk-target-gap)', marginTop: 4 };

  /* The focus ring is drawn from --pk-focus-*, and only while the button really
     holds keyboard focus — an inline style cannot express :focus-visible. */
  function StateAction({ onClick, children }) {
    const [ring, setRing] = React.useState(false);
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
          border: '1px solid var(--pk-border-strong)',
          background: 'transparent', color: 'var(--pk-ink)',
          fontFamily: 'inherit', fontSize: 13, fontWeight: 600, cursor: 'pointer',
          outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
          outlineOffset: 'var(--pk-focus-offset)',
        }}
      >
        {children}
      </button>
    );
  }

  function NothingToDo({
    title = 'Plus rien en attente',
    description = '',
    lastHandledAt = '',
    actionLabel = '',
    onAction,
    style,
    ...rest
  }) {
    return (
      <div {...rest} style={{ ...ROOT, ...style }}>
        <div style={HEAD}>
          <i className="ri-checkbox-circle-line" aria-hidden="true" style={ICON} />
          <div style={TITLE}>{title}</div>
        </div>

        <p style={TEXT}>
          {description}
          {lastHandledAt ? (
            <span style={QUIET}>{'Le dernier point a été traité à ' + lastHandledAt + '.'}</span>
          ) : null}
        </p>

        {actionLabel ? (
          <div style={ACTIONS}>
            <StateAction onClick={onAction}>{actionLabel}</StateAction>
          </div>
        ) : null}
      </div>
    );
  }

  NS.NothingToDo = NothingToDo;
})();
