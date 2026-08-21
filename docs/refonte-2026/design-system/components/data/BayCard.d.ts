import * as React from 'react';

export interface BayCardProps extends React.HTMLAttributes<HTMLDivElement> {
  name: React.ReactNode;
  state?: 'occupied' | 'free' | 'down';
  /** Make and plate. */
  vehicle?: React.ReactNode;
  customer?: React.ReactNode;
  /** Footer line, e.g. "2 RDV restants aujourd'hui". */
  note?: React.ReactNode;
}

/** A workshop bay: occupied (yellow cap, white surface) or free (dashed outline). */
export function BayCard(props: BayCardProps): React.JSX.Element;
