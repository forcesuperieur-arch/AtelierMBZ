import * as React from 'react';

export interface QueueItem {
  /** Kind + age, uppercase: "Devis · 6 jours". */
  kind: React.ReactNode;
  /** Remix Icon class. */
  icon: string;
  title: React.ReactNode;
  /** What it blocks, in one line. */
  detail?: React.ReactNode;
  level?: 'critical' | 'watch' | 'normal';
  /** Up to two action labels; the first renders as the accent action. */
  actions?: string[];
  /** Count shown in the collapsed rail. */
  count?: number | string;
}

export interface QueuePanelProps extends React.HTMLAttributes<HTMLElement> {
  items?: QueueItem[];
  count?: number | string;
  /** 52px counter rail instead of the 320px panel. */
  collapsed?: boolean;
  onToggle?: () => void;
  footer?: React.ReactNode;
}

/**
 * « À traiter » — the queue that follows the user across every screen.
 *
 * @startingPoint section="Data" subtitle="The to-handle queue, expanded and collapsed" viewport="700x420"
 */
export function QueuePanel(props: QueuePanelProps): React.JSX.Element;
