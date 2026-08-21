/* ErrorState — maquette 29d.
 *
 * The rule of tour 29: say what happened, what it prevents, and the one action
 * that helps. Two sentences, because they answer two different questions —
 * `description` carries the CAUSE (le serveur n'a pas répondu), `consequence`
 * carries what it cost, which is usually nothing, and that is the first thing
 * anyone wants to know.
 *
 * Two actions, because one locks the user in: retry, and the legitimate way
 * out that lets the work continue without the server ("Voir la feuille du
 * jour"). The timestamped code is not decoration: it is there to be read out
 * on the phone.
 *
 * No decorative illustration, no centring. React only, no other dependency.
 */
(function () {
  const NS = (window.PaddockDesignSystem_8059f4 = window.PaddockDesignSystem_8059f4 || {});

  /* Tinted fill and a solid edge: something broke, unlike the dashed EmptyState. */
  const ROOT = {
    display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left',
    background: 'var(--pk-error-surface)', border: '1px solid var(--pk-error-line)',
    borderRadius: 'var(--pk-radius-card)',
  };
  const HEAD = { display: 'flex', alignItems: 'center', gap: 10 };
  const ICON = { fontSize: 20, flexShrink: 0, color: 'var(--pk-error-line)' };
  const TITLE = { fontSize: 15, fontWeight: 600, color: 'var(--pk-error-ink)' };
  const TEXT = { margin: 0, maxWidth: '62ch', fontSize: 13, lineHeight: 1.5, color: 'var(--pk-error-ink)' };
  /* The consequence sits on its own line, quieter: it reassures, it does not alarm. */
  const CONSEQUENCE = { display: 'block', marginTop: 2, color: 'var(--pk-ink-quiet)' };
  const ACTIONS = { display: 'flex', flexWrap: 'wrap', gap: 'var(--pk-target-gap)', marginTop: 4 };
  const CODE = { margin: 0, fontSize: 12, color: 'var(--pk-ink-muted)' };

  /* The focus ring is drawn from --pk-focus-*, and only while the button really
     holds keyboard focus — an inline style cannot express :focus-visible. */
  function StateAction({ tone, icon, onClick, children }) {
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
          display: 'inline-flex', alignItems: 'center', gap: 7,
          borderRadius: 'var(--pk-radius-pill)',
          border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
          background: accent ? 'var(--pk-accent)' : 'transparent',
          color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
          fontFamily: 'inherit', fontSize: 13, fontWeight: 600, cursor: 'pointer',
          outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
          outlineOffset: 'var(--pk-focus-offset)',
        }}
      >
        {icon ? <i className={icon} aria-hidden="true" style={{ fontSize: 16 }} /> : null}
        {children}
      </button>
    );
  }

  function ErrorState({
    icon = 'ri-error-warning-line',
    title = 'Chargement impossible',
    description = "Le serveur n'a pas répondu.",
    consequence = "Rien n'a été modifié.",
    actionLabel = 'Réessayer',
    issueLabel = '',
    code = '',
    failedAt = '',
    onRetry,
    onIssue,
    style,
    ...rest
  }) {
    return (
      <div role="alert" {...rest} style={{ ...ROOT, ...style }}>
        <div style={HEAD}>
          <i className={icon} aria-hidden="true" style={ICON} />
          <div style={TITLE}>{title}</div>
        </div>

        <p style={TEXT}>
          {description}
          {consequence ? <span style={CONSEQUENCE}>{consequence}</span> : null}
        </p>

        <div style={ACTIONS}>
          <StateAction tone="accent" icon="ri-refresh-line" onClick={onRetry}>{actionLabel}</StateAction>
          {issueLabel ? <StateAction onClick={onIssue}>{issueLabel}</StateAction> : null}
        </div>

        {code ? <p style={CODE}>{'Erreur ' + code + (failedAt ? ' · ' + failedAt : '') + ' · à donner au support'}</p> : null}
      </div>
    );
  }

  NS.ErrorState = ErrorState;
})();
