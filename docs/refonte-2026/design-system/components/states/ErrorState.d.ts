import * as React from 'react';

export interface ErrorStateProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Remix Icon class, line style — the failure's own glyph: "ri-cloud-off-line" when the server never answered. */
  icon?: string;
  /** What could not be done, named. Never "une erreur est survenue". */
  title?: React.ReactNode;
  /** The CAUSE: what happened, server or network side. */
  description?: React.ReactNode;
  /** The CONSEQUENCE: what it did not cost. Sits on its own line, quieter — it is the first question asked. */
  consequence?: React.ReactNode;
  /** Label of the retry action, which always renders. */
  actionLabel?: string;
  /** The legitimate way out — the work that survives the outage: « Voir la feuille du jour ». */
  issueLabel?: string;
  /** Support code, read out on the phone: "PLN-503". */
  code?: string;
  /** Hour the call failed, as "14:52". Rendered next to `code`, and only with it. */
  failedAt?: string;
  onRetry?: () => void;
  onIssue?: () => void;
}

/**
 * The screen the server could not fill — cause, cost, retry, and the way of working without it.
 *
 * @startingPoint section="States" subtitle="Server silent — cause, consequence, way out, support code" viewport="700x240"
 */
export function ErrorState(props: ErrorStateProps): React.JSX.Element;
