import * as React from 'react';

export interface PlanningBay {
  name: string;
  /** Mechanic assigned, or "Non affecté". */
  assignee?: string;
  tone?: 'neutral' | 'success';
}

export interface PlanningGridProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Row labels, e.g. ['08:00','09:00',…]. Lunch is simply an absent hour. */
  hours?: string[];
  bays?: PlanningBay[];
  /** AppointmentBlock children, placed by column / row. */
  children?: React.ReactNode;
}

/**
 * The planning grid — hours down the left, one column per bay.
 *
 * @startingPoint section="Planning" subtitle="Day grid by bay with placed appointments" viewport="1000x520"
 */
export function PlanningGrid(props: PlanningGridProps): React.JSX.Element;
