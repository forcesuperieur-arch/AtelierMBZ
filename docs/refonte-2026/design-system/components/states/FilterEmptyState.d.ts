import * as React from 'react';

export interface FilterSuggestion {
  /** The filter label exactly as it reads on its pill: "Roubaix". */
  filter: string;
  /** How many rows come back if that one filter goes. The figure is the message. */
  count: number;
  /** What is being counted, already plural: "devis critiques". */
  noun: string;
}

export interface FilterEmptyStateProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: React.ReactNode;
  /** How many filters are active right now. Rendered as "3 filtres sont actifs." */
  filterCount: number;
  /** The most profitable removal, computed by the calling screen. Null when it cannot be. */
  suggestion?: FilterSuggestion | null;
  /** Receives `suggestion.filter` — the screen drops that one pill and re-runs the query. */
  onRemove?: (filter: string) => void;
  onClear?: () => void;
}

/**
 * The rows exist; the filters hide them — and here is what one removal brings back.
 *
 * @startingPoint section="States" subtitle="Filtered to nothing, with the costed way out" viewport="700x200"
 */
export function FilterEmptyState(props: FilterEmptyStateProps): React.JSX.Element;
