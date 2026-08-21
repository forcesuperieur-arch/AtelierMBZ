/* Fiche client — tour 23. Tout sur un écran : encours, ce qui est en cours,
   les motos, l'historique fusionné, les notes internes à droite. */
const { Button: PkButton, Pill: PkPill } = window.PaddockDesignSystem_8059f4;

const cdOverline = { fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const cdStatCard = { padding: '12px 18px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 3, minWidth: 116 };
const cdCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' };
const cdChip = { whiteSpace: 'nowrap', flexShrink: 0, display: 'flex', alignItems: 'center', gap: 6, padding: '5px 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const cdHistCols = { display: 'grid', gridTemplateColumns: '118px 24px 1fr 170px 110px 100px', alignItems: 'center', gap: 12, padding: '8px 16px', borderBottom: '1px solid var(--pk-border-quiet)' };

const CD_HISTORY = [
  { date: 'Aujourd’hui', icon: 'ri-tools-fill', iconColor: 'var(--pk-warning-ink)', title: 'Révision 20 000 km · en cours', sub: 'Plaquettes avant proposées, en attente d’accord', bike: 'Tracer 9', amount: '268,00 €', state: 'En atelier', stateColor: 'var(--pk-warning-ink)', live: true },
  { date: '12 août', icon: 'ri-draft-line', title: 'Devis DV-2431 accepté en ligne', sub: 'Signature électronique · 4 jours après l’envoi', bike: 'Tracer 9', amount: '268,00 €', state: 'Accepté', stateColor: 'var(--pk-success-ink)' },
  { date: '4 août', icon: 'ri-calendar-check-line', title: 'RDV pris par téléphone', sub: 'Motif annoncé : révision + bruit de frein avant', bike: 'Tracer 9', amount: '—', state: 'Julie D.' },
  { date: '2 mars 2026', icon: 'ri-bank-card-line', title: 'Pneu arrière + équilibrage', sub: 'Facture FA-1104 · payée par carte', bike: 'Tracer 9', amount: '214,00 €', state: 'Payé', stateColor: 'var(--pk-success-ink)' },
  { date: '18 sept. 2025', icon: 'ri-bank-card-line', title: 'Révision 10 000 km', sub: 'Facture FA-0912 · réalisée à Roubaix', bike: 'Tracer 9', amount: '189,00 €', state: 'Payé', stateColor: 'var(--pk-success-ink)' },
];

const CD_BIKES = [
  { icon: 'ri-motorbike-fill', name: 'Yamaha Tracer 9 · 2022', plate: 'GT-908-ZK · 28 412 km', last: 'Aujourd’hui', next: '30 000 km ou août 2027', tag: 'EN ATELIER', here: true },
  { icon: 'ri-motorbike-line', name: 'Yamaha MT-125 · 2019', plate: 'CV-330-BX · 14 060 km', last: 'Mai 2024', next: 'En retard de 15 mois', nextLate: true, tag: 'AU GARAGE' },
];

function ClientDetailScreen({ onOpenBike, onOpenOrder }) {
  const [tab, setTab] = React.useState('Tout l’historique');
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, background: 'var(--pk-canvas)' }}>
      <div style={{ flexShrink: 0, padding: '18px 22px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'flex-start', gap: 24 }}>
        <div style={{ width: 56, height: 56, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 19, fontWeight: 600, flexShrink: 0 }}>NB</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 7, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Nadia Belkacem</span>
            <span style={{ padding: '3px 10px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, letterSpacing: '0.04em' }}>FIDÈLE · 5 ANS</span>
          </div>
          <div style={{ display: 'flex', gap: 18, fontSize: 13, color: 'var(--pk-ink-quiet)' }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-phone-line" style={{ fontSize: 15 }} />07 88 21 63 40</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-mail-line" style={{ fontSize: 15 }} />n.belkacem@mail.fr</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-map-pin-line" style={{ fontSize: 15 }} />Lambersart (59)</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-message-2-line" style={{ fontSize: 15 }} />SMS accepté</span>
          </div>
          <div style={{ display: 'flex', gap: 8, marginTop: 2 }}>
            <button type="button" style={cdChip}><i className="ri-add-line" style={{ fontSize: 15 }} />Nouveau RDV</button>
            <button type="button" style={cdChip}><i className="ri-draft-line" style={{ fontSize: 15 }} />Nouveau devis</button>
            <button type="button" style={cdChip}><i className="ri-sticky-note-line" style={{ fontSize: 15 }} />Ajouter une note</button>
          </div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', gap: 12 }}>
          <div style={cdStatCard}><span style={cdOverline}>Passages</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>5</span><span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>depuis 2021</span></div>
          <div style={cdStatCard}><span style={cdOverline}>Total dépensé</span><span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>839 €</span><span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>168 € en moyenne</span></div>
          <div style={{ ...cdStatCard, background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)' }}>
            <span style={{ ...cdOverline, color: 'var(--pk-success-ink)' }}>Encours</span>
            <span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-success-ink)' }}>0 €</span>
            <span style={{ fontSize: 11, color: 'var(--pk-success-ink)' }}>rien à réclamer</span>
          </div>
        </div>
      </div>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '13px 16px', background: '#000', color: 'var(--pk-ink-on-dark)', borderRadius: 'var(--pk-radius-card)' }}>
            <i className="ri-tools-fill" style={{ fontSize: 20, color: 'var(--pk-accent)' }} />
            <span style={{ fontSize: 15, fontWeight: 600 }}>Sa Tracer 9 est à l’atelier en ce moment</span>
            <span style={{ fontSize: 14, color: '#d4d4d4' }}>Pont 2 · sortie annoncée 17 h · 1 travail en attente d’accord</span>
            <div style={{ flex: 1 }} />
            <PkButton variant="primary" tone="accent" size="small" startIcon="ri-external-link-line" onClick={onOpenOrder}>Ouvrir l’OR 2431</PkButton>
          </div>

          <div style={cdCard}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 14, fontWeight: 600 }}>Ses motos</span>
              <span style={{ minWidth: 20, height: 20, padding: '0 6px', borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', fontSize: 11, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>2</span>
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Ajouter une moto</span>
            </div>
            {CD_BIKES.map((b, i) => (
              <button type="button" key={b.plate} onClick={b.here ? onOpenBike : undefined}
                style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '9px 16px', background: i === 0 ? 'var(--pk-surface-raised)' : 'transparent', border: 'none', borderBottomStyle: 'solid', borderBottomWidth: i === 0 ? 1 : 0, borderBottomColor: 'var(--pk-border-quiet)', textAlign: 'left', color: 'inherit', cursor: 'pointer', width: '100%' }}>
                <i className={b.icon} style={{ fontSize: 22, color: 'var(--pk-ink-quiet)' }} />
                <div style={{ width: 240 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{b.name}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{b.plate}</div></div>
                <div style={{ width: 150 }}><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Dernier passage</div><div style={{ fontSize: 13 }}>{b.last}</div></div>
                <div style={{ width: 170 }}><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Prochaine révision</div><div style={{ fontSize: 13, color: b.nextLate ? 'var(--pk-error-ink)' : undefined, fontWeight: b.nextLate ? 600 : undefined }}>{b.next}</div></div>
                <div style={{ flex: 1 }} />
                {b.here
                  ? <span style={{ padding: '4px 10px', background: 'var(--pk-accent-soft)', border: '1px solid var(--pk-accent)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-accent-ink)' }}>{b.tag}</span>
                  : <span style={{ padding: '4px 10px', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-quiet)' }}>{b.tag}</span>}
                <i className="ri-arrow-right-s-line" style={{ fontSize: 18, color: 'var(--pk-ink-muted)' }} />
              </button>
            ))}
          </div>

          <div style={{ ...cdCard, flex: 1, minHeight: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, padding: '0 16px', borderBottom: '1px solid var(--pk-border)' }}>
              {['Tout l’historique', 'Interventions', 'Devis et factures', 'Échanges'].map((t) => (
                <button type="button" key={t} onClick={() => setTab(t)}
                  style={{ padding: '12px 2px', background: 'transparent', border: 'none', borderBottomStyle: 'solid', borderBottomWidth: 2, borderBottomColor: tab === t ? '#000' : 'transparent', fontSize: 14, fontWeight: tab === t ? 600 : 400, color: tab === t ? 'inherit' : 'var(--pk-ink-quiet)', cursor: 'pointer' }}>{t}</button>
              ))}
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Du plus récent au plus ancien</span>
            </div>
            {CD_HISTORY.map((r, i) => (
              <div key={r.title} style={{ ...cdHistCols, background: i % 2 === 0 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: r.live ? '3px solid var(--pk-accent)' : 'none' }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>{r.date}</span>
                <i className={r.icon} style={{ fontSize: 17, color: r.iconColor || 'var(--pk-ink-quiet)' }} />
                <div><div style={{ fontSize: 13, fontWeight: 600 }}>{r.title}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{r.sub}</div></div>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{r.bike}</span>
                <span style={{ fontSize: 13, fontWeight: r.amount === '—' ? 400 : 600, color: r.amount === '—' ? 'var(--pk-ink-muted)' : undefined }}>{r.amount}</span>
                <span style={{ fontSize: 12, fontWeight: r.stateColor ? 600 : 400, color: r.stateColor || 'var(--pk-ink-quiet)' }}>{r.state}</span>
              </div>
            ))}
            <div style={{ flex: 1 }} />
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
              <i className="ri-arrow-down-s-line" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
              <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-link)' }}>Voir les 2 passages plus anciens</span>
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Mai 2024 et avril 2021 · MT-125 · 168 €</span>
            </div>
          </div>
        </div>

        <aside style={{ width: 344, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ padding: '13px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
            <i className="ri-lock-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />
            <span style={{ fontSize: 15, fontWeight: 600 }}>Notes internes</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Ajouter</span>
          </div>
          <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-accent-soft)', display: 'flex', flexDirection: 'column', gap: 5 }}>
            <div style={{ fontSize: 13, lineHeight: 1.45 }}>Tient à récupérer sa moto avant 17 h, garde d’enfants. À rappeler si ça glisse.</div>
            <div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>Julie D. · ce matin</div>
          </div>
          <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 5 }}>
            <div style={{ fontSize: 13, lineHeight: 1.45 }}>Roule beaucoup, 9 400 km par an. Proposer la révision au kilométrage plutôt qu’à l’année.</div>
            <div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>Karim M. · mars 2026</div>
          </div>
          <div style={{ padding: '12px 18px', display: 'flex', flexDirection: 'column', gap: 8, borderBottom: '1px solid var(--pk-border-quiet)' }}>
            <span style={cdOverline}>Préférences</span>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Contact</span><span style={{ fontWeight: 600 }}>SMS puis appel</span></div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Facturation</span><span style={{ fontWeight: 600 }}>Particulier</span></div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Atelier habituel</span><span style={{ fontWeight: 600 }}>Atelier Principal</span></div>
            </div>
          </div>
          <div style={{ padding: '12px 18px', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={cdOverline}>Relation</span>
            <div style={{ padding: '11px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 4 }}>
              <div style={{ fontSize: 13, fontWeight: 600 }}>Cliente depuis avril 2021</div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Aucun retard de paiement · aucun litige</div>
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)' }}>
            <PkButton variant="secondary" size="medium" startIcon="ri-phone-line" style={{ width: '100%' }}>Appeler Nadia</PkButton>
          </div>
        </aside>
      </div>
    </div>
  );
}
Object.assign(window, { ClientDetailScreen });
