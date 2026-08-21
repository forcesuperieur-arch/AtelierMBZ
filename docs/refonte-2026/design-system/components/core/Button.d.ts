import * as React from 'react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Visual weight: filled / outlined / text. */
  variant?: 'primary' | 'secondary' | 'tertiary';
  /** Colour family: black / Motoblouz yellow / red. */
  tone?: 'neutral' | 'accent' | 'error';
  /** Min-height 41 / 52 / 60px. */
  size?: 'small' | 'medium' | 'large';
  /** Pill (999px) or 8px. */
  shape?: 'rounded' | 'square';
  fullWidth?: boolean;
  disabled?: boolean;
  /** Remix Icon class, e.g. "ri-add-line". */
  startIcon?: string;
  endIcon?: string;
}

/**
 * The Paddock action button — the Motoblouz Gazoline Button API, restricted to
 * the axes Paddock actually uses.
 *
 * @startingPoint section="Core" subtitle="Action button — variants, tones, sizes" viewport="700x180"
 */
export function Button(props: ButtonProps): React.JSX.Element;
