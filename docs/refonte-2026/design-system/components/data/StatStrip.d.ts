import * as React from 'react';

export interface StatStripItem {
  label: React.ReactNode;
  value: React.ReactNode;
  /** Secondary reading appended after a middot: "58 %", "1,3/client". */
  suffix?: React.ReactNode;
  tone?: 'neutral' | 'error';
}

export interface StatStripProps extends React.HTMLAttributes<HTMLDivElement> {
  items?: StatStripItem[];
}

/** Four measures on one line, framing the list underneath. */
export function StatStrip(props: StatStripProps): React.JSX.Element;
