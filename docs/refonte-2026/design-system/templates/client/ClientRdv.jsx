/* Détail d'un rendez-vous (`pages/rdvs/[id].vue`) et prise de rendez-vous en
   quatre étapes (`pages/rdvs/new.vue`). Le détail porte le seul moment où le
   client décide : accepter ou refuser des travaux supplémentaires, signature
   à l'appui. */
const rdRow = { display: 'flex', justifyContent: 'space-between', gap: 12, padding: '11px 0', borderBottom: '1px solid var(--border-2)', fontSize: 14 };
const rdBlock = { marginTop: 20 };
const rdLabel = { fontSize: 13, fontWeight: 700, textTransform: 'uppercase', color: 'var(--content-3)', margin: '0 0 10px' };
const rdListItem = { display: 'flex', justifyContent: 'space-between', gap: 12, padding: '8px 0', borderBottom: '1px solid var(--border-2)', fontSize: 14 };
const rdBtn = { padding: '10px 16px', borderRadius: 8, fontSize: 14, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer' };

function SignatureModal({ title, confirmLabel, onClose, onSigned }) {
  return (
    <div style={{ position: 'fixed', inset: 0, background: 'var(--scrim)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24, zIndex: 100 }}>
      <div style={{ width: '100%', maxWidth: 460, background: 'var(--surface-1)', border: '1px solid var(--border-1)', borderRadius: 16, padding: 24 }}>
        <h2 style={{ fontSize: 17, fontWeight: 800, margin: '0 0 6px' }}>{title}</h2>
        <p style={{ fontSize: 13, color: 'var(--content-3)', margin: '0 0 16px' }}>Signez dans le cadre ci-dessous. Votre signature vaut accord sur le montant indiqué.</p>
        <div style={{ height: 130, background: 'var(--surface-2)', border: '1px dashed var(--border-control)', borderRadius: 12, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 6, color: 'var(--content-3)' }}>
          <i className="ri-pen-nib-line" style={{ fontSize: 26 }} />
          <span style={{ fontSize: 13 }}>Signer ici</span>
        </div>
        <div style={{ display: 'flex', gap: 8, marginTop: 16, justifyContent: 'flex-end' }}>
          <button type="button" onClick={onClose} style={{ ...rdBtn, background: 'transparent', border: '1px solid var(--border-1)', color: 'var(--content-1)', fontWeight: 600 }}>Annuler</button>
          <button type="button" onClick={onSigned} style={{ ...rdBtn, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none' }}>{confirmLabel}</button>
        </div>
      </div>
    </div>
  );
}

function RdvDetailScreen({ onBack }) {
  const r = window.CL_RDV_DETAIL;
  const [decision, setDecision] = React.useState(null);
  const [signing, setSigning] = React.useState(false);
  const [confirmAnnul, setConfirmAnnul] = React.useState(false);
  const [annulDemandee, setAnnulDemandee] = React.useState(false);
  const euro = (n) => n.toFixed(2).replace('.', ',') + ' € TTC';
  const timeline = decision === 'accepte' ? r.timeline.concat([{ statut: 'attente_pieces', date: 'à l’instant' }]) : r.timeline;

  return (
    <div>
      <button type="button" onClick={onBack} style={{ color: 'var(--content-3)', fontSize: 13, background: 'none', border: 'none', padding: 0, cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: 4 }}><i className="ri-arrow-left-line" style={{ fontSize: 16 }} /> Retour aux RDV</button>
      <h1 style={{ fontSize: 20, fontWeight: 800, margin: '12px 0 16px' }}>Détail du rendez-vous</h1>

      <div style={rdRow}><span style={{ color: 'var(--content-3)' }}>Date</span><span style={{ fontWeight: 600 }}>{r.date}</span></div>
      <div style={rdRow}><span style={{ color: 'var(--content-3)' }}>Statut</span><span style={{ ...window.clStatusPill, ...window.statusTone(r.statut) }}>{window.rdvStatutLabel(r.statut)}</span></div>
      <div style={rdRow}><span style={{ color: 'var(--content-3)' }}>Intervention</span><span style={{ fontWeight: 600 }}>{r.intervention}</span></div>
      <div style={rdRow}><span style={{ color: 'var(--content-3)' }}>Moto</span><span style={{ fontWeight: 600 }}>{r.moto}</span></div>
      <div style={rdRow}><span style={{ color: 'var(--content-3)' }}>Immatriculation</span><span style={{ fontWeight: 600 }}>{r.plaque}</span></div>

      <div style={rdBlock}>
        <div style={rdLabel}>Prestations prévues</div>
        <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
          {r.prestations.map((p) => (
            <li key={p.designation} style={rdListItem}><span>{p.designation}</span><span style={{ fontWeight: 600, whiteSpace: 'nowrap' }}>{euro(p.prix)}</span></li>
          ))}
        </ul>
        <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 10, fontSize: 14 }}><span>Total estimé</span><strong>{euro(r.prixEstime)}</strong></div>
        <p style={{ fontSize: 11, color: 'var(--content-3)', marginTop: 6 }}>Montant indicatif (estimation), hors éventuels travaux supplémentaires.</p>
      </div>

      <div style={rdBlock}>
        <div style={rdLabel}>Suivi de votre moto</div>
        <div style={{ ...window.clCard, padding: 16 }}><window.RdvTimeline steps={timeline} /></div>
      </div>

      <div style={rdBlock}>
        <div style={rdLabel}>Travaux supplémentaires</div>
        <div style={{ ...window.clCard, padding: 16, border: decision ? '1px solid var(--border-2)' : '1px solid var(--warning)', background: decision ? 'var(--surface-1)' : 'var(--warning-soft)' }}>
          <div style={{ display: 'flex', gap: 8, marginBottom: 10 }}>
            <span style={{ ...window.clStatusPill, background: decision === 'accepte' ? 'var(--success-soft)' : decision === 'refuse' ? 'var(--error-soft)' : 'var(--warning-soft)', color: decision === 'accepte' ? 'var(--success-content)' : decision === 'refuse' ? 'var(--error-content)' : 'var(--warning-content)', border: '1px solid currentColor' }}>{decision === 'accepte' ? 'Acceptés et signés' : decision === 'refuse' ? 'Refusés' : 'En attente de votre décision'}</span>
            {decision ? null : <span style={{ ...window.clStatusPill, background: 'var(--error-soft)', color: 'var(--error-content)' }}>Urgent</span>}
          </div>
          <p style={{ fontSize: 14, lineHeight: 1.55, margin: '0 0 12px' }}>{r.demande.description}</p>
          <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
            {r.demande.prestations.map((p) => (
              <li key={p.designation} style={rdListItem}><span>{p.designation}</span><span style={{ fontWeight: 600, whiteSpace: 'nowrap' }}>{euro(p.prix)}</span></li>
            ))}
          </ul>
          <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 10, fontSize: 14 }}><span>Total estimé</span><strong>{euro(r.demande.prixEstime)}</strong></div>

          {decision ? (
            <div style={{ marginTop: 12, fontSize: 13, color: 'var(--content-3)' }}>{decision === 'accepte' ? 'Acceptés et signés le 26 août à 15:41. L’atelier a été prévenu.' : 'Refusés le 26 août à 15:41. La moto reste prête à 17 h.'}</div>
          ) : (
            <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
              <button type="button" onClick={() => setDecision('refuse')} style={{ ...rdBtn, flex: 1, background: 'transparent', border: '1px solid var(--border-control)', color: 'var(--content-1)', fontWeight: 600 }}>Refuser</button>
              <button type="button" onClick={() => setSigning(true)} style={{ ...rdBtn, flex: 1, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none' }}>Accepter et signer</button>
            </div>
          )}
        </div>
      </div>

      <div style={rdBlock}>
        <div style={rdLabel}>Photos de l’intervention</div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {[0, 1, 2].map((i) => (
            <div key={i} style={{ width: 120, height: 120, background: 'var(--surface-3)', border: '1px solid var(--border-2)', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--content-3)' }}><i className="ri-image-line" style={{ fontSize: 24 }} /></div>
          ))}
        </div>
      </div>

      <div style={rdBlock}>
        <div style={rdLabel}>État des lieux d’entrée</div>
        <div style={{ ...window.clCard, padding: 16 }}>
          <div style={{ ...rdRow, padding: '8px 0' }}><span style={{ color: 'var(--content-3)' }}>Signé le</span><span>{r.etatDesLieux.signedAt}</span></div>
          <div style={{ ...rdRow, padding: '8px 0' }}><span style={{ color: 'var(--content-3)' }}>Kilométrage</span><span>{window.clNum(r.etatDesLieux.kilometrage)} km</span></div>
          <div style={{ ...rdRow, padding: '8px 0', borderBottom: 'none' }}><span style={{ color: 'var(--content-3)' }}>Niveau de carburant</span><span>{r.etatDesLieux.carburant}</span></div>
          <p style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--content-2)', margin: '8px 0 0' }}>{r.etatDesLieux.observations}</p>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 12, padding: '6px 12px', border: '1px solid var(--border-1)', borderRadius: 8, fontSize: 12, fontWeight: 600 }}><i className="ri-file-text-line" style={{ fontSize: 15 }} />Télécharger le PDF</span>
        </div>
      </div>

      <div style={rdBlock}>
        <div style={rdLabel}>Ordres de réparation</div>
        {r.ordres.map((o) => (
          <div key={o.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, ...window.clCard }}>
            <span style={{ fontSize: 14 }}>{o.numero} — {o.type}</span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--border-1)', borderRadius: 8, fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-file-text-line" style={{ fontSize: 15 }} />Télécharger le PDF</span>
          </div>
        ))}
      </div>

      <div style={rdBlock}>
        {annulDemandee ? (
          <div style={{ padding: '13px 16px', background: 'var(--warning-soft)', border: '1px solid var(--warning)', borderRadius: 12, fontSize: 13, color: 'var(--warning-content)' }}>Demande d’annulation envoyée le 26 août. L’atelier va vous recontacter.</div>
        ) : confirmAnnul ? (
          <div>
            <p style={{ fontSize: 13, color: 'var(--content-1)', marginBottom: 10 }}>Confirmer la demande d’annulation ? L’atelier sera prévenu et vous recontactera.</p>
            <div style={{ display: 'flex', gap: 8 }}>
              <button type="button" onClick={() => { setAnnulDemandee(true); setConfirmAnnul(false); }} style={{ ...rdBtn, background: 'var(--error)', color: 'var(--on-error)', border: 'none' }}>Oui, demander l’annulation</button>
              <button type="button" onClick={() => setConfirmAnnul(false)} style={{ ...rdBtn, background: 'transparent', border: '1px solid var(--border-1)', color: 'var(--content-1)', fontWeight: 600 }}>Non, garder mon RDV</button>
            </div>
          </div>
        ) : (
          <button type="button" onClick={() => setConfirmAnnul(true)} style={{ ...rdBtn, background: 'transparent', border: '1px solid var(--error)', color: 'var(--error-content)' }}>Demander l’annulation de ce rendez-vous</button>
        )}
      </div>

      {signing ? <SignatureModal title="Accepter les travaux supplémentaires" confirmLabel="Accepter et signer" onClose={() => setSigning(false)} onSigned={() => { setDecision('accepte'); setSigning(false); }} /> : null}
    </div>
  );
}

const BK_STEPS = ['Moto', 'Entretien', 'Créneau', 'Confirmation'];

function BookingScreen({ onBack, onDone }) {
  const [step, setStep] = React.useState(1);
  const [moto, setMoto] = React.useState(1);
  const [prestas, setPrestas] = React.useState([1]);
  const [heure, setHeure] = React.useState(null);
  const motos = window.CL_MOTOS;
  const dispo = window.CL_PRESTATIONS;
  const card = { ...window.clCard, padding: 20 };
  const cardTitle = { fontSize: 16, fontWeight: 800, margin: '0 0 14px' };
  const actions = { display: 'flex', gap: 8, marginTop: 18, justifyContent: 'flex-end' };
  const secondary = { ...rdBtn, background: 'transparent', border: '1px solid var(--border-1)', color: 'var(--content-1)', fontWeight: 600 };
  const primary = { ...rdBtn, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none' };
  const choice = (on) => ({ display: 'flex', flexDirection: 'column', gap: 3, padding: '13px 15px', borderRadius: 10, border: on ? '2px solid var(--accent)' : '1px solid var(--border-1)', background: on ? 'var(--accent-soft)' : 'var(--surface-2)', cursor: 'pointer', fontFamily: 'inherit', textAlign: 'left', color: 'inherit' });
  const toggle = (id) => setPrestas(prestas.indexOf(id) === -1 ? prestas.concat([id]) : prestas.filter((p) => p !== id));

  return (
    <div>
      <button type="button" onClick={onBack} style={{ color: 'var(--content-3)', fontSize: 13, background: 'none', border: 'none', padding: 0, cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: 4 }}><i className="ri-arrow-left-line" style={{ fontSize: 16 }} /> Mes rendez-vous</button>
      <h1 style={{ fontSize: 20, fontWeight: 800, margin: '12px 0 16px' }}>Prendre un rendez-vous</h1>

      <ol style={{ listStyle: 'none', display: 'flex', gap: 10, flexWrap: 'wrap', margin: '0 0 18px', padding: 0 }}>
        {BK_STEPS.map((label, i) => {
          const active = step === i + 1;
          const done = step > i + 1;
          return (
            <li key={label} style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 13, fontWeight: active ? 700 : 500, color: active ? 'var(--content-1)' : done ? 'var(--success-content)' : 'var(--content-3)' }}>
              <span style={{ width: 22, height: 22, borderRadius: 999, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 700, background: active ? 'var(--accent)' : done ? 'var(--success-soft)' : 'var(--surface-3)', color: active ? 'var(--accent-ink)' : done ? 'var(--success-content)' : 'var(--content-3)' }}>{done ? '✓' : i + 1}</span>
              {label}
            </li>
          );
        })}
      </ol>

      {step === 1 ? (
        <section style={card}>
          <h2 style={cardTitle}>Quelle moto ?</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {motos.map((m) => (
              <button type="button" key={m.id} onClick={() => setMoto(m.id)} style={choice(moto === m.id)}>
                <span style={{ fontSize: 15, fontWeight: 700 }}>{m.marque} {m.modele}</span>
                <span style={{ display: 'flex', gap: 10, fontSize: 12, color: 'var(--content-3)' }}><span>{m.plaque}</span><span>{m.cylindree}</span></span>
              </button>
            ))}
            <button type="button" onClick={() => setMoto(null)} style={choice(moto === null)}><span style={{ fontSize: 15, fontWeight: 700 }}>Sans moto en particulier</span></button>
          </div>
          <p style={{ fontSize: 13, color: 'var(--content-3)', margin: '12px 0 0' }}>Une autre moto que celles listées ? <span style={{ color: 'var(--accent-content)', fontWeight: 600 }}>Ajouter une moto</span></p>
          <div style={actions}><button type="button" onClick={() => setStep(2)} style={primary}>Continuer</button></div>
        </section>
      ) : null}

      {step === 2 ? (
        <section style={card}>
          <h2 style={cardTitle}>Quel entretien ?</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {dispo.map((p) => {
              const on = prestas.indexOf(p.id) !== -1;
              return (
                <button type="button" key={p.id} onClick={() => toggle(p.id)} style={{ ...choice(on), flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <span style={{ width: 18, height: 18, flex: 'none', borderRadius: 4, border: '1.5px solid ' + (on ? 'var(--accent-graphic)' : 'var(--border-control)'), background: on ? 'var(--accent)' : 'transparent', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, color: 'var(--accent-ink)' }}>{on ? '✓' : ''}</span>
                  <span style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
                    <span style={{ fontSize: 15, fontWeight: 700 }}>{p.nom}</span>
                    <span style={{ fontSize: 12, color: 'var(--content-3)' }}>{p.description}</span>
                  </span>
                  <span style={{ fontSize: 14, fontWeight: 700, whiteSpace: 'nowrap' }}>{p.prix}</span>
                </button>
              );
            })}
          </div>
          <div style={actions}>
            <button type="button" onClick={() => setStep(1)} style={secondary}>Retour</button>
            <button type="button" onClick={() => setStep(3)} style={primary}>Continuer</button>
          </div>
        </section>
      ) : null}

      {step === 3 ? (
        <section style={card}>
          <h2 style={cardTitle}>Quel créneau ?</h2>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14, flexWrap: 'wrap' }}>
            <button type="button" style={{ ...secondary, padding: '8px 12px', fontSize: 13 }}>Jour précédent</button>
            <div style={{ ...window.clInput, minHeight: 38, background: 'var(--surface-1)' }}>mardi 26 août 2026</div>
            <button type="button" style={{ ...secondary, padding: '8px 12px', fontSize: 13 }}>Jour suivant</button>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(92px, 1fr))', gap: 8 }}>
            {window.CL_SLOTS.map((t) => (
              <button type="button" key={t} onClick={() => setHeure(t)}
                style={{ minHeight: 44, borderRadius: 8, fontSize: 14, fontWeight: 700, fontFamily: 'inherit', cursor: 'pointer', background: heure === t ? 'var(--accent)' : 'var(--surface-2)', color: heure === t ? 'var(--accent-ink)' : 'var(--content-1)', border: heure === t ? 'none' : '1px solid var(--border-1)' }}>{t}</button>
            ))}
          </div>
          <div style={actions}>
            <button type="button" onClick={() => setStep(2)} style={secondary}>Retour</button>
            <button type="button" disabled={!heure} onClick={() => setStep(4)} style={{ ...primary, background: heure ? 'var(--accent)' : 'var(--surface-3)', color: heure ? 'var(--accent-ink)' : 'var(--content-disabled)', cursor: heure ? 'pointer' : 'not-allowed' }}>Continuer</button>
          </div>
        </section>
      ) : null}

      {step === 4 ? (
        <section style={card}>
          <h2 style={cardTitle}>Vérifier et confirmer</h2>
          <dl style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: '10px 16px', margin: 0, fontSize: 14 }}>
            <dt style={{ color: 'var(--content-3)' }}>Moto</dt>
            <dd style={{ margin: 0, fontWeight: 600 }}>{moto ? motos.filter((m) => m.id === moto)[0].marque + ' ' + motos.filter((m) => m.id === moto)[0].modele : 'Sans moto en particulier'}</dd>
            <dt style={{ color: 'var(--content-3)' }}>Prestations</dt>
            <dd style={{ margin: 0, fontWeight: 600 }}>{prestas.length ? dispo.filter((p) => prestas.indexOf(p.id) !== -1).map((p) => p.nom).join(', ') : '—'}</dd>
            <dt style={{ color: 'var(--content-3)' }}>Créneau</dt>
            <dd style={{ margin: 0, fontWeight: 600 }}>mardi 26 août 2026 à {heure}</dd>
            <dt style={{ color: 'var(--content-3)' }}>Total estimé</dt>
            <dd style={{ margin: 0, fontWeight: 600 }}>289,00 € (indicatif)</dd>
          </dl>
          <div style={{ ...window.clField, marginTop: 16 }}>
            <label style={window.clLabel}>Un détail à préciser pour l’atelier ? (facultatif)</label>
            <div style={{ ...window.clInput, minHeight: 72, alignItems: 'flex-start', padding: '10px 12px', color: 'var(--content-3)' }}>Bruit, symptôme, demande particulière…</div>
          </div>
          <div style={actions}>
            <button type="button" onClick={() => setStep(3)} style={secondary}>Retour</button>
            <button type="button" onClick={onDone} style={primary}>Confirmer le rendez-vous</button>
          </div>
        </section>
      ) : null}
    </div>
  );
}

Object.assign(window, { RdvDetailScreen, BookingScreen, SignatureModal });
