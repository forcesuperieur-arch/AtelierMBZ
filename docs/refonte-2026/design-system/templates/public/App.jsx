/* Racine du front public — les six écrans sans compte, côte à côte ou un par un. */
const PB_SCREENS = [
  { id: 'landing', label: 'Landing', render: (l) => <window.LandingScreen logo={l} /> },
  { id: 'booking', label: 'Prise de RDV', render: (l) => <window.BookingFlow logo={l} /> },
  { id: 'track', label: 'Suivi par lien', render: (l) => <window.TrackScreen logo={l} /> },
  { id: 'ready', label: 'Prête', render: (l) => <window.TrackReadyScreen logo={l} /> },
  { id: 'reset', label: 'Mot de passe oublié', render: (l) => <window.PasswordResetScreen logo={l} /> },
  { id: 'legal', label: 'Mentions', render: (l) => <window.LegalScreen logo={l} /> },
  { id: 'terms', label: 'CGV', render: (l) => <window.TermsScreen logo={l} /> },
];
const pbTab = { padding: '7px 14px', borderRadius: 'var(--pk-radius-pill)', cursor: 'pointer', fontSize: 12, whiteSpace: 'nowrap', fontFamily: 'inherit' };

function PaddockPublicApp(props) {
  const p = props || {};
  const logo = p.logo || '../../assets/paddock-logo-horizontal.svg';
  const [screen, setScreen] = React.useState(p.startScreen || 'landing');
  if (p.layout === 'rangée') {
    return (
      <div style={{ display: 'flex', gap: 20, alignItems: 'flex-start', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)' }}>
        {PB_SCREENS.filter((s) => s.id !== 'terms').map((s) => (
          <div key={s.id} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-muted)' }}>{s.label}</span>
            {s.render(logo)}
          </div>
        ))}
      </div>
    );
  }
  const cur = PB_SCREENS.find((s) => s.id === screen) || PB_SCREENS[0];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, alignItems: 'flex-start', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)' }}>
      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
        {PB_SCREENS.map((s) => (
          <button key={s.id} type="button" onClick={() => setScreen(s.id)}
            style={{ ...pbTab, fontWeight: s.id === screen ? 600 : 400, background: s.id === screen ? '#000' : 'transparent', color: s.id === screen ? '#fff' : 'var(--pk-ink)', border: '1px solid ' + (s.id === screen ? '#000' : 'var(--pk-border-control)') }}>{s.label}</button>
        ))}
      </div>
      {cur.render(logo)}
    </div>
  );
}
Object.assign(window, { PaddockPublicApp });
