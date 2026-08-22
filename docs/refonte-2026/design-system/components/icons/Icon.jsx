import React from 'react';
import icons from './icon-data.js';

/* The Motoblouz house glyph set — 19 icons materialised from the Figma kit
   « MB — Components ». They paint in currentColor and carry no colour of
   their own. Where a glyph does not exist (the whole workshop vocabulary:
   pont, OR, moto, planning), Paddock falls back to Remix Icon — see the
   ICONOGRAPHY section of readme.md. */
export function Icon({ name, size = 24, style, ...rest }) {
  const d = icons[name];
  if (!d) return null;
  return (
    <svg width={size} height={size} viewBox={d.viewBox} fill="none" aria-hidden="true" focusable="false"
      style={{ display: 'block', flex: 'none', ...style }}
      dangerouslySetInnerHTML={{ __html: d.body }} {...rest} />
  );
}

export const IconNames = Object.keys(icons);
