import * as React from 'react';

export interface TopBarProps extends React.HTMLAttributes<HTMLElement> {
  /** Page name, shown when the header is not carrying a workshop switcher. */
  title?: React.ReactNode;
  /** Workshop name — renders the store switcher. */
  workshop?: React.ReactNode;
  /** Live-data line, e.g. "Données en direct · 14:32". */
  live?: React.ReactNode;
}

export interface SearchFieldProps extends React.HTMLAttributes<HTMLDivElement> {
  placeholder?: string;
  shortcut?: string | null;
}

export interface IconActionProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Remix Icon class. */
  icon: string;
  badge?: number | string;
  /** Accessible name — required, the button has no text. */
  label: string;
}

/** 52px application header. */
export function TopBar(props: TopBarProps): React.JSX.Element;
/** The one search field: client, plate, or work-order number. */
export function SearchField(props: SearchFieldProps): React.JSX.Element;
/** Square icon-only header action with optional badge. */
export function IconAction(props: IconActionProps): React.JSX.Element;
