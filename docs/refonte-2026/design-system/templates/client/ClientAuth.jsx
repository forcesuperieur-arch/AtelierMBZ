/* Les pages hors session : landing et connexion. Elles sont en `layout: false`
   dans le code — pas de barre de nav, la bascule de thème posée en coin. Le
   fond combine un halo jaune radial, une trame diagonale à 135° et la surface
   de page, exactement comme `pages/landing.vue` et `pages/login.vue`. */
const auBg = {
  minHeight: '100%',
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  color: 'var(--content-1)',
  fontFamily: 'var(--mb-font-montserrat)',
  position: 'relative',
};
const auHeroBtn = { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, padding: '14px 20px', borderRadius: 9, fontWeight: 700, fontSize: 15, textDecoration: 'none', cursor: 'pointer', fontFamily: 'inherit', border: 'none' };

const AU_FEATURES = [
  { icon: 'ri-calendar-line', title: 'Prise de rendez-vous en ligne', text: 'Réservez un créneau atelier en quelques clics, à l’heure qui vous convient.' },
  { icon: 'ri-radar-line', title: 'Suivi en temps réel', text: 'Suivez l’avancement de votre intervention, de la réception à la restitution.' },
  { icon: 'ri-folder-history-line', title: 'Historique & documents', text: 'Retrouvez vos motos, vos interventions passées et vos documents dans votre espace client.' },
  { icon: 'ri-notification-3-line', title: 'Notifications automatiques', text: 'Confirmations et rappels par email ou SMS, sans rien à faire.' },
];

function LandingScreen({ logo, dark, onToggleTheme, onLogin }) {
  return (
    <div style={{ ...auBg, padding: '56px 24px 24px', background: 'radial-gradient(700px 360px at 50% 0%, var(--accent-soft), transparent 70%), repeating-linear-gradient(135deg, var(--overlay-soft) 0 2px, transparent 2px 6px), var(--surface-0)' }}>
      <window.ThemeToggle dark={dark} onToggle={onToggleTheme} floating />
      <section style={{ textAlign: 'center', maxWidth: 420, width: '100%' }}>
        <img src={logo} alt="Paddock" style={{ width: 'min(100%, 240px)', height: 'auto', display: 'block', margin: '0 auto 16px' }} />
        <p style={{ fontSize: 15, color: 'var(--content-3)', margin: '0 0 32px' }}>Votre atelier moto en ligne</p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <button type="button" onClick={onLogin} style={{ ...auHeroBtn, background: 'var(--accent)', color: 'var(--accent-ink)' }}><i className="ri-calendar-line" style={{ fontSize: 19 }} />Prendre un rendez-vous</button>
          <button type="button" onClick={onLogin} style={{ ...auHeroBtn, background: 'transparent', border: '1px solid var(--border-control)', color: 'var(--content-1)' }}><i className="ri-user-line" style={{ fontSize: 19 }} />Accéder à mon espace client</button>
        </div>
      </section>
      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14, maxWidth: 720, width: '100%', marginTop: 44 }}>
        {AU_FEATURES.map((f) => (
          <div key={f.title} style={{ padding: 20, background: 'var(--surface-1)', border: '1px solid var(--border-2)', borderRadius: 14 }}>
            <i className={f.icon} style={{ fontSize: 24, color: 'var(--accent-content)' }} />
            <h2 style={{ fontSize: 15, fontWeight: 800, margin: '10px 0 6px' }}>{f.title}</h2>
            <p style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--content-3)', margin: 0 }}>{f.text}</p>
          </div>
        ))}
      </section>
      <div style={{ flex: 1 }} />
      <window.LegalFooter />
    </div>
  );
}

function LoginScreen({ logo, dark, onToggleTheme, onSubmit, onForgot }) {
  return (
    <div style={{ ...auBg, justifyContent: 'center', gap: 20, padding: 24, background: 'radial-gradient(700px 360px at 50% 18%, var(--accent-soft), transparent 70%), repeating-linear-gradient(135deg, var(--overlay-soft) 0 2px, transparent 2px 6px), var(--surface-0)' }}>
      <window.ThemeToggle dark={dark} onToggle={onToggleTheme} floating />
      <div style={{ position: 'relative', width: '100%', maxWidth: 380, padding: '40px 32px 32px', background: 'linear-gradient(180deg, var(--surface-2), var(--surface-1))', border: '1px solid var(--border-2)', borderRadius: 16, textAlign: 'center', boxShadow: '0 16px 48px rgba(0,0,0,0.55)', overflow: 'hidden' }}>
        <img src={logo} alt="Paddock" style={{ width: 'min(100%, 220px)', height: 'auto', margin: '0 auto 12px', display: 'block' }} />
        <h1 style={{ fontSize: 22, fontWeight: 800, marginBottom: 4 }}>Espace Client</h1>
        <p style={{ fontSize: 13, color: 'var(--content-3)', marginBottom: 20 }}>Connexion à votre espace client</p>
        <div style={{ ...window.clField, textAlign: 'left', marginBottom: 12 }}>
          <label style={window.clLabel}>Email</label>
          <div style={{ ...window.clInput, minHeight: 44, background: 'var(--surface-1)' }}>n.belkacem@gmail.com</div>
        </div>
        <div style={{ ...window.clField, textAlign: 'left', marginBottom: 18 }}>
          <label style={window.clLabel}>Mot de passe</label>
          <div style={{ ...window.clInput, minHeight: 44, background: 'var(--surface-1)' }}>••••••••</div>
        </div>
        <button type="button" onClick={onSubmit} style={{ width: '100%', minHeight: 46, borderRadius: 9, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none', fontSize: 15, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer' }}>Se connecter</button>
        <button type="button" onClick={onForgot} style={{ fontSize: 13, color: 'var(--content-3)', marginTop: 16, display: 'inline-block', background: 'none', border: 'none', cursor: 'pointer', fontFamily: 'inherit' }}>Activer mon compte / Mot de passe oublié</button>
      </div>
      <window.LegalFooter />
    </div>
  );
}

function ForgotScreen({ logo, dark, onToggleTheme, onBack }) {
  const [sent, setSent] = React.useState(false);
  return (
    <div style={{ ...auBg, justifyContent: 'center', gap: 20, padding: 24, background: 'radial-gradient(700px 360px at 50% 18%, var(--accent-soft), transparent 70%), repeating-linear-gradient(135deg, var(--overlay-soft) 0 2px, transparent 2px 6px), var(--surface-0)' }}>
      <window.ThemeToggle dark={dark} onToggle={onToggleTheme} floating />
      <div style={{ width: '100%', maxWidth: 380, padding: '40px 32px 32px', background: 'linear-gradient(180deg, var(--surface-2), var(--surface-1))', border: '1px solid var(--border-2)', borderRadius: 16, textAlign: 'center', boxShadow: '0 16px 48px rgba(0,0,0,0.55)' }}>
        <img src={logo} alt="Paddock" style={{ width: 'min(100%, 200px)', height: 'auto', margin: '0 auto 12px', display: 'block' }} />
        <h1 style={{ fontSize: 20, fontWeight: 800, marginBottom: 4 }}>Activer mon compte</h1>
        <p style={{ fontSize: 13, color: 'var(--content-3)', marginBottom: 20, lineHeight: 1.5 }}>Donnez l’adresse e-mail de votre compte : nous y envoyons un lien pour choisir un mot de passe.</p>
        {sent ? (
          <div style={{ padding: '14px 16px', background: 'var(--success-soft)', border: '1px solid var(--success)', borderRadius: 10, fontSize: 13, lineHeight: 1.5, color: 'var(--success-content)', textAlign: 'left' }}>Si cette adresse a un compte, le lien arrive sous une minute et reste valable 30 minutes. Pensez aux indésirables.</div>
        ) : (
          <React.Fragment>
            <div style={{ ...window.clField, textAlign: 'left', marginBottom: 18 }}>
              <label style={window.clLabel}>Email</label>
              <div style={{ ...window.clInput, minHeight: 44, background: 'var(--surface-1)' }}>n.belkacem@gmail.com</div>
            </div>
            <button type="button" onClick={() => setSent(true)} style={{ width: '100%', minHeight: 46, borderRadius: 9, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none', fontSize: 15, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer' }}>Envoyer le lien</button>
          </React.Fragment>
        )}
        <button type="button" onClick={onBack} style={{ fontSize: 13, color: 'var(--content-3)', marginTop: 16, background: 'none', border: 'none', cursor: 'pointer', fontFamily: 'inherit' }}>Retour à la connexion</button>
      </div>
      <window.LegalFooter />
    </div>
  );
}

Object.assign(window, { LandingScreen, LoginScreen, ForgotScreen });
