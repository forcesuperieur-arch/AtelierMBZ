import * as React from 'react';

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
  outlineOffset: 'var(--pk-focus-offset)',
};

function DetailLine({ icon, ink, title, detail }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '12px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
      <i className={icon} aria-hidden="true" style={{ flex: 'none', fontSize: 18, color: ink }} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-ink)' }}>{title}</div>
        {detail ? <div style={{ fontSize: 12, lineHeight: 1.45, color: 'var(--pk-ink-muted)' }}>{detail}</div> : null}
      </div>
    </div>
  );
}

export function OfflineBanner({
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

  return (
    <section
      role="status"
      style={{
        background: 'var(--pk-warning-surface)',
        borderBottom: '1px solid var(--pk-warning-line)',
        color: 'var(--pk-warning-ink)',
        ...style,
      }}
      {...rest}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 20px' }}>
        <i className="ri-wifi-off-line" aria-hidden="true" style={{ flex: 'none', fontSize: 20 }} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 13, fontWeight: 600 }}>Hors ligne depuis {since}</div>
          <div style={{ fontSize: 12, lineHeight: 1.45, color: 'var(--pk-ink-quiet)' }}>Vous pouvez continuer à {stillPossible}</div>
        </div>
        <button
          type="button"
          aria-expanded={open}
          onClick={() => setOpen(!open)}
          onFocus={(event) => setRinged(event.target.matches(':focus-visible'))}
          onBlur={() => setRinged(false)}
          style={{ ...TOGGLE_STYLE, outline: ringed ? 'var(--pk-focus-width) solid var(--pk-focus-ring)' : 'none' }}
        >
          {open ? 'Masquer le détail' : 'Voir le détail'}
        </button>
      </div>

      {open ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '0 20px 14px 52px' }}>
          {pending > 0 ? (
            <DetailLine icon="ri-check-line" ink="var(--pk-success-ink)" title={pendingLabel} detail={pendingDetail} />
          ) : null}
          {unavailable ? (
            <DetailLine icon="ri-close-line" ink="var(--pk-error-ink)" title="Indisponible hors ligne" detail={unavailable} />
          ) : null}
          <p style={{ margin: 0, fontSize: 12, lineHeight: 1.45, color: 'var(--pk-ink-quiet)' }}>
            Tout repart automatiquement au retour du réseau. Aucune saisie n’est perdue.
          </p>
        </div>
      ) : null}
    </section>
  );
}
