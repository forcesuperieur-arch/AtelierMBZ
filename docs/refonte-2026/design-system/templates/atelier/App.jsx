/* Racine du front atelier — le shell (rail, en-tête, colonne de file) et le
   routage des 26 écrans. Les réglages exposés en Tweaks arrivent en props. */
const DS_A = window.PaddockDesignSystem_8059f4;
const { IconRail, SideNav, TopBar, SearchField, IconAction, QueuePanel, Button } = DS_A;

const QUEUE_STATE = { stat: null, planning: null, explorer: null, bench: null, reception: 'expanded', bays: 'collapsed', clients: 'collapsed' };
const RAIL_OF = { explorer: 'stat', periode: 'stat', analyse: 'stat', client: 'clients', 'devis-composer': 'devis' };

const HEADER = {
  stat: { context: 'Données en direct · 14:32', action: 'Nouveau RDV' },
  planning: { title: 'Planning', action: 'RDV' },
  reception: { title: 'Réception', meta: 'Vendredi 15 août · 08:12', action: 'Nouveau RDV' },
  bays: { title: 'Ponts & Méca', meta: 'Mis à jour à l’instant', action: 'Ouvrir le planning', plain: true },
  clients: { title: 'Clients', meta: '1 284 fiches', action: 'Fiche client', plain: true, go: 'client' },
  explorer: { title: 'Stat', meta: 'Explorer', action: 'Nouveau RDV' },
  periode: { title: 'Stat', action: 'Nouveau RDV' },
  analyse: { title: 'Stat', action: 'Nouveau RDV' },
  client: { title: 'Clients', meta: 'Nadia Belkacem', action: 'Nouveau RDV' },
  motos: { title: 'Motos', meta: 'GT-908-ZK', action: 'Nouveau RDV' },
  devis: { title: 'Devis', meta: '11 en attente', action: 'Nouveau devis', go: 'devis-composer' },
  'devis-composer': { title: 'Devis', meta: 'DV-2447 · brouillon · enregistré il y a 4 s', action: 'Retour à la liste', plain: true, go: 'devis' },
  factures: { title: 'Factures', meta: 'Août 2026', action: 'Nouvelle facture' },
  stock: { title: 'Stock', meta: '412 références · 4 sous le seuil', action: 'Nouvelle pièce' },
  rdv: { title: 'Prise de RDV', meta: 'Comptoir', action: 'Voir le planning', plain: true, go: 'planning' },
  restitution: { title: 'En atelier', meta: 'Restitution · OR 2418', action: 'Voir le planning', plain: true, go: 'planning' },
  etat: { title: 'Réception', meta: 'État des lieux · OR 2431', action: 'Retour à la réception', plain: true, go: 'reception' },
};

const BUILT = ['stat', 'planning', 'reception', 'bays', 'clients', 'client', 'motos', 'explorer', 'bench',
  'periode', 'analyse', 'devis', 'devis-composer', 'factures', 'stock', 'admin', 'rdv', 'restitution', 'etat'];

function PaddockAtelierApp(props) {
  const p = props || {};
  const logo = p.logo || '../../assets/paddock-logo-favicon.svg';
  const workshop = p.workshopName || 'Atelier Principal';
  const dark = p.theme === 'sombre';
  const scale = p.density === 'compacte' ? 0.92 : p.density === 'large' ? 1.06 : 1;

  const [screen, setScreen] = React.useState(p.startScreen || 'stat');
  const [panel, setPanel] = React.useState('reception');
  const [collapsedNav, setCollapsedNav] = React.useState(p.railCollapsed !== false);
  const [queueOpen, setQueueOpen] = React.useState(true);
  const [adminPage, setAdminPage] = React.useState('config');
  const [rdvPanel, setRdvPanel] = React.useState(null);
  React.useEffect(() => { setCollapsedNav(p.railCollapsed !== false); }, [p.railCollapsed]);

  const bench = screen === 'bench';
  const admin = screen === 'admin';
  const h = HEADER[screen] || HEADER.stat;
  const queueMode = QUEUE_STATE[screen];
  const go = (id) => { if (BUILT.indexOf(id) !== -1) setScreen(id); };
  const statTab = (t) => go(t === 'Atelier' ? 'stat' : t === 'Explorer' ? 'explorer' : t === 'Période' ? 'periode' : 'analyse');
  const frame = { zoom: scale === 1 ? undefined : scale };

  if (admin) return <div style={frame}><window.AdminScreen page={adminPage} onPage={setAdminPage} onExit={() => setScreen('stat')} logo={logo} /></div>;

  return (
    <div className={bench || dark ? 'pk-workshop' : undefined} style={{ ...frame, width: 1440, height: 900, display: 'flex', background: 'var(--pk-canvas)', overflow: 'hidden' }}>
      {collapsedNav ? (
        <IconRail logo={logo} user="JD" active={RAIL_OF[screen] || screen}
          items={window.RAIL_ITEMS} onSelect={go}
          footer={<IconAction icon="ri-contract-right-line" label="Déplier le menu" onClick={() => setCollapsedNav(false)} style={{ width: 44, height: 44, color: 'var(--pk-ink-quiet)' }} />} />
      ) : (
        <SideNav logo={logo} workshop={workshop} groups={window.NAV_GROUPS}
          active={RAIL_OF[screen] || screen} onSelect={go} onCollapse={() => setCollapsedNav(true)} />
      )}

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, position: 'relative' }}>
        {bench ? null : (
          <TopBar title={h.title} workshop={h.title ? undefined : workshop} live={h.context}>
            {h.meta ? <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{h.meta}</span> : null}
            {screen === 'planning' ? <>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 13, whiteSpace: 'nowrap' }}>
                <i className="ri-arrow-left-s-line" style={{ fontSize: 16 }} />Ven. 15 août<i className="ri-arrow-right-s-line" style={{ fontSize: 16 }} />
              </div>
              <div style={{ display: 'flex', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', overflow: 'hidden' }}>
                <span style={{ padding: '6px 13px', fontSize: 12, background: '#000', color: '#fff' }}>Jour</span>
                <span style={{ padding: '6px 13px', fontSize: 12, borderLeft: '1px solid var(--pk-border-control)' }}>Semaine</span>
              </div>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}>08:00 → 18:30 · pause 12:30</span>
              <div style={{ flex: 1 }} />
              <button type="button" onClick={() => setRdvPanel('detail')} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-ink)', background: 'transparent', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', padding: '5px 11px', cursor: 'pointer', whiteSpace: 'nowrap' }}>
                <i className="ri-calendar-event-line" style={{ fontSize: 15 }} />Détail du RDV de 08:30
              </button>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-error-ink)', whiteSpace: 'nowrap' }}>
                <i className="ri-error-warning-line" style={{ fontSize: 15 }} />1 conflit
              </div>
            </> : <div style={{ flex: 1 }} />}
            <SearchField />
            <IconAction icon="ri-notification-3-line" label="Notifications" badge={4} />
            <IconAction icon="ri-contrast-2-line" label="Poste d’atelier" onClick={() => setScreen('bench')} />
            <Button variant="primary" tone="accent" size="small" startIcon={h.plain ? undefined : 'ri-add-line'} onClick={h.go ? () => go(h.go) : undefined}>{h.action}</Button>
          </TopBar>
        )}

        <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
          {screen === 'stat' ? <window.StatScreen onOpenPlanning={() => setScreen('planning')} /> : null}
          {screen === 'planning' ? <window.PlanningScreen panel={panel} onOpen={setPanel} onClose={() => setPanel(null)} /> : null}
          {screen === 'periode' ? <window.StatPeriodScreen tab="Période" onTab={statTab} /> : null}
          {screen === 'analyse' ? <window.StatPeriodScreen tab="Analyse" onTab={statTab} /> : null}
          {screen === 'client' ? <window.ClientDetailScreen onOpenBike={() => go('motos')} onOpenOrder={() => go('bays')} /> : null}
          {screen === 'motos' ? <window.BikeDetailScreen onRecall={() => go('planning')} /> : null}
          {screen === 'devis' ? <window.QuotesListScreen onOpenQuote={() => go('devis-composer')} /> : null}
          {screen === 'devis-composer' ? <window.QuoteScreen onSend={() => go('devis')} /> : null}
          {screen === 'factures' ? <window.InvoicesScreen /> : null}
          {screen === 'rdv' ? <window.NewBookingScreen onCreate={() => go('planning')} /> : null}
          {screen === 'restitution' ? <window.HandoverScreen onDone={() => go('planning')} /> : null}
          {screen === 'etat' ? <window.PhotoLogScreen /> : null}
          {screen === 'stock' ? <window.StockScreen /> : null}
          {screen === 'reception' ? <window.ReceptionScreen onCheckIn={() => { setScreen('planning'); setPanel('reception'); }} /> : null}
          {screen === 'bays' ? <window.BaysScreen onOpenPlanning={() => setScreen('planning')} /> : null}
          {screen === 'clients' ? <window.ClientsScreen /> : null}
          {screen === 'explorer' ? <window.ExplorerScreen /> : null}
          {bench ? <window.BenchScreen /> : null}

          {screen === 'planning' && rdvPanel ? (
            <div style={{ position: 'absolute', top: 52, right: 0, bottom: 0, display: 'flex', zIndex: 5 }}>
              {rdvPanel === 'detail'
                ? <window.AppointmentPanel onClose={() => setRdvPanel(null)} onCheckIn={() => { setRdvPanel(null); setPanel('reception'); }} onReschedule={() => setRdvPanel('report')} />
                : <window.ReschedulePanel onClose={() => setRdvPanel(null)} />}
            </div>
          ) : null}

          {queueMode ? (
            <QueuePanel items={window.QUEUE_ITEMS} count={7}
              collapsed={queueMode === 'collapsed' ? true : !queueOpen}
              onToggle={() => setQueueOpen((v) => !v)} />
          ) : null}
        </div>

        {bench ? (
          <div style={{ padding: '10px 24px', background: '#000', color: '#a5a5a5', fontSize: 12, display: 'flex', alignItems: 'center', gap: 12 }}>
            Écran d’atelier, thème sombre par défaut selon le poste.
            <button type="button" onClick={() => setScreen('stat')} style={{ background: 'transparent', border: '1px solid #4a4a4a', color: '#f6f6f6', padding: '6px 12px', fontSize: 12, cursor: 'pointer' }}>Revenir au bureau</button>
          </div>
        ) : null}
      </div>
    </div>
  );
}
Object.assign(window, { PaddockAtelierApp });
