/* Les écrans du portail connecté : tableau de bord, mes RDV, historique,
   mes motos, mon profil. Recréés depuis `pages/index.vue`, `pages/rdvs/index.vue`,
   `pages/historique.vue`, `pages/motos.vue` et `pages/profil.vue`. */
const cd = () => window;

function DashboardScreen({ onNav, onOpenRdv, state }) {
  if (state === 'chargement') return <div><h1 style={window.clH1}>Bonjour Nadia</h1><window.ClientLoading rows={2} height={96} caption="Chargement de votre espace…" /></div>;
  if (state === 'erreur') return <div><h1 style={window.clH1}>Bonjour Nadia</h1><window.ClientError onRetry={() => onNav('dashboard')} /></div>;
  const vide = state === 'vide';
  const motos = vide ? [] : window.CL_MOTOS;
  const due = vide ? [] : motos.filter((m) => m.prochaineVidange && m.prochaineVidange.due);
  const rdvs = vide ? [] : window.CL_RDVS;
  const prochain = rdvs.filter((r) => r.futur)[0];
  const passes = rdvs.filter((r) => !r.futur).length;
  const dashCard = { ...window.clCard, padding: 16, display: 'block', width: '100%', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' };
  const dashLabel = { fontSize: 12, color: 'var(--content-3)', fontWeight: 600, marginBottom: 6 };
  const dashValue = { fontSize: 18, fontWeight: 800, color: 'var(--accent-content)' };
  return (
    <div>
      <h1 style={window.clH1}>Bonjour Nadia</h1>
      {state === 'hors-ligne' ? <window.ClientOffline /> : null}

      {vide ? (
        <div style={{ marginBottom: 20 }}>
          <window.ClientEmpty icon="ri-hand-heart-line" title="Bienvenue — votre espace est prêt"
            text="Il se remplira au fil de vos passages : vos rendez-vous, vos motos, vos ordres de réparation en PDF. Le plus simple pour commencer est d’enregistrer votre moto : au comptoir, tout ira plus vite."
            action="Ajouter ma moto" onAction={() => onNav('motos')}
            secondary="Prendre un rendez-vous" onSecondary={() => onNav('booking')} />
        </div>
      ) : null}

      {due.map((v) => (
        <div key={v.id} style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '14px 16px', marginBottom: 16, background: 'var(--warning-soft)', border: '1px solid var(--warning)', borderRadius: 12 }}>
          <i className="ri-tools-line" style={{ fontSize: 20, color: 'var(--warning-content)' }} />
          <div>
            <div style={{ fontWeight: 700, fontSize: 14, color: 'var(--warning-content)' }}>Vidange recommandée — {v.marque} {v.modele}</div>
            <div style={{ fontSize: 12, color: 'var(--content-3)', marginTop: 2 }}>{window.clNum(v.kilometrage)} km parcourus, seuil recommandé {window.clNum(v.prochaineVidange.km)} km.</div>
          </div>
          <button type="button" onClick={() => onNav('booking')} style={{ ...window.clPrimaryBtn, marginLeft: 'auto' }}>Prendre rendez-vous</button>
        </div>
      ))}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12 }}>
        <button type="button" onClick={() => (prochain ? onOpenRdv(prochain.id) : onNav('booking'))} style={dashCard}>
          <div style={dashLabel}>Prochain RDV</div>
          <div style={{ ...dashValue, color: prochain ? 'var(--accent-content)' : 'var(--content-3)' }}>{prochain ? 'mar. 26 août 08:00' : 'Aucun'}</div>
          <div style={{ marginTop: 4, fontSize: 12, color: 'var(--content-3)' }}>{prochain ? prochain.vehicule : 'Prendre un rendez-vous'}</div>
        </button>
        <button type="button" onClick={() => onNav('motos')} style={dashCard}>
          <div style={dashLabel}>Motos</div><div style={dashValue}>{motos.length}</div>
        </button>
        <button type="button" onClick={() => onNav('historique')} style={dashCard}>
          <div style={dashLabel}>RDV passés</div><div style={dashValue}>{passes}</div>
        </button>
        <button type="button" onClick={() => onNav('historique')} style={dashCard}>
          <div style={dashLabel}>Historique</div>
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 14, color: 'var(--accent-content)', fontWeight: 700 }}>Voir <i className="ri-arrow-right-line" style={{ fontSize: 16 }} /></div>
        </button>
      </div>

      <div style={{ marginTop: 28, display: motos.length ? 'block' : 'none' }}>
        <h2 style={window.clSectionTitle}>Mes motos</h2>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
          {motos.map((m) => (
            <button type="button" key={m.id} onClick={() => onNav('motos')} style={{ padding: '8px 14px', background: 'var(--overlay-hover)', border: '1px solid var(--border-1)', borderRadius: 999, fontSize: 13, fontWeight: 600, color: 'var(--content-1)', fontFamily: 'inherit', cursor: 'pointer', whiteSpace: 'nowrap' }}>{m.marque} {m.modele}</button>
          ))}
        </div>
      </div>
    </div>
  );
}

function RdvListScreen({ onNav, onOpenRdv, state }) {
  const rdvs = state === 'vide' ? [] : window.CL_RDVS;
  const aVenir = rdvs.filter((r) => r.futur);
  const passes = rdvs.filter((r) => !r.futur);
  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 16, flexWrap: 'wrap' }}>
        <h1 style={{ fontSize: 20, fontWeight: 800 }}>Mes rendez-vous</h1>
        <button type="button" onClick={() => onNav('booking')} style={window.clPrimaryBtn}>Prendre un rendez-vous</button>
      </div>
      {state === 'hors-ligne' ? <window.ClientOffline /> : null}
      {state === 'chargement' ? <window.ClientLoading /> : null}
      {state === 'erreur' ? <window.ClientError title="Impossible de charger vos rendez-vous" onRetry={() => onNav('rdvs')} /> : null}
      {state === 'vide' ? <window.ClientEmpty {...window.CL_EMPTY.rdvs} onAction={() => onNav('booking')} /> : null}
      {state === 'chargement' || state === 'erreur' || state === 'vide' ? null : (
        <React.Fragment>
          <section>
            <h2 style={window.clSectionTitle}>À venir</h2>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {aVenir.map((r) => <window.RdvCard key={r.id} rdv={r} onOpen={() => onOpenRdv(r.id)} />)}
            </div>
          </section>
          <section style={{ marginTop: 28 }}>
            <h2 style={window.clSectionTitle}>Passés</h2>
            {passes.length
              ? <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>{passes.map((r) => <window.RdvCard key={r.id} rdv={r} onOpen={() => onOpenRdv(r.id)} />)}</div>
              : <window.ClientEmpty icon="ri-history-line" title={window.CL_EMPTY.passes.title} text={window.CL_EMPTY.passes.text} />}
          </section>
        </React.Fragment>
      )}
    </div>
  );
}

function HistoriqueScreen({ onNav, state }) {
  const pdfBtn = { display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 10, padding: '6px 12px', border: '1px solid var(--border-1)', borderRadius: 8, fontSize: 12, fontWeight: 600, color: 'var(--content-1)', textDecoration: 'none', whiteSpace: 'nowrap' };
  return (
    <div>
      <h1 style={window.clH1}>Historique</h1>
      {state === 'hors-ligne' ? <window.ClientOffline /> : null}
      {state === 'chargement' ? <window.ClientLoading rows={3} height={110} /> : null}
      {state === 'erreur' ? <window.ClientError title="Impossible de charger votre historique" onRetry={() => onNav('historique')} /> : null}
      {state === 'vide' ? <window.ClientEmpty {...window.CL_EMPTY.historique} onSecondary={() => onNav('rdvs')} /> : null}
      <div style={{ display: state === 'normale' || !state ? 'flex' : 'none', flexDirection: 'column', gap: 10 }}>
        {window.CL_HISTORIQUE.map((it) => (
          <div key={it.id} style={{ ...window.clCard, fontSize: 14 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
              <div>
                <div style={{ color: 'var(--content-3)', fontSize: 12 }}>{it.date}</div>
                <div style={{ color: 'var(--content-1)', fontWeight: 700 }}>{it.vehicule}</div>
              </div>
              <div style={{ fontWeight: 800, color: 'var(--accent-content)', whiteSpace: 'nowrap' }}>N° {it.numero}</div>
            </div>
            <p style={{ margin: '8px 0 0', fontSize: 13, lineHeight: 1.5, color: 'var(--content-2)' }}>{it.travaux}</p>
            <span style={pdfBtn}><i className="ri-file-text-line" style={{ fontSize: 15 }} />Télécharger le PDF</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function MotosScreen({ onNav, state }) {
  const [adding, setAdding] = React.useState(false);
  const [saved, setSaved] = React.useState(null);
  const motoField = { ...window.clField, gap: 5 };
  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 16, flexWrap: 'wrap' }}>
        <h1 style={{ fontSize: 20, fontWeight: 800 }}>Mes motos</h1>
        <button type="button" onClick={() => setAdding(!adding)} style={{ ...window.clPrimaryBtn, background: adding ? 'transparent' : 'var(--accent)', color: adding ? 'var(--content-1)' : 'var(--accent-ink)', border: adding ? '1px solid var(--border-1)' : 'none' }}>{adding ? 'Annuler' : '+ Ajouter une moto'}</button>
      </div>

      {adding ? (
        <div style={{ ...window.clCard, padding: 16, marginBottom: 16 }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12 }}>
            <div style={motoField}><label style={window.clLabel}>Marque *</label><div style={{ ...window.clInput, color: 'var(--content-3)' }}>Yamaha</div></div>
            <div style={motoField}><label style={window.clLabel}>Modèle *</label><div style={{ ...window.clInput, color: 'var(--content-3)' }}>MT-07</div></div>
            <div style={motoField}><label style={window.clLabel}>Plaque</label><div style={{ ...window.clInput, color: 'var(--content-3)' }}>AB-123-CD</div></div>
            <div style={motoField}><label style={window.clLabel}>Type</label><div style={{ ...window.clInput, color: 'var(--content-3)', justifyContent: 'space-between' }}>— Choisir —<i className="ri-arrow-down-s-line" style={{ fontSize: 16 }} /></div></div>
            <div style={motoField}><label style={window.clLabel}>Cylindrée</label><div style={{ ...window.clInput, color: 'var(--content-3)' }}>700</div></div>
            <div style={motoField}><label style={window.clLabel}>Année</label><div style={{ ...window.clInput, color: 'var(--content-3)' }}>2022</div></div>
          </div>
          <p style={{ fontSize: 12, color: 'var(--content-3)', margin: '10px 0 0' }}>Choisissez une suggestion pour préremplir type, cylindrée et année automatiquement.</p>
          <button type="button" onClick={() => setAdding(false)} style={{ ...window.clPrimaryBtn, marginTop: 12, padding: '10px 16px', fontSize: 14 }}>Ajouter</button>
        </div>
      ) : null}

      {state === 'hors-ligne' ? <window.ClientOffline /> : null}
      {state === 'chargement' ? <window.ClientLoading rows={2} height={210} /> : null}
      {state === 'erreur' ? <window.ClientError title="Impossible de charger vos motos" onRetry={() => onNav('motos')} /> : null}
      {state === 'vide' && !adding ? <window.ClientEmpty {...window.CL_EMPTY.motos} onAction={() => setAdding(true)} /> : null}
      <div style={{ display: state === 'normale' || !state ? 'flex' : 'none', flexDirection: 'column', gap: 12 }}>
        {window.CL_MOTOS.map((m) => (
          <div key={m.id} style={{ ...window.clCard, padding: 16 }}>
            <div style={{ fontSize: 16, fontWeight: 800 }}>{m.marque} {m.modele}</div>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 4, fontSize: 12, color: 'var(--content-3)' }}>
              <span>{m.plaque}</span><span>{m.annee}</span><span>{m.cylindree}</span>
            </div>
            <div style={{ ...motoField, marginTop: 12 }}><label style={window.clLabel}>Kilométrage</label><div style={window.clInput}>{window.clNum(m.kilometrage)}</div></div>
            <div style={{ ...motoField, marginTop: 10 }}>
              <label style={window.clLabel}>Notes</label>
              <div style={{ ...window.clInput, minHeight: 56, alignItems: 'flex-start', padding: '10px 12px', color: m.notes ? 'var(--content-1)' : 'var(--content-3)' }}>{m.notes || 'Entretiens perso, particularités…'}</div>
            </div>
            <button type="button" onClick={() => setSaved(m.id)} style={{ ...window.clPrimaryBtn, marginTop: 12, padding: '10px 16px', fontSize: 14 }}>Enregistrer</button>
            {saved === m.id ? <div style={{ marginTop: 8, fontSize: 13, color: 'var(--success-content)' }}>Enregistré.</div> : null}
          </div>
        ))}
      </div>
    </div>
  );
}

function ProfilScreen({ onNav, state }) {
  const [confirmDelete, setConfirmDelete] = React.useState(false);
  if (state === 'chargement') return <div><h1 style={window.clH1}>Mon profil</h1><window.ClientLoading rows={1} height={330} /></div>;
  if (state === 'erreur') return <div><h1 style={window.clH1}>Mon profil</h1><window.ClientError title="Impossible de charger votre profil" onRetry={() => onNav('profil')} /></div>;
  const f = { ...window.clField, marginBottom: 12 };
  return (
    <div>
      <h1 style={window.clH1}>Mon profil</h1>
      {state === 'hors-ligne' ? <window.ClientOffline /> : null}
      <div style={{ ...window.clCard, padding: 20 }}>
        <div style={f}><label style={window.clLabel}>Prénom</label><div style={window.clInput}>Nadia</div></div>
        <div style={f}><label style={window.clLabel}>Nom</label><div style={window.clInput}>Belkacem</div></div>
        <div style={f}><label style={window.clLabel}>Email</label><div style={window.clInput}>n.belkacem@gmail.com</div></div>
        <div style={f}><label style={window.clLabel}>Téléphone</label><div style={window.clInput}>06 12 34 56 78</div></div>
        <div style={f}><label style={window.clLabel}>Adresse</label><div style={{ ...window.clInput, minHeight: 68, alignItems: 'flex-start', padding: '10px 12px' }}>7 rue Jean Bart, 59140 Dunkerque</div></div>
        <button type="button" style={{ ...window.clPrimaryBtn, padding: '11px 18px', fontSize: 14 }}>Enregistrer</button>
      </div>

      <div style={{ marginTop: 24, padding: 20, background: 'var(--error-soft)', border: '1px solid var(--error)', borderRadius: 12 }}>
        <h2 style={{ fontSize: 15, fontWeight: 800, margin: 0, color: 'var(--error-content)' }}>Supprimer mon compte</h2>
        <p style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--content-2)', margin: '8px 0 12px' }}>Vos informations personnelles sont anonymisées et vous ne pourrez plus vous connecter à cet espace. Vos rendez-vous et documents liés à l’atelier sont conservés pour ses obligations légales. Cette action est irréversible.</p>
        {confirmDelete ? (
          <div>
            <p style={{ fontSize: 13, fontWeight: 600, margin: '0 0 10px' }}>Confirmer la suppression définitive de votre compte ?</p>
            <div style={{ display: 'flex', gap: 8 }}>
              <button type="button" style={{ padding: '9px 14px', borderRadius: 8, background: 'var(--error)', color: 'var(--on-error)', border: 'none', fontSize: 13, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer' }}>Oui, supprimer</button>
              <button type="button" onClick={() => setConfirmDelete(false)} style={{ padding: '9px 14px', borderRadius: 8, background: 'transparent', border: '1px solid var(--border-1)', color: 'var(--content-1)', fontSize: 13, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' }}>Annuler</button>
            </div>
          </div>
        ) : (
          <button type="button" onClick={() => setConfirmDelete(true)} style={{ padding: '9px 14px', borderRadius: 8, background: 'transparent', border: '1px solid var(--error)', color: 'var(--error-content)', fontSize: 13, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer' }}>Supprimer mon compte</button>
        )}
      </div>
    </div>
  );
}

Object.assign(window, { DashboardScreen, RdvListScreen, HistoriqueScreen, MotosScreen, ProfilScreen });
