/* Connexion (tour 40a) — SSO Motoblouz, quatre états. En production il n'y a
   pas de formulaire métier : un bouton, puis le choix d'atelier. Les deux états
   d'échec disent quoi faire et à qui s'adresser. */
const lgCard = { width: 496, background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)' };
const lgCap = { padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const lgSite = { display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: 'var(--pk-surface)', cursor: 'pointer', fontFamily: 'inherit', textAlign: 'left', color: 'inherit' };
const lgPill = { minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 16px', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' };

function LoginSso({ logo, onSignIn }) {
  return (
    <div style={{ ...lgCard, minHeight: 400, background: '#141414', color: '#f6f6f6', alignItems: 'center', justifyContent: 'center', gap: 22, padding: 40 }}>
      <img src={logo} alt="Paddock" style={{ width: 64, height: 64, display: 'block', flex: 'none' }} />
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, textAlign: 'center' }}>
        <span style={{ fontSize: 22, fontWeight: 700, letterSpacing: '0.2em' }}>PADDOCK</span>
        <span style={{ fontSize: 14, color: '#a5a5a5' }}>Gestion d’atelier · Motoblouz</span>
      </div>
      <button type="button" onClick={onSignIn} style={{ minHeight: 56, display: 'flex', alignItems: 'center', gap: 11, padding: '0 26px', background: 'var(--pk-accent)', color: '#000', fontSize: 16, fontWeight: 600, border: 'none', fontFamily: 'inherit', cursor: 'pointer' }}><i className="ri-shield-keyhole-line" style={{ fontSize: 21 }} />Se connecter avec Motoblouz</button>
      <span style={{ fontSize: 13, color: '#a5a5a5', textAlign: 'center', lineHeight: 1.5, maxWidth: 320 }}>Votre compte Motoblouz suffit. Aucun mot de passe propre à Paddock.</span>
      <div style={{ flex: 1 }} />
      <span style={{ fontSize: 12, color: '#6f6e6e' }}>v3.2 · Un problème d’accès ? support-atelier@motoblouz.com</span>
    </div>
  );
}

function LoginSitePicker({ onPick }) {
  const [sel, setSel] = React.useState('lille');
  const sites = [
    { id: 'lille', name: 'Atelier Principal — Lille', sub: 'Votre dernier atelier · 6 ponts' },
    { id: 'roubaix', name: 'Roubaix', sub: '3 ponts' },
    { id: 'reseau', name: 'Vue réseau · 4 ateliers', sub: 'Direction · lecture seule' },
  ];
  return (
    <div style={{ ...lgCard, minHeight: 400 }}>
      <div style={lgCap}>Après le SSO · plusieurs ateliers</div>
      <div style={{ padding: '20px 22px', display: 'flex', flexDirection: 'column', gap: 6 }}>
        <div style={{ fontSize: 20, fontWeight: 500, letterSpacing: '-0.015em' }}>Bonjour Pascal</div>
        <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Où travaillez-vous aujourd’hui ? Vous pourrez changer à tout moment.</div>
      </div>
      <div style={{ padding: '0 22px', display: 'flex', flexDirection: 'column', gap: 8 }}>
        {sites.map((s) => (
          <button type="button" key={s.id} onClick={() => { setSel(s.id); if (onPick) onPick(s.id); }}
            style={{ ...lgSite, border: sel === s.id ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border)' }}>
            <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{s.name}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{s.sub}</div></div>
            {sel === s.id ? <i className="ri-arrow-right-line" style={{ fontSize: 18, color: 'var(--pk-accent-ink)' }} /> : null}
          </button>
        ))}
      </div>
      <div style={{ flex: 1 }} />
      <div style={{ padding: '14px 22px', borderTop: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10 }}>
        <span style={{ display: 'inline-flex', width: 40, height: 22, padding: 2, background: 'var(--pk-accent)', borderRadius: 'var(--pk-radius-pill)', flexShrink: 0 }}><span style={{ width: 18, height: 18, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: 'auto' }} /></span>
        <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Reprendre directement sur cet atelier la prochaine fois</span>
      </div>
    </div>
  );
}

function LoginDenied() {
  return (
    <div style={{ ...lgCard, minHeight: 340 }}>
      <div style={lgCap}>Refus d’accès</div>
      <div style={{ flex: 1, padding: '26px 30px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 13, textAlign: 'center' }}>
        <i className="ri-shield-cross-line" style={{ fontSize: 34, color: 'var(--pk-error-ink)' }} />
        <div style={{ fontSize: 18, fontWeight: 600 }}>Votre compte n’a pas accès à Paddock</div>
        <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Vous êtes bien connecté avec <strong style={{ fontWeight: 600 }}>t.roche@motoblouz.com</strong>, mais aucun atelier ne vous est rattaché.</div>
        <div style={{ width: '100%', padding: '13px 15px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', textAlign: 'left', fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Un responsable d’atelier peut vous ouvrir l’accès depuis <strong style={{ fontWeight: 600, color: 'var(--pk-ink)' }}>Administration › Utilisateurs</strong>. Pour l’Atelier Principal, c’est Julie Dubois.</div>
        <div style={{ display: 'flex', gap: 8 }}>
          <span style={{ ...lgPill, background: 'var(--pk-accent)', color: '#000' }}>Demander l’accès</span>
          <span style={{ ...lgPill, border: '1px solid #000' }}>Changer de compte</span>
        </div>
      </div>
    </div>
  );
}

function LoginExpired() {
  return (
    <div style={{ ...lgCard, minHeight: 340, background: 'rgba(20,20,20,0.55)', alignItems: 'center', justifyContent: 'center', padding: 30 }}>
      <div style={{ width: '100%', background: 'var(--pk-surface-raised)', border: '1px solid #6f6e6e', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)' }}>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10 }}>
          <i className="ri-time-line" style={{ fontSize: 19, color: 'var(--pk-warning-ink-soft)' }} />
          <span style={{ fontSize: 15, fontWeight: 600 }}>Votre session a expiré</span>
        </div>
        <div style={{ padding: '16px 18px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Après 8 heures, Paddock referme la session. C’est une règle de sécurité, pas une panne.</div>
          <div style={{ padding: '12px 14px', background: 'var(--pk-success-surface)', borderLeft: '3px solid var(--pk-success-line)', display: 'flex', gap: 10 }}>
            <i className="ri-save-line" style={{ fontSize: 17, color: 'var(--pk-success-ink)', flexShrink: 0 }} />
            <div style={{ fontSize: 13, lineHeight: 1.5 }}>Le devis DV-2447 en cours de saisie est conservé. Vous le retrouverez à l’endroit exact où vous l’avez laissé.</div>
          </div>
          <span style={{ minHeight: 48, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, background: 'var(--pk-accent)', color: '#000', fontSize: 15, fontWeight: 600 }}><i className="ri-shield-keyhole-line" style={{ fontSize: 19 }} />Rouvrir la session</span>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { LoginSso, LoginSitePicker, LoginDenied, LoginExpired });
