import * as React from 'react';

export interface StepBarProps extends React.HTMLAttributes<HTMLDivElement> {
  total?: number;
  /** 1-based index of the current step. */
  current?: number;
  /** The step written out: "Étape 2 sur 4 · ce dont vous avez besoin". */
  label?: React.ReactNode;
}

/** Booking progress on the customer front. Sits on the black header. */
export function StepBar(props: StepBarProps): React.JSX.Element;
