import * as React from 'react';

export interface PermissionCalloutProps extends React.HTMLAttributes<HTMLDivElement> {
  /** What is being refused, in the words of the job. Never "accès refusé". */
  title?: React.ReactNode;
  /** The ceiling, then who decides above it: « Vous pouvez accorder jusqu'à 15 %. Au-delà, la validation revient à la direction. » */
  description?: React.ReactNode;
  /** The road up — ask the person who decides, named: « Demander à Pascal M. ». */
  requestLabel?: string;
  /** The road back inside the limit, with the figure in it: « Ramener à 15 % ». */
  complyLabel?: string;
  /** What asking actually sets in motion: « La demande part par notification, avec le motif que vous avez saisi. » */
  note?: React.ReactNode;
  onRequest?: () => void;
  onComply?: () => void;
}

/**
 * The refusal that names the ceiling, names who decides above it, and keeps both roads open.
 *
 * @startingPoint section="States" subtitle="Beyond your rights — the ceiling, the decider, both roads" viewport="700x240"
 */
export function PermissionCallout(props: PermissionCalloutProps): React.JSX.Element;
