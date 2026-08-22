/* L'enveloppe de la refonte : rail sur grand écran, barre en bas sur téléphone.
   Le passage se fait à 900 px de large, mesuré sur le conteneur et non sur la
   fenêtre — le template est monté dans un cadre, pas dans un onglet. */

function PortalShell() {
  const [demo, setDemo] = React.useState(() => window.__pkPortal || {});
  React.useEffect(() => {
    const h = (e) => setDemo(e.detail);
    document.addEventListener('pk-portal-props', h);
    return () => document.removeEventListener('pk-portal-props', h);
  }, []);

  const theme = demo.theme === 'sombre' ? 'dark' : 'light';
  const etat = demo.state || 'normale';
  const largeur = demo.width || 'auto';
  const [ecran, setEcran] = React.useState(demo.startScreen || 'accueil');
  React.useEffect(() => { if (demo.startScreen) setEcran(demo.startScreen); }, [demo.startScreen]);

  const [devisFait, setDevisFait] = React.useState(false);
  const [nonLus, setNonLus] = React.useState(1);

  const ref = React.useRef(null);
  const [w, setW] = React.useState(1100);
  React.useEffect(() => {
    if (!ref.current || typeof ResizeObserver === 'undefined') return;
    const ro = new ResizeObserver((es) => setW(es[0].contentRect.width));
    ro.observe(ref.current);
    return () => ro.disconnect();
  }, []);
  const cadre = largeur === 'téléphone' ? 390 : largeur === 'grand écran' ? 1280 : w;
  const large = cadre >= 900;

  /* Deux variantes du logo. Le suffixe « -light » de l'asset désigne le FOND :
     encre foncée, pour un fond clair. Sans suffixe, l'encre est crème. */
  const mark = theme === 'dark' ? (demo.logoFondSombre || '../../assets/paddock-logo-stacked.svg') : (demo.logoFondClair || '../../assets/paddock-logo-stacked-light.svg');
  const nav = window.PT_NAV;
  const go = (id) => setEcran(id);

  const contenu = () => {
    if (etat === 'chargement') return <PortalSkeleton large={large} />;
    if (etat === 'erreur') return <PortalErreur />;
    if (etat === 'vide') return <PortalVide go={go} ecran={ecran} />;
    switch (ecran) {
      case 'devis': return <window.PortalDevis go={go} fait={devisFait} onAccord={() => { setDevisFait(true); go('accueil'); }} />;
      case 'suivi': return <window.PortalSuivi />;
      case 'messages': return <window.PortalMessages onLu={() => setNonLus(0)} />;
      case 'moto': return <window.PortalMoto />;
      case 'documents': return <window.PortalDocuments />;
      case 'compte': return <window.PortalCompte go={go} large={large} />;
      case 'legal-clauses': case 'legal-confidentialite': case 'legal-cgv': case 'legal-mentions':
        return <window.PortalLegal go={go} page={ecran} large={large} />;
      default: return <window.PortalAccueil go={go} devisFait={devisFait} messagesNonLus={nonLus} />;
    }
  };

  const titre = ecran === 'devis' ? 'Devis'
    : ecran.startsWith('legal-') ? window.PT_LEGAL[ecran].titre
    : (nav.find((n) => n.id === ecran) || nav[0]).label;

  return (
    <div ref={ref} data-theme={theme} style={{ background: 'var(--surface-0)', color: 'var(--content-1)', minHeight: 900, display: 'flex', justifyContent: 'center' }}>
      <div style={{ width: largeur === 'auto' ? '100%' : cadre, maxWidth: '100%', display: 'flex', flexDirection: large ? 'row' : 'column', minHeight: 900, background: 'var(--surface-0)', borderLeft: largeur !== 'auto' ? '1px solid var(--border-2)' : 'none', borderRight: largeur !== 'auto' ? '1px solid var(--border-2)' : 'none' }}>

        {large ? (
          <nav style={{ width: 248, boxSizing: 'border-box', flexShrink: 0, background: 'var(--surface-1)', borderRight: '1px solid var(--border-1)', padding: '24px 16px', display: 'flex', flexDirection: 'column', gap: 28 }}>
            <img src={mark} alt="Paddock" style={{ height: 88, alignSelf: 'flex-start', marginLeft: -6 }} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              {nav.map((n) => {
                const on = n.id === ecran || (ecran === 'devis' && n.id === 'accueil') || (ecran.startsWith('legal-') && n.id === 'compte');
                return (
                  <button key={n.id} onClick={() => go(n.id)} style={{ minHeight: 44, display: 'flex', alignItems: 'center', gap: 12, padding: '0 12px', background: on ? 'var(--surface-inverse)' : 'transparent', color: on ? 'var(--content-inverse)' : 'var(--content-2)', border: 'none', borderRadius: 8, fontSize: 15, fontWeight: on ? 700 : 500, cursor: 'pointer', fontFamily: 'inherit', textAlign: 'left' }}>
                    <i className={n.icon} style={{ fontSize: 19 }}></i>
                    <span style={{ flex: 1 }}>{n.label}</span>
                    {n.id === 'messages' && nonLus > 0 && <span style={{ minWidth: 20, height: 20, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--accent)', color: 'var(--accent-ink)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700 }}>{nonLus}</span>}
                  </button>
                );
              })}
            </div>
            <div style={{ marginTop: 'auto', display: 'flex', flexDirection: 'column', gap: 10, padding: '0 12px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={{ width: 32, height: 32, borderRadius: 'var(--pk-radius-pill)', background: 'var(--surface-3)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 700, color: 'var(--content-2)' }}>TB</span>
                <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                  <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--content-1)' }}>{window.PT_CLIENT.prenom} {window.PT_CLIENT.nom}</span>
                  <span style={{ fontSize: 12, color: 'var(--content-3)' }}>Client depuis 2025</span>
                </div>
              </div>
            </div>
          </nav>
        ) : (
          <header style={{ height: 56, flexShrink: 0, background: 'var(--surface-1)', borderBottom: '1px solid var(--border-1)', display: 'flex', alignItems: 'center', gap: 10, padding: '0 16px', position: 'sticky', top: 0, zIndex: 5 }}>
            {ecran === 'devis' ? (
              <button onClick={() => go('accueil')} style={{ width: 40, height: 40, marginLeft: -8, background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--content-1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }} aria-label="Retour"><i className="ri-arrow-left-line" style={{ fontSize: 20 }}></i></button>
            ) : (
              <img src="../../assets/paddock-logo-symbol.svg" alt="" style={{ height: 26 }} />
            )}
            <span style={{ fontSize: 16, fontWeight: 700 }}>{titre}</span>
          </header>
        )}

        <main style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
          {etat === 'hors-ligne' && (
            <div style={{ background: 'var(--surface-2)', borderBottom: '1px solid var(--border-1)', padding: '12px 20px', display: 'flex', alignItems: 'center', gap: 10, fontSize: 14, color: 'var(--content-2)' }}>
              <i className="ri-wifi-off-line" style={{ fontSize: 17, flexShrink: 0 }}></i>
              <span>Pas de réseau. Ce que vous voyez date de <strong style={{ color: 'var(--content-1)' }}>11 h 12</strong>.</span>
            </div>
          )}
          <div style={{ flex: 1, maxWidth: large ? 720 : 'none', width: '100%', margin: large ? '0 auto' : 0, display: 'flex', flexDirection: 'column' }}>
            {contenu()}
          </div>
        </main>

        {!large && (
          <nav style={{ position: 'sticky', bottom: 0, background: 'var(--surface-1)', borderTop: '1px solid var(--border-1)', display: 'flex', zIndex: 5 }}>
            {nav.map((n) => {
              const on = n.id === ecran || (ecran === 'devis' && n.id === 'accueil') || (ecran.startsWith('legal-') && n.id === 'compte');
              return (
                <button key={n.id} onClick={() => go(n.id)} style={{ flex: 1, minHeight: 60, background: 'transparent', border: 'none', borderTop: '2px solid ' + (on ? 'var(--content-1)' : 'transparent'), cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3, color: on ? 'var(--content-1)' : 'var(--content-3)', fontFamily: 'inherit', position: 'relative' }}>
                  <i className={n.icon} style={{ fontSize: 21 }}></i>
                  <span style={{ fontSize: 10, fontWeight: on ? 700 : 500 }}>{n.label}</span>
                  {n.id === 'messages' && nonLus > 0 && <span style={{ position: 'absolute', top: 8, right: '50%', marginRight: -18, width: 8, height: 8, borderRadius: 'var(--pk-radius-pill)', background: 'var(--accent)' }}></span>}
                </button>
              );
            })}
          </nav>
        )}
      </div>
    </div>
  );
}

/* ---------- États ---------- */

function PortalVide({ go, ecran }) {
  const textes = {
    accueil: ['Bienvenue chez Paddock', 'Votre compte est créé. Prenez un rendez-vous et tout le reste se remplira : le suivi, les documents, le carnet d’entretien.', 'Prendre un rendez-vous', 'ri-calendar-line'],
    suivi: ['Aucune intervention en cours', 'Le suivi s’ouvre le jour où vous déposez la moto. Vous verrez alors chaque étape, l’état des lieux et l’heure de restitution.', 'Prendre un rendez-vous', 'ri-progress-4-line'],
    messages: ['Aucun message', 'L’atelier vous écrit quand il a une question ou une nouvelle. Vous pouvez aussi ouvrir la conversation.', 'Écrire à l’atelier', 'ri-chat-3-line'],
    moto: ['Aucune moto enregistrée', 'Ajoutez votre moto une fois : plaque, modèle, kilométrage. Le carnet d’entretien se remplit ensuite à chaque passage, sans rien faire de votre côté.', 'Ajouter ma moto', 'ri-motorbike-line'],
    documents: ['Aucun document', 'Factures, ordres de réparation et états des lieux arrivent ici automatiquement après chaque intervention.', 'Prendre un rendez-vous', 'ri-file-list-2-line'],
  };
  const [t, p, action, icon] = textes[ecran] || textes.accueil;
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', textAlign: 'center', padding: '48px 28px', gap: 16 }}>
      <span style={{ width: 56, height: 56, borderRadius: 'var(--pk-radius-pill)', background: 'var(--surface-2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <i className={icon} style={{ fontSize: 26, color: 'var(--content-3)' }}></i>
      </span>
      <h1 style={{ ...window.ptH1, fontSize: 22 }}>{t}</h1>
      <p style={{ ...window.ptBody, maxWidth: 420 }}>{p}</p>
      <button style={{ ...window.ptPrimary, width: 'auto', minWidth: 240 }} onClick={() => go('accueil')}>{action}</button>
    </div>
  );
}

function PortalSkeleton({ large }) {
  const bloc = (h, w2) => <div style={{ height: h, width: w2 || '100%', background: 'var(--surface-2)', borderRadius: 6 }}></div>;
  return (
    <div style={{ ...window.ptPage, gap: 20 }}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>{bloc(30, large ? 260 : '68%')}{bloc(18, '52%')}</div>
      <div style={{ ...window.ptCard, display: 'flex', flexDirection: 'column', gap: 14 }}>{bloc(22, '46%')}{bloc(16)}{bloc(16, '82%')}{bloc(56)}</div>
      <div style={{ ...window.ptCard, display: 'flex', flexDirection: 'column', gap: 14 }}>{bloc(20, '40%')}{bloc(4)}{bloc(16, '60%')}</div>
      <span style={{ ...window.ptMeta, fontSize: 12 }}>Chargement — les blocs ont la taille de ce qui arrive, pour que rien ne saute.</span>
    </div>
  );
}

function PortalErreur() {
  return (
    <div style={{ ...window.ptPage, flex: 1, justifyContent: 'center', gap: 18 }}>
      <div style={{ background: 'var(--error-soft)', border: '1px solid var(--error)', borderRadius: 8, padding: 24, display: 'flex', flexDirection: 'column', gap: 14 }}>
        <i className="ri-error-warning-line" style={{ fontSize: 26, color: 'var(--error-content)' }}></i>
        <h1 style={{ ...window.ptH1, fontSize: 22 }}>Impossible de charger votre espace</h1>
        <p style={window.ptBody}>Nos serveurs ne répondent pas. <strong style={{ color: 'var(--content-1)' }}>Rien n’est perdu</strong> : votre rendez-vous, vos documents et l’intervention en cours sont intacts.</p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <button style={window.ptPrimary}>Réessayer</button>
          <a href="tel:0320000000" style={{ ...window.ptGhost, textDecoration: 'none' }}><i className="ri-phone-line" style={{ fontSize: 17 }}></i>Appeler l’atelier — 03 20 00 00 00</a>
        </div>
        <span style={{ ...window.ptMeta, fontSize: 12 }}>Votre moto est à l’atelier, quelqu’un peut répondre. Référence à donner : <strong style={{ color: 'var(--content-2)' }}>PT-5F31</strong>.</span>
      </div>
    </div>
  );
}

/* Point de montage. Le DC publie ses valeurs sur window puis émet l'événement :
   un composant monté depuis le scope global ne reçoit pas les props du DC. */
function PaddockClientPortal() {
  const [v, setV] = React.useState(() => (window.__pkPortal || {}).version || 'refonte');
  React.useEffect(() => {
    const h = (e) => setV(e.detail.version || 'refonte');
    document.addEventListener('pk-portal-props', h);
    return () => document.removeEventListener('pk-portal-props', h);
  }, []);
  if (v === 'code actuel' && window.PaddockClientApp) return <window.PaddockClientApp />;
  return <PortalShell />;
}

Object.assign(window, { PaddockClientPortal, PortalShell, PortalVide, PortalSkeleton, PortalErreur });
