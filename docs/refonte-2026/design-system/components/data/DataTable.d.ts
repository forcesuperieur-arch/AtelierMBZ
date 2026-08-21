import * as React from 'react';

export interface DataTableColumn {
  key: string;
  header: React.ReactNode;
  /** CSS grid track, e.g. "1.3fr" or "130px". */
  width?: string;
  align?: 'left' | 'right' | 'center';
  strong?: boolean;
  quiet?: boolean;
  render?: (row: any) => React.ReactNode;
}

export interface DataTableProps extends React.HTMLAttributes<HTMLDivElement> {
  columns?: DataTableColumn[];
  /** Rows. `tone: 'flagged'` tints the row with the error surface. */
  rows?: any[];
  rowKey?: (row: any, index: number) => React.Key;
  caption?: React.ReactNode;
  /** Row count and the export / act-on-selection actions. */
  footer?: React.ReactNode;
}

/**
 * Dense record list — the result of an Explorer question, a client list, an audit log.
 *
 * @startingPoint section="Data" subtitle="Dense record list with header and footer actions" viewport="700x320"
 */
export function DataTable(props: DataTableProps): React.JSX.Element;
