import * as React from 'react';

export interface AppointmentBlockProps extends React.HTMLAttributes<HTMLDivElement> {
  /** received / running / ready / confirmed / waiting (customer) / open (panel showing) / conflict / unassigned (no bay) / done. */
  state?: 'received' | 'running' | 'ready' | 'confirmed' | 'waiting' | 'open' | 'conflict' | 'unassigned' | 'done';
  /** Hour + state, written out: "08:30 · réception en cours". */
  statusLabel: React.ReactNode;
  /** Remix Icon class. */
  icon?: string;
  /** Model · customer surname. */
  vehicle?: React.ReactNode;
  detail?: React.ReactNode;
  detailTone?: 'quiet' | 'warning' | 'error';
  /** Bottom-pinned line, e.g. "Panneau ouvert →". */
  note?: React.ReactNode;
  /** Grid column (1 is the hour gutter). */
  column?: number;
  /** Grid row start. */
  row?: number;
  /** Row span = duration. */
  span?: number;
}

/** One appointment in the planning grid. */
export function AppointmentBlock(props: AppointmentBlockProps): React.JSX.Element;
