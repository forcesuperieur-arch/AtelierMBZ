import * as React from 'react';

export interface StatusStep {
  title: React.ReactNode;
  detail?: React.ReactNode;
  state?: 'done' | 'current' | 'pending';
}

export interface StatusTimelineProps extends React.HTMLAttributes<HTMLDivElement> {
  steps?: StatusStep[];
}

/**
 * "Where is my motorcycle" — the tracking timeline opened from an SMS link.
 *
 * @startingPoint section="Client" subtitle="Job progress timeline for the customer link" viewport="700x340"
 */
export function StatusTimeline(props: StatusTimelineProps): React.JSX.Element;
