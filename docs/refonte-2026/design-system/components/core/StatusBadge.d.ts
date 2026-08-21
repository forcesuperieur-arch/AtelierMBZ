import * as React from 'react';

export interface StatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: 'neutral' | 'success' | 'warning' | 'error' | 'info';
  /** Remix Icon class. */
  icon?: string;
}

export interface CounterProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: 'neutral' | 'accent' | 'error';
}

/** Uppercase status word. Always paired with a written label — never colour alone. */
export function StatusBadge(props: StatusBadgeProps): React.JSX.Element;

/** Round count pill used next to a section title or a tab. */
export function Counter(props: CounterProps): React.JSX.Element;
