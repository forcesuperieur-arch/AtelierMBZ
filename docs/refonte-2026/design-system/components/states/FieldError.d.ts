import * as React from 'react';

export interface FieldErrorProps extends React.HTMLAttributes<HTMLParagraphElement> {
  /** What is wrong, naming the expected value with its origin and its date. Empty renders nothing at all. */
  message?: React.ReactNode;
  /** The legitimate case that beats the rule: « Compteur remplacé ». Renders as a link inside the sentence. */
  issueLabel?: string;
  onIssue?: () => void;
}

/**
 * The line under a refused entry — the reference value, its date, and the door left open.
 *
 * @startingPoint section="States" subtitle="Refused entry naming the expected value and its way out" viewport="700x160"
 */
export function FieldError(props: FieldErrorProps): React.JSX.Element | null;
