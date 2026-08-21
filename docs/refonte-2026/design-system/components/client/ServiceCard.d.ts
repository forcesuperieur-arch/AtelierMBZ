import * as React from 'react';

export interface ServiceCardProps extends React.HTMLAttributes<HTMLDivElement> {
  title: React.ReactNode;
  /** Price as displayed, including the "dès" qualifier: "dès 145 €". */
  price?: React.ReactNode;
  description?: React.ReactNode;
  /** Green recommendation line, e.g. "Conseillée pour votre kilométrage". */
  recommendation?: React.ReactNode;
  selected?: boolean;
}

export interface SlotDay {
  label: string;
  slots: { time: string; full?: boolean }[];
}

export interface SlotGridProps extends React.HTMLAttributes<HTMLDivElement> {
  days?: SlotDay[];
  /** "${day.label} ${slot.time}" of the chosen slot. */
  selected?: string;
  onSelect?: (id: string) => void;
}

/**
 * A service with its price, on the customer front.
 *
 * @startingPoint section="Client" subtitle="Service choice with price and recommendation" viewport="700x300"
 */
export function ServiceCard(props: ServiceCardProps): React.JSX.Element;

/** Real bookable slots, two per day, full ones kept visible. */
export function SlotGrid(props: SlotGridProps): React.JSX.Element;
