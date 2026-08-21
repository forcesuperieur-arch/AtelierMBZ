import * as React from 'react';

export interface FieldProps extends React.HTMLAttributes<HTMLDivElement> {
  label?: React.ReactNode;
  /** Rendered value. This is a design-kit field: it displays, it does not type. */
  value?: React.ReactNode;
  placeholder?: React.ReactNode;
  hint?: React.ReactNode;
  /** 38px workshop-panel height with 8px radius, instead of the 52px square customer field. */
  dense?: boolean;
  focused?: boolean;
  error?: React.ReactNode;
  /** Remix Icon class shown at the right edge (chevron for a select-like field). */
  endIcon?: string;
}

/** Labelled field: label above, never a placeholder standing in for the label. */
export function Field(props: FieldProps): React.JSX.Element;
