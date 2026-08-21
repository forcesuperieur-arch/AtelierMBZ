import * as React from 'react';

export interface CalloutProps extends React.HTMLAttributes<HTMLDivElement> {
  tone?: 'info' | 'success' | 'warning' | 'error' | 'accent';
  title?: React.ReactNode;
  /** Remix Icon class — overrides the tone's default glyph. */
  icon?: string;
  /** Left-edge variant: 3px accent bar on a quiet surface, no tinted fill. */
  edge?: boolean;
}

/** A persistent explanation attached to the thing it concerns. */
export function Callout(props: CalloutProps): React.JSX.Element;
