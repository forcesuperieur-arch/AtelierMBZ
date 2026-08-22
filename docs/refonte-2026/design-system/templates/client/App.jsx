/* Racine du portail client. Les pages hors session (landing, connexion, mot de
   passe oublié) n'ont pas de coquille ; une fois connecté, tout passe par
   `ClientLayout`. Le thème sombre applique `data-theme="dark"` sur la racine,
   comme le fait `useTheme()` dans le code. */
function PaddockClientApp(props) {
  const p = props || {};
  const logo = p.logo || '../../assets/paddock-logo-stacked.svg';
  const logoLight = p.logoLight || '../../assets/paddock-logo-stacked-light.svg';
  const [screen, setScreen] = React.useState(p.startScreen || 'landing');
  const [dark, setDark] = React.useState(p.theme === 'sombre');
  React.useEffect(() => { setDark(p.theme === 'sombre'); }, [p.theme]);
  React.useEffect(() => { document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light'); }, [dark]);
  const toggle = () => setDark((v) => !v);
  /* Le mot-symbole des fichiers de marque est en blanc cassé (« place on dark
     background ») : c'est le thème CLAIR qui prend la variante `-light`. */
  const brand = dark ? logo : logoLight;

  if (screen === 'landing') return <window.LandingScreen logo={brand} dark={dark} onToggleTheme={toggle} onLogin={() => setScreen('login')} />;
  if (screen === 'login') return <window.LoginScreen logo={brand} dark={dark} onToggleTheme={toggle} onSubmit={() => setScreen('dashboard')} onForgot={() => setScreen('forgot')} />;
  if (screen === 'forgot') return <window.ForgotScreen logo={brand} dark={dark} onToggleTheme={toggle} onBack={() => setScreen('login')} />;

  return (
    <window.ClientLayout screen={screen} onNav={setScreen} dark={dark} onToggleTheme={toggle} onLogout={() => setScreen('landing')} logo={brand}>
      {screen === 'dashboard' ? <window.DashboardScreen onNav={setScreen} onOpenRdv={() => setScreen('rdv')} /> : null}
      {screen === 'rdvs' ? <window.RdvListScreen onNav={setScreen} onOpenRdv={() => setScreen('rdv')} /> : null}
      {screen === 'rdv' ? <window.RdvDetailScreen onBack={() => setScreen('rdvs')} /> : null}
      {screen === 'booking' ? <window.BookingScreen onBack={() => setScreen('rdvs')} onDone={() => setScreen('rdvs')} /> : null}
      {screen === 'historique' ? <window.HistoriqueScreen /> : null}
      {screen === 'motos' ? <window.MotosScreen /> : null}
      {screen === 'profil' ? <window.ProfilScreen /> : null}
    </window.ClientLayout>
  );
}
Object.assign(window, { PaddockClientApp });
