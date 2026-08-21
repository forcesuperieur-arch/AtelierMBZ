import * as React from 'react';

export type IconName =
  | 'AddLine' | 'SubtractFill'
  | 'ArrowUpSLine' | 'ArrowDownSLine' | 'ArrowLeftSLine' | 'ArrowRightSLine'
  | 'ArrowLeftLine' | 'ArrowRightLine'
  | 'CheckFill' | 'CheckboxBlankCircleFill' | 'CloseLine' | 'CloseFill'
  | 'SearchLine'
  | 'StarLine' | 'StarFill' | 'StarHalfLine' | 'StarHalfFill'
  | 'ShoppingBasket2Line' | 'ShoppingBasket2Fill';

export interface IconProps extends Omit<React.SVGProps<SVGSVGElement>, 'name'> {
  name: IconName;
  /** Rendered square size in px. Default 24. */
  size?: number;
}

/**
 * The Motoblouz house glyph set, painted in currentColor.
 *
 * @startingPoint section="Brand" subtitle="The 19 house glyphs, at their real sizes" viewport="700x150"
 */
export function Icon(props: IconProps): React.JSX.Element | null;

/** Every available glyph name, in source order. Capitalised so the compiled bundle exposes it on the window namespace. */
export const IconNames: IconName[];
