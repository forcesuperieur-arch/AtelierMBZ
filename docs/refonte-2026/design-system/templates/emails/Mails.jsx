/* Les trois e-mails clients (tour 21). Une seule colonne de 552 px dans une
   gouttière de 600, en-tête noir + liseré jaune, un bloc de contenu, un bouton,
   un pied légal. Pas d'image de fond, pas de colonnes : ce qui survit à tous
   les clients de messagerie. */
const emWrap = { width: 600, background: 'var(--pk-page)', padding: '24px 0', display: 'flex', flexDirection: 'column', alignItems: 'center' };
const emCard = { width: 552, background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)' };
const emBody = { padding: '26px 28px', display: 'flex', flexDirection: 'column', gap: 18, color: 'var(--pk-ink)' };
const emLine = { display: 'flex', justifyContent: 'space-between', padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 14 };
const emTotal = { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', padding: '14px 16px', background: '#000', color: '#fff' };
const emCta = { minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 16, fontWeight: 600 };
const emNote = { padding: '14px 16px', background: 'var(--pk-accent-soft)', borderLeft: '3px solid var(--pk-accent)', fontSize: 14, lineHeight: 1.55 };
const emFoot = { padding: '18px 28px', background: 'var(--pk-page)', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 7 };

function MailHead({ logo }) {
  return (
    <React.Fragment>
      <div style={{ background: '#000', padding: '20px 28px', display: 'flex', alignItems: 'center', gap: 13 }}>
        <img src={logo} alt="Paddock" style={{ width: 40, height: 40, display: 'block', flex: 'none' }} />
        <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
          <span style={{ fontSize: 14, fontWeight: 700, letterSpacing: '0.14em', color: '#fff' }}>PADDOCK</span>
          <span style={{ fontSize: 12, color: '#a5a5a5' }}>Atelier Principal — Lille</span>
        </div>
      </div>
      <div style={{ height: 3, background: 'var(--pk-accent)' }} />
    </React.Fragment>
  );
}

function MailPreview({ subject, meta }) {
  return (
    <div style={{ width: '100%', padding: '0 24px 14px', display: 'flex', flexDirection: 'column', gap: 3 }}>
      <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>De : Paddock Atelier Principal · atelier@paddock.fr</div>
      <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--pk-ink)' }}>{subject}</div>
      <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{meta}</div>
    </div>
  );
}

function MailFooter({ second, legal }) {
  return (
    <div style={emFoot}>
      <div style={{ fontSize: 12, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Paddock Atelier Principal · 12 rue de la Gare, 59000 Lille · 03 20 55 41 90<br />{second}</div>
      <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{legal || <React.Fragment>Vous recevez cet e-mail car vous avez un rendez-vous en cours. <span style={{ textDecoration: 'underline' }}>Gérer mes préférences</span></React.Fragment>}</div>
    </div>
  );
}

function QuoteMail({ logo }) {
  return (
    <div style={emWrap}>
      <MailPreview subject="Votre devis de 268,00 € pour la Tracer 9" meta="Réponse attendue avant le 11 septembre · signature en ligne" />
      <div style={emCard}>
        <MailHead logo={logo} />
        <div style={emBody}>
          <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.25 }}>Votre devis est prêt</div>
          <div style={{ fontSize: 15, lineHeight: 1.6 }}>Bonjour Nadia,<br />Voici le devis pour l’entretien de votre <strong style={{ fontWeight: 600 }}>Yamaha Tracer 9 (GT-908-ZK)</strong>. Aucun travail ne sera engagé avant votre accord.</div>
          <div style={{ border: '1px solid var(--pk-border)' }}>
            <div style={emLine}><span>Main d’œuvre · 2 h 30</span><span style={{ fontWeight: 600 }}>172,50 €</span></div>
            <div style={emLine}><span>Pièces et consommables</span><span style={{ fontWeight: 600 }}>50,83 €</span></div>
            <div style={{ ...emLine, color: 'var(--pk-ink-muted)' }}><span>TVA 20 %</span><span style={{ fontWeight: 600 }}>44,67 €</span></div>
            <div style={emTotal}><span style={{ fontSize: 14, fontWeight: 600 }}>Total à régler</span><span style={{ fontSize: 22, fontWeight: 700 }}>268,00 €</span></div>
          </div>
          <div style={emNote}>Les plaquettes de frein avant sont usées mais ne sont pas comprises dans ce montant. Nous vous les proposerons séparément à 74,90 €, pose comprise.</div>
          <div style={emCta}>Voir et signer le devis</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Ce devis est valable 30 jours. Une question ? Répondez à cet e-mail ou appelez-nous au 03 20 55 41 90.</div>
        </div>
        <MailFooter second="SIRET 812 445 907 00023 · TVA FR32812445907" />
      </div>
    </div>
  );
}

function InvoiceMail({ logo }) {
  return (
    <div style={emWrap}>
      <MailPreview subject="Votre facture FA-1231 · MT-09" meta="Merci de votre visite. Facture réglée, rien à faire." />
      <div style={emCard}>
        <MailHead logo={logo} />
        <div style={emBody}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '13px 16px', background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)' }}>
            <i className="ri-checkbox-circle-fill" style={{ fontSize: 20, color: 'var(--pk-success-ink)' }} />
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--pk-success-ink)' }}>Facture réglée</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>412,50 € payés par carte le 15 août · rien à faire</div>
            </div>
          </div>
          <div style={{ fontSize: 15, lineHeight: 1.6 }}>Bonjour Ludovic,<br />Votre <strong style={{ fontWeight: 600 }}>Yamaha MT-09</strong> vous a été restituée. Voici le détail de ce qui a été fait.</div>
          <div style={{ border: '1px solid var(--pk-border)' }}>
            <div style={emLine}><span>Révision 20 000 km</span><span style={{ fontWeight: 600 }}>231,05 €</span></div>
            <div style={emLine}><span>Bougies remplacées</span><span style={{ fontWeight: 600 }}>92,00 €</span></div>
            <div style={emLine}><span>Réglage de chaîne</span><span style={{ fontWeight: 600 }}>20,70 €</span></div>
            <div style={emTotal}><span style={{ fontSize: 14, fontWeight: 600 }}>Total TTC</span><span style={{ fontSize: 22, fontWeight: 700 }}>412,50 €</span></div>
          </div>
          <div style={emNote}>Les plaquettes de frein avant n’ont pas été montées : la pièce était indisponible. Elles ne vous ont pas été facturées. Nous vous attendons <strong style={{ fontWeight: 600 }}>jeudi 21 août à 9 h</strong>, le créneau est réservé.</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '13px 16px', border: '1px solid var(--pk-border)' }}>
            <i className="ri-file-pdf-2-line" style={{ fontSize: 26, color: 'var(--pk-error-ink)' }} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 14, fontWeight: 600 }}>FA-1231.pdf</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Facture · 84 Ko</div>
            </div>
            <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--pk-link)' }}>Télécharger</span>
          </div>
          <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', fontSize: 16, fontWeight: 600 }}>Voir l’historique de ma moto</div>
        </div>
        <MailFooter second="SIRET 812 445 907 00023 · TVA FR32812445907" legal="Médiateur de la consommation : AME Conso, 11 place Dauphine, 75001 Paris." />
      </div>
    </div>
  );
}

function ReminderMail({ logo }) {
  return (
    <div style={emWrap}>
      <MailPreview subject="Demain 8 h 30 · votre Tracer 9 à l’atelier" meta="12 rue de la Gare, Lille · pour décaler, un lien dans l’e-mail" />
      <div style={emCard}>
        <MailHead logo={logo} />
        <div style={emBody}>
          <div style={{ display: 'flex', alignItems: 'stretch', gap: 16 }}>
            <div style={{ width: 92, flexShrink: 0, border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
              <div style={{ background: '#000', color: '#fff', padding: '5px 0', textAlign: 'center', fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase' }}>Vendredi</div>
              <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '8px 0' }}>
                <span style={{ fontSize: 34, fontWeight: 700, lineHeight: 1 }}>16</span>
                <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>août</span>
              </div>
            </div>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 5, minWidth: 0 }}>
              <div style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>8 h 30</div>
              <div style={{ fontSize: 15, lineHeight: 1.5 }}>Yamaha Tracer 9 · GT-908-ZK</div>
              <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }}>Révision 20 000 km · environ 3 h</div>
            </div>
          </div>
          <div style={{ fontSize: 15, lineHeight: 1.6 }}>Bonjour Nadia,<br />Nous vous attendons demain matin. Prévoyez la carte grise et pensez à retirer vos affaires du top-case.</div>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 11, padding: '13px 16px', background: 'var(--pk-page)' }}>
            <i className="ri-map-pin-line" style={{ fontSize: 20, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
            <div style={{ fontSize: 14, lineHeight: 1.55 }}><strong style={{ fontWeight: 600 }}>Atelier Principal</strong><br />12 rue de la Gare, 59000 Lille<br />Parking moto devant l’atelier</div>
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <div style={{ ...emCta, flex: 1, fontSize: 15 }}>Ajouter à mon agenda</div>
            <div style={{ flex: 1, minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', fontSize: 15, fontWeight: 600 }}>Décaler ce rendez-vous</div>
          </div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Un empêchement ? Prévenez-nous la veille : le créneau part à quelqu’un d’autre plutôt que de rester vide.</div>
        </div>
        <MailFooter second="Ouvert du lundi au vendredi 8 h – 18 h 30, samedi 9 h – 12 h 30" />
      </div>
    </div>
  );
}
Object.assign(window, { QuoteMail, InvoiceMail, ReminderMail, MailHead, MailPreview, MailFooter });
