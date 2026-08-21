import * as React from 'react';

export interface NothingToDoProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: React.ReactNode;
  /** What was cleared, enumerated — not what is missing. */
  description?: React.ReactNode;
  /** Hour the last item was handled, as "14:18". It is the proof the screen is alive. */
  lastHandledAt?: string;
  /** One quiet action, and it looks forward: the work here is done. */
  actionLabel?: string;
  onAction?: () => void;
}

/**
 * The queue reached zero — a result, dated by the hour it was reached.
 *
 * @startingPoint section="States" subtitle="An earned void, in the success trio" viewport="700x200"
 */
export function NothingToDo(props: NothingToDoProps): React.JSX.Element;
