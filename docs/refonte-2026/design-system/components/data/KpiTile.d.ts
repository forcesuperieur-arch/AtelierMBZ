import * as React from 'react';

export interface KpiTileProps extends React.HTMLAttributes<HTMLDivElement> {
  label: React.ReactNode;
  value: React.ReactNode;
  /** Denominator or unit, rendered smaller and quiet: "/6". */
  unit?: React.ReactNode;
  /** The line under the number: what it means, or what to do. */
  note?: React.ReactNode;
  /** 0..1 — draws the 4px meter. Above 0.8 the fill turns yellow. */
  ratio?: number;
  tone?: 'neutral' | 'warning' | 'error';
}

/**
 * A counter, and where it leads.
 *
 * @startingPoint section="Data" subtitle="KPI tile row with meter and alert tone" viewport="700x160"
 */
export function KpiTile(props: KpiTileProps): React.JSX.Element;
