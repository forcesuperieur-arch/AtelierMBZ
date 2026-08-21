import * as React from 'react';

export interface BayProgrammeRow {
  time: string;
  /** Model · customer, or the conflict in words. */
  label: React.ReactNode;
  /** Written status: Terminé, En cours, Réservé, Non affecté, À arbitrer. */
  status: React.ReactNode;
  state?: 'done' | 'running' | 'booked' | 'unassigned';
  tone?: 'neutral' | 'error';
}

export interface BayControlCardProps extends React.HTMLAttributes<HTMLDivElement> {
  name: React.ReactNode;
  state?: 'occupied' | 'free' | 'conflict' | 'maintenance';
  /** Bay type and capacity: "Type ATELIER · 350 kg". */
  spec?: React.ReactNode;
  /** Mechanic attached. Omit for "Aucun mécanicien rattaché". */
  mechanic?: React.ReactNode;
  programme?: BayProgrammeRow[];
  /** Replaces the programme when the bay is out of capacity. */
  note?: React.ReactNode;
}

/**
 * The bay as a control surface: state, configuration and the day's programme.
 *
 * @startingPoint section="Data" subtitle="Bay control card — occupied, free, conflict, maintenance" viewport="700x300"
 */
export function BayControlCard(props: BayControlCardProps): React.JSX.Element;
