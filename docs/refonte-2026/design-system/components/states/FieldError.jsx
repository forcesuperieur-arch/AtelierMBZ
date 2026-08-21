/* FieldError — maquette 29f.
 *
 * "Valeur invalide" teaches nobody anything. The template wants the reference
 * value, where it came from and when: « Inférieur au dernier relevé connu :
 * 24 180 km en mars 2026. Un compteur ne recule pas. »
 *
 * And it wants the way out. A replaced odometer really does go backwards, so a
 * flat refusal would force the receptionist to type a false number to move on.
 * `issueLabel` names that legitimate case instead of making it impossible.
 *
 * Icon plus text, never red alone. React only, no other dependency.
 */

import React from 'react';

const ROOT = {
  display: 'flex', alignItems: 'flex-start', gap: 6, margin: 0, textAlign: 'left',
  fontSize: 13, lineHeight: 1.45, color: 'var(--pk-error-ink)',
};
const ICON = { fontSize: 15, flexShrink: 0, marginTop: 1, color: 'var(--pk-error-line)' };

/* The way out is a link inside the sentence, not a fourth button on the form:
   it belongs to the refused field, and it must not compete with "Valider". */
function IssueLink({ onClick, children }) {
  const [ring, setRing] = React.useState(false);
  return (
    <button
      type="button"
      onClick={onClick}
      onFocus={(e) => setRing(e.currentTarget.matches(':focus-visible'))}
      onBlur={() => setRing(false)}
      style={{
        display: 'inline', marginLeft: 6, padding: 0, border: 'none', background: 'none',
        font: 'inherit', fontWeight: 600, color: 'var(--pk-link)',
        textDecoration: 'underline', cursor: 'pointer',
        outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
        outlineOffset: 'var(--pk-focus-offset)',
      }}
    >
      {children}
    </button>
  );
}

export function FieldError({ message = '', issueLabel = '', onIssue, style, ...rest }) {
  if (!message) return null;
  return (
    <p role="alert" {...rest} style={{ ...ROOT, ...style }}>
      <i className="ri-error-warning-line" aria-hidden="true" style={ICON} />
      <span>
        {message}
        {issueLabel ? <IssueLink onClick={onIssue}>{issueLabel}</IssueLink> : null}
      </span>
    </p>
  );
}
