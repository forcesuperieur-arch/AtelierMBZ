import * as React from 'react';

export interface SidePanelProps extends React.HTMLAttributes<HTMLElement> {
  /** Remix Icon class shown in yellow on the black header. */
  icon?: string;
  title: React.ReactNode;
  /** Vehicle · plate · customer, on one line. */
  subtitle?: React.ReactNode;
  /** Sticky footer: the action that closes this moment, plus its escape hatches. */
  footer?: React.ReactNode;
  onClose?: () => void;
  width?: number | string;
}

export interface PanelSectionProps extends React.HTMLAttributes<HTMLDivElement> {
  label?: React.ReactNode;
  /** Right-aligned secondary value on the label line. */
  aside?: React.ReactNode;
}

/**
 * The 456px work panel: reception, hand-back and appointment detail open here,
 * to the right of the planning, which stays readable behind.
 *
 * @startingPoint section="Shell" subtitle="456px work panel with sections and footer action" viewport="700x520"
 */
export function SidePanel(props: SidePanelProps): React.JSX.Element;
export function PanelSection(props: PanelSectionProps): React.JSX.Element;
