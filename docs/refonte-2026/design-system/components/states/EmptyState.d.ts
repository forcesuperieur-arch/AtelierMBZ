import * as React from 'react';

export interface EmptyStateProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Remix Icon class, line style. Decoration only — the title carries the meaning. */
  icon?: string;
  title?: React.ReactNode;
  /** Where the data comes from, in one sentence. Never "aucune donnée disponible". */
  description?: React.ReactNode;
  /** The fast road in — import, connect, reprendre un fichier. Renders as the accent action. */
  actionLabel?: string;
  /** The other road: « Créer à la main » next to « Importer un fichier clients ». */
  secondaryLabel?: string;
  onAction?: () => void;
  onSecondary?: () => void;
}

/**
 * The list that has never been filled — and the door the data comes through.
 *
 * @startingPoint section="States" subtitle="Empty list on day one, with both ways in" viewport="700x200"
 */
export function EmptyState(props: EmptyStateProps): React.JSX.Element;
