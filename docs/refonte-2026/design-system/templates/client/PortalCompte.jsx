/* Mon compte + les pages légales.
   Aligne `client-frontend/pages/profil.vue`, `clauses.vue` et
   `politique-confidentialite.vue` sur la refonte. Le code actuel empile un
   formulaire nu et une zone rouge ; ici la suppression de compte est traitée
   comme ce qu'elle est : une décision qui mérite d'être comprise avant d'être
   prise, avec ce qui disparaît et ce qui reste, séparés. */

function PtChamp({ label, valeur, type, aide, large }) {
  return (
    <label style={{ display: 'flex', flexDirection: 'column', gap: 7, gridColumn: large ? 'span 2' : 'auto' }}>
      <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--content-2)' }}>{label}</span>
      {type === 'zone' ? (
        <textarea defaultValue={valeur} rows="3" style={{ font: 'inherit', fontSize: 15, minHeight: 76, padding: '12px 14px', background: 'var(--surface-0)', color: 'var(--content-1)', border: '1px solid var(--border-2)', borderRadius: 6, resize: 'none' }}></textarea>
      ) : (
        <input defaultValue={valeur} type={type || 'text'} style={{ font: 'inherit', fontSize: 15, minHeight: 44, padding: '0 14px', background: 'var(--surface-0)', color: 'var(--content-1)', border: '1px solid var(--border-2)', borderRadius: 6 }} />
      )}
      {aide ? <span style={{ ...window.ptMeta, fontSize: 12 }}>{aide}</span> : null}
    </label>
  );
}

function PortalCompte({ go, large }) {
  const c = window.PT_CLIENT;
  const [enregistre, setEnregistre] = React.useState(false);
  const [etape, setEtape] = React.useState(0);

  return (
    <div style={window.ptPage}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <h1 style={window.ptH1}>Mon compte</h1>
        <p style={window.ptBody}>Ces informations servent à vous joindre pendant une intervention et à établir vos factures.</p>
      </div>

      <div style={{ ...window.ptCard, display: 'flex', flexDirection: 'column', gap: 18 }}>
        <div style={{ display: 'grid', gridTemplateColumns: large ? '1fr 1fr' : '1fr', gap: 16 }}>
          <PtChamp label="Prénom" valeur={c.prenom} />
          <PtChamp label="Nom" valeur={c.nom} />
          <PtChamp label="E-mail" valeur={c.email} type="email" aide="Sert aussi d’identifiant de connexion." large={large} />
          <PtChamp label="Téléphone" valeur={c.tel} type="tel" aide="C’est ce numéro que l’atelier appelle si une question bloque l’intervention." large={large} />
          <PtChamp label="Adresse" valeur={c.adresse} type="zone" large={large} />
        </div>
        <div style={{ display: 'flex', flexDirection: large ? 'row' : 'column', alignItems: large ? 'center' : 'stretch', gap: 12 }}>
          <button style={{ ...window.ptPrimary, width: large ? 'auto' : '100%', minWidth: 200 }} onClick={() => setEnregistre(true)}>Enregistrer</button>
          {enregistre ? (
            <span style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 14, color: 'var(--success-content)' }}>
              <i className="ri-check-line" style={{ fontSize: 17 }}></i>Modifications enregistrées
            </span>
          ) : null}
        </div>
      </div>

      <div style={{ ...window.ptCard, display: 'flex', flexDirection: 'column', gap: 14 }}>
        <h2 style={window.ptH2}>Connexion</h2>
        <div style={{ display: 'flex', flexDirection: large ? 'row' : 'column', gap: 12 }}>
          <button style={{ ...window.ptGhost, width: large ? 'auto' : '100%', minWidth: 220 }}><i className="ri-lock-line" style={{ fontSize: 17 }}></i>Changer mon mot de passe</button>
          <button style={{ ...window.ptGhost, width: large ? 'auto' : '100%', minWidth: 180 }}><i className="ri-logout-box-r-line" style={{ fontSize: 17 }}></i>Me déconnecter</button>
        </div>
      </div>

      {/* La suppression : ce qui disparaît et ce qui reste, séparés — l'atelier a
          des obligations de conservation, un client a le droit de le savoir avant
          de cliquer, pas dans un paragraphe après. */}
      <div style={{ background: 'var(--surface-1)', border: '1px solid var(--border-1)', borderRadius: 8, padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
        <h2 style={window.ptH2}>Supprimer mon compte</h2>
        <div style={{ display: 'grid', gridTemplateColumns: large ? '1fr 1fr' : '1fr', gap: 14 }}>
          <div style={{ background: 'var(--error-soft)', border: '1px solid var(--error-line)', borderRadius: 6, padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ ...window.ptLabel, color: 'var(--error-content)' }}>Ce qui disparaît</span>
            <span style={{ ...window.ptBody, fontSize: 14 }}>Vos nom, adresse, e-mail et téléphone sont anonymisés. Vous ne pouvez plus vous connecter à cet espace ni consulter vos documents en ligne.</span>
          </div>
          <div style={{ background: 'var(--surface-2)', border: '1px solid var(--border-2)', borderRadius: 6, padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={window.ptLabel}>Ce qui reste</span>
            <span style={{ ...window.ptBody, fontSize: 14 }}>Les interventions faites sur votre moto et les factures émises restent chez l’atelier : la loi l’y oblige. Une facture reste demandable au comptoir.</span>
          </div>
        </div>
        {etape === 0 ? (
          <button style={{ ...window.ptGhost, width: large ? 'auto' : '100%', minWidth: 240, color: 'var(--error-content)', borderColor: 'var(--error-line)' }} onClick={() => setEtape(1)}>Supprimer mon compte</button>
        ) : etape === 1 ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <span style={{ ...window.ptBody, fontSize: 14, color: 'var(--content-1)', fontWeight: 600 }}>Pour confirmer, tapez <strong>SUPPRIMER</strong> ci-dessous. L’action est définitive.</span>
            <input placeholder="SUPPRIMER" style={{ font: 'inherit', fontSize: 15, minHeight: 44, padding: '0 14px', maxWidth: large ? 260 : 'none', background: 'var(--surface-0)', color: 'var(--content-1)', border: '1px solid var(--border-2)', borderRadius: 6 }} />
            <div style={{ display: 'flex', flexDirection: large ? 'row' : 'column', gap: 10 }}>
              <button style={{ ...window.ptGhost, width: large ? 'auto' : '100%', minWidth: 200, background: 'var(--error)', color: '#fff', borderColor: 'var(--error)' }} onClick={() => setEtape(2)}>Oui, supprimer définitivement</button>
              <button style={{ ...window.ptGhost, width: large ? 'auto' : '100%', minWidth: 120 }} onClick={() => setEtape(0)}>Annuler</button>
            </div>
          </div>
        ) : (
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10, background: 'var(--surface-2)', borderRadius: 6, padding: '14px 16px' }}>
            <i className="ri-time-line" style={{ fontSize: 19, color: 'var(--content-3)', flexShrink: 0 }}></i>
            <span style={{ ...window.ptBody, fontSize: 14 }}>Demande enregistrée. Votre compte est fermé sous 30 jours ; d’ici là, une reconnexion l’annule. Un e-mail de confirmation part maintenant.</span>
          </div>
        )}
      </div>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px 20px', paddingTop: 4 }}>
        {[['legal-clauses', 'Clauses particulières'], ['legal-confidentialite', 'Confidentialité'], ['legal-cgv', 'Conditions générales'], ['legal-mentions', 'Mentions légales']].map(([id, l]) => (
          <button key={id} onClick={() => go(id)} style={{ font: 'inherit', fontSize: 13, color: 'var(--content-3)', background: 'none', border: 'none', padding: 0, cursor: 'pointer', textDecoration: 'underline', textUnderlineOffset: 3 }}>{l}</button>
        ))}
      </div>
    </div>
  );
}

/* ---------- Pages légales ---------- */

/* Le code actuel rend une table des matières en puces et un mur de texte. Ici
   chaque article s'ouvre sur la phrase qui compte, le reste est dépliable : un
   client cherche une réponse, il ne lit pas un contrat. */
function PortalLegal({ go, page, large }) {
  const doc = window.PT_LEGAL[page];
  const [ouvert, setOuvert] = React.useState(0);
  return (
    <div style={{ ...window.ptPage, maxWidth: 760 }}>
      <button onClick={() => go('compte')} style={{ font: 'inherit', display: 'flex', alignItems: 'center', gap: 7, minHeight: 44, alignSelf: 'flex-start', background: 'none', border: 'none', padding: 0, color: 'var(--content-3)', fontSize: 14, cursor: 'pointer' }}>
        <i className="ri-arrow-left-line" style={{ fontSize: 17 }}></i>Mon compte
      </button>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <h1 style={window.ptH1}>{doc.titre}</h1>
        <p style={window.ptBody}>{doc.intro}</p>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, paddingTop: 4 }}>
          <window.PtChip tone="neutre">{doc.version}</window.PtChip>
          <span style={{ ...window.ptMeta, fontSize: 12 }}>{doc.regle}</span>
        </div>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        {doc.articles.map((a, i) => {
          const on = ouvert === i;
          return (
            <div key={a.titre} style={{ background: 'var(--surface-1)', border: '1px solid var(--border-1)', borderRadius: 8, overflow: 'hidden' }}>
              <button onClick={() => setOuvert(on ? -1 : i)} style={{ font: 'inherit', width: '100%', minHeight: 56, padding: '14px 18px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 14, background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}>
                <span style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--content-1)' }}>{a.titre}</span>
                  <span style={{ ...window.ptMeta, fontSize: 13, textWrap: 'pretty' }}>{a.cle}</span>
                </span>
                <i className={on ? 'ri-subtract-line' : 'ri-add-line'} style={{ fontSize: 19, color: 'var(--content-3)', flexShrink: 0 }}></i>
              </button>
              {on ? <p style={{ ...window.ptBody, fontSize: 14, padding: '0 18px 18px', borderTop: '1px solid var(--border-2)', paddingTop: 14 }}>{a.texte}</p> : null}
            </div>
          );
        })}
      </div>
      <p style={{ ...window.ptMeta, fontSize: 12 }}>Une question sur un point précis ? L’atelier répond au 03 20 00 00 00 ou par la messagerie de votre espace.</p>
    </div>
  );
}

Object.assign(window, { PortalCompte, PortalLegal, PtChamp });
