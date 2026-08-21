import * as React from 'react';

export interface FilterPillProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  label: React.ReactNode;
  /** Trailing count, bold. Omit for a pill that is not a counter. */
  count?: number | string;
  selected?: boolean;
  /** Dashed outline — used for the "sans pont" anomaly filter. */
  dashed?: boolean;
  tone?: 'neutral' | 'warning';
  /** Remix Icon class. */
  icon?: string;
}

/** Planning filter pill: a state, how many are in it, and whether it is the current filter. */
export function FilterPill(props: FilterPillProps): React.JSX.Element;
