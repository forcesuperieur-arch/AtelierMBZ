/* @ds-bundle: {"format":4,"namespace":"PaddockDesignSystem_8059f4","components":[{"name":"ServiceCard","sourcePath":"components/client/ServiceCard.jsx"},{"name":"SlotGrid","sourcePath":"components/client/ServiceCard.jsx"},{"name":"StatusTimeline","sourcePath":"components/client/StatusTimeline.jsx"},{"name":"StepBar","sourcePath":"components/client/StepBar.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Callout","sourcePath":"components/core/Callout.jsx"},{"name":"Field","sourcePath":"components/core/Field.jsx"},{"name":"FilterPill","sourcePath":"components/core/FilterPill.jsx"},{"name":"StatusBadge","sourcePath":"components/core/StatusBadge.jsx"},{"name":"Counter","sourcePath":"components/core/StatusBadge.jsx"},{"name":"BayCard","sourcePath":"components/data/BayCard.jsx"},{"name":"BayControlCard","sourcePath":"components/data/BayControlCard.jsx"},{"name":"DataTable","sourcePath":"components/data/DataTable.jsx"},{"name":"KpiTile","sourcePath":"components/data/KpiTile.jsx"},{"name":"QueuePanel","sourcePath":"components/data/QueuePanel.jsx"},{"name":"QueueRow","sourcePath":"components/data/QueueRow.jsx"},{"name":"StatStrip","sourcePath":"components/data/StatStrip.jsx"},{"name":"Icon","sourcePath":"components/icons/Icon.jsx"},{"name":"IconNames","sourcePath":"components/icons/Icon.jsx"},{"name":"AppointmentBlock","sourcePath":"components/planning/AppointmentBlock.jsx"},{"name":"PlanningGrid","sourcePath":"components/planning/PlanningGrid.jsx"},{"name":"IconRail","sourcePath":"components/shell/IconRail.jsx"},{"name":"PageHeading","sourcePath":"components/shell/PageHeading.jsx"},{"name":"PillTabs","sourcePath":"components/shell/PageHeading.jsx"},{"name":"UnderlineTabs","sourcePath":"components/shell/PageHeading.jsx"},{"name":"SideNav","sourcePath":"components/shell/SideNav.jsx"},{"name":"SidePanel","sourcePath":"components/shell/SidePanel.jsx"},{"name":"PanelSection","sourcePath":"components/shell/SidePanel.jsx"},{"name":"TopBar","sourcePath":"components/shell/TopBar.jsx"},{"name":"SearchField","sourcePath":"components/shell/TopBar.jsx"},{"name":"IconAction","sourcePath":"components/shell/TopBar.jsx"},{"name":"EmptyState","sourcePath":"components/states/EmptyState.jsx"},{"name":"ErrorState","sourcePath":"components/states/ErrorState.jsx"},{"name":"FieldError","sourcePath":"components/states/FieldError.jsx"},{"name":"FilterEmptyState","sourcePath":"components/states/FilterEmptyState.jsx"},{"name":"LoadingState","sourcePath":"components/states/LoadingState.jsx"},{"name":"NothingToDo","sourcePath":"components/states/NothingToDo.jsx"},{"name":"OfflineBanner","sourcePath":"components/states/OfflineBanner.jsx"},{"name":"PermissionCallout","sourcePath":"components/states/PermissionCallout.jsx"}],"sourceHashes":{"components/client/ServiceCard.jsx":"a46a44fdac04","components/client/StatusTimeline.jsx":"d4a27ce015c0","components/client/StepBar.jsx":"b9aee1480bd5","components/core/Button.jsx":"af3ba49cfcef","components/core/Callout.jsx":"0c0dd1465a5a","components/core/Field.jsx":"90ae329b2e4b","components/core/FilterPill.jsx":"acf4ce933aa7","components/core/StatusBadge.jsx":"f57261672d2f","components/data/BayCard.jsx":"8b1c53ce71fc","components/data/BayControlCard.jsx":"073b9af51095","components/data/DataTable.jsx":"32e51c5e8ca9","components/data/KpiTile.jsx":"1a3b23ca7bf9","components/data/QueuePanel.jsx":"19cead30e4be","components/data/QueueRow.jsx":"fea0af906676","components/data/StatStrip.jsx":"6a8781760db8","components/icons/Icon.jsx":"c6ef315bb465","components/icons/icon-data.js":"ffeedf10c3a3","components/planning/AppointmentBlock.jsx":"1d18c91bd757","components/planning/PlanningGrid.jsx":"92aec10ab646","components/shell/IconRail.jsx":"ed912455c973","components/shell/PageHeading.jsx":"4da0157260af","components/shell/SideNav.jsx":"0dd27c5a656d","components/shell/SidePanel.jsx":"bbc1fcd089e6","components/shell/TopBar.jsx":"6a4cd31e2fe7","components/states/EmptyState.jsx":"dc6759f0bb7d","components/states/ErrorState.jsx":"7be3c4a82b79","components/states/FieldError.jsx":"6443ddb1c917","components/states/FilterEmptyState.jsx":"039a06890840","components/states/LoadingState.jsx":"f99b276e4f9d","components/states/NothingToDo.jsx":"525ea20dd129","components/states/OfflineBanner.jsx":"91c192405ad7","components/states/PermissionCallout.jsx":"3ea6086e5d49"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.PaddockDesignSystem_8059f4 = window.PaddockDesignSystem_8059f4 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/client/ServiceCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* A service, with its price, on the customer front. The price is announced
   before the slot is chosen — never after. Selected state is a 2px black
   frame; a recommendation is written, not implied. */
function ServiceCard({
  title,
  price,
  description,
  recommendation,
  selected = false,
  children,
  onClick,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: onClick ? 'button' : undefined,
    onClick: onClick
  }, rest, {
    style: {
      background: 'var(--pk-surface-raised)',
      border: selected ? '2px solid #000' : '1px solid var(--pk-border)',
      padding: 16,
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      fontSize: 17,
      fontWeight: 600
    }
  }, title), price ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: selected ? 19 : 15,
      fontWeight: selected ? 700 : 600,
      color: selected ? 'inherit' : 'var(--pk-ink-quiet)'
    }
  }, price) : null), description ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      lineHeight: 1.5,
      color: 'var(--pk-ink-quiet)'
    }
  }, description) : null, recommendation ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 7,
      fontSize: 13,
      fontWeight: 600,
      color: 'var(--pk-success-ink)',
      marginTop: 2
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-star-line",
    style: {
      fontSize: 15
    }
  }), recommendation) : null, children);
}

/* Real slots the planning can actually hold. A full slot stays visible and
   says "complet" — hiding it would make the week look emptier than it is. */
function SlotGrid({
  days = [],
  selected,
  onSelect,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      fontFamily: 'var(--mb-font-montserrat)',
      ...style
    }
  }), days.map(d => /*#__PURE__*/React.createElement("div", {
    key: d.label,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 96,
      flex: 'none',
      fontSize: 15,
      fontWeight: 600,
      color: 'var(--pk-ink)'
    }
  }, d.label), d.slots.map(s => {
    const id = d.label + ' ' + s.time;
    const on = selected === id;
    const full = s.full;
    return /*#__PURE__*/React.createElement("button", {
      key: id,
      type: "button",
      disabled: full,
      onClick: onSelect ? () => onSelect(id) : undefined,
      style: {
        flex: 1,
        minHeight: 52,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: full ? 'var(--pk-neutral-surface)' : on ? '#000' : 'var(--pk-surface-raised)',
        border: full || !on ? '1px solid var(--pk-border)' : '1px solid #000',
        color: full ? '#a5a5a5' : on ? 'var(--pk-accent)' : 'var(--pk-ink)',
        fontSize: full ? 15 : 16,
        fontWeight: on ? 700 : full ? 400 : 600,
        cursor: full ? 'default' : 'pointer',
        fontFamily: 'inherit'
      }
    }, full ? 'complet' : s.time);
  }))));
}
Object.assign(__ds_scope, { ServiceCard, SlotGrid });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/client/ServiceCard.jsx", error: String((e && e.message) || e) }); }

// components/client/StatusTimeline.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const DOT = {
  done: 'var(--pk-success-ink)',
  current: 'var(--pk-accent)',
  pending: 'transparent'
};

/* Where is my motorcycle. One line per event, newest last, the pending step
   left hollow. No date maths: the workshop writes the hour it happened. */
function StatusTimeline({
  steps = [],
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      flexDirection: 'column',
      fontFamily: 'var(--mb-font-montserrat)',
      ...style
    }
  }), steps.map((s, i) => {
    const last = i === steps.length - 1;
    const pending = s.state === 'pending';
    return /*#__PURE__*/React.createElement("div", {
      key: s.title,
      style: {
        display: 'flex',
        gap: 14
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        flex: 'none'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 12,
        height: 12,
        borderRadius: 'var(--pk-radius-pill)',
        display: 'block',
        background: DOT[s.state] || DOT.pending,
        border: pending ? '2px solid #a5a5a5' : 'none'
      }
    }), last ? null : /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1,
        width: 2,
        background: 'var(--pk-border)'
      }
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        paddingBottom: last ? 0 : 11
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        fontSize: 15,
        fontWeight: 600,
        color: pending ? 'var(--pk-ink-muted)' : 'var(--pk-ink)'
      }
    }, s.title), s.detail ? /*#__PURE__*/React.createElement("div", {
      style: {
        fontSize: 14,
        color: pending ? 'var(--pk-ink-muted)' : 'var(--pk-ink-quiet)',
        marginTop: 2
      }
    }, s.detail) : null));
  }));
}
Object.assign(__ds_scope, { StatusTimeline });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/client/StatusTimeline.jsx", error: String((e && e.message) || e) }); }

// components/client/StepBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Four bars on black, one per step. The customer comes twice a year: nothing
   can be learnt, so the step is also written out in words underneath. */
function StepBar({
  total = 4,
  current = 1,
  label,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12,
      fontFamily: 'var(--mb-font-montserrat)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, Array.from({
    length: total
  }).map((_, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    style: {
      flex: 1,
      height: 4,
      background: i < current ? 'var(--pk-accent)' : '#4a4a4a'
    }
  }))), label ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      color: '#a5a5a5'
    }
  }, label) : null);
}
Object.assign(__ds_scope, { StepBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/client/StepBar.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const HEIGHT = {
  small: 41,
  medium: 52,
  large: 60
};
const FONT = {
  small: 13,
  medium: 15,
  large: 16
};
const PAD = {
  small: '0 16px',
  medium: '0 22px',
  large: '0 26px'
};
function skin(variant, tone) {
  if (variant === 'primary') {
    if (tone === 'accent') return {
      background: 'var(--pk-accent)',
      color: '#000',
      border: '1px solid transparent'
    };
    if (tone === 'error') return {
      background: 'var(--pk-error-line)',
      color: '#fff',
      border: '1px solid transparent'
    };
    return {
      background: '#000',
      color: '#fff',
      border: '1px solid transparent'
    };
  }
  if (variant === 'secondary') {
    if (tone === 'accent') return {
      background: 'transparent',
      color: 'var(--pk-accent-ink)',
      border: '1px solid var(--pk-accent)'
    };
    if (tone === 'error') return {
      background: 'transparent',
      color: 'var(--pk-error-ink)',
      border: '1px solid var(--pk-error-line)'
    };
    return {
      background: 'transparent',
      color: 'var(--pk-ink)',
      border: '1px solid var(--pk-border-strong)'
    };
  }
  return {
    background: 'transparent',
    color: tone === 'error' ? 'var(--pk-error-ink)' : 'var(--pk-link)',
    border: '1px solid transparent'
  };
}
function Button({
  children,
  variant = 'primary',
  tone = 'neutral',
  size = 'medium',
  shape = 'rounded',
  fullWidth = false,
  disabled = false,
  startIcon,
  endIcon,
  type = 'button',
  onClick,
  style,
  ...rest
}) {
  const s = skin(variant, tone);
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled,
    onClick: onClick
  }, rest, {
    style: {
      minHeight: HEIGHT[size] || HEIGHT.medium,
      padding: PAD[size] || PAD.medium,
      display: fullWidth ? 'flex' : 'inline-flex',
      width: fullWidth ? '100%' : undefined,
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      fontFamily: 'var(--mb-font-montserrat)',
      fontSize: FONT[size] || FONT.medium,
      fontWeight: variant === 'primary' ? 700 : 600,
      lineHeight: 1.2,
      borderRadius: shape === 'rounded' ? 'var(--pk-radius-pill)' : 'var(--pk-radius-card)',
      whiteSpace: 'nowrap',
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.45 : 1,
      transition: 'background var(--pk-duration-state) var(--pk-easing), color var(--pk-duration-state) var(--pk-easing)',
      ...s,
      ...style
    }
  }), startIcon ? /*#__PURE__*/React.createElement("i", {
    className: startIcon,
    style: {
      fontSize: FONT[size] + 4
    }
  }) : null, children, endIcon ? /*#__PURE__*/React.createElement("i", {
    className: endIcon,
    style: {
      fontSize: FONT[size] + 4
    }
  }) : null);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Callout.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONES = {
  info: {
    surface: 'var(--pk-info-surface)',
    line: 'var(--pk-info-line)',
    ink: 'var(--pk-info-ink)',
    icon: 'ri-information-line'
  },
  success: {
    surface: 'var(--pk-success-surface)',
    line: 'var(--pk-success-line)',
    ink: 'var(--pk-success-ink)',
    icon: 'ri-checkbox-circle-line'
  },
  warning: {
    surface: 'var(--pk-warning-surface)',
    line: 'var(--pk-warning-line)',
    ink: 'var(--pk-warning-ink)',
    icon: 'ri-alert-line'
  },
  error: {
    surface: 'var(--pk-error-surface)',
    line: 'var(--pk-error-line)',
    ink: 'var(--pk-error-ink)',
    icon: 'ri-error-warning-line'
  },
  accent: {
    surface: 'var(--pk-accent-soft)',
    line: 'var(--pk-accent)',
    ink: 'var(--pk-accent-ink)',
    icon: 'ri-lightbulb-line'
  }
};

/* A persistent explanation attached to the thing it concerns. Flat tinted
   surface, 1px line in the tone colour, glyph + text — never colour alone. */
function Callout({
  children,
  tone = 'info',
  icon,
  title,
  edge = false,
  style,
  ...rest
}) {
  const t = TONES[tone] || TONES.info;
  const frame = edge ? {
    background: 'var(--pk-surface)',
    borderLeft: '3px solid ' + t.line
  } : {
    background: t.surface,
    border: '1px solid ' + t.line
  };
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      gap: 10,
      padding: '11px 14px',
      borderRadius: edge ? 0 : 'var(--pk-radius-card)',
      fontFamily: 'var(--mb-font-montserrat)',
      fontSize: 13,
      lineHeight: 1.5,
      color: edge ? 'var(--pk-ink-quiet)' : t.ink,
      ...frame,
      ...style
    }
  }), /*#__PURE__*/React.createElement("i", {
    className: icon || t.icon,
    style: {
      fontSize: 17,
      flexShrink: 0,
      color: edge ? t.ink : 'inherit'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, title ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      fontWeight: 600,
      marginBottom: 3,
      color: t.ink
    }
  }, title) : null, children));
}
Object.assign(__ds_scope, { Callout });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Callout.jsx", error: String((e && e.message) || e) }); }

// components/core/Field.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Label above the field, never a placeholder standing in for it. The field
   height is a min-height so enlarged text is not clipped: 52px on the
   customer front, 38px in a dense workshop panel. A filled-and-focused
   field carries a black border; an untouched one a grey control border. */
function Field({
  label,
  value,
  placeholder,
  hint,
  dense = false,
  focused = false,
  error,
  endIcon,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: dense ? 4 : 6,
      fontFamily: 'var(--mb-font-montserrat)',
      ...style
    }
  }), label ? /*#__PURE__*/React.createElement("span", {
    style: dense ? {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    } : {
      fontSize: 12,
      fontWeight: 700,
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: 'var(--pk-ink-muted)'
    }
  }, label) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: dense ? 38 : 52,
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      padding: dense ? '0 11px' : '0 15px',
      background: 'var(--pk-surface-raised)',
      border: '1px solid ' + (error ? 'var(--pk-error-line)' : focused ? 'var(--pk-border-strong)' : 'var(--pk-border-control)'),
      borderRadius: dense ? 'var(--pk-radius-card)' : 0,
      fontSize: dense ? 14 : 16,
      color: value ? 'var(--pk-ink)' : 'var(--pk-ink-muted)'
    }
  }, value || placeholder, endIcon ? /*#__PURE__*/React.createElement("i", {
    className: endIcon,
    style: {
      fontSize: 16,
      color: 'var(--pk-ink-muted)',
      marginLeft: 'auto'
    }
  }) : null), error ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      fontSize: 13,
      color: 'var(--pk-error-ink)'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-error-warning-line",
    style: {
      fontSize: 15
    }
  }), error) : hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      color: 'var(--pk-ink-quiet)'
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Field });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Field.jsx", error: String((e && e.message) || e) }); }

// components/core/FilterPill.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function FilterPill({
  label,
  count,
  selected = false,
  dashed = false,
  tone = 'neutral',
  icon,
  onClick,
  style,
  ...rest
}) {
  const toneSkin = tone === 'warning' ? {
    background: 'var(--pk-warning-surface)',
    border: '1px solid var(--pk-warning-line)',
    color: 'var(--pk-warning-ink)'
  } : selected ? {
    background: '#000',
    border: '1px solid #000',
    color: '#fff'
  } : {
    background: 'transparent',
    border: (dashed ? '1px dashed ' : '1px solid ') + 'var(--pk-border-control)',
    color: 'var(--pk-ink)'
  };
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    onClick: onClick
  }, rest, {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 7,
      padding: '6px 12px',
      borderRadius: 'var(--pk-radius-pill)',
      fontFamily: 'var(--mb-font-montserrat)',
      fontSize: 12,
      fontWeight: selected || tone === 'warning' ? 600 : 400,
      lineHeight: 1.2,
      whiteSpace: 'nowrap',
      cursor: 'pointer',
      transition: 'background var(--pk-duration-state) var(--pk-easing)',
      ...toneSkin,
      ...style
    }
  }), icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 15
    }
  }) : null, label, count != null ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700
    }
  }, count) : null);
}
Object.assign(__ds_scope, { FilterPill });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/FilterPill.jsx", error: String((e && e.message) || e) }); }

// components/core/StatusBadge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONES = {
  neutral: 'var(--pk-ink-muted)',
  success: 'var(--pk-success-ink)',
  warning: 'var(--pk-warning-line)',
  error: 'var(--pk-error-ink)',
  info: 'var(--pk-info-ink)'
};
function StatusBadge({
  children,
  tone = 'neutral',
  icon,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({}, rest, {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 5,
      fontFamily: 'var(--mb-font-montserrat)',
      fontSize: 11,
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: TONES[tone] || TONES.neutral,
      ...style
    }
  }), icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 14
    }
  }) : null, children);
}
function Counter({
  children,
  tone = 'neutral',
  style,
  ...rest
}) {
  const skin = tone === 'accent' ? {
    background: 'var(--pk-accent)',
    color: '#000'
  } : tone === 'error' ? {
    background: 'var(--pk-error-line)',
    color: '#fff'
  } : {
    background: '#000',
    color: '#fff'
  };
  return /*#__PURE__*/React.createElement("span", _extends({}, rest, {
    style: {
      minWidth: 20,
      height: 20,
      padding: '0 6px',
      borderRadius: 'var(--pk-radius-pill)',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontFamily: 'var(--mb-font-montserrat)',
      fontSize: 11,
      fontWeight: 700,
      ...skin,
      ...style
    }
  }), children);
}
Object.assign(__ds_scope, { StatusBadge, Counter });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/StatusBadge.jsx", error: String((e && e.message) || e) }); }

// components/data/BayCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* A workshop bay. Occupied bays carry a 3px yellow cap and a white surface;
   a free bay is a dashed outline — an empty bay must read as empty at a
   glance, not as a card with no content. */
function BayCard({
  name,
  state = 'occupied',
  vehicle,
  customer,
  note,
  style,
  ...rest
}) {
  const free = state === 'free';
  const down = state === 'down';
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      border: free ? '1px dashed var(--pk-border-control)' : '1px solid var(--pk-border)',
      borderTop: free ? '1px dashed var(--pk-border-control)' : '3px solid ' + (down ? 'var(--pk-error-line)' : 'var(--pk-accent)'),
      borderRadius: 'var(--pk-radius-tile)',
      padding: '10px 12px',
      background: free ? 'transparent' : 'var(--pk-surface-raised)',
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 14,
      fontWeight: 700
    }
  }, name), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: free ? 'var(--pk-success-ink)' : down ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)'
    }
  }, free ? 'Libre' : down ? 'Hors service' : 'Occupé')), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: free ? 400 : 600,
      color: free ? 'var(--pk-ink-quiet)' : 'inherit'
    }
  }, vehicle || 'Disponible'), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    }
  }, customer || '—'), note ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      color: 'var(--pk-ink-muted)',
      borderTop: '1px solid var(--pk-border-quiet)',
      paddingTop: 6
    }
  }, note) : null);
}
Object.assign(__ds_scope, { BayCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/BayCard.jsx", error: String((e && e.message) || e) }); }

// components/data/BayControlCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The bay as a control surface, not just a status: state, configuration
   (activation, mechanic attached) and the day's programme — without going
   through the admin screens. A deactivated bay leaves the occupancy rate and
   the planning, and the card says so. */
function BayControlCard({
  name,
  state = 'occupied',
  spec,
  mechanic,
  programme = [],
  note,
  style,
  ...rest
}) {
  const free = state === 'free';
  const conflict = state === 'conflict';
  const maintenance = state === 'maintenance';
  const label = free ? 'Libre' : conflict ? 'Conflit' : maintenance ? 'Maintenance' : 'Occupé';
  const chip = free ? {
    background: 'var(--pk-success-surface)',
    border: '1px solid var(--pk-success-line)',
    color: 'var(--pk-success-ink)'
  } : conflict ? {
    background: 'var(--pk-error-surface)',
    border: '1px solid var(--pk-error-line)',
    color: 'var(--pk-error-ink)'
  } : maintenance ? {
    background: 'var(--pk-warning-surface)',
    border: '1px solid var(--pk-warning-line)',
    color: 'var(--pk-warning-ink)'
  } : {
    background: 'var(--pk-neutral-surface)',
    border: 'none',
    color: 'var(--pk-ink)'
  };
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      background: maintenance ? 'var(--pk-canvas)' : free ? 'var(--pk-surface-raised)' : 'var(--pk-surface)',
      border: free ? '1px dashed var(--pk-border-control)' : '1px solid ' + (conflict ? 'var(--pk-error-line)' : 'var(--pk-border)'),
      borderTop: free || maintenance ? undefined : '3px solid ' + (conflict ? 'var(--pk-error-line)' : 'var(--pk-accent)'),
      borderRadius: 'var(--pk-radius-card)',
      padding: 14,
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 16,
      fontWeight: 700,
      color: maintenance ? 'var(--pk-ink-quiet)' : 'inherit'
    }
  }, name), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 4,
      padding: '3px 9px',
      borderRadius: 'var(--pk-radius-pill)',
      fontSize: 11,
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '0.05em',
      ...chip
    }
  }, conflict ? /*#__PURE__*/React.createElement("i", {
    className: "ri-error-warning-line",
    style: {
      fontSize: 13
    }
  }) : maintenance ? /*#__PURE__*/React.createElement("i", {
    className: "ri-tools-fill",
    style: {
      fontSize: 13
    }
  }) : null, label), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), spec ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      color: 'var(--pk-ink-muted)'
    }
  }, spec) : null), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 10,
      background: maintenance ? 'var(--pk-surface)' : free ? 'var(--pk-surface)' : 'var(--pk-surface-raised)',
      border: '1px solid var(--pk-border-quiet)',
      borderRadius: 'var(--pk-radius-tile)',
      display: 'flex',
      flexDirection: 'column',
      gap: 7
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 10,
      fontWeight: 700,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: 'var(--pk-ink-muted)'
    }
  }, "Configuration"), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      padding: '4px 10px',
      borderRadius: 'var(--pk-radius-pill)',
      fontSize: 11,
      fontWeight: 600,
      background: maintenance ? 'var(--pk-accent)' : 'transparent',
      color: maintenance ? '#000' : 'var(--pk-ink)',
      border: maintenance ? 'none' : '1px solid var(--pk-border-control)'
    }
  }, maintenance ? 'Activer' : 'Désactiver')), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      height: 32,
      padding: '0 10px',
      border: '1px solid var(--pk-border-control)',
      borderRadius: 'var(--pk-radius-card)',
      fontSize: 12,
      color: mechanic ? 'inherit' : 'var(--pk-ink-quiet)'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-user-line",
    style: {
      fontSize: 14,
      color: 'var(--pk-ink-quiet)'
    }
  }), mechanic || 'Aucun mécanicien rattaché', /*#__PURE__*/React.createElement("i", {
    className: "ri-arrow-down-s-line",
    style: {
      fontSize: 16,
      color: 'var(--pk-ink-quiet)',
      marginLeft: 'auto'
    }
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 5
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 10,
      fontWeight: 700,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: 'var(--pk-ink-muted)'
    }
  }, maintenance ? 'Hors capacité' : 'Programme du jour'), programme.map(p => /*#__PURE__*/React.createElement("div", {
    key: p.time + p.label,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      fontSize: 12,
      color: p.tone === 'error' ? 'var(--pk-error-ink)' : 'inherit'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700,
      width: 34
    }
  }, p.time), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      minWidth: 0,
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      whiteSpace: 'nowrap'
    }
  }, p.label), /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 600,
      color: p.tone === 'error' ? 'var(--pk-error-ink)' : p.state === 'done' ? 'var(--pk-success-ink)' : p.state === 'running' ? 'var(--pk-link)' : 'var(--pk-ink-muted)'
    }
  }, p.status))), note ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    }
  }, note) : null));
}
Object.assign(__ds_scope, { BayControlCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/BayControlCard.jsx", error: String((e && e.message) || e) }); }

// components/data/DataTable.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Dense list of records. Header row on white, hairline separators, every
   other row white. Columns declare their own alignment and renderer; a
   selected-row or total row is a plain `tone` on the row object. */
function DataTable({
  columns = [],
  rows = [],
  rowKey,
  caption,
  footer,
  style,
  ...rest
}) {
  const template = columns.map(c => c.width || '1fr').join(' ');
  const cellBase = {
    fontSize: 13,
    minWidth: 0,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  };
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)',
      display: 'flex',
      flexDirection: 'column',
      overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), caption ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      padding: '12px 16px',
      borderBottom: '1px solid var(--pk-border)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      fontWeight: 600
    }
  }, caption)) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: template,
      alignItems: 'center',
      gap: 12,
      padding: '8px 16px',
      borderBottom: '1px solid var(--pk-border)',
      background: 'var(--pk-surface-raised)',
      fontSize: 10,
      fontWeight: 700,
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: 'var(--pk-ink-muted)'
    }
  }, columns.map(c => /*#__PURE__*/React.createElement("span", {
    key: c.key,
    style: {
      textAlign: c.align || 'left'
    }
  }, c.header))), rows.map((row, i) => /*#__PURE__*/React.createElement("div", {
    key: rowKey ? rowKey(row, i) : i,
    style: {
      display: 'grid',
      gridTemplateColumns: template,
      alignItems: 'center',
      gap: 12,
      padding: '10px 16px',
      borderBottom: '1px solid var(--pk-border-quiet)',
      background: row.tone === 'flagged' ? 'var(--pk-error-surface)' : i % 2 ? 'var(--pk-surface-raised)' : 'transparent'
    }
  }, columns.map(c => /*#__PURE__*/React.createElement("span", {
    key: c.key,
    style: {
      ...cellBase,
      textAlign: c.align || 'left',
      fontWeight: c.strong ? 600 : 400,
      color: c.quiet ? 'var(--pk-ink-quiet)' : 'inherit'
    }
  }, c.render ? c.render(row) : row[c.key])))), footer ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      padding: '11px 16px',
      borderTop: '1px solid var(--pk-border)',
      marginTop: 'auto'
    }
  }, footer) : null);
}
Object.assign(__ds_scope, { DataTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/DataTable.jsx", error: String((e && e.message) || e) }); }

// components/data/KpiTile.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* A counter, and where it leads. Every Paddock tile is a link and the
   destination arrives already filtered on what the number said — a counter
   that leads nowhere is a dead counter. Hover shows the border, never an
   underline: the number must stay a number. */
function KpiTile({
  label,
  value,
  unit,
  note,
  ratio,
  tone = 'neutral',
  onClick,
  style,
  ...rest
}) {
  const alert = tone === 'error';
  const warn = tone === 'warning';
  return /*#__PURE__*/React.createElement("div", _extends({
    role: onClick ? 'button' : undefined,
    tabIndex: onClick ? 0 : undefined,
    onClick: onClick
  }, rest, {
    style: {
      background: warn ? 'var(--pk-warning-surface)' : 'var(--pk-surface)',
      border: '1px solid ' + (alert ? 'var(--pk-error-line)' : warn ? 'var(--pk-accent)' : 'var(--pk-border)'),
      borderRadius: 'var(--pk-radius-card)',
      padding: '14px 16px',
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      transition: 'border-color var(--pk-duration-state) var(--pk-easing)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 600,
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: warn ? 'var(--pk-warning-ink)' : 'var(--pk-ink-muted)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 32,
      fontWeight: 700,
      lineHeight: 1,
      color: alert ? 'var(--pk-error-ink)' : warn ? 'var(--pk-warning-ink)' : 'inherit'
    }
  }, value, unit ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 18,
      fontWeight: 500,
      color: 'var(--pk-ink-muted)'
    }
  }, unit) : null), ratio != null ? /*#__PURE__*/React.createElement("div", {
    style: {
      height: 4,
      background: 'var(--pk-border-quiet)',
      borderRadius: 'var(--pk-radius-pill)',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: Math.round(ratio * 100) + '%',
      height: '100%',
      background: ratio > 0.8 ? 'var(--pk-accent)' : '#000'
    }
  })) : /*#__PURE__*/React.createElement("div", {
    style: {
      height: 4
    }
  }), note ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      fontWeight: alert ? 600 : 400,
      color: alert ? 'var(--pk-error-ink)' : warn ? 'var(--pk-warning-ink)' : 'var(--pk-ink-quiet)',
      display: 'flex',
      alignItems: 'center',
      gap: 5
    }
  }, alert ? /*#__PURE__*/React.createElement("i", {
    className: "ri-error-warning-line",
    style: {
      fontSize: 14
    }
  }) : null, note) : null);
}
Object.assign(__ds_scope, { KpiTile });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/KpiTile.jsx", error: String((e && e.message) || e) }); }

// components/data/QueuePanel.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* « À traiter » — the queue that follows the user across every screen.
   Expanded (320px) where there is room, collapsed to a 52px counter rail
   otherwise. It is never absent: the file is the job. */
function QueuePanel({
  items = [],
  count,
  collapsed = false,
  onToggle,
  footer = 'Voir toute la file →',
  style,
  ...rest
}) {
  if (collapsed) {
    return /*#__PURE__*/React.createElement("aside", _extends({}, rest, {
      style: {
        width: 52,
        flexShrink: 0,
        background: 'var(--pk-surface)',
        borderLeft: '1px solid var(--pk-border)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        padding: '12px 0',
        gap: 14,
        fontFamily: 'var(--mb-font-montserrat)',
        ...style
      }
    }), /*#__PURE__*/React.createElement("button", {
      type: "button",
      onClick: onToggle,
      "aria-label": "D\xE9plier la file \xE0 traiter",
      style: {
        background: 'transparent',
        border: 'none',
        cursor: 'pointer',
        color: 'var(--pk-ink-quiet)',
        display: 'flex'
      }
    }, /*#__PURE__*/React.createElement("i", {
      className: "ri-sidebar-unfold-line",
      style: {
        fontSize: 18
      }
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        width: 28,
        height: 1,
        background: 'var(--pk-border-quiet)'
      }
    }), items.map(it => /*#__PURE__*/React.createElement("div", {
      key: it.title,
      title: it.kind,
      style: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 3
      }
    }, /*#__PURE__*/React.createElement("i", {
      className: it.icon,
      style: {
        fontSize: 18,
        color: 'var(--pk-ink-quiet)'
      }
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        minWidth: 18,
        height: 18,
        padding: '0 5px',
        borderRadius: 'var(--pk-radius-pill)',
        background: it.level === 'critical' ? 'var(--pk-error-line)' : 'var(--pk-neutral-surface)',
        color: it.level === 'critical' ? '#fff' : 'var(--pk-ink)',
        fontSize: 10,
        fontWeight: 700,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }
    }, it.count))), /*#__PURE__*/React.createElement("div", {
      style: {
        writingMode: 'vertical-rl',
        marginTop: 6,
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: '0.1em',
        textTransform: 'uppercase',
        color: 'var(--pk-ink-muted)'
      }
    }, "\xC0 traiter \xB7 ", count));
  }
  return /*#__PURE__*/React.createElement("aside", _extends({}, rest, {
    style: {
      width: 320,
      flexShrink: 0,
      background: 'var(--pk-surface)',
      borderLeft: '1px solid var(--pk-border)',
      display: 'flex',
      flexDirection: 'column',
      overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      padding: '14px 16px',
      borderBottom: '1px solid var(--pk-border)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      fontWeight: 600
    }
  }, "\xC0 traiter"), /*#__PURE__*/React.createElement("span", {
    style: {
      minWidth: 20,
      height: 20,
      padding: '0 6px',
      background: 'var(--pk-error-line)',
      color: '#fff',
      fontSize: 11,
      fontWeight: 700,
      borderRadius: 'var(--pk-radius-pill)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, count), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onToggle,
    "aria-label": "Replier la file \xE0 traiter",
    style: {
      background: 'transparent',
      border: 'none',
      cursor: 'pointer',
      color: 'var(--pk-ink-muted)',
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-sidebar-fold-line",
    style: {
      fontSize: 17
    }
  }))), items.map(it => /*#__PURE__*/React.createElement("div", {
    key: it.title,
    style: {
      padding: '12px 16px',
      borderBottom: '1px solid var(--pk-border-quiet)',
      borderLeft: '3px solid ' + (it.level === 'critical' ? 'var(--pk-error-line)' : it.level === 'watch' ? 'var(--pk-warning-line)' : 'var(--pk-border)'),
      display: 'flex',
      flexDirection: 'column',
      gap: 5
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: it.icon,
    style: {
      fontSize: 15,
      color: 'var(--pk-ink-quiet)'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 700,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: it.level === 'critical' ? 'var(--pk-error-ink)' : it.level === 'watch' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-muted)'
    }
  }, it.kind)), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 600
    }
  }, it.title), it.detail ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    }
  }, it.detail) : null, it.actions ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 6,
      marginTop: 4
    }
  }, it.actions.map((a, i) => /*#__PURE__*/React.createElement("span", {
    key: a,
    style: {
      padding: '4px 10px',
      borderRadius: 'var(--pk-radius-pill)',
      fontSize: 12,
      background: i === 0 ? 'var(--pk-accent)' : 'transparent',
      color: i === 0 ? '#000' : 'var(--pk-ink)',
      border: i === 0 ? 'none' : '1px solid var(--pk-border-control)',
      fontWeight: i === 0 ? 600 : 400
    }
  }, a))) : null)), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '12px 16px',
      borderTop: '1px solid var(--pk-border)',
      fontSize: 12,
      fontWeight: 600,
      color: 'var(--pk-link)'
    }
  }, footer));
}
Object.assign(__ds_scope, { QueuePanel });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/QueuePanel.jsx", error: String((e && e.message) || e) }); }

// components/data/QueueRow.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const EDGE = {
  critical: 'var(--pk-error-line)',
  watch: 'var(--pk-warning-line)',
  normal: 'var(--pk-border)'
};
const INK = {
  critical: 'var(--pk-error-ink)',
  watch: 'var(--pk-warning-line)',
  normal: 'var(--pk-ink-muted)'
};
const LABEL = {
  critical: 'Critique',
  watch: 'À surveiller',
  normal: 'Normal'
};

/* One line of the "file à traiter" queue: what it is, since when, how bad,
   how many. The severity is carried by the left edge AND the written label —
   never by colour alone. */
function QueueRow({
  icon,
  title,
  detail,
  level = 'normal',
  count,
  statusLabel,
  onClick,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: onClick ? 'button' : undefined,
    onClick: onClick
  }, rest, {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      padding: '12px 16px',
      borderBottom: '1px solid var(--pk-border-quiet)',
      borderLeft: '3px solid ' + (EDGE[level] || EDGE.normal),
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      cursor: onClick ? 'pointer' : 'default',
      ...style
    }
  }), icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 18,
      color: 'var(--pk-ink-quiet)'
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 600
    }
  }, title), detail ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    }
  }, detail) : null), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '0.06em',
      color: INK[level]
    }
  }, statusLabel || LABEL[level]), count != null ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 20,
      fontWeight: 700,
      minWidth: 24,
      textAlign: 'right'
    }
  }, count) : null, /*#__PURE__*/React.createElement("i", {
    className: "ri-arrow-right-s-line",
    style: {
      fontSize: 18,
      color: 'var(--pk-ink-muted)'
    }
  }));
}
Object.assign(__ds_scope, { QueueRow });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/QueueRow.jsx", error: String((e && e.message) || e) }); }

// components/data/StatStrip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Four measures on one line, in a single bordered strip with hairline
   dividers. Used when the page's subject is the list underneath: the numbers
   frame it, they are not the content. Distinct from KpiTile, which is a card
   and a link. */
function StatStrip({
  items = [],
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(' + items.length + ', minmax(0, 1fr))',
      background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), items.map((it, i) => /*#__PURE__*/React.createElement("div", {
    key: it.label,
    style: {
      padding: '10px 20px',
      borderRight: i < items.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none',
      display: 'flex',
      flexDirection: 'column',
      gap: 3
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 10,
      fontWeight: 700,
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: it.tone === 'error' ? 'var(--pk-error-ink)' : 'var(--pk-ink-muted)'
    }
  }, it.label), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 22,
      fontWeight: 700,
      lineHeight: 1,
      color: it.tone === 'error' ? 'var(--pk-error-ink)' : 'inherit'
    }
  }, it.value, it.suffix ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      fontWeight: 500,
      color: 'var(--pk-ink-muted)'
    }
  }, " \xB7 ", it.suffix) : null))));
}
Object.assign(__ds_scope, { StatStrip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/StatStrip.jsx", error: String((e && e.message) || e) }); }

// components/icons/icon-data.js
try { (() => {
// Generated by fig_materialize (moduleFormat: 'icon-data') — 19 icon(s)
// as { viewBox, body } SVG-markup entries. Render via the sibling Icon.jsx
// (<Icon name="AddLine" />), or consume the path data directly.
let __ds_default_components_icons_icon_data_12stud1;
try {
  __ds_default_components_icons_icon_data_12stud1 = {
    "AddLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6 6 L 6 0 L 8 0 L 8 6 L 14 6 L 14 8 L 8 8 L 8 14 L 6 14 L 6 8 L 0 8 L 0 6 L 6 6 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5 5)\"/>"
    },
    "ArrowDownSLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6.364 4.95 L 11.314 0 L 12.728 1.414 L 6.364 7.778 L 0 1.414 L 1.414 0 L 6.364 4.95 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5.636 8.222)\"/>"
    },
    "ArrowLeftLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 3.828 6.778 L 16 6.778 L 16 8.778 L 3.828 8.778 L 9.192 14.142 L 7.778 15.556 L 0 7.778 L 7.778 0 L 9.192 1.414 L 3.828 6.778 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 4 4.222)\"/>"
    },
    "ArrowLeftSLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 2.828 6.364 L 7.778 11.314 L 6.364 12.728 L 0 6.364 L 6.364 0 L 7.778 1.414 L 2.828 6.364 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 8 5.636)\"/>"
    },
    "ArrowRightLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 12.172 6.778 L 6.808 1.414 L 8.222 0 L 16 7.778 L 8.222 15.556 L 6.808 14.142 L 12.172 8.778 L 0 8.778 L 0 6.778 L 12.172 6.778 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 4 4.222)\"/>"
    },
    "ArrowRightSLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 4.95 6.364 L 0 1.414 L 1.414 0 L 7.778 6.364 L 1.414 12.728 L 0 11.314 L 4.95 6.364 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 8.222 5.636)\"/>"
    },
    "ArrowUpSLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6.364 2.828 L 1.414 7.778 L 0 6.364 L 6.364 0 L 12.728 6.364 L 11.314 7.778 L 6.364 2.828 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5.636 8)\"/>"
    },
    "CheckFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6.364 9.193 L 15.556 0 L 16.971 1.414 L 6.364 12.021 L 0 5.657 L 1.414 4.243 L 6.364 9.193 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 3.636 5.979)\"/>"
    },
    "CheckboxBlankCircleFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 10 20 C 15.523 20 20 15.523 20 10 C 20 4.477 15.523 0 10 0 C 4.477 0 0 4.477 0 10 C 0 15.523 4.477 20 10 20 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 2 2)\"/>"
    },
    "CloseFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6.364 4.95 L 11.314 0 L 12.728 1.414 L 7.778 6.364 L 12.728 11.314 L 11.314 12.728 L 6.364 7.778 L 1.414 12.728 L 0 11.314 L 4.95 6.364 L 0 1.414 L 1.414 0 L 6.364 4.95 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5.636 5.636)\"/>"
    },
    "CloseLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 6.364 4.95 L 11.314 0 L 12.728 1.414 L 7.778 6.364 L 12.728 11.314 L 11.314 12.728 L 6.364 7.778 L 1.414 12.728 L 0 11.314 L 4.95 6.364 L 0 1.414 L 1.414 0 L 6.364 4.95 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5.636 5.636)\"/>"
    },
    "SearchLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 16.031 14.617 L 20.314 18.899 L 18.899 20.314 L 14.617 16.031 C 13.024 17.308 11.042 18.003 9 18 C 4.032 18 0 13.968 0 9 C 0 4.032 4.032 0 9 0 C 13.968 0 18 4.032 18 9 C 18.003 11.042 17.308 13.024 16.031 14.617 Z M 14.025 13.875 C 15.294 12.57 16.003 10.82 16 9 C 16 5.132 12.867 2 9 2 C 5.132 2 2 5.132 2 9 C 2 12.867 5.132 16 9 16 C 10.82 16.003 12.57 15.294 13.875 14.025 L 14.025 13.875 L 14.025 13.875 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 2 2)\"/>"
    },
    "StarFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 11.413 17.76 L 4.36 21.708 L 5.935 13.78 L 0 8.292 L 8.027 7.34 L 11.413 0 L 14.799 7.34 L 22.826 8.292 L 16.891 13.78 L 18.466 21.708 L 11.413 17.76 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 0.587 0.500)\"/>"
    },
    "StarHalfFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 11.413 15.468 L 15.66 17.845 L 14.711 13.072 L 18.284 9.767 L 13.451 9.194 L 11.413 4.775 L 11.413 15.468 Z M 11.413 17.76 L 4.36 21.708 L 5.935 13.78 L 0 8.292 L 8.027 7.34 L 11.413 0 L 14.799 7.34 L 22.826 8.292 L 16.891 13.78 L 18.466 21.708 L 11.413 17.76 L 11.413 17.76 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 0.587 0.500)\"/>"
    },
    "StarHalfLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 11.413 15.468 L 15.66 17.845 L 14.711 13.072 L 18.284 9.767 L 13.451 9.194 L 11.413 4.775 L 11.413 15.468 Z M 11.413 17.76 L 4.36 21.708 L 5.935 13.78 L 0 8.292 L 8.027 7.34 L 11.413 0 L 14.799 7.34 L 22.826 8.292 L 16.891 13.78 L 18.466 21.708 L 11.413 17.76 L 11.413 17.76 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 0.587 0.500)\"/>"
    },
    "StarLine": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 11.413 17.76 L 4.36 21.708 L 5.935 13.78 L 0 8.292 L 8.027 7.34 L 11.413 0 L 14.799 7.34 L 22.826 8.292 L 16.891 13.78 L 18.466 21.708 L 11.413 17.76 Z M 11.413 15.468 L 15.66 17.845 L 14.711 13.072 L 18.284 9.767 L 13.451 9.194 L 11.413 4.775 L 9.375 9.195 L 4.542 9.767 L 8.115 13.072 L 7.166 17.845 L 11.413 15.468 L 11.413 15.468 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 0.587 0.500)\"/>"
    },
    "SubtractFill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 0 0 L 14 0 L 14 2 L 0 2 L 0 0 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 5 11)\"/>"
    },
    "ShoppingBasket2Line": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 0 0 L 24 0 L 24 24 L 0 24 L 0 0 Z\" fill=\"none\" fill-rule=\"evenodd\"/><path d=\"M 13.366 0 L 16.577 5.562 L 20 5.562 L 20 7.562 L 18.833 7.562 L 18.076 16.645 C 18.055 16.895 17.941 17.128 17.757 17.298 C 17.572 17.468 17.331 17.562 17.08 17.562 L 2.92 17.562 C 2.669 17.562 2.428 17.468 2.243 17.298 C 2.059 17.128 1.945 16.895 1.924 16.645 L 1.166 7.562 L 0 7.562 L 0 5.562 L 3.422 5.562 L 6.634 0 L 8.366 1 L 5.732 5.562 L 14.267 5.562 L 11.634 1 L 13.366 0 L 13.366 0 Z M 16.826 7.562 L 3.173 7.562 L 3.84 15.562 L 16.159 15.562 L 16.826 7.562 Z M 11 9.562 L 11 13.562 L 9 13.562 L 9 9.562 L 11 9.562 Z M 7 9.562 L 7 13.562 L 5 13.562 L 5 9.562 L 7 9.562 Z M 15 9.562 L 15 13.562 L 13 13.562 L 13 9.562 L 15 9.562 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 2 3.438)\"/>"
    },
    "ShoppingBasket2Fill": {
      viewBox: "0 0 24 24",
      body: "<path d=\"M 0 0 L 24 0 L 24 24 L 0 24 L 0 0 Z\" fill=\"none\" fill-rule=\"evenodd\"/><path d=\"M 13.366 0 L 16.577 5.562 L 20 5.562 L 20 7.562 L 18.833 7.562 L 18.076 16.645 C 18.055 16.895 17.941 17.128 17.757 17.298 C 17.572 17.468 17.331 17.562 17.08 17.562 L 2.92 17.562 C 2.669 17.562 2.428 17.468 2.243 17.298 C 2.059 17.128 1.945 16.895 1.924 16.645 L 1.166 7.562 L 0 7.562 L 0 5.562 L 3.422 5.562 L 6.634 0 L 8.366 1 L 5.732 5.562 L 14.267 5.562 L 11.634 1 L 13.366 0 L 13.366 0 Z M 11 9.562 L 9 9.562 L 9 13.562 L 11 13.562 L 11 9.562 Z M 7 9.562 L 5 9.562 L 5 13.562 L 7 13.562 L 7 9.562 Z M 15 9.562 L 13 9.562 L 13 13.562 L 15 13.562 L 15 9.562 Z\" fill=\"currentColor\" fill-rule=\"nonzero\" transform=\"matrix(1 0 0 1 2 3.438)\"/>"
    }
  };
} catch {}
Object.assign(__ds_scope, { __ds_default_components_icons_icon_data_12stud1 });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/icons/icon-data.js", error: String((e && e.message) || e) }); }

__ds_scope.__ds_default_components_icons_icon_data_12stud1$1nb03e1 = __ds_scope.__ds_default_components_icons_icon_data_12stud1;

// components/icons/Icon.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The Motoblouz house glyph set — 19 icons materialised from the Figma kit
   « MB — Components ». They paint in currentColor and carry no colour of
   their own. Where a glyph does not exist (the whole workshop vocabulary:
   pont, OR, moto, planning), Paddock falls back to Remix Icon — see the
   ICONOGRAPHY section of readme.md. */
function Icon({
  name,
  size = 24,
  style,
  ...rest
}) {
  const d = __ds_scope.__ds_default_components_icons_icon_data_12stud1$1nb03e1[name];
  if (!d) return null;
  return /*#__PURE__*/React.createElement("svg", _extends({
    width: size,
    height: size,
    viewBox: d.viewBox,
    fill: "none",
    "aria-hidden": "true",
    focusable: "false",
    style: {
      display: 'block',
      flex: 'none',
      ...style
    },
    dangerouslySetInnerHTML: {
      __html: d.body
    }
  }, rest));
}
const IconNames = Object.keys(__ds_scope.__ds_default_components_icons_icon_data_12stud1$1nb03e1);
Object.assign(__ds_scope, { Icon, IconNames });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/icons/Icon.jsx", error: String((e && e.message) || e) }); }

// components/planning/AppointmentBlock.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const STATES = {
  received: {
    surface: 'var(--pk-success-surface)',
    line: 'var(--pk-success-line)',
    ink: 'var(--pk-success-ink)',
    cap: true
  },
  running: {
    surface: 'var(--pk-success-surface)',
    line: 'var(--pk-success-line)',
    ink: 'var(--pk-success-ink)',
    cap: true
  },
  ready: {
    surface: 'var(--pk-surface-raised)',
    line: 'var(--pk-success-line)',
    ink: 'var(--pk-success-ink)',
    cap: true
  },
  confirmed: {
    surface: 'var(--pk-info-surface)',
    line: 'var(--pk-info-line)',
    ink: 'var(--pk-info-ink)',
    cap: true
  },
  waiting: {
    surface: 'var(--pk-warning-surface)',
    line: 'var(--pk-warning-line)',
    ink: 'var(--pk-warning-ink)',
    cap: true
  },
  open: {
    surface: 'var(--pk-warning-surface)',
    line: 'var(--pk-accent)',
    ink: 'var(--pk-warning-ink)',
    frame: 2
  },
  conflict: {
    surface: 'var(--pk-error-surface)',
    line: 'var(--pk-error-line)',
    ink: 'var(--pk-error-ink)',
    cap: true
  },
  unassigned: {
    surface: 'transparent',
    line: 'var(--pk-border-control)',
    ink: 'var(--pk-ink-muted)',
    dashed: true
  },
  done: {
    surface: 'var(--pk-neutral-surface)',
    line: 'var(--pk-border)',
    ink: 'var(--pk-ink-quiet)'
  }
};

/* One appointment in the grid. The state is read from the coloured edge, the
   written status line and the icon together. Placement is by grid column
   (bay) and row span (duration). */
function AppointmentBlock({
  state = 'confirmed',
  statusLabel,
  icon,
  vehicle,
  detail,
  detailTone,
  note,
  column,
  row,
  span = 2,
  onClick,
  style,
  ...rest
}) {
  const s = STATES[state] || STATES.confirmed;
  const border = s.frame ? '2px solid ' + s.line : s.dashed ? '1px dashed ' + s.line : '1px solid ' + s.line;
  return /*#__PURE__*/React.createElement("div", _extends({
    role: onClick ? 'button' : undefined,
    onClick: onClick
  }, rest, {
    style: {
      gridColumn: column,
      gridRow: row ? row + ' / span ' + span : undefined,
      margin: 3,
      padding: '6px 8px',
      background: s.surface,
      border,
      borderLeft: s.cap ? '3px solid ' + s.line : border,
      borderRadius: 'var(--pk-radius-block)',
      display: 'flex',
      flexDirection: 'column',
      gap: 2,
      overflow: 'hidden',
      cursor: onClick ? 'pointer' : 'default',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 4,
      fontSize: 11,
      fontWeight: 700,
      color: s.ink
    }
  }, icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 13
    }
  }) : null, statusLabel), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      fontWeight: 600
    }
  }, vehicle), detail ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      color: detailTone === 'error' ? 'var(--pk-error-ink)' : detailTone === 'warning' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-quiet)'
    }
  }, detail) : null, note ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 600,
      color: s.ink
    }
  }, note)) : null);
}
Object.assign(__ds_scope, { AppointmentBlock });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/planning/AppointmentBlock.jsx", error: String((e && e.message) || e) }); }

// components/planning/PlanningGrid.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The planning is the workstation: hours down the left, one column per bay,
   appointments placed as blocks. The grid never animates on a day change —
   it is read, not watched. */
function PlanningGrid({
  hours = [],
  bays = [],
  children,
  style,
  ...rest
}) {
  const template = '54px repeat(' + bays.length + ', 1fr)';
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)',
      display: 'flex',
      flexDirection: 'column',
      overflow: 'hidden',
      minHeight: 0,
      flex: 1,
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: template,
      borderBottom: '1px solid var(--pk-border)',
      background: 'var(--pk-surface-raised)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '9px 6px',
      fontSize: 11,
      fontWeight: 700,
      color: 'var(--pk-ink-muted)',
      borderRight: '1px solid var(--pk-border-quiet)'
    }
  }, "Heure"), bays.map((b, i) => /*#__PURE__*/React.createElement("div", {
    key: b.name,
    style: {
      padding: '9px 10px',
      borderRight: i < bays.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      fontWeight: 700
    }
  }, b.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      color: b.tone === 'success' ? 'var(--pk-success-ink)' : 'var(--pk-ink-quiet)'
    }
  }, b.assignee)))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: 'grid',
      gridTemplateColumns: template,
      gridTemplateRows: 'repeat(' + hours.length + ', 1fr)',
      minHeight: 0
    }
  }, hours.map((h, i) => /*#__PURE__*/React.createElement("div", {
    key: h,
    style: {
      gridColumn: 1,
      gridRow: i + 1,
      borderRight: '1px solid var(--pk-border-quiet)',
      borderBottom: i < hours.length - 1 ? '1px solid var(--pk-border-quiet)' : 'none',
      padding: '3px 6px',
      fontSize: 11,
      color: 'var(--pk-ink-muted)'
    }
  }, h)), children));
}
Object.assign(__ds_scope, { PlanningGrid });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/planning/PlanningGrid.jsx", error: String((e && e.message) || e) }); }

// components/shell/IconRail.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The 64px icon rail. Fixed width, no hover expansion: the icons never move.
   A section the workshop does not use leaves the rail — it never becomes a
   greyed entry. */
function IconRail({
  items = [],
  active,
  onSelect,
  logo = null,
  footer,
  user,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("nav", _extends({}, rest, {
    style: {
      width: 'var(--pk-rail-width)',
      flexShrink: 0,
      background: 'var(--pk-surface)',
      borderRight: '1px solid var(--pk-border)',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      padding: '12px 0',
      gap: 2,
      ...style
    }
  }), logo ? /*#__PURE__*/React.createElement("img", {
    src: logo,
    alt: "Paddock",
    style: {
      width: 40,
      height: 40,
      display: 'block',
      flex: 'none',
      marginBottom: 14
    }
  }) : null, items.map(it => {
    const on = it.id === active;
    return /*#__PURE__*/React.createElement("button", {
      key: it.id,
      type: "button",
      title: it.label,
      "aria-label": it.label,
      "aria-current": on ? 'page' : undefined,
      onClick: onSelect ? () => onSelect(it.id) : undefined,
      style: {
        position: 'relative',
        width: 'var(--pk-rail-item)',
        height: 'var(--pk-rail-item)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: on ? 'var(--pk-accent)' : 'transparent',
        color: on ? '#000' : 'var(--pk-ink-quiet)',
        borderRadius: 'var(--pk-radius-card)',
        border: 'none',
        cursor: 'pointer',
        transition: 'background var(--pk-duration-state) var(--pk-easing)'
      }
    }, /*#__PURE__*/React.createElement("i", {
      className: it.icon,
      style: {
        fontSize: 20
      }
    }), it.badge ? /*#__PURE__*/React.createElement("span", {
      style: {
        position: 'absolute',
        top: 6,
        right: 5,
        minWidth: 16,
        height: 16,
        padding: '0 4px',
        background: 'var(--pk-error-line)',
        color: '#fff',
        fontSize: 10,
        fontWeight: 700,
        borderRadius: 'var(--pk-radius-pill)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }
    }, it.badge) : null);
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), footer, user ? /*#__PURE__*/React.createElement("div", {
    style: {
      width: 32,
      height: 32,
      borderRadius: 'var(--pk-radius-pill)',
      background: 'var(--pk-info-line)',
      color: '#fff',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontSize: 12,
      fontWeight: 600,
      marginTop: 8
    }
  }, user) : null);
}
Object.assign(__ds_scope, { IconRail });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/shell/IconRail.jsx", error: String((e && e.message) || e) }); }

// components/shell/PageHeading.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Page title, 4px yellow underline, one line saying what the page answers.
   The yellow bar is the only decoration a page header gets. */
function PageHeading({
  title,
  description,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      justifyContent: 'space-between',
      gap: 16,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 4,
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 28,
      fontWeight: 500,
      letterSpacing: '-0.015em',
      lineHeight: 1.1
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 44,
      height: 4,
      background: 'var(--pk-accent)'
    }
  }), description ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      color: 'var(--pk-ink-quiet)',
      marginTop: 4
    }
  }, description) : null), children);
}

/* Pill tab set used on Stat. Active state is carried by fill and colour,
   never by weight — a weight change shifts the widths of the whole set. */
function PillTabs({
  items = [],
  value,
  onChange,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      gap: 4,
      padding: 3,
      background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-pill)',
      ...style
    }
  }), items.map(it => {
    const on = it.value === value;
    return /*#__PURE__*/React.createElement("button", {
      key: it.value,
      type: "button",
      onClick: onChange ? () => onChange(it.value) : undefined,
      style: {
        padding: '7px 16px',
        borderRadius: 'var(--pk-radius-pill)',
        border: 'none',
        cursor: 'pointer',
        background: on ? '#000' : 'transparent',
        color: on ? '#fff' : 'var(--pk-ink-quiet)',
        fontFamily: 'var(--mb-font-montserrat)',
        fontSize: 13,
        fontWeight: 500,
        display: 'flex',
        alignItems: 'center',
        gap: 6
      }
    }, it.label, it.count != null ? /*#__PURE__*/React.createElement("span", {
      style: {
        minWidth: 18,
        height: 18,
        padding: '0 5px',
        background: 'var(--pk-accent)',
        color: '#000',
        fontSize: 11,
        fontWeight: 700,
        borderRadius: 'var(--pk-radius-pill)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }
    }, it.count) : null);
  }));
}

/* Underlined tab set used inside a working screen (Stat › Explorer). */
function UnderlineTabs({
  items = [],
  value,
  onChange,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 18,
      padding: '0 22px',
      background: 'var(--pk-surface)',
      borderBottom: '1px solid var(--pk-border)',
      fontFamily: 'var(--mb-font-montserrat)',
      ...style
    }
  }), items.map(it => {
    const on = it.value === value;
    return /*#__PURE__*/React.createElement("button", {
      key: it.value,
      type: "button",
      onClick: onChange ? () => onChange(it.value) : undefined,
      style: {
        padding: '13px 2px',
        background: 'transparent',
        border: 'none',
        cursor: 'pointer',
        borderBottom: on ? '2px solid ' + 'var(--pk-ink)' : '2px solid transparent',
        fontSize: 14,
        fontWeight: on ? 600 : 400,
        color: on ? 'var(--pk-ink)' : 'var(--pk-ink-quiet)'
      }
    }, it.label);
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), children);
}
Object.assign(__ds_scope, { PageHeading, PillTabs, UnderlineTabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/shell/PageHeading.jsx", error: String((e && e.message) || e) }); }

// components/shell/SideNav.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The 224px navigation, grouped by trade (Pilotage / Atelier / Commerce).
   The 64px IconRail is this same nav collapsed — the two are one control with
   two states, not two competing models. A module the workshop does not use
   leaves the nav; it never becomes a greyed entry. */
function SideNav({
  groups = [],
  active,
  onSelect,
  workshop,
  onCollapse,
  logo,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("nav", _extends({}, rest, {
    style: {
      width: 224,
      flexShrink: 0,
      background: 'var(--pk-surface)',
      borderRight: '1px solid var(--pk-border)',
      display: 'flex',
      flexDirection: 'column',
      padding: '12px 0',
      overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      margin: '0 12px 16px',
      padding: '8px 10px',
      background: '#000',
      borderRadius: 'var(--pk-radius-card)'
    }
  }, logo ? /*#__PURE__*/React.createElement("img", {
    src: logo,
    alt: "Paddock",
    style: {
      width: 32,
      height: 32,
      display: 'block',
      flex: 'none'
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      fontWeight: 700,
      color: '#fff',
      letterSpacing: '0.14em'
    }
  }, "PADDOCK"), workshop ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      color: '#d4d4d4'
    }
  }, workshop) : null), /*#__PURE__*/React.createElement("i", {
    className: "ri-arrow-down-s-line",
    style: {
      fontSize: 16,
      color: '#a5a5a5',
      marginLeft: 'auto'
    }
  })), groups.map(g => /*#__PURE__*/React.createElement(React.Fragment, {
    key: g.label
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '14px 20px 6px',
      fontSize: 10,
      fontWeight: 700,
      letterSpacing: '0.1em',
      textTransform: 'uppercase',
      color: 'var(--pk-ink-muted)'
    }
  }, g.label), g.items.map(it => {
    const on = it.id === active;
    return /*#__PURE__*/React.createElement("button", {
      key: it.id,
      type: "button",
      onClick: onSelect ? () => onSelect(it.id) : undefined,
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        margin: '0 8px',
        padding: '8px 12px',
        background: on ? 'var(--pk-accent)' : 'transparent',
        color: on ? '#000' : 'var(--pk-ink)',
        border: 'none',
        borderRadius: 'var(--pk-radius-card)',
        cursor: 'pointer',
        textAlign: 'left',
        fontFamily: 'inherit',
        fontSize: 13,
        fontWeight: on ? 600 : 400,
        transition: 'background var(--pk-duration-state) var(--pk-easing)'
      }
    }, /*#__PURE__*/React.createElement("i", {
      className: it.icon,
      style: {
        fontSize: 17,
        color: on ? '#000' : 'var(--pk-ink-quiet)'
      }
    }), it.label, it.badge != null ? /*#__PURE__*/React.createElement("span", {
      style: {
        marginLeft: 'auto',
        minWidth: 20,
        height: 20,
        padding: '0 6px',
        background: it.badgeTone === 'error' ? 'var(--pk-error-line)' : 'var(--pk-neutral-surface)',
        color: it.badgeTone === 'error' ? '#fff' : 'var(--pk-ink)',
        fontSize: 11,
        fontWeight: 700,
        borderRadius: 'var(--pk-radius-pill)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }
    }, it.badge) : null);
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onCollapse,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      margin: '0 8px',
      padding: '8px 12px',
      background: 'transparent',
      border: 'none',
      borderTop: '1px solid var(--pk-border-quiet)',
      cursor: 'pointer',
      fontFamily: 'inherit',
      fontSize: 13,
      color: 'var(--pk-ink)',
      textAlign: 'left'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-contract-left-line",
    style: {
      fontSize: 17,
      color: 'var(--pk-ink-quiet)'
    }
  }), "Replier le menu"));
}
Object.assign(__ds_scope, { SideNav });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/shell/SideNav.jsx", error: String((e && e.message) || e) }); }

// components/shell/SidePanel.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* The work panel. Reception, hand-back and appointment detail are moments of
   an appointment, not pages: they open to the right of the planning, which
   stays readable behind. Black header, yellow left edge, sticky footer with
   the one action that closes the moment. */
function SidePanel({
  icon,
  title,
  subtitle,
  children,
  footer,
  onClose,
  width = 'var(--pk-panel-width)',
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("aside", _extends({}, rest, {
    style: {
      width,
      flexShrink: 0,
      background: 'var(--pk-surface)',
      borderLeft: '2px solid var(--pk-accent)',
      display: 'flex',
      flexDirection: 'column',
      overflow: 'hidden',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '13px 18px',
      background: '#000',
      color: '#f6f6f6',
      display: 'flex',
      alignItems: 'center',
      gap: 10
    }
  }, icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 18,
      color: 'var(--pk-accent)'
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      fontWeight: 600
    }
  }, title), subtitle ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      color: '#d4d4d4'
    }
  }, subtitle) : null), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Fermer le panneau",
    onClick: onClose,
    style: {
      background: 'transparent',
      border: 'none',
      cursor: 'pointer',
      color: '#a5a5a5',
      display: 'flex'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-close-line",
    style: {
      fontSize: 20
    }
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minHeight: 0,
      overflowY: 'auto',
      display: 'flex',
      flexDirection: 'column'
    }
  }, children), footer ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '14px 18px',
      borderTop: '1px solid var(--pk-border)',
      display: 'flex',
      flexDirection: 'column',
      gap: 9
    }
  }, footer) : null);
}

/* A block inside the panel: overline + content, separated by a hairline. */
function PanelSection({
  label,
  children,
  aside,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      padding: '14px 18px',
      borderBottom: '1px solid var(--pk-border-quiet)',
      display: 'flex',
      flexDirection: 'column',
      gap: 9,
      ...style
    }
  }), label ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 700,
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: 'var(--pk-ink-muted)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), aside ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      color: 'var(--pk-ink-quiet)'
    }
  }, aside) : null) : null, children);
}
Object.assign(__ds_scope, { SidePanel, PanelSection });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/shell/SidePanel.jsx", error: String((e && e.message) || e) }); }

// components/shell/TopBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* 52px application header: context on the left, search and actions on the right. */
function TopBar({
  children,
  title,
  workshop,
  live,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("header", _extends({}, rest, {
    style: {
      height: 'var(--pk-header-height)',
      flexShrink: 0,
      background: 'var(--pk-surface)',
      borderBottom: '1px solid var(--pk-border)',
      display: 'flex',
      alignItems: 'center',
      gap: 14,
      padding: '0 20px',
      fontFamily: 'var(--mb-font-montserrat)',
      color: 'var(--pk-ink)',
      ...style
    }
  }), title ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      fontWeight: 600
    }
  }, title) : null, workshop ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      paddingRight: 16,
      borderRight: '1px solid var(--pk-border-quiet)'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-store-2-line",
    style: {
      fontSize: 16,
      color: 'var(--pk-ink-muted)'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      fontWeight: 600
    }
  }, workshop), /*#__PURE__*/React.createElement("i", {
    className: "ri-arrow-down-s-line",
    style: {
      fontSize: 16,
      color: 'var(--pk-ink-muted)'
    }
  })) : null, live ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      fontSize: 12,
      fontWeight: 500,
      color: 'var(--pk-ink-muted)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 7,
      height: 7,
      borderRadius: 'var(--pk-radius-pill)',
      background: 'var(--pk-success-line)'
    }
  }), live) : null, children);
}
function SearchField({
  placeholder = 'Client, immat, n° d’OR…',
  shortcut = '⌘K',
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      height: 32,
      padding: '0 10px',
      border: '1px solid var(--pk-border-control)',
      borderRadius: 'var(--pk-radius-card)',
      color: 'var(--pk-ink-muted)',
      fontSize: 12,
      minWidth: 200,
      ...style
    }
  }), /*#__PURE__*/React.createElement("i", {
    className: "ri-search-line",
    style: {
      fontSize: 15
    }
  }), placeholder, shortcut ? /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: 'auto',
      fontSize: 10,
      border: '1px solid var(--pk-border)',
      borderRadius: 4,
      padding: '1px 4px'
    }
  }, shortcut) : null);
}
function IconAction({
  icon,
  badge,
  label,
  onClick,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": label,
    onClick: onClick
  }, rest, {
    style: {
      position: 'relative',
      width: 36,
      height: 36,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'transparent',
      border: 'none',
      cursor: 'pointer',
      color: 'var(--pk-ink)',
      ...style
    }
  }), /*#__PURE__*/React.createElement("i", {
    className: icon,
    style: {
      fontSize: 18
    }
  }), badge ? /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: 4,
      right: 3,
      minWidth: 16,
      height: 16,
      padding: '0 4px',
      background: 'var(--pk-error-line)',
      color: '#fff',
      fontSize: 10,
      fontWeight: 700,
      borderRadius: 'var(--pk-radius-pill)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, badge) : null);
}
Object.assign(__ds_scope, { TopBar, SearchField, IconAction });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/shell/TopBar.jsx", error: String((e && e.message) || e) }); }

// components/states/EmptyState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* EmptyState — maquette 29a.
 *
 * "Aucune donnée disponible" answers nobody. The question in front of an empty
 * list is "how does it fill up", so the description names the DOOR the data
 * comes through — a client is created when a booking is taken, not here — and
 * the two actions offer both roads, the fast one and the manual one.
 *
 * No decorative illustration, no centring: the block is read left to right
 * like every other block on the screen. React only, no other dependency.
 */

/* Dashed border: a place waiting to be filled, not a place that broke. */
const ROOT = {
  display: 'flex',
  flexDirection: 'column',
  gap: 8,
  padding: 20,
  textAlign: 'left',
  background: 'var(--pk-surface)',
  border: '1px dashed var(--pk-border-control)',
  borderRadius: 'var(--pk-radius-card)'
};
const HEAD = {
  display: 'flex',
  alignItems: 'center',
  gap: 10
};
const ICON = {
  fontSize: 20,
  flexShrink: 0,
  color: 'var(--pk-ink-muted)'
};
const TITLE = {
  fontSize: 15,
  fontWeight: 600,
  color: 'var(--pk-ink)'
};
const TEXT = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 13,
  lineHeight: 1.5,
  color: 'var(--pk-ink-quiet)'
};
const ACTIONS = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--pk-target-gap)',
  marginTop: 4
};

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({
  tone,
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  const accent = tone === 'accent';
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      minHeight: 'var(--pk-target-desk)',
      padding: '0 16px',
      display: 'inline-flex',
      alignItems: 'center',
      borderRadius: 'var(--pk-radius-pill)',
      border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
      background: accent ? 'var(--pk-accent)' : 'transparent',
      color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
      fontFamily: 'inherit',
      fontSize: 13,
      fontWeight: 600,
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, children);
}
function EmptyState({
  icon = 'ri-inbox-line',
  title = 'Rien à afficher ici',
  description = '',
  actionLabel = '',
  secondaryLabel = '',
  onAction,
  onSecondary,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: HEAD
  }, /*#__PURE__*/React.createElement("i", {
    className: icon,
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("div", {
    style: TITLE
  }, title)), description ? /*#__PURE__*/React.createElement("p", {
    style: TEXT
  }, description) : null, actionLabel || secondaryLabel ? /*#__PURE__*/React.createElement("div", {
    style: ACTIONS
  }, actionLabel ? /*#__PURE__*/React.createElement(StateAction, {
    tone: "accent",
    onClick: onAction
  }, actionLabel) : null, secondaryLabel ? /*#__PURE__*/React.createElement(StateAction, {
    onClick: onSecondary
  }, secondaryLabel) : null) : null);
}
Object.assign(__ds_scope, { EmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/EmptyState.jsx", error: String((e && e.message) || e) }); }

// components/states/ErrorState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* ErrorState — maquette 29d.
 *
 * The rule of tour 29: say what happened, what it prevents, and the one action
 * that helps. Two sentences, because they answer two different questions —
 * `description` carries the CAUSE (le serveur n'a pas répondu), `consequence`
 * carries what it cost, which is usually nothing, and that is the first thing
 * anyone wants to know.
 *
 * Two actions, because one locks the user in: retry, and the legitimate way
 * out that lets the work continue without the server ("Voir la feuille du
 * jour"). The timestamped code is not decoration: it is there to be read out
 * on the phone.
 *
 * No decorative illustration, no centring. React only, no other dependency.
 */

/* Tinted fill and a solid edge: something broke, unlike the dashed EmptyState. */
const ROOT = {
  display: 'flex',
  flexDirection: 'column',
  gap: 8,
  padding: 20,
  textAlign: 'left',
  background: 'var(--pk-error-surface)',
  border: '1px solid var(--pk-error-line)',
  borderRadius: 'var(--pk-radius-card)'
};
const HEAD = {
  display: 'flex',
  alignItems: 'center',
  gap: 10
};
const ICON = {
  fontSize: 20,
  flexShrink: 0,
  color: 'var(--pk-error-line)'
};
const TITLE = {
  fontSize: 15,
  fontWeight: 600,
  color: 'var(--pk-error-ink)'
};
const TEXT = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 13,
  lineHeight: 1.5,
  color: 'var(--pk-error-ink)'
};
/* The consequence sits on its own line, quieter: it reassures, it does not alarm. */
const CONSEQUENCE = {
  display: 'block',
  marginTop: 2,
  color: 'var(--pk-ink-quiet)'
};
const ACTIONS = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--pk-target-gap)',
  marginTop: 4
};
const CODE = {
  margin: 0,
  fontSize: 12,
  color: 'var(--pk-ink-muted)'
};

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({
  tone,
  icon,
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  const accent = tone === 'accent';
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      minHeight: 'var(--pk-target-desk)',
      padding: '0 16px',
      display: 'inline-flex',
      alignItems: 'center',
      gap: 7,
      borderRadius: 'var(--pk-radius-pill)',
      border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
      background: accent ? 'var(--pk-accent)' : 'transparent',
      color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
      fontFamily: 'inherit',
      fontSize: 13,
      fontWeight: 600,
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, icon ? /*#__PURE__*/React.createElement("i", {
    className: icon,
    "aria-hidden": "true",
    style: {
      fontSize: 16
    }
  }) : null, children);
}
function ErrorState({
  icon = 'ri-error-warning-line',
  title = 'Chargement impossible',
  description = "Le serveur n'a pas répondu.",
  consequence = "Rien n'a été modifié.",
  actionLabel = 'Réessayer',
  issueLabel = '',
  code = '',
  failedAt = '',
  onRetry,
  onIssue,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "alert"
  }, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: HEAD
  }, /*#__PURE__*/React.createElement("i", {
    className: icon,
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("div", {
    style: TITLE
  }, title)), /*#__PURE__*/React.createElement("p", {
    style: TEXT
  }, description, consequence ? /*#__PURE__*/React.createElement("span", {
    style: CONSEQUENCE
  }, consequence) : null), /*#__PURE__*/React.createElement("div", {
    style: ACTIONS
  }, /*#__PURE__*/React.createElement(StateAction, {
    tone: "accent",
    icon: "ri-refresh-line",
    onClick: onRetry
  }, actionLabel), issueLabel ? /*#__PURE__*/React.createElement(StateAction, {
    onClick: onIssue
  }, issueLabel) : null), code ? /*#__PURE__*/React.createElement("p", {
    style: CODE
  }, 'Erreur ' + code + (failedAt ? ' · ' + failedAt : '') + ' · à donner au support') : null);
}
Object.assign(__ds_scope, { ErrorState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/ErrorState.jsx", error: String((e && e.message) || e) }); }

// components/states/FieldError.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* FieldError — maquette 29f.
 *
 * "Valeur invalide" teaches nobody anything. The template wants the reference
 * value, where it came from and when: « Inférieur au dernier relevé connu :
 * 24 180 km en mars 2026. Un compteur ne recule pas. »
 *
 * And it wants the way out. A replaced odometer really does go backwards, so a
 * flat refusal would force the receptionist to type a false number to move on.
 * `issueLabel` names that legitimate case instead of making it impossible.
 *
 * Icon plus text, never red alone. React only, no other dependency.
 */

const ROOT = {
  display: 'flex',
  alignItems: 'flex-start',
  gap: 6,
  margin: 0,
  textAlign: 'left',
  fontSize: 13,
  lineHeight: 1.45,
  color: 'var(--pk-error-ink)'
};
const ICON = {
  fontSize: 15,
  flexShrink: 0,
  marginTop: 1,
  color: 'var(--pk-error-line)'
};

/* The way out is a link inside the sentence, not a fourth button on the form:
   it belongs to the refused field, and it must not compete with "Valider". */
function IssueLink({
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      display: 'inline',
      marginLeft: 6,
      padding: 0,
      border: 'none',
      background: 'none',
      font: 'inherit',
      fontWeight: 600,
      color: 'var(--pk-link)',
      textDecoration: 'underline',
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, children);
}
function FieldError({
  message = '',
  issueLabel = '',
  onIssue,
  style,
  ...rest
}) {
  if (!message) return null;
  return /*#__PURE__*/React.createElement("p", _extends({
    role: "alert"
  }, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("i", {
    className: "ri-error-warning-line",
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("span", null, message, issueLabel ? /*#__PURE__*/React.createElement(IssueLink, {
    onClick: onIssue
  }, issueLabel) : null));
}
Object.assign(__ds_scope, { FieldError });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/FieldError.jsx", error: String((e && e.message) || e) }); }

// components/states/FilterEmptyState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* FilterEmptyState — maquette 29b.
 *
 * An empty screen and a screen filtered to nothing look alike and do not mean
 * the same thing: here the rows EXIST, the filters hide them. So the template
 * says how many filters are on and, whenever the calling screen can compute it,
 * what one precise removal would bring back — with the figure. Without the
 * figure the user pulls filters at random.
 *
 * Solid border, not dashed: nothing is missing. React only, no other dependency.
 */

const ROOT = {
  display: 'flex',
  flexDirection: 'column',
  gap: 8,
  padding: 20,
  textAlign: 'left',
  background: 'var(--pk-surface)',
  border: '1px solid var(--pk-border)',
  borderRadius: 'var(--pk-radius-card)'
};
const HEAD = {
  display: 'flex',
  alignItems: 'center',
  gap: 10
};
const ICON = {
  fontSize: 20,
  flexShrink: 0,
  color: 'var(--pk-ink-muted)'
};
const TITLE = {
  fontSize: 15,
  fontWeight: 600,
  color: 'var(--pk-ink)'
};
const TEXT = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 13,
  lineHeight: 1.5,
  color: 'var(--pk-ink-quiet)'
};
const ACTIONS = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--pk-target-gap)',
  marginTop: 4
};

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({
  tone,
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  const accent = tone === 'accent';
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      minHeight: 'var(--pk-target-desk)',
      padding: '0 16px',
      display: 'inline-flex',
      alignItems: 'center',
      borderRadius: 'var(--pk-radius-pill)',
      border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
      background: accent ? 'var(--pk-accent)' : 'transparent',
      color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
      fontFamily: 'inherit',
      fontSize: 13,
      fontWeight: 600,
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, children);
}
function FilterEmptyState({
  title = 'Aucun résultat ne correspond',
  filterCount = 0,
  suggestion = null,
  onRemove,
  onClear,
  style,
  ...rest
}) {
  const activeLine = filterCount > 1 ? filterCount + ' filtres sont actifs.' : filterCount + ' filtre est actif.';
  const gainLine = suggestion ? ' En retirant « ' + suggestion.filter + ' », ' + suggestion.count + ' ' + suggestion.noun + ' apparaîtrai' + (suggestion.count > 1 ? 'ent' : 't') + '.' : '';
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: HEAD
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-filter-off-line",
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("div", {
    style: TITLE
  }, title)), /*#__PURE__*/React.createElement("p", {
    style: TEXT
  }, activeLine + gainLine), /*#__PURE__*/React.createElement("div", {
    style: ACTIONS
  }, suggestion ? /*#__PURE__*/React.createElement(StateAction, {
    tone: "accent",
    onClick: () => onRemove && onRemove(suggestion.filter)
  }, 'Retirer « ' + suggestion.filter + ' »') : null, /*#__PURE__*/React.createElement(StateAction, {
    onClick: onClear
  }, "Tout effacer")));
}
Object.assign(__ds_scope, { FilterEmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/FilterEmptyState.jsx", error: String((e && e.message) || e) }); }

// components/states/LoadingState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* 29c — on dessine la forme du tableau attendu, à la taille qu'il prendra.
 Rien ne tourne, rien ne clignote : un disque qui tourne n'apprend rien et
 la page saute quand les données arrivent. Les rangées s'éteignent vers le
 bas une fois pour toutes, sans animation. */

const SR_ONLY = {
  position: 'absolute',
  width: 1,
  height: 1,
  overflow: 'hidden',
  clipPath: 'inset(50%)',
  whiteSpace: 'nowrap'
};

/* Largeur de la première colonne, variée d'une rangée à l'autre : une pile de
 barres identiques ne ressemble à aucune liste réelle. */
const FIRST_COLUMN_FLEX = [1.6, 1.15, 1.85, 1.35];
const FADE_STEP = 0.16;
const FADE_FLOOR = 0.34;
function columnFlex(columnIndex, columnCount, rowIndex) {
  if (columnIndex === 0) return FIRST_COLUMN_FLEX[rowIndex % FIRST_COLUMN_FLEX.length];
  if (columnIndex === columnCount - 1) return 0.6;
  return 1;
}
function range(count) {
  return Array.from({
    length: Math.max(1, count)
  }, (_, index) => index);
}
function LoadingState({
  title = 'Chargement en cours',
  caption,
  columns = 5,
  rows = 6,
  compact = false,
  style,
  ...rest
}) {
  const columnCount = Math.max(1, columns);
  const columnIndexes = range(columnCount);
  const rowIndexes = range(rows);
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "status",
    "aria-busy": "true",
    style: {
      position: 'relative',
      display: 'flex',
      flexDirection: 'column',
      gap: 1,
      background: 'var(--pk-border-quiet)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)',
      overflow: 'hidden',
      ...style
    }
  }, rest), caption ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      padding: '11px 16px',
      background: 'var(--pk-surface)',
      fontSize: 14,
      fontWeight: 600,
      color: 'var(--pk-ink)'
    }
  }, caption) : null, compact ? null : /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 16,
      padding: '12px 16px',
      background: 'var(--pk-surface-raised)'
    },
    "aria-hidden": "true"
  }, columnIndexes.map(columnIndex => /*#__PURE__*/React.createElement("span", {
    key: `head-${columnIndex}`,
    style: {
      flex: columnIndex === 0 ? 1.6 : columnFlex(columnIndex, columnCount, 0),
      maxWidth: 90,
      height: 8,
      borderRadius: 'var(--pk-radius-block)',
      background: 'var(--pk-neutral-surface)'
    }
  }))), rowIndexes.map(rowIndex => /*#__PURE__*/React.createElement("div", {
    key: `row-${rowIndex}`,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 16,
      padding: '13px 16px',
      background: 'var(--pk-surface)'
    },
    "aria-hidden": "true"
  }, columnIndexes.map(columnIndex => /*#__PURE__*/React.createElement("span", {
    key: `cell-${rowIndex}-${columnIndex}`,
    style: {
      flex: columnFlex(columnIndex, columnCount, rowIndex),
      height: 10,
      borderRadius: 'var(--pk-radius-block)',
      background: 'var(--pk-neutral-surface)',
      opacity: Math.max(FADE_FLOOR, 1 - rowIndex * FADE_STEP)
    }
  })))), /*#__PURE__*/React.createElement("span", {
    style: SR_ONLY
  }, title));
}
Object.assign(__ds_scope, { LoadingState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/LoadingState.jsx", error: String((e && e.message) || e) }); }

// components/states/NothingToDo.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* NothingToDo — maquette 29h.
 *
 * "Un vide obtenu se félicite ; il ne se dessine pas comme un manque." An empty
 * queue is a RESULT, not a breakdown: hence the green trio instead of the grey
 * dashed box, the enumeration of what was cleared rather than of what is
 * missing, and the hour of the last item handled — without it, zero reads like
 * a load that failed.
 *
 * React only, no other dependency.
 */

/* Surface + line + ink: the success tokens are always used as a trio. */
const ROOT = {
  display: 'flex',
  flexDirection: 'column',
  gap: 8,
  padding: 20,
  textAlign: 'left',
  background: 'var(--pk-success-surface)',
  border: '1px solid var(--pk-success-line)',
  borderRadius: 'var(--pk-radius-card)'
};
const HEAD = {
  display: 'flex',
  alignItems: 'center',
  gap: 10
};
const ICON = {
  fontSize: 20,
  flexShrink: 0,
  color: 'var(--pk-success-line)'
};
const TITLE = {
  fontSize: 15,
  fontWeight: 600,
  color: 'var(--pk-success-ink)'
};
const TEXT = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 13,
  lineHeight: 1.5,
  color: 'var(--pk-success-ink)'
};
const QUIET = {
  display: 'block',
  marginTop: 2,
  color: 'var(--pk-ink-quiet)'
};
const ACTIONS = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--pk-target-gap)',
  marginTop: 4
};

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      minHeight: 'var(--pk-target-desk)',
      padding: '0 16px',
      display: 'inline-flex',
      alignItems: 'center',
      borderRadius: 'var(--pk-radius-pill)',
      border: '1px solid var(--pk-border-strong)',
      background: 'transparent',
      color: 'var(--pk-ink)',
      fontFamily: 'inherit',
      fontSize: 13,
      fontWeight: 600,
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, children);
}
function NothingToDo({
  title = 'Plus rien en attente',
  description = '',
  lastHandledAt = '',
  actionLabel = '',
  onAction,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: HEAD
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-checkbox-circle-line",
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("div", {
    style: TITLE
  }, title)), /*#__PURE__*/React.createElement("p", {
    style: TEXT
  }, description, lastHandledAt ? /*#__PURE__*/React.createElement("span", {
    style: QUIET
  }, 'Le dernier point a été traité à ' + lastHandledAt + '.') : null), actionLabel ? /*#__PURE__*/React.createElement("div", {
    style: ACTIONS
  }, /*#__PURE__*/React.createElement(StateAction, {
    onClick: onAction
  }, actionLabel)) : null);
}
Object.assign(__ds_scope, { NothingToDo });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/NothingToDo.jsx", error: String((e && e.message) || e) }); }

// components/states/OfflineBanner.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* 29e — le bandeau annonce d'abord ce qui MARCHE ENCORE. Le poste d'atelier
 travaille dans des zones sans couverture : un bandeau qui ne dit que la
 panne fait arrêter le travail sans raison, alors que pointer et
 réceptionner restent possibles hors ligne. Replié par défaut, il informe
 sans manger la hauteur d'un écran tactile. */

const TOGGLE_STYLE = {
  flex: 'none',
  minHeight: 'var(--pk-target-desk)',
  padding: '0 14px',
  border: '1px solid var(--pk-warning-line)',
  borderRadius: 'var(--pk-radius-pill)',
  background: 'transparent',
  color: 'inherit',
  font: 'inherit',
  fontSize: 12,
  fontWeight: 600,
  whiteSpace: 'nowrap',
  cursor: 'pointer',
  outlineOffset: 'var(--pk-focus-offset)'
};
function DetailLine({
  icon,
  ink,
  title,
  detail
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 11,
      padding: '12px 14px',
      background: 'var(--pk-surface)',
      border: '1px solid var(--pk-border)',
      borderRadius: 'var(--pk-radius-card)'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: icon,
    "aria-hidden": "true",
    style: {
      flex: 'none',
      fontSize: 18,
      color: ink
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 600,
      color: 'var(--pk-ink)'
    }
  }, title), detail ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      lineHeight: 1.45,
      color: 'var(--pk-ink-muted)'
    }
  }, detail) : null));
}
function OfflineBanner({
  offline = false,
  since = 'quelques instants',
  stillPossible = 'travailler',
  pending = 0,
  pendingDetail,
  unavailable,
  defaultOpen = false,
  style,
  ...rest
}) {
  const [open, setOpen] = React.useState(defaultOpen);
  const [ringed, setRinged] = React.useState(false);
  if (!offline) return null;
  const pendingLabel = `${pending} action${pending > 1 ? 's' : ''} en attente d’envoi`;
  return /*#__PURE__*/React.createElement("section", _extends({
    role: "status",
    style: {
      background: 'var(--pk-warning-surface)',
      borderBottom: '1px solid var(--pk-warning-line)',
      color: 'var(--pk-warning-ink)',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      padding: '10px 20px'
    }
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-wifi-off-line",
    "aria-hidden": "true",
    style: {
      flex: 'none',
      fontSize: 20
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 600
    }
  }, "Hors ligne depuis ", since), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      lineHeight: 1.45,
      color: 'var(--pk-ink-quiet)'
    }
  }, "Vous pouvez continuer \xE0 ", stillPossible)), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-expanded": open,
    onClick: () => setOpen(!open),
    onFocus: event => setRinged(event.target.matches(':focus-visible')),
    onBlur: () => setRinged(false),
    style: {
      ...TOGGLE_STYLE,
      outline: ringed ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none'
    }
  }, open ? 'Masquer le détail' : 'Voir le détail')), open ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      padding: '0 20px 14px 52px'
    }
  }, pending > 0 ? /*#__PURE__*/React.createElement(DetailLine, {
    icon: "ri-check-line",
    ink: "var(--pk-success-ink)",
    title: pendingLabel,
    detail: pendingDetail
  }) : null, unavailable ? /*#__PURE__*/React.createElement(DetailLine, {
    icon: "ri-close-line",
    ink: "var(--pk-error-ink)",
    title: "Indisponible hors ligne",
    detail: unavailable
  }) : null, /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      fontSize: 12,
      lineHeight: 1.45,
      color: 'var(--pk-ink-quiet)'
    }
  }, "Tout repart automatiquement au retour du r\xE9seau. Aucune saisie n\u2019est perdue.")) : null);
}
Object.assign(__ds_scope, { OfflineBanner });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/OfflineBanner.jsx", error: String((e && e.message) || e) }); }

// components/states/PermissionCallout.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* PermissionCallout — maquette 29g.
 *
 * A refusal that only forbids leaves nobody to ask, and the user will phone the
 * boss anyway. So the template states the CEILING ("vous pouvez accorder
 * jusqu'à 15 %"), names who decides above it, and opens both roads: ask, or
 * come back inside the limit.
 *
 * The note says what asking sets in motion — without it, nobody knows whether
 * something has just left their hands.
 *
 * Warning tint, not error: nothing broke, the limit is working as designed.
 * No decorative illustration, no centring. React only, no other dependency.
 */

const ROOT = {
  display: 'flex',
  flexDirection: 'column',
  gap: 8,
  padding: 20,
  textAlign: 'left',
  background: 'var(--pk-warning-surface)',
  border: '1px solid var(--pk-warning-line)',
  borderRadius: 'var(--pk-radius-card)'
};
const HEAD = {
  display: 'flex',
  alignItems: 'center',
  gap: 10
};
const ICON = {
  fontSize: 20,
  flexShrink: 0,
  color: 'var(--pk-warning-line)'
};
const TITLE = {
  fontSize: 15,
  fontWeight: 600,
  color: 'var(--pk-warning-ink)'
};
const TEXT = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 13,
  lineHeight: 1.5,
  color: 'var(--pk-warning-ink)'
};
const ACTIONS = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--pk-target-gap)',
  marginTop: 4
};
const NOTE = {
  margin: 0,
  maxWidth: '62ch',
  fontSize: 12,
  color: 'var(--pk-ink-quiet)'
};

/* The focus ring is drawn from --pk-focus-*, and only while the button really
   holds keyboard focus — an inline style cannot express :focus-visible. */
function StateAction({
  tone,
  onClick,
  children
}) {
  const [ring, setRing] = React.useState(false);
  const accent = tone === 'accent';
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onFocus: e => setRing(e.currentTarget.matches(':focus-visible')),
    onBlur: () => setRing(false),
    style: {
      minHeight: 'var(--pk-target-desk)',
      padding: '0 16px',
      display: 'inline-flex',
      alignItems: 'center',
      borderRadius: 'var(--pk-radius-pill)',
      border: accent ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-strong)',
      background: accent ? 'var(--pk-accent)' : 'transparent',
      color: accent ? 'var(--pk-accent-ink-deep)' : 'var(--pk-ink)',
      fontFamily: 'inherit',
      fontSize: 13,
      fontWeight: 600,
      cursor: 'pointer',
      outline: ring ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none',
      outlineOffset: 'var(--pk-focus-offset)'
    }
  }, children);
}
function PermissionCallout({
  title = 'Cette action dépasse vos droits',
  description = '',
  requestLabel = '',
  complyLabel = '',
  note = '',
  onRequest,
  onComply,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "alert"
  }, rest, {
    style: {
      ...ROOT,
      ...style
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: HEAD
  }, /*#__PURE__*/React.createElement("i", {
    className: "ri-shield-keyhole-line",
    "aria-hidden": "true",
    style: ICON
  }), /*#__PURE__*/React.createElement("div", {
    style: TITLE
  }, title)), description ? /*#__PURE__*/React.createElement("p", {
    style: TEXT
  }, description) : null, requestLabel || complyLabel ? /*#__PURE__*/React.createElement("div", {
    style: ACTIONS
  }, requestLabel ? /*#__PURE__*/React.createElement(StateAction, {
    tone: "accent",
    onClick: onRequest
  }, requestLabel) : null, complyLabel ? /*#__PURE__*/React.createElement(StateAction, {
    onClick: onComply
  }, complyLabel) : null) : null, note ? /*#__PURE__*/React.createElement("p", {
    style: NOTE
  }, note) : null);
}
Object.assign(__ds_scope, { PermissionCallout });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/states/PermissionCallout.jsx", error: String((e && e.message) || e) }); }

if (__ds_scope.__ds_default_components_icons_icon_data_12stud1$1nb03e1 === undefined) __ds_scope.__ds_default_components_icons_icon_data_12stud1$1nb03e1 = __ds_scope.__ds_default_components_icons_icon_data_12stud1;

__ds_ns.ServiceCard = __ds_scope.ServiceCard;

__ds_ns.SlotGrid = __ds_scope.SlotGrid;

__ds_ns.StatusTimeline = __ds_scope.StatusTimeline;

__ds_ns.StepBar = __ds_scope.StepBar;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Callout = __ds_scope.Callout;

__ds_ns.Field = __ds_scope.Field;

__ds_ns.FilterPill = __ds_scope.FilterPill;

__ds_ns.StatusBadge = __ds_scope.StatusBadge;

__ds_ns.Counter = __ds_scope.Counter;

__ds_ns.BayCard = __ds_scope.BayCard;

__ds_ns.BayControlCard = __ds_scope.BayControlCard;

__ds_ns.DataTable = __ds_scope.DataTable;

__ds_ns.KpiTile = __ds_scope.KpiTile;

__ds_ns.QueuePanel = __ds_scope.QueuePanel;

__ds_ns.QueueRow = __ds_scope.QueueRow;

__ds_ns.StatStrip = __ds_scope.StatStrip;

__ds_ns.Icon = __ds_scope.Icon;

__ds_ns.IconNames = __ds_scope.IconNames;

__ds_ns.AppointmentBlock = __ds_scope.AppointmentBlock;

__ds_ns.PlanningGrid = __ds_scope.PlanningGrid;

__ds_ns.IconRail = __ds_scope.IconRail;

__ds_ns.PageHeading = __ds_scope.PageHeading;

__ds_ns.PillTabs = __ds_scope.PillTabs;

__ds_ns.UnderlineTabs = __ds_scope.UnderlineTabs;

__ds_ns.SideNav = __ds_scope.SideNav;

__ds_ns.SidePanel = __ds_scope.SidePanel;

__ds_ns.PanelSection = __ds_scope.PanelSection;

__ds_ns.TopBar = __ds_scope.TopBar;

__ds_ns.SearchField = __ds_scope.SearchField;

__ds_ns.IconAction = __ds_scope.IconAction;

__ds_ns.EmptyState = __ds_scope.EmptyState;

__ds_ns.ErrorState = __ds_scope.ErrorState;

__ds_ns.FieldError = __ds_scope.FieldError;

__ds_ns.FilterEmptyState = __ds_scope.FilterEmptyState;

__ds_ns.LoadingState = __ds_scope.LoadingState;

__ds_ns.NothingToDo = __ds_scope.NothingToDo;

__ds_ns.OfflineBanner = __ds_scope.OfflineBanner;

__ds_ns.PermissionCallout = __ds_scope.PermissionCallout;

})();
