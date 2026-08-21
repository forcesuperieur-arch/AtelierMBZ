/* Tour 53 — les trois dernières pages du front client : la landing où atterrit
   un motard qui a cherché « atelier moto Dunkerque », le mot de passe oublié
   (client seulement, le SSO reste au personnel), et les CGV versionnées. */
const ldShell = { width: 390, height: 844, background: 'var(--pk-page)', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' };
const ldBar = { flexShrink: 0, display: 'flex', alignItems: 'center', gap: 10, padding: '14px 20px', background: '#000', color: '#f6f6f6' };
const ldPrice = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: '11px 16px', display: 'flex', alignItems: 'baseline', gap: 10 };
const ldCta = { minHeight: 56, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 17, fontWeight: 700 };
const ldFootLine = { display: 'flex', alignItems: 'center', gap: 10, fontSize: 14 };
const ldLabel = { fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const cgArt = { display: 'flex', flexDirection: 'column', gap: 8 };
const cgBody = { fontSize: 16, lineHeight: 1.65, color: '#3a3a3a' };

function LandingScreen({ logo }) {
  return (
    <div style={ldShell}>
      <div style={ldBar}>
        <img src={logo} alt="Paddock" style={{ height: 20, display: 'block' }} />
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, fontWeight: 600, color: '#d4d4d4' }}>Se connecter</span>
      </div>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0, overflow: 'hidden' }}>
        <div style={{ background: '#000', color: '#f6f6f6', padding: '20px 20px 22px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ fontSize: 27, fontWeight: 700, lineHeight: 1.15, letterSpacing: '-0.015em' }}>Votre moto entretenue,<br />au prix annoncé.</div>
          <div style={{ fontSize: 15, lineHeight: 1.55, color: '#d4d4d4' }}>Atelier Motoblouz à Dunkerque. Forfaits affichés, créneau réservé en ligne, moto prête le soir même pour la plupart des interventions.</div>
          <span style={ldCta}>Prendre rendez-vous</span>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#a5a5a5' }}><i className="ri-time-line" style={{ fontSize: 16 }} />Prochain créneau : mardi 26 août</div>
        </div>
        <div style={{ padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
          <span style={ldLabel}>Les forfaits les plus demandés</span>
          <div style={ldPrice}>
            <div style={{ flex: 1 }}><div style={{ fontSize: 16, fontWeight: 600 }}>Révision constructeur</div><div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Selon le carnet de votre modèle</div></div>
            <span style={{ fontSize: 16, fontWeight: 700 }}>dès 189 €</span>
          </div>
          <div style={ldPrice}>
            <div style={{ flex: 1 }}><div style={{ fontSize: 16, fontWeight: 600 }}>Pneus montés équilibrés</div><div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Pneu compris, à la dimension</div></div>
            <span style={{ fontSize: 16, fontWeight: 700 }}>dès 145 €</span>
          </div>
          <div style={ldPrice}>
            <div style={{ flex: 1 }}><div style={{ fontSize: 16, fontWeight: 600 }}>Diagnostic panne</div><div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Déduit si vous faites la réparation ici</div></div>
            <span style={{ fontSize: 16, fontWeight: 700 }}>59 €</span>
          </div>
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Tout travail non prévu vous est demandé avant d’être fait — jamais de surprise sur la facture.</div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ padding: '14px 20px', background: 'var(--pk-surface)', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 7 }}>
          <div style={ldFootLine}><i className="ri-map-pin-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />14 rue de la Fonderie, Dunkerque</div>
          <div style={ldFootLine}><i className="ri-phone-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />03 28 00 00 00</div>
          <div style={ldFootLine}><i className="ri-time-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Mardi – samedi · 8 h – 19 h (17 h le samedi)</div>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', marginTop: 4 }}>Mentions légales · CGV · Confidentialité</span>
        </div>
      </div>
    </div>
  );
}

function PasswordResetScreen({ logo }) {
  const [step, setStep] = React.useState('ask');
  const [expired, setExpired] = React.useState(false);
  if (step === 'ask') return (
    <div style={{ ...ldShell, height: 620 }}>
      <div style={ldBar}>
        <i className="ri-arrow-left-line" style={{ fontSize: 20 }} />
        <img src={logo} alt="Paddock" style={{ height: 18, display: 'block' }} />
      </div>
      <div style={{ flex: 1, padding: '26px 20px', display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div style={{ fontSize: 24, fontWeight: 600, lineHeight: 1.2, letterSpacing: '-0.01em' }}>Mot de passe oublié</div>
        <div style={{ fontSize: 15, lineHeight: 1.55, color: 'var(--pk-ink-quiet)' }}>Donnez l’adresse e-mail de votre compte : nous y envoyons un lien pour en choisir un nouveau.</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span style={ldLabel}>E-mail</span>
          <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', padding: '0 15px', background: 'var(--pk-surface)', border: '1px solid #000', fontSize: 16 }}>n.belkacem@gmail.com</div>
        </div>
        <button type="button" onClick={() => setStep('new')} style={{ ...ldCta, border: 'none', fontFamily: 'inherit', cursor: 'pointer' }}>Envoyer le lien</button>
        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: '14px 16px', fontSize: 14, lineHeight: 1.55, color: 'var(--pk-ink-quiet)' }}>Si cette adresse a un compte, le lien arrive sous une minute et reste valable 30 minutes. Pensez aux indésirables.</div>
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Plus accès à cette boîte mail ? Appelez l’atelier au 03 28 00 00 00 : on vérifie votre identité et on change l’adresse du compte.</div>
      </div>
    </div>
  );
  return (
    <div style={{ ...ldShell, height: 620 }}>
      <div style={ldBar}><img src={logo} alt="Paddock" style={{ height: 18, display: 'block' }} /></div>
      {expired ? (
        <div style={{ flex: 1, padding: '26px 20px', display: 'flex', flexDirection: 'column', gap: 16 }}>
          <i className="ri-time-line" style={{ fontSize: 40, color: 'var(--pk-warning-ink-soft)' }} />
          <div style={{ fontSize: 24, fontWeight: 600, lineHeight: 1.2, letterSpacing: '-0.01em' }}>Ce lien a expiré</div>
          <div style={{ fontSize: 15, lineHeight: 1.55, color: 'var(--pk-ink-quiet)' }}>Les liens valent 30 minutes. Celui-ci a été envoyé il y a plus longtemps — nous pouvons vous en envoyer un nouveau tout de suite.</div>
          <button type="button" onClick={() => { setExpired(false); setStep('ask'); }} style={{ ...ldCta, border: 'none', fontFamily: 'inherit', cursor: 'pointer' }}>En recevoir un nouveau</button>
          <div style={{ flex: 1 }} />
          <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Rien n’a été perdu : votre compte et vos rendez-vous sont intacts.</div>
        </div>
      ) : (
        <div style={{ flex: 1, padding: '26px 20px', display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={{ fontSize: 24, fontWeight: 600, lineHeight: 1.2, letterSpacing: '-0.01em' }}>Choisissez un nouveau mot de passe</div>
          <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }}>Pour le compte <strong style={{ fontWeight: 600, color: 'var(--pk-ink)' }}>n.belkacem@gmail.com</strong></div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={ldLabel}>Nouveau mot de passe</span>
            <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', padding: '0 15px', background: 'var(--pk-surface)', border: '1px solid #000', fontSize: 16, justifyContent: 'space-between' }}><span>••••••••••••</span><i className="ri-eye-line" style={{ fontSize: 19, color: 'var(--pk-ink-quiet)' }} /></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 13, color: 'var(--pk-success-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />12 caractères ou plus — c’est la seule règle</div>
          </div>
          <span style={ldCta}>Enregistrer et me connecter</span>
          <div style={{ flex: 1 }} />
          <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ ...ldLabel, color: 'var(--pk-warning-ink-soft)' }}>Si le lien a expiré</span>
            <div style={{ fontSize: 14, lineHeight: 1.55, color: 'var(--pk-ink-quiet)' }}>Cette page le dit clairement — « ce lien a expiré, en recevoir un nouveau » — avec un seul bouton qui renvoie un lien frais. Jamais de formulaire qui échoue à la soumission.</div>
            <button type="button" onClick={() => setExpired(true)} style={{ minHeight: 40, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>Voir cet état</button>
          </div>
        </div>
      )}
    </div>
  );
}

function TermsScreen({ logo }) {
  const articles = [
    ['1 · Le devis et le prix', 'Le prix signé est le prix payé.', ' Les forfaits affichés comprennent pièces et main-d’œuvre décrites. Un devis signé n’est jamais modifié rétroactivement, même si nos tarifs changent entre la signature et l’intervention.'],
    ['2 · Les travaux découverts en cours d’intervention', 'Rien n’est fait sans votre accord.', ' Si nous découvrons un travail nécessaire non prévu, nous vous le proposons par SMS ou téléphone avec son prix et son délai. Sans réponse de votre part, nous terminons ce qui était convenu et vous restituons le véhicule.'],
    ['3 · Le rendez-vous et son annulation', 'Annulation libre jusqu’à la veille 18 h.', ' Par SMS, en ligne ou par téléphone, sans frais. Un rendez-vous non honoré sans prévenir peut conditionner les réservations suivantes à un acompte.'],
    ['4 · Le véhicule pendant l’intervention', 'Un état des lieux photo à l’entrée et à la sortie.', ' Six angles horodatés, signés par vous et par nous ; il fait foi en cas de litige. Le véhicule reste assuré par son propriétaire pendant son séjour à l’atelier. Les essais routiers sont limités à ce que l’intervention exige.'],
    ['5 · Les pièces remplacées', 'Vos pièces vous attendent quinze jours.', ' Elles sont restituées sur demande à la sortie, puis recyclées passé ce délai. Les pièces sous garantie constructeur suivent le circuit de la marque.'],
    ['6 · Le paiement et la restitution', 'Vous réglez à la restitution.', ' Carte, espèces ou virement. Le véhicule est restitué contre paiement intégral des travaux acceptés. Une moto non récupérée sous quinze jours après notification peut donner lieu à des frais de gardiennage annoncés à l’avance.'],
    ['7 · La garantie et les litiges', 'Nos interventions sont garanties un an, pièces et main-d’œuvre.', ' En cas de désaccord, l’historique complet du dossier — devis, accords, photos, versions des présentes conditions — vous est remis sur demande. À défaut d’accord amiable, le médiateur de la consommation dont relève l’atelier peut être saisi gratuitement.'],
  ];
  return (
    <div style={{ width: 900, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' }}>
      <div style={{ background: '#000', color: '#f6f6f6', padding: '26px 44px', display: 'flex', alignItems: 'center', gap: 16 }}>
        <img src={logo} alt="Paddock" style={{ height: 22, display: 'block' }} />
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 14, color: '#a5a5a5' }}>Conditions générales</span>
      </div>
      <div style={{ padding: '40px 44px', display: 'flex', flexDirection: 'column', gap: 26, maxWidth: 720 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ fontSize: 30, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.15 }}>Conditions générales de service</div>
          <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
          <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Version 3 · en vigueur depuis le 12 juin 2026 · votre rendez-vous est régi par la version en vigueur le jour de la réservation</div>
        </div>
        {articles.map(([title, lead, rest]) => (
          <div key={title} style={cgArt}>
            <div style={{ fontSize: 18, fontWeight: 600 }}>{title}</div>
            <div style={cgBody}><strong style={{ fontWeight: 600, color: 'var(--pk-ink)' }}>{lead}</strong>{rest}</div>
          </div>
        ))}
      </div>
      <div style={{ padding: '22px 44px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', display: 'flex', alignItems: 'center', gap: 16, fontSize: 14, color: 'var(--pk-ink-quiet)' }}>
        <span>Motoblouz Atelier Dunkerque</span>
        <div style={{ flex: 1 }} />
        <span>Mentions légales · Confidentialité</span>
      </div>
    </div>
  );
}
Object.assign(window, { LandingScreen, PasswordResetScreen, TermsScreen });
