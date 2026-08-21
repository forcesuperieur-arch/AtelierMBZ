import * as React from 'react';

export interface OfflineBannerProps extends React.HTMLAttributes<HTMLElement> {
  /** Renders nothing when false — there is no reassuring "en ligne" bar. */
  offline?: boolean;
  /** Elapsed time in plain words: "2 min". */
  since?: string;
  /** What still works, continuing "Vous pouvez continuer à …": "pointer et à réceptionner". */
  stillPossible?: string;
  /** Actions queued for sending — shown in the detail, hidden when 0. */
  pending?: number;
  /** What the queue holds: "Pointages et 1 réception · signature capturée sur la tablette". */
  pendingDetail?: React.ReactNode;
  /** The acts the outage blocks, named one by one: "Envoi de SMS, encaissement, aperçu PDF". */
  unavailable?: React.ReactNode;
  /** Start unfolded, for a screen that must show the queue without a press. */
  defaultOpen?: boolean;
}

/**
 * The network-is-gone strip that leads with what the workshop can still do.
 *
 * @startingPoint section="States" subtitle="Global offline banner, collapsed and unfolded" viewport="700x300"
 */
export function OfflineBanner(props: OfflineBannerProps): React.JSX.Element | null;
