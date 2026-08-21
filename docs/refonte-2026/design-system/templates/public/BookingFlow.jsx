/* Prise de rendez-vous en ligne (tour 50a) — sans compte. Une question par écran,
   l'étape en clair, et le prix annoncé avant le créneau. Les créneaux proposés
   sont ceux que le planning peut vraiment tenir. */
const bkShell = { width: 390, height: 844, background: 'var(--pk-page)', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' };
const bkTop = { flexShrink: 0, background: '#000', color: '#f6f6f6', padding: '14px 20px 16px', display: 'flex', flexDirection: 'column', gap: 12 };
const bkBody = { flex: 1, padding: '22px 20px', display: 'flex', flexDirection: 'column', gap: 14, minHeight: 0, overflow: 'hidden' };
const bkH1 = { fontSize: 24, fontWeight: 600, lineHeight: 1.2, letterSpacing: '-0.01em' };
const bkLabel = { fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const bkCta = { minHeight: 56, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 17, fontWeight: 700, border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const bkCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: 16 };
const bkSlot = { flex: 1, minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' };

function BkSteps({ n, logo, back, title, sub }) {
  return (
    <div style={bkTop}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
        {back ? <React.Fragment><i className="ri-arrow-left-line" style={{ fontSize: 22 }} /><span style={{ fontSize: 15, fontWeight: 600 }}>{title}</span></React.Fragment>
          : <React.Fragment><img src={logo} alt="Paddock" style={{ height: 20, display: 'block' }} /><div style={{ flex: 1 }} /><span style={{ fontSize: 12, color: '#a5a5a5' }}>Dunkerque</span></React.Fragment>}
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {[1, 2, 3, 4].map((i) => <span key={i} style={{ flex: 1, height: 4, background: i <= n ? 'var(--pk-accent)' : '#4a4a4a' }} />)}
      </div>
      <span style={{ fontSize: 12, color: '#a5a5a5' }}>Étape {n} sur 4 · {sub}</span>
    </div>
  );
}

function BookingFlow({ logo }) {
  const [step, setStep] = React.useState(1);
  const [slot, setSlot] = React.useState('mar-8');
  const [done, setDone] = React.useState(false);

  if (done) return (
    <div style={bkShell}>
      <div style={{ ...bkTop, padding: '18px 20px' }}><img src={logo} alt="Paddock" style={{ height: 18, display: 'block' }} /></div>
      <div style={{ ...bkBody, gap: 18 }}>
        <i className="ri-checkbox-circle-fill" style={{ fontSize: 48, color: 'var(--pk-success-ink)' }} />
        <div style={bkH1}>C’est réservé</div>
        <div style={{ fontSize: 15, lineHeight: 1.55, color: 'var(--pk-ink-quiet)' }}>Mardi 26 août, dépôt à 8 h 00. Un SMS de confirmation part sur le 06 12 34 56 78, et un autre quand la moto sera prête.</div>
        <div style={{ ...bkCard, display: 'flex', flexDirection: 'column', gap: 7 }}>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, fontSize: 15 }}><span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>Révision 20 000 km</span><span style={{ fontWeight: 700 }}>289 €</span></div>
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Annulation libre jusqu’au lundi 18 h, par SMS ou par téléphone.</div>
        </div>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={() => { setDone(false); setStep(1); }} style={bkCta}>Recommencer la démonstration</button>
      </div>
    </div>
  );

  if (step === 1) return (
    <div style={bkShell}>
      <BkSteps n={1} logo={logo} sub="votre moto" />
      <div style={{ ...bkBody, gap: 18 }}>
        <div style={bkH1}>Quelle moto voulez-vous nous confier ?</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={bkLabel}>Immatriculation</span>
          <div style={{ minHeight: 56, display: 'flex', alignItems: 'center', padding: '0 16px', background: 'var(--pk-surface)', border: '1px solid #000', fontSize: 22, fontWeight: 600, letterSpacing: '0.06em' }}>EF-771-GH</div>
          <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Nous retrouvons la moto si elle est déjà passée chez nous.</span>
        </div>
        <div style={{ ...bkCard, display: 'flex', gap: 14, alignItems: 'flex-start' }}>
          <i className="ri-checkbox-circle-fill" style={{ fontSize: 22, color: 'var(--pk-success-ink)', flexShrink: 0 }} />
          <div>
            <div style={{ fontSize: 16, fontWeight: 600 }}>Yamaha MT-07 · 2021</div>
            <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', marginTop: 3 }}>Dernier passage le 19 février, 17 400 km. Révision des 20 000 conseillée.</div>
          </div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={bkLabel}>Kilométrage actuel</span>
          <div style={{ minHeight: 56, display: 'flex', alignItems: 'center', padding: '0 16px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', fontSize: 20 }}>19 842 km</div>
        </div>
        <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Première visite ? Saisir la moto à la main — marque, modèle, année, trois champs.</div>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={() => setStep(2)} style={bkCta}>Continuer</button>
        <span style={{ fontSize: 13, textAlign: 'center', color: 'var(--pk-ink-muted)' }}>Aucun compte à créer.</span>
      </div>
    </div>
  );

  if (step === 2) return (
    <div style={bkShell}>
      <BkSteps n={2} back title="MT-07 · EF-771-GH" sub="ce dont vous avez besoin" />
      <div style={bkBody}>
        <div style={bkH1}>De quoi a-t-elle besoin ?</div>
        <div style={{ ...bkCard, border: '2px solid #000', display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
            <span style={{ flex: 1, fontSize: 17, fontWeight: 600 }}>Révision 20 000 km</span>
            <span style={{ fontSize: 19, fontWeight: 700 }}>289 €</span>
          </div>
          <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Huile, filtres, bougies, contrôle des 22 points constructeur. Immobilisation : une demi-journée.</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 13, fontWeight: 600, color: 'var(--pk-success-ink)', marginTop: 2 }}><i className="ri-star-line" style={{ fontSize: 15 }} />Conseillée pour votre kilométrage</div>
        </div>
        <div style={{ ...bkCard, display: 'flex', alignItems: 'baseline', gap: 10 }}>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 17, fontWeight: 600 }}>Pneus</div>
            <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Avant, arrière ou les deux</div>
          </div>
          <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--pk-ink-quiet)' }}>dès 145 €</span>
        </div>
        <div style={{ ...bkCard, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ fontSize: 17, fontWeight: 600 }}>Autre chose, ou je ne sais pas</div>
          <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Décrivez le symptôme. Nous prévoyons un diagnostic d’une heure et vous appelons avant tout travail.</div>
          <div style={{ minHeight: 56, padding: '11px 14px', background: 'var(--pk-surface-raised)', border: '1px solid #6f6e6e', fontSize: 14, color: 'var(--pk-ink-quiet)' }}>Un bruit à froid côté droit…</div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ background: '#fff5d9', border: '1px solid var(--pk-accent)', padding: '13px 15px', fontSize: 13, lineHeight: 1.5, color: '#4a3000' }}>Le prix affiché est celui du forfait. Si nous découvrons autre chose, nous vous demandons votre accord avant de le faire.</div>
        <button type="button" onClick={() => setStep(3)} style={bkCta}>Voir les créneaux · 289 €</button>
      </div>
    </div>
  );

  if (step === 3) return (
    <div style={bkShell}>
      <BkSteps n={3} back title="Révision 20 000 · 289 €" sub="quand nous la déposez-vous" />
      <div style={{ ...bkBody, gap: 16 }}>
        <div style={bkH1}>Quand pouvez-vous la déposer ?</div>
        <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Dépôt entre 8 h et 9 h, moto prête en fin de journée. Ces créneaux sont réservés en direct : ce que vous choisissez est tenu.</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {[['Lun 25 août', [['lun-8', '8 h 00'], null]], ['Mar 26 août', [['mar-8', '8 h 00'], ['mar-830', '8 h 30']]], ['Mer 27 août', [['mer-8', '8 h 00'], ['mer-9', '9 h 00']]], ['Jeu 28 août', [null, null]]].map(([day, slots]) => (
            <div key={day} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <span style={{ width: 96, flex: 'none', fontSize: 15, fontWeight: 600 }}>{day}</span>
              {slots.map((s, i) => (s
                ? <button type="button" key={s[0]} onClick={() => setSlot(s[0])}
                    style={{ ...bkSlot, background: slot === s[0] ? '#000' : 'var(--pk-surface)', color: slot === s[0] ? 'var(--pk-accent)' : 'inherit', border: slot === s[0] ? 'none' : '1px solid var(--pk-border)', fontWeight: slot === s[0] ? 700 : 600 }}>{s[1]}</button>
                : <span key={day + i} style={{ ...bkSlot, background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', fontSize: 15, fontWeight: 400, color: 'var(--pk-ink-muted)', cursor: 'default' }}>complet</span>))}
            </div>
          ))}
        </div>
        <div style={{ ...bkCard, display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 600 }}><i className="ri-calendar-check-line" style={{ fontSize: 18, color: 'var(--pk-success-ink)' }} />Mardi 26 août, dépôt à 8 h 00</div>
          <div style={{ fontSize: 14, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Moto prête le mardi soir à partir de 17 h. Nous vous envoyons un SMS dès qu’elle est prête.</div>
        </div>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Besoin d’un prêt de véhicule ou d’une reprise en soirée ? Notez-le à l’étape suivante.</span>
        <button type="button" onClick={() => setStep(4)} style={bkCta}>Continuer</button>
      </div>
    </div>
  );

  return (
    <div style={bkShell}>
      <BkSteps n={4} back title="Mar 26 août · 8 h 00" sub="pour vous joindre" />
      <div style={{ ...bkBody, padding: 20, gap: 12 }}>
        <div style={bkH1}>Comment vous joindre ?</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={bkLabel}>Nom</span>
            <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', padding: '0 15px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', fontSize: 16 }}>Nadia Belkacem</div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={bkLabel}>Téléphone</span>
            <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', padding: '0 15px', background: 'var(--pk-surface)', border: '1px solid #000', fontSize: 16 }}>06 12 34 56 78</div>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Un SMS pour confirmer, un autre quand la moto est prête. Rien d’autre.</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={bkLabel}>E-mail (facultatif)</span>
            <div style={{ minHeight: 52, display: 'flex', alignItems: 'center', padding: '0 15px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', fontSize: 16, color: 'var(--pk-ink-quiet)' }}>pour recevoir la facture</div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={bkLabel}>À nous signaler</span>
            <div style={{ minHeight: 48, padding: '11px 14px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', fontSize: 14, color: 'var(--pk-ink-quiet)' }}>Top-case, antivol, réserve d’essence…</div>
          </div>
        </div>
        <div style={{ ...bkCard, padding: 15, display: 'flex', flexDirection: 'column', gap: 7 }}>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, fontSize: 15 }}><span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>Révision 20 000 km</span><span style={{ fontWeight: 700 }}>289 €</span></div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, fontSize: 15 }}><span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>Acompte à la réservation</span><span style={{ fontWeight: 600 }}>aucun</span></div>
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', paddingTop: 6, borderTop: '1px solid var(--pk-border-quiet)' }}>Vous réglez à la restitution. Annulation libre jusqu’à la veille 18 h, par SMS ou par téléphone.</div>
        </div>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={() => setDone(true)} style={bkCta}>Réserver le mardi 26 à 8 h</button>
        <span style={{ fontSize: 12, lineHeight: 1.5, textAlign: 'center', color: 'var(--pk-ink-muted)' }}>En réservant vous acceptez les conditions de l’atelier et notre politique de confidentialité.</span>
      </div>
    </div>
  );
}
Object.assign(window, { BookingFlow, BkSteps });
