/* Admin › Horaires et fermetures (tour 14a). Une ligne par jour, la pause sur la
   même ligne que l'ouverture, et le panneau « Ce que ça change » à droite :
   rien ne s'enregistre tant que les RDV hors horaires n'ont pas de sort. */
const hrCell = { padding: '6px 11px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 6 };
const hrRow = { display: 'grid', gridTemplateColumns: '150px 92px 1fr 1fr 130px', alignItems: 'center', padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const hrPill = { flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap', cursor: 'pointer' };

function HoursToggle({ on }) {
  return (
    <span style={{ display: 'inline-flex', width: 40, height: 22, padding: 2, background: on ? 'var(--pk-accent)' : 'var(--pk-border)', borderRadius: 'var(--pk-radius-pill)' }}>
      <span style={{ width: 18, height: 18, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: on ? 'auto' : 0 }} />
    </span>
  );
}

function HoursRange({ from, to, alt, mark }) {
  return (
    <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <span style={{ ...hrCell, background: alt ? 'var(--pk-surface)' : 'var(--pk-surface-raised)' }}>{from}</span>
      <span style={{ color: 'var(--pk-ink-muted)' }}>→</span>
      <span style={{ ...hrCell, background: alt ? 'var(--pk-surface)' : 'var(--pk-surface-raised)', border: mark ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border-control)', fontWeight: mark ? 700 : 400 }}>{to}</span>
    </span>
  );
}

function HoursScreen() {
  const [wedTo, setWedTo] = React.useState('17:00');
  const [fate, setFate] = React.useState(null);
  const changed = wedTo === '17:00';
  const closures = [
    { date: 'Vendredi 15 août', why: 'Jour férié · Assomption', note: 'Aucun RDV concerné', good: true },
    { date: 'Du 10 au 24 août', why: 'Congés d’été', note: 'Déjà appliqué', good: false },
    { date: 'Lundi 1er novembre', why: 'Toussaint', note: 'Aucun RDV concerné', good: true },
  ];
  return (
    <React.Fragment>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Horaires et fermetures</div>
          <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
          <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>Ces heures produisent les créneaux proposés au comptoir et sur l’app cliente.</div>
        </div>

        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '150px 92px 1fr 1fr 130px', padding: '9px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span>Jour</span><span>Ouvert</span><span>Ouverture</span><span>Pause déjeuner</span><span style={{ textAlign: 'right' }}>Capacité</span>
          </div>

          <div style={hrRow}>
            <span style={{ fontWeight: 600 }}>Lundi</span><span><HoursToggle on /></span>
            <HoursRange from="08:00" to="18:30" /><HoursRange from="12:30" to="13:30" />
            <span style={{ textAlign: 'right', fontWeight: 600 }}>9 h 30</span>
          </div>
          <div style={{ ...hrRow, background: 'var(--pk-surface-raised)' }}>
            <span style={{ fontWeight: 600 }}>Mardi</span><span><HoursToggle on /></span>
            <HoursRange from="08:00" to="18:30" alt /><HoursRange from="12:30" to="13:30" alt />
            <span style={{ textAlign: 'right', fontWeight: 600 }}>9 h 30</span>
          </div>

          <div style={{ ...hrRow, background: changed ? 'var(--pk-accent-soft)' : 'transparent' }}>
            <span style={{ fontWeight: 600 }}>Mercredi{changed ? <span style={{ display: 'block', fontSize: 11, fontWeight: 500, color: 'var(--pk-accent-ink)' }}>modifié à l’instant</span> : null}</span>
            <span><HoursToggle on /></span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={hrCell}>08:00</span>
              <span style={{ color: 'var(--pk-ink-muted)' }}>→</span>
              <button type="button" onClick={() => setWedTo(changed ? '18:30' : '17:00')}
                style={{ ...hrCell, border: changed ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border-control)', fontWeight: changed ? 700 : 400, fontSize: 13, fontFamily: 'inherit', color: 'inherit', cursor: 'pointer' }}>{wedTo}</button>
            </span>
            <HoursRange from="12:30" to="13:30" />
            <span style={{ textAlign: 'right', fontWeight: changed ? 700 : 600, color: changed ? 'var(--pk-accent-ink)' : 'inherit' }}>{changed ? '8 h 00' : '9 h 30'}{changed ? <span style={{ display: 'block', fontSize: 11, fontWeight: 600 }}>− 1 h 30</span> : null}</span>
          </div>

          <div style={hrRow}>
            <span style={{ fontWeight: 600 }}>Jeudi</span><span><HoursToggle on /></span>
            <HoursRange from="08:00" to="18:30" /><HoursRange from="12:30" to="13:30" />
            <span style={{ textAlign: 'right', fontWeight: 600 }}>9 h 30</span>
          </div>
          <div style={{ ...hrRow, background: 'var(--pk-surface-raised)' }}>
            <span style={{ fontWeight: 600 }}>Vendredi</span><span><HoursToggle on /></span>
            <HoursRange from="08:00" to="18:30" alt /><HoursRange from="12:30" to="13:30" alt />
            <span style={{ textAlign: 'right', fontWeight: 600 }}>9 h 30</span>
          </div>
          <div style={hrRow}>
            <span style={{ fontWeight: 600 }}>Samedi</span><span><HoursToggle on /></span>
            <HoursRange from="09:00" to="12:30" />
            <span style={{ color: 'var(--pk-ink-muted)' }}>Aucune</span>
            <span style={{ textAlign: 'right', fontWeight: 600 }}>3 h 30</span>
          </div>
          <div style={{ ...hrRow, borderBottom: 'none', background: '#f4f4f4', color: 'var(--pk-ink-muted)' }}>
            <span style={{ fontWeight: 600 }}>Dimanche</span><span><HoursToggle on={false} /></span>
            <span>Fermé</span><span>—</span><span style={{ textAlign: 'right' }}>—</span>
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Fermetures exceptionnelles</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-add-line" style={{ fontSize: 15 }} />Ajouter une date</span>
          </div>
          {closures.map((c, i) => (
            <div key={c.date} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', borderBottom: i === closures.length - 1 ? 'none' : '1px solid var(--pk-border-quiet)', fontSize: 13, background: i === 1 ? 'var(--pk-surface-raised)' : 'transparent' }}>
              <i className="ri-calendar-close-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />
              <span style={{ fontWeight: 600, width: 200 }}>{c.date}</span>
              <span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>{c.why}</span>
              <span style={{ fontSize: 12, color: c.good ? 'var(--pk-success-ink)' : 'var(--pk-ink-quiet)' }}>{c.note}</span>
              <i className="ri-close-line" style={{ fontSize: 18, color: 'var(--pk-ink-muted)' }} />
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Ce que ça change</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{changed ? '1 modification' : 'Aucune modification'}</span>
        </div>

        {changed ? (
          <React.Fragment>
            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
              <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Capacité hebdomadaire</span>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
                <span style={{ fontSize: 15, color: 'var(--pk-ink-muted)', textDecoration: 'line-through' }}>51 h 00</span>
                <i className="ri-arrow-right-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
                <span style={{ fontSize: 24, fontWeight: 700 }}>49 h 30</span>
              </div>
              <span style={{ fontSize: 13, color: 'var(--pk-accent-ink)' }}>− 1 h 30 par semaine · environ 6 h par mois</span>
            </div>

            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', background: fate ? 'var(--pk-success-surface)' : 'var(--pk-error-surface)', display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <i className={fate ? 'ri-check-line' : 'ri-error-warning-fill'} style={{ fontSize: 18, color: fate ? 'var(--pk-success-ink)' : 'var(--pk-error-ink)' }} />
                <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: fate ? 'var(--pk-success-ink)' : 'var(--pk-error-ink)' }}>{fate === 'move' ? '2 RDV à replacer' : fate === 'keep' ? '2 RDV gardés en exception' : '2 RDV tombent hors horaires'}</span>
              </div>
              <div style={{ padding: '11px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 3 }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Mercredi 20 août · 17:00 → 18:00</span>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Z650 · S. Amrani · pont 4 · confirmé</span>
              </div>
              <div style={{ padding: '11px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 3 }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Mercredi 27 août · 16:30 → 18:00</span>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>CB500F · P. Guérin · pont 2 · réservé</span>
              </div>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>{fate ? 'Sort choisi. L’enregistrement est débloqué.' : 'Choisissez leur sort avant d’enregistrer. Aucun client n’est prévenu automatiquement.'}</span>
              <div style={{ display: 'flex', gap: 8 }}>
                <button type="button" onClick={() => setFate('move')} style={{ ...hrPill, background: fate === 'move' ? 'var(--pk-accent)' : 'var(--pk-surface-raised)', color: '#000', border: fate === 'move' ? 'none' : '1px solid var(--pk-border-control)', fontFamily: 'inherit' }}>Les replacer</button>
                <button type="button" onClick={() => setFate('keep')} style={{ ...hrPill, background: fate === 'keep' ? 'var(--pk-accent)' : 'transparent', color: '#000', border: fate === 'keep' ? 'none' : '1px solid var(--pk-border-strong)', fontFamily: 'inherit' }}>Les garder</button>
              </div>
            </div>

            <div style={{ padding: '14px 18px', display: 'flex', flexDirection: 'column', gap: 9 }}>
              <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Répercussions</span>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 9, fontSize: 13, lineHeight: 1.45 }}><i className="ri-calendar-2-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />Les créneaux du mercredi après 17:00 disparaissent du comptoir et de l’app cliente.</div>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 9, fontSize: 13, lineHeight: 1.45 }}><i className="ri-hourglass-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />Le calcul d’immobilisation en heures ouvrées suit automatiquement.</div>
            </div>
          </React.Fragment>
        ) : (
          <div style={{ padding: '18px', fontSize: 13, color: 'var(--pk-ink-quiet)', lineHeight: 1.5 }}>Les horaires sont ceux enregistrés le 2 juillet. Modifiez la fermeture du mercredi pour voir l’impact avant enregistrement.</div>
        )}

        <div style={{ flex: 1 }} />
        <div style={{ padding: '14px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <button type="button" disabled={changed && !fate}
            style={{ minHeight: 52, borderRadius: 'var(--pk-radius-pill)', border: 'none', background: changed && !fate ? 'var(--pk-border)' : 'var(--pk-accent)', color: changed && !fate ? 'var(--pk-ink-muted)' : '#000', fontSize: 15, fontWeight: 600, fontFamily: 'inherit', cursor: changed && !fate ? 'not-allowed' : 'pointer' }}>Enregistrer les horaires</button>
          <button type="button" onClick={() => { setWedTo('18:30'); setFate(null); }}
            style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>Annuler la modification</button>
        </div>
      </aside>
    </React.Fragment>
  );
}
Object.assign(window, { HoursScreen });
