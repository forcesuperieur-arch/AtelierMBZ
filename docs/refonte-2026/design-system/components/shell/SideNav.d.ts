import * as React from 'react';

export interface SideNavItem {
  id: string;
  label: string;
  /** Remix Icon class. */
  icon: string;
  badge?: number | string;
  /** 'error' turns the badge red — reserved for what blocks a motorcycle. */
  badgeTone?: 'neutral' | 'error';
}

export interface SideNavGroup {
  /** Trade group heading: Pilotage, Atelier, Commerce, Réglages. */
  label: string;
  items: SideNavItem[];
}

export interface SideNavProps extends React.HTMLAttributes<HTMLElement> {
  groups?: SideNavGroup[];
  active?: string;
  onSelect?: (id: string) => void;
  /** Workshop name under the wordmark. */
  workshop?: React.ReactNode;
  onCollapse?: () => void;
  /** Path to the Paddock symbol, relative to the consuming page. */
  logo?: string;
}

/**
 * The 224px grouped navigation — the expanded state of IconRail.
 *
 * @startingPoint section="Shell" subtitle="224px grouped navigation with badges" viewport="700x520"
 */
export function SideNav(props: SideNavProps): React.JSX.Element;
