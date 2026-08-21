import * as React from 'react';

export interface IconRailItem {
  id: string;
  label: string;
  /** Remix Icon class, e.g. "ri-calendar-2-line". */
  icon: string;
  badge?: number | string;
}

export interface IconRailProps extends React.HTMLAttributes<HTMLElement> {
  items?: IconRailItem[];
  active?: string;
  onSelect?: (id: string) => void;
  /** Path to the Paddock symbol, relative to the consuming page. Omit to render no mark. */
  logo?: string | null;
  footer?: React.ReactNode;
  /** Initials shown at the bottom. */
  user?: string;
}

/**
 * The 64px navigation rail — fixed width, no hover expansion.
 *
 * @startingPoint section="Shell" subtitle="64px icon rail with active state and badges" viewport="700x400"
 */
export function IconRail(props: IconRailProps): React.JSX.Element;
