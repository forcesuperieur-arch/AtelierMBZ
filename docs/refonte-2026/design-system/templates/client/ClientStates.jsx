/* Les états du portail client — vide, chargement, erreur, hors ligne.
 *
 * Un client vient deux fois par an. Le jour de son inscription, il voit TOUS
 * les états vides d'affilée : aucun RDV, aucune moto, aucun historique. C'est
 * son premier contact avec le produit, pas un cas limite.
 *
 * Trois règles tenues ici :
 *   — « Aucune donnée » ne répond à personne. Le texte nomme la PORTE par
 *     laquelle la donnée entre : un historique se remplit en venant à l'atelier,
 *     pas en cliquant ici.
 *   — Un vide mérité se dit comme une bonne nouvelle, pas comme un manque.
 *   — Une erreur dit ce qui est perdu, ce qui ne l'est pas, et par où sortir.
 *     Le téléphone de l'atelier est toujours la sortie de secours : la moto est
 *     là-bas, quelqu'un peut répondre.
 */
const stRoot = { display: 'flex', flexDirection: 'column', gap: 8, padding: 20, textAlign: 'left', background: 'var(--surface-1)', borderRadius: 12 };
const stHead = { display: 'flex', alignItems: 'center', gap: 10 };
const stTitle = { fontSize: 15, fontWeight: 700, color: 'var(--content-1)' };
const stText = { margin: 0, maxWidth: '62ch', fontSize: 13, lineHeight: 1.55, color: 'var(--content-3)' };
const stActions = { display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 4 };
const stBtn = { minHeight: 40, padding: '0 16px', display: 'inline-flex', alignItems: 'center', borderRadius: 8, fontFamily: 'inherit', fontSize: 13, fontWeight: 700, cursor: 'pointer' };
const stBtnAccent = { ...stBtn, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none' };
const stBtnGhost = { ...stBtn, background: 'transparent', color: 'var(--content-1)', border: '1px solid var(--border-1)', fontWeight: 600 };

/* Vide en attente : bordure pointillée — une place à remplir, pas une panne. */
function ClientEmpty({ icon, title, text, action, onAction, secondary, onSecondary }) {
  return (
    <div style={{ ...stRoot, border: '1px dashed var(--border-control)' }}>
      <div style={stHead}>
        <i className={icon} aria-hidden="true" style={{ fontSize: 20, flexShrink: 0, color: 'var(--content-3)' }} />
        <div style={stTitle}>{title}</div>
      </div>
      <p style={stText}>{text}</p>
      {action || secondary ? (
        <div style={stActions}>
          {action ? <button type="button" onClick={onAction} style={stBtnAccent}>{action}</button> : null}
          {secondary ? <button type="button" onClick={onSecondary} style={stBtnGhost}>{secondary}</button> : null}
        </div>
      ) : null}
    </div>
  );
}

/* Vide mérité : trait plein vert, pas de pointillé. Rien n'attend d'être rempli. */
function ClientNothing({ title, text }) {
  return (
    <div style={{ ...stRoot, border: '1px solid var(--success)', background: 'var(--success-soft)' }}>
      <div style={stHead}>
        <i className="ri-checkbox-circle-fill" aria-hidden="true" style={{ fontSize: 20, flexShrink: 0, color: 'var(--success-content)' }} />
        <div style={{ ...stTitle, color: 'var(--success-content)' }}>{title}</div>
      </div>
      <p style={{ ...stText, color: 'var(--content-2)' }}>{text}</p>
    </div>
  );
}

/* Chargement : le squelette tient la taille exacte de ce qui arrive, pour que
   rien ne saute quand la donnée se pose. Pas de tourniquet centré. */
function ClientLoading({ rows = 3, height = 74, caption = 'Chargement…' }) {
  return (
    <div aria-busy="true" aria-live="polite" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} style={{ height, borderRadius: 12, background: 'var(--surface-1)', border: '1px solid var(--border-2)', display: 'flex', alignItems: 'center', padding: '0 16px', gap: 12 }}>
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 7 }}>
            <span style={{ height: 12, width: '42%', borderRadius: 4, background: 'var(--surface-3)' }} />
            <span style={{ height: 10, width: '28%', borderRadius: 4, background: 'var(--surface-3)', opacity: 0.7 }} />
          </div>
          <span style={{ height: 22, width: 78, borderRadius: 20, background: 'var(--surface-3)' }} />
        </div>
      ))}
      <span style={{ fontSize: 12, color: 'var(--content-3)' }}>{caption}</span>
    </div>
  );
}

/* Erreur : la cause, ce qui n'est pas perdu, la sortie, et le téléphone. */
function ClientError({ title = 'Impossible d’afficher cette page', text, onRetry }) {
  return (
    <div style={{ ...stRoot, border: '1px solid var(--error)', background: 'var(--error-soft)' }}>
      <div style={stHead}>
        <i className="ri-error-warning-line" aria-hidden="true" style={{ fontSize: 20, flexShrink: 0, color: 'var(--error-content)' }} />
        <div style={{ ...stTitle, color: 'var(--error-content)' }}>{title}</div>
      </div>
      <p style={{ ...stText, color: 'var(--content-2)' }}>{text || 'Nos serveurs n’ont pas répondu. Votre rendez-vous et vos documents ne sont pas perdus : ils sont enregistrés côté atelier, c’est l’affichage qui échoue.'}</p>
      <div style={stActions}>
        <button type="button" onClick={onRetry} style={stBtnAccent}>Réessayer</button>
        <span style={stBtnGhost}><i className="ri-phone-line" style={{ fontSize: 15, marginRight: 6 }} />03 28 00 00 00</span>
      </div>
      <span style={{ fontSize: 11, color: 'var(--content-3)', marginTop: 2 }}>Si le problème persiste, donnez la référence <strong style={{ fontWeight: 700 }}>PK-5031</strong> à l’atelier.</span>
    </div>
  );
}

/* Hors ligne : un motard consulte souvent son suivi dans un parking ou un
   sous-sol. La bannière ne barre pas l'écran — elle date ce qui est affiché,
   parce qu'une page figée ressemble à une page à jour. */
function ClientOffline({ since = '2 minutes' }) {
  return (
    <div role="status" style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 16px', marginBottom: 16, background: 'var(--warning-soft)', border: '1px solid var(--warning)', borderRadius: 12 }}>
      <i className="ri-wifi-off-line" aria-hidden="true" style={{ fontSize: 18, color: 'var(--warning-content)', flexShrink: 0 }} />
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--warning-content)' }}>Hors ligne</div>
        <div style={{ fontSize: 12, color: 'var(--content-3)' }}>Ce que vous voyez date d’il y a {since}. L’écran se remettra à jour tout seul au retour du réseau.</div>
      </div>
    </div>
  );
}

/* Les textes, écran par écran. Séparés des composants : ce sont eux qui
   portent le travail, et ce sont eux qu'on relit. */
const CL_EMPTY = {
  rdvs: { icon: 'ri-calendar-line', title: 'Aucun rendez-vous pour le moment', text: 'Vos rendez-vous apparaissent ici dès qu’ils sont pris — en ligne, au comptoir ou par téléphone. Un rendez-vous pris à l’atelier arrive dans cette liste sans que vous ayez rien à faire.', action: 'Prendre un rendez-vous', secondary: 'Appeler l’atelier' },
  motos: { icon: 'ri-motorbike-line', title: 'Aucune moto enregistrée', text: 'Une moto s’ajoute ici, ou toute seule lors de votre premier passage à l’atelier : nous la créons à partir de votre carte grise. L’enregistrer maintenant fait gagner du temps au comptoir.', action: 'Ajouter une moto' },
  historique: { icon: 'ri-folder-history-line', title: 'Votre historique est vide', text: 'Il se remplit à chaque intervention terminée : les travaux effectués, la date, et l’ordre de réparation en PDF. Rien à faire de votre côté.', secondary: 'Voir mes rendez-vous' },
  passes: { title: 'Aucun rendez-vous passé', text: 'Normal si c’est votre première visite. Vos rendez-vous rejoindront cette liste une fois la moto restituée.' },
};

Object.assign(window, { ClientEmpty, ClientNothing, ClientLoading, ClientError, ClientOffline, CL_EMPTY });
