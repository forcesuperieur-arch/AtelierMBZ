import * as React from 'react';

export interface LoadingStateProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Read out while the rows are still empty — say what is loading, not "chargement". */
  title?: string;
  /** Header line naming the list in words: "Devis". The grey bars alone say nothing. */
  caption?: React.ReactNode;
  /** Columns of the table being waited for — the same count as the real DataTable. */
  columns?: number;
  /** Rows to reserve — the page size, so the layout settles once and not twice. */
  rows?: number;
  /** Drop the header strip, for a skeleton inside a panel that already has a title. */
  compact?: boolean;
}

/**
 * The shape of the table that is on its way — never a spinner, never a bar that blinks.
 *
 * @startingPoint section="States" subtitle="Skeleton holding the exact size of the awaited table" viewport="700x320"
 */
export function LoadingState(props: LoadingStateProps): React.JSX.Element;
