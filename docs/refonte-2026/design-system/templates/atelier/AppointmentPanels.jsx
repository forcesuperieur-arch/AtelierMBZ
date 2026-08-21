/* Panneaux de rendez-vous — tour 36. 36a : le détail qui s'ouvre sur la case
   cliquée. 36b : reporter ou annuler, avec le créneau libéré chiffré. */
const { Button: RvButton } = window.PaddockDesignSystem_8059f4;

const rvOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const rvShell = { width: 520, background: 'var(--pk-surface)', border: '1px solid var(--pk-border-control)', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)', height: '100%' };
const rvSection = { padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 };
const rvGhost = { flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const rvLine = { display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 };
const rvSlot = { display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', width: '100%', textAlign: 'left', color: 'inherit', cursor: 'pointer' };

function AppointmentPanel({ onClose, onCheckIn, onReschedule }) {
  return (
    <div style={rvShell}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px', borderBottom: '1px solid var(--pk-border)' }}>
        <span style={{ padding: '3px 10px', background: '#000', color: '#fff', fontSize: 11, fontWeight: 700, letterSpacing: '0.04em' }}>RDV CONFIRMÉ</span>
        <div style={{ flex: 1 }} />
        <i className="ri-external-link-line" style={{ fontSize: 19, color: 'var(--pk-ink-muted)' }} />
        <button type="button" onClick={onClose} style={{ background: 'transparent', border: 'none', color: 'var(--pk-ink-muted)', cursor: 'pointer' }}><i className="ri-close-line" style={{ fontSize: 22 }} /></button>
      </div>

      <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'flex-start', gap: 16 }}>
        <div style={{ width: 76, flexShrink: 0, border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ background: '#000', color: '#fff', padding: '4px 0', textAlign: 'center', fontSize: 11, fontWeight: 700, letterSpacing: '0.06em' }}>VEN</div>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '6px 0' }}>
            <span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>15</span>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>août</span>
          </div>
        </div>
        <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.15 }}>08:30 → 11:30</div>
          <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }}>3 h planifiées · pont 2 · Karim M.</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 4 }}>
            <span style={{ padding: '4px 10px', border: '1px solid var(--pk-border)', fontSize: 12 }}>Révision 20 000 km</span>
          </div>
        </div>
      </div>

      <div style={{ padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 12 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <div style={{ width: 38, height: 38, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 600, flexShrink: 0 }}>NB</div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 15, fontWeight: 600 }}>Nadia Belkacem</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>07 88 21 63 40 · cliente depuis 2021</div>
          </div>
          <button type="button" style={{ minHeight: 40, display: 'flex', alignItems: 'center', gap: 7, padding: '0 13px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}><i className="ri-phone-line" style={{ fontSize: 16 }} />Appeler</button>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', border: '1px solid var(--pk-border)' }}>
          <i className="ri-motorbike-fill" style={{ fontSize: 22, color: 'var(--pk-ink-quiet)' }} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14, fontWeight: 600 }}>Yamaha Tracer 9 · GT-908-ZK</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>28 412 km · 4e passage</div>
          </div>
          <i className="ri-arrow-right-s-line" style={{ fontSize: 19, color: 'var(--pk-ink-muted)' }} />
        </div>
      </div>

      <div style={{ ...rvSection, gap: 8 }}>
        <span style={rvOverline}>Motif annoncé au téléphone</span>
        <div style={{ padding: '12px 14px', background: 'var(--pk-surface-raised)', borderLeft: '3px solid var(--pk-accent)', fontSize: 14, lineHeight: 1.5 }}>Révision d’entretien, et un bruit de frein à l’avant depuis deux semaines. Rappeler avant tout travail supplémentaire.</div>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Noté par Julie D. le 4 août</span>
      </div>

      <div style={rvSection}>
        <span style={rvOverline}>Ce que le carnet annonce</span>
        <div style={{ ...rvLine, fontSize: 14 }}><i className="ri-error-warning-line" style={{ fontSize: 17, color: 'var(--pk-error-ink)' }} />Plaquettes avant à changer<div style={{ flex: 1 }} /><span style={{ fontWeight: 600 }}>+ 74,90 €</span></div>
        <div style={{ ...rvLine, fontSize: 14, color: 'var(--pk-ink-quiet)' }}><i className="ri-time-line" style={{ fontSize: 17 }} />Pneu arrière à surveiller<div style={{ flex: 1 }} /><span>~ 3 000 km</span></div>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', lineHeight: 1.45 }}>À annoncer à la réception : le devis est prêt, il suffit de l’envoyer.</span>
      </div>

      <div style={rvSection}>
        <span style={rvOverline}>Suivi</span>
        <div style={rvLine}><i className="ri-check-line" style={{ fontSize: 16, color: 'var(--pk-success-ink)' }} />Pris le 4 août par Julie D.<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-muted)' }}>téléphone</span></div>
        <div style={rvLine}><i className="ri-check-line" style={{ fontSize: 16, color: 'var(--pk-success-ink)' }} />Confirmation envoyée<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-muted)' }}>4 août · SMS + e-mail</span></div>
        <div style={rvLine}><i className="ri-check-line" style={{ fontSize: 16, color: 'var(--pk-success-ink)' }} />Rappel de la veille<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-muted)' }}>hier 18 h</span></div>
      </div>

      <div style={{ flex: 1 }} />
      <div style={{ padding: '16px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
        <RvButton variant="primary" tone="accent" size="medium" fullWidth onClick={onCheckIn}>Réceptionner la moto</RvButton>
        <div style={{ display: 'flex', gap: 8 }}>
          <button type="button" style={rvGhost}>Déplacer</button>
          <button type="button" style={rvGhost} onClick={onReschedule}>Reporter</button>
          <button type="button" style={{ ...rvGhost, border: '1px solid var(--pk-error-line)', color: 'var(--pk-error-ink)' }}>Annuler</button>
        </div>
      </div>
    </div>
  );
}

const RV_REASONS = ['Empêchement · indisponible ce jour-là', 'Moto immobilisée, ne peut pas venir', 'A fait faire ailleurs', 'Ne répond plus'];
const RV_SLOTS = [
  { when: 'Mardi 26 août · 09:00', who: 'Pont 2 · Karim M. · 1 h 20' },
  { when: 'Mercredi 27 août · 14:30', who: 'Pont 4 · Sophie L. · 1 h 20' },
  { when: 'Jeudi 28 août · 08:30', who: 'Pont 2 · Karim M. · 1 h 20' },
];

function ReschedulePanel({ onClose }) {
  const [mode, setMode] = React.useState('Reporter');
  const [who, setWho] = React.useState('Le client');
  const [reason, setReason] = React.useState(RV_REASONS[0]);
  const [slot, setSlot] = React.useState(0);
  const [waitlist, setWaitlist] = React.useState(true);
  return (
    <div style={rvShell}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px', borderBottom: '1px solid var(--pk-border)' }}>
        <span style={{ fontSize: 15, fontWeight: 600 }}>RDV du 22 août · Hugo Lacroix</span>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={onClose} style={{ background: 'transparent', border: 'none', color: 'var(--pk-ink-muted)', cursor: 'pointer' }}><i className="ri-close-line" style={{ fontSize: 22 }} /></button>
      </div>

      <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 8 }}>
        {['Reporter', 'Annuler sans report'].map((m) => (
          <button type="button" key={m} onClick={() => setMode(m)}
            style={{ flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', background: mode === m ? '#000' : 'transparent', color: mode === m ? '#fff' : 'inherit', border: mode === m ? 'none' : '1px solid var(--pk-border-control)', fontSize: 14, fontWeight: mode === m ? 600 : 400, cursor: 'pointer' }}>{m}</button>
        ))}
      </div>

      <div style={{ ...rvSection, gap: 10 }}>
        <span style={rvOverline}>Qui demande, et pourquoi</span>
        <div style={{ display: 'flex', gap: 8 }}>
          {['Le client', 'L’atelier'].map((w) => (
            <button type="button" key={w} onClick={() => setWho(w)}
              style={{ flex: 1, minHeight: 40, display: 'flex', alignItems: 'center', justifyContent: 'center', background: who === w ? 'var(--pk-accent)' : 'transparent', color: who === w ? '#000' : 'inherit', border: who === w ? 'none' : '1px solid var(--pk-border-control)', fontSize: 13, fontWeight: who === w ? 600 : 400, cursor: 'pointer' }}>{w}</button>
          ))}
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          {RV_REASONS.map((r) => (
            <button type="button" key={r} onClick={() => setReason(r)}
              style={{ minHeight: 42, display: 'flex', alignItems: 'center', padding: '0 13px', background: reason === r ? '#000' : 'transparent', color: reason === r ? '#fff' : 'inherit', border: reason === r ? 'none' : '1px solid var(--pk-border-control)', fontSize: 13, fontWeight: reason === r ? 600 : 400, textAlign: 'left', cursor: 'pointer' }}>{r}</button>
          ))}
        </div>
      </div>

      {mode === 'Reporter' ? (
        <div style={{ ...rvSection, gap: 10, background: 'var(--pk-accent-soft)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
            <i className="ri-calendar-check-line" style={{ fontSize: 18, color: 'var(--pk-accent-ink)' }} />
            <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-accent-ink)' }}>Nouveau créneau · le plus proche qui convient</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
            {RV_SLOTS.map((s, i) => (
              <button type="button" key={s.when} onClick={() => setSlot(i)}
                style={{ ...rvSlot, border: slot === i ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border)' }}>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{s.when}</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>{s.who}</div></div>
                {slot === i ? <i className="ri-check-line" style={{ fontSize: 20, color: 'var(--pk-accent-ink)' }} /> : null}
              </button>
            ))}
          </div>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Trois propositions plutôt qu’un calendrier vide : c’est ce qu’on lit au client au téléphone.</span>
        </div>
      ) : null}

      <div style={{ ...rvSection, gap: 10 }}>
        <span style={rvOverline}>Le créneau libéré</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', border: '1px solid var(--pk-border)' }}>
          <i className="ri-time-line" style={{ fontSize: 20, color: 'var(--pk-ink-quiet)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Vendredi 22 août · 10:00 → 11:20</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Pont 3 · 1 h 20 de capacité rendue</div></div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Proposer à la liste d’attente</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>2 clients attendent un créneau de cette durée</div></div>
          <button type="button" onClick={() => setWaitlist((v) => !v)} style={{ display: 'inline-flex', width: 46, height: 26, padding: 3, background: waitlist ? 'var(--pk-accent)' : 'var(--pk-border)', borderRadius: 'var(--pk-radius-pill)', flexShrink: 0, border: 'none', cursor: 'pointer' }}>
            <span style={{ width: 20, height: 20, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: waitlist ? 'auto' : 0 }} />
          </button>
        </div>
      </div>

      <div style={rvSection}>
        <span style={rvOverline}>Ce que le client reçoit</span>
        <div style={{ padding: '12px 14px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', fontSize: 14, lineHeight: 1.55 }}>
          {mode === 'Reporter'
            ? <>Bonjour Hugo, votre rendez-vous du 22 août est reporté au <strong style={{ fontWeight: 600 }}>{RV_SLOTS[slot].when.replace(' · ', ' à ')}</strong>. Atelier Principal, 12 rue de la Gare. À bientôt.</>
            : <>Bonjour Hugo, votre rendez-vous du 22 août est annulé. Rappelez-nous quand vous voulez fixer une nouvelle date. Atelier Principal.</>}
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>SMS uniquement · l’e-mail ferait doublon</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-link)' }}>Modifier</span>
        </div>
      </div>

      <div style={{ flex: 1 }} />
      <div style={{ padding: '16px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
        <RvButton variant="primary" tone="accent" size="medium" fullWidth onClick={onClose}>
          {mode === 'Reporter' ? 'Reporter au ' + RV_SLOTS[slot].when.split(' · ')[0].replace('Mardi ', '').replace('Mercredi ', '').replace('Jeudi ', '') : 'Annuler le rendez-vous'}
        </RvButton>
        <button type="button" onClick={onClose} style={{ ...rvGhost, flex: 'none' }}>Ne rien changer</button>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>Le motif et le report sont inscrits au journal d’audit.</span>
      </div>
    </div>
  );
}
Object.assign(window, { AppointmentPanel, ReschedulePanel });
