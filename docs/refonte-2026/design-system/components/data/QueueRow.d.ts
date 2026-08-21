import * as React from 'react';

export interface QueueRowProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Remix Icon class naming the kind of item. */
  icon?: string;
  title: React.ReactNode;
  /** Age and threshold, or what it blocks. */
  detail?: React.ReactNode;
  level?: 'critical' | 'watch' | 'normal';
  count?: number | string;
  /** Overrides the level's French label. */
  statusLabel?: React.ReactNode;
}

/** One line of the "file à traiter" queue. */
export function QueueRow(props: QueueRowProps): React.JSX.Element;
