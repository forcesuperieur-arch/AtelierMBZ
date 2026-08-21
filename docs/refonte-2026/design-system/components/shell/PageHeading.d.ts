import * as React from 'react';

export interface PageHeadingProps extends React.HTMLAttributes<HTMLDivElement> {
  title: React.ReactNode;
  /** One line saying what the page answers. */
  description?: React.ReactNode;
}

export interface TabItem { value: string; label: React.ReactNode; count?: number }

export interface PillTabsProps extends React.HTMLAttributes<HTMLDivElement> {
  items?: TabItem[];
  value?: string;
  onChange?: (value: string) => void;
}

export interface UnderlineTabsProps extends React.HTMLAttributes<HTMLDivElement> {
  items?: TabItem[];
  value?: string;
  onChange?: (value: string) => void;
}

/** Page title with the 4px yellow underline. */
export function PageHeading(props: PageHeadingProps): React.JSX.Element;
/** Pill tab set (Stat: Atelier / Période / Analyse / Explorer). */
export function PillTabs(props: PillTabsProps): React.JSX.Element;
/** Underlined tab bar inside a working screen. */
export function UnderlineTabs(props: UnderlineTabsProps): React.JSX.Element;
