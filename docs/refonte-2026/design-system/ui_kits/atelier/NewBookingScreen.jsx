/* Prise de RDV au comptoir — un seul écran : client et moto, prestations
   filtrées pour la cylindrée, créneau recommandé à droite. */
const { Button: NrButton } = window.PaddockDesignSystem_8059f4;

const nrOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const nrStep = { width: 22, height: 22, background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' };
const nrChip = { whiteSpace: 'nowrap', flexShrink: 0, padding: '5px 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const nrSlot = { padding: '8px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 13, fontWeight: 500, cursor: 'pointer', color: 'inherit' };
const nrSlotOff = { ...nrSlot, background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', color: '#a5a5a5', fontWeight: 400, cursor: 'default' };

const NR_SERVICES = [
  { name: 'Révision 20 000 km', sub: 'Vidange, filtres, bougies, contrôle général', time: '1 h 40', price: '172,50 €', on: true },
  { name: 'Plaquettes de frein avant', sub: 'Pièce en rupture · délai fournisseur 48 h', subError: true, time: '1 h', price: '74,90 €', on: true },
  { name: 'Contrôle et réglage de chaîne', sub: 'Tension, alignement, graissage', time: '0 h 20', price: '28,00 €' },
  { name: 'Purge du circuit de frein', sub: 'Recommandé tous les 2 ans', time: '0 h 40', price: '56,00 €' },
  { name: 'Remplacement pneu arrière', sub: '180/55 ZR17 · 1 en stock', time: '0 h 50', price: '168,00 €' },
  { name: 'Diagnostic électronique', sub: 'Lecture de la valise, forfait 1 h', time: '1 h', price: '96,00 €' },
];

const NR_DAYS = [
  { day: 'Lundi 17', bays: '2 ponts libres', slots: [{ t: '08:00' }, { t: '10:45' }, { t: '14:00', off: true }, { t: '15:30' }] },
  { day: 'Mardi 18', bays: '3 ponts libres', best: true, raised: true, slots: [{ t: '08:30' }, { t: '11:00' }, { t: '14:00' }, { t: '16:00' }],
    note: 'Les plaquettes seront livrées lundi : ce créneau permet de tout faire en une fois.' },
  { day: 'Mercredi 19', bays: '1 pont libre', slots: [{ t: '09:00' }, { t: '13:30', off: true }] },
];

function NewBookingScreen({ onCreate }) {
  const [picked, setPicked] = React.useState(['Révision 20 000 km', 'Plaquettes de frein avant']);
  const [slot, setSlot] = React.useState('Mardi 18 08:30');
  const [cat, setCat] = React.useState('Entretien · 8');
  const toggle = (n) => setPicked((p) => (p.indexOf(n) === -1 ? p.concat(n) : p.filter((x) => x !== n)));
  const chosen = NR_SERVICES.filter((s) => picked.indexOf(s.name) !== -1);
  const total = chosen.reduce((t, s) => t + parseFloat(s.price.replace(/\s/g, '').replace(',', '.')), 0);

  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Nouveau rendez-vous</div>
          <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
        </div>

        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
            <span style={nrStep}>1</span>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Client et moto</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-success-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />Fiche existante trouvée</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, height: 42, padding: '0 14px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)' }}>
            <i className="ri-search-line" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <span style={{ fontSize: 14 }}>GT-908-ZK</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>plaque, nom ou téléphone</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '12px 14px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-success-line)', borderRadius: 'var(--pk-radius-card)' }}>
            <div style={{ width: 38, height: 38, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 600 }}>NB</div>
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: 14, fontWeight: 600 }}>Nadia Belkacem</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>07 88 21 63 40 · cliente depuis 2021 · 4 passages</div>
            </div>
            <div style={{ width: 1, height: 34, background: 'var(--pk-border-quiet)' }} />
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: 14, fontWeight: 600 }}>Yamaha Tracer 9 · 2022</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>GT-908-ZK · 890 cm³ · roadster</div>
            </div>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Changer</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: 'var(--pk-ink-quiet)' }}>
            <i className="ri-information-line" style={{ fontSize: 15 }} />
            Dernier passage le 2 mars 2026 · révision 20 000 km attendue depuis 1 200 km
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '14px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={nrStep}>2</span>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Prestations</span>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>filtrées pour un roadster 890 cm³</span>
            <div style={{ flex: 1 }} />
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 30, padding: '0 10px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 12, width: 200 }}><i className="ri-search-line" style={{ fontSize: 14 }} />Chercher une prestation</div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)' }}>
            {['Entretien · 8', 'Freinage · 5', 'Pneumatiques · 4', 'Diagnostic · 3'].map((c) => (
              <button type="button" key={c} onClick={() => setCat(c)}
                style={{ ...nrChip, background: cat === c ? '#000' : 'transparent', color: cat === c ? '#fff' : 'inherit', border: cat === c ? 'none' : '1px solid var(--pk-border-control)', fontWeight: cat === c ? 600 : 400 }}>{c}</button>
            ))}
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Ajouter une ligne libre</span>
          </div>
          {NR_SERVICES.map((s, i) => {
            const on = picked.indexOf(s.name) !== -1;
            return (
              <button type="button" key={s.name} onClick={() => toggle(s.name)}
                style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '12px 16px', background: on ? 'var(--pk-accent-soft)' : i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', border: 'none', borderBottomStyle: 'solid', borderBottomWidth: i === NR_SERVICES.length - 1 ? 0 : 1, borderBottomColor: 'var(--pk-border-quiet)', textAlign: 'left', color: 'inherit', cursor: 'pointer', width: '100%' }}>
                {on
                  ? <div style={{ width: 22, height: 22, background: 'var(--pk-accent)', borderRadius: 5, display: 'flex', alignItems: 'center', justifyContent: 'center' }}><i className="ri-check-line" style={{ fontSize: 15, color: '#000' }} /></div>
                  : <div style={{ width: 22, height: 22, border: '1px solid var(--pk-border-control)', borderRadius: 5 }} />}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 14, fontWeight: 600 }}>{s.name}</div>
                  <div style={{ fontSize: 12, color: s.subError ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)' }}>{s.sub}</div>
                </div>
                <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', width: 70, textAlign: 'right' }}>{s.time}</span>
                <span style={{ fontSize: 14, fontWeight: 700, width: 90, textAlign: 'right' }}>{s.price}</span>
              </button>
            );
          })}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 400, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={nrStep}>3</span>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Créneau</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>2 h 40 à placer</span>
        </div>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'center', gap: 8 }}>
          <i className="ri-arrow-left-s-line" style={{ fontSize: 18, color: 'var(--pk-ink-quiet)' }} />
          <span style={{ flex: 1, textAlign: 'center', fontSize: 13, fontWeight: 600 }}>Semaine du 17 au 22 août</span>
          <i className="ri-arrow-right-s-line" style={{ fontSize: 18, color: 'var(--pk-ink-quiet)' }} />
        </div>
        {NR_DAYS.map((d) => (
          <div key={d.day} style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', background: d.raised ? 'var(--pk-surface-raised)' : 'transparent', display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
              <span style={{ fontSize: 13, fontWeight: 700 }}>{d.day}</span>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{d.bays}</span>
              {d.best ? <><div style={{ flex: 1 }} /><span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-success-ink)' }}>Recommandé</span></> : null}
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7 }}>
              {d.slots.map((s) => {
                const key = d.day + ' ' + s.t;
                const on = slot === key;
                return s.off
                  ? <span key={s.t} style={nrSlotOff}>{s.t}</span>
                  : <button type="button" key={s.t} onClick={() => setSlot(key)} style={{ ...nrSlot, background: on ? 'var(--pk-accent)' : 'var(--pk-surface-raised)', border: on ? '1px solid var(--pk-accent)' : '1px solid var(--pk-border-control)', color: on ? '#000' : 'inherit', fontWeight: on ? 600 : 500 }}>{s.t}</button>;
              })}
            </div>
            {d.note ? <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, color: 'var(--pk-success-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />{d.note}</div> : null}
          </div>
        ))}
        <div style={{ flex: 1 }} />
        <div style={{ padding: '16px 18px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', display: 'flex', flexDirection: 'column', gap: 10 }}>
          <span style={nrOverline}>Récapitulatif</span>
          {chosen.map((s) => (
            <div key={s.name} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>{s.name}</span><span style={{ fontWeight: 600 }}>{s.price}</span></div>
          ))}
          <div style={{ height: 1, background: 'var(--pk-border-quiet)' }} />
          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
            <span style={{ fontSize: 14, fontWeight: 600 }}>Total estimé</span>
            <span style={{ fontSize: 22, fontWeight: 700 }}>{total.toFixed(2).replace('.', ',')} €</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: 13, color: 'var(--pk-ink-quiet)' }}>
            <span>{slot.replace(' ', ' août · ')} → 11:10</span>
            <span>2 h 40</span>
          </div>
          <NrButton variant="primary" tone="accent" size="medium" fullWidth onClick={onCreate}>Créer le rendez-vous</NrButton>
        </div>
      </aside>
    </div>
  );
}
Object.assign(window, { NewBookingScreen });
