/* Restitution (tour 9a) — le seul moment du parcours qui n'avait pas d'écran
   atelier. Bandeau d'identité noir, relecture de ce qui a été fait, état
   d'entrée/sortie côte à côte, et la facture + l'encaissement en panneau. */
const hvRow = { display: 'flex', alignItems: 'center', gap: 14, padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)' };
const hvPay = { flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6, borderRadius: 'var(--pk-radius-card)', fontSize: 13, fontFamily: 'inherit', cursor: 'pointer', whiteSpace: 'nowrap' };
const hvShot = { flex: 1, aspectRatio: '4 / 3', background: 'var(--pk-canvas)', border: '1px solid var(--pk-border)', borderRadius: 6, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--pk-ink-muted)' };

const HV_LINES = [
  { name: 'Révision 20 000 km', sub: 'Huile Motul 7100, filtre HF204, filtre à air · main d’œuvre 109,25 € + pièces 121,80 €', time: '1 h 35', total: '231,05 €' },
  { name: 'Bougies remplacées', sub: 'Travail complémentaire accepté par téléphone lundi 11:20 · main d’œuvre 28,75 € + pièces 63,25 €', subTone: 'warn', time: '0 h 25', total: '92,00 €' },
  { name: 'Contrôle et réglage de chaîne', sub: 'Tension reprise, kit encore bon pour ~8 000 km · main d’œuvre seule', time: '0 h 18', total: '20,70 €' },
];

function HandoverScreen({ onDone }) {
  const [pay, setPay] = React.useState('cb');
  const [shots, setShots] = React.useState(0);
  const missing = 3 - shots;
  const outLabels = ['Face', 'Côté droit', 'Compteur'];
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
      <div style={{ flexShrink: 0, background: '#000', color: '#f6f6f6', padding: '16px 24px', display: 'flex', alignItems: 'center', gap: 26 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
            <span style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em' }}>Yamaha MT-09</span>
            <span style={{ padding: '3px 9px', border: '1px solid #a5a5a5', borderRadius: 4, fontSize: 13, fontWeight: 600, letterSpacing: '0.05em' }}>EX-421-QR</span>
          </div>
          <span style={{ fontSize: 13, color: '#d4d4d4' }}>Ludovic Renard · 06 12 44 98 07 · au comptoir</span>
        </div>
        <div style={{ width: 1, height: 44, background: '#2f2f2f' }} />
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#a5a5a5' }}>Immobilisation</span>
          <span style={{ fontSize: 16, fontWeight: 600 }}>7 h 40 · dans les délais</span>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#a5a5a5' }}>Essai routier</span>
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 16, fontWeight: 600, color: '#4dbb3a' }}><i className="ri-check-line" style={{ fontSize: 17 }} />Validé par Karim M.</span>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3, alignItems: 'flex-end' }}>
          <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#a5a5a5' }}>Reste à payer</span>
          <span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1, color: 'var(--pk-accent)' }}>412,50 €</span>
        </div>
      </div>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ flex: 1, padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
          <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 15, fontWeight: 600 }}>Ce qui a été fait</span>
              <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>à relire avec le client · montants HT</span>
              <div style={{ flex: 1 }} />
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-link)', whiteSpace: 'nowrap' }}><i className="ri-printer-line" style={{ fontSize: 15 }} />Imprimer le récapitulatif</span>
            </div>

            {HV_LINES.map((l, i) => (
              <div key={l.name} style={{ ...hvRow, background: i % 2 ? 'var(--pk-surface-raised)' : 'transparent' }}>
                <i className="ri-checkbox-circle-fill" style={{ fontSize: 18, color: 'var(--pk-success-line)', flexShrink: 0 }} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 14, fontWeight: 600 }}>{l.name}</div>
                  <div style={{ fontSize: 12, color: l.subTone === 'warn' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-muted)' }}>{l.sub}</div>
                </div>
                <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>{l.time}</span>
                <span style={{ fontSize: 14, fontWeight: 700, width: 100, textAlign: 'right' }}>{l.total}</span>
              </div>
            ))}

            <div style={{ ...hvRow, borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
              <i className="ri-close-circle-line" style={{ fontSize: 18, color: 'var(--pk-ink-muted)', flexShrink: 0 }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--pk-ink-quiet)' }}>Plaquettes avant — non faites</div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Pièce en rupture · le client repasse jeudi, créneau à proposer</div>
              </div>
              <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>—</span>
              <span style={{ fontSize: 14, fontWeight: 600, width: 100, textAlign: 'right', color: 'var(--pk-ink-muted)' }}>retiré</span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 13, fontWeight: 600 }}>Total HT</span>
              <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>2 h 18</span>
              <span style={{ fontSize: 15, fontWeight: 700, width: 100, textAlign: 'right' }}>343,75 €</span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 15, fontWeight: 600 }}>État d’entrée et de sortie</span>
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Compteur : 28 412 km → 28 419 km (essai)</span>
            </div>

            <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 1, background: 'var(--pk-canvas)', minHeight: 0 }}>
              <div style={{ background: 'var(--pk-surface)', padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>À l’entrée · 08:34</span>
                  <div style={{ flex: 1 }} />
                  <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>signé par le client</span>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                  <div style={hvShot}><i className="ri-image-line" style={{ fontSize: 22 }} /></div>
                  <div style={hvShot}><i className="ri-image-line" style={{ fontSize: 22 }} /></div>
                  <div style={hvShot}><i className="ri-image-line" style={{ fontSize: 22 }} /></div>
                </div>
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8, fontSize: 13, color: 'var(--pk-ink-quiet)' }}><i className="ri-alert-line" style={{ fontSize: 16, color: 'var(--pk-warning-ink-soft)', flexShrink: 0 }} />Rayure réservoir côté droit relevée à l’entrée · top-case laissé sur la moto</div>
              </div>

              <div style={{ background: 'var(--pk-surface-raised)', padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>À la sortie · maintenant</span>
                  <div style={{ flex: 1 }} />
                  <span style={{ fontSize: 12, fontWeight: 600, color: missing ? 'var(--pk-warning-ink-soft)' : 'var(--pk-success-ink)' }}>{missing ? missing + ' photos à prendre' : 'série complète'}</span>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                  {outLabels.map((lbl, i) => (i < shots
                    ? <div key={lbl} style={hvShot}><i className="ri-image-line" style={{ fontSize: 22 }} /></div>
                    : <button type="button" key={lbl} onClick={() => setShots(i + 1)} style={{ flex: 1, aspectRatio: '4 / 3', border: '1px dashed var(--pk-border-control)', borderRadius: 6, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3, color: 'var(--pk-ink-quiet)', background: 'transparent', fontFamily: 'inherit', cursor: 'pointer' }}>
                        <i className="ri-camera-line" style={{ fontSize: 20 }} /><span style={{ fontSize: 11 }}>{lbl}</span>
                      </button>))}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--pk-ink-quiet)' }}><i className="ri-qr-code-line" style={{ fontSize: 16 }} />Ou scanner pour prendre les photos au téléphone</div>
              </div>
            </div>
          </div>
        </div>

        <aside style={{ width: 380, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Facture FA-1231</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-link)', whiteSpace: 'nowrap' }}><i className="ri-file-text-line" style={{ fontSize: 15 }} />Aperçu</span>
          </div>

          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>Main d’œuvre · 2 h 18</span><span style={{ fontWeight: 600 }}>158,70 €</span></div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>Pièces et consommables</span><span style={{ fontWeight: 600 }}>185,05 €</span></div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, color: 'var(--pk-ink-quiet)' }}><span>Total HT</span><span style={{ fontWeight: 600 }}>343,75 €</span></div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>TVA 20 %</span><span style={{ fontWeight: 600 }}>68,75 €</span></div>
            <div style={{ height: 1, background: 'var(--pk-border-quiet)' }} />
            <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}><span style={{ fontSize: 14, fontWeight: 600 }}>Total TTC</span><span style={{ fontSize: 22, fontWeight: 700 }}>412,50 €</span></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, color: 'var(--pk-success-ink)' }}><i className="ri-check-line" style={{ fontSize: 15 }} />Conforme au devis accepté · aucun dépassement</div>
          </div>

          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Encaissement</span>
            <div style={{ display: 'flex', gap: 7 }}>
              {[['cb', 'ri-bank-card-line', 'CB'], ['esp', 'ri-money-euro-box-line', 'Espèces'], ['chq', 'ri-file-paper-line', 'Chèque']].map(([id, icon, label]) => (
                <button type="button" key={id} onClick={() => setPay(id)}
                  style={{ ...hvPay, background: pay === id ? 'var(--pk-accent)' : 'transparent', color: pay === id ? '#000' : 'inherit', border: pay === id ? 'none' : '1px solid var(--pk-border-control)', fontWeight: pay === id ? 600 : 400 }}>
                  <i className={icon} style={{ fontSize: 17 }} />{label}
                </button>
              ))}
            </div>
          </div>

          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Signature du client</span>
            <div style={{ height: 76, border: '1px dashed var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 4, color: 'var(--pk-ink-muted)', background: 'var(--pk-surface-raised)' }}>
              <i className="ri-pen-nib-line" style={{ fontSize: 24 }} />
              <span style={{ fontSize: 12 }}>Signer sur l’écran ou envoyer le lien</span>
            </div>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>La signature vaut acceptation de l’état de sortie et de la facture.</span>
          </div>

          <div style={{ flex: 1 }} />
          <div style={{ padding: '14px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
            {missing ? (
              <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '10px 12px', background: 'var(--pk-warning-surface)', border: '1px solid var(--pk-warning-line)', borderRadius: 'var(--pk-radius-card)' }}>
                <i className="ri-alert-line" style={{ fontSize: 17, color: 'var(--pk-warning-ink)', flexShrink: 0 }} />
                <span style={{ fontSize: 12, color: 'var(--pk-warning-ink)', lineHeight: 1.4 }}>{missing} photos de sortie manquantes — la restitution reste possible, l’absence est tracée.</span>
              </div>
            ) : (
              <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '10px 12px', background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)', borderRadius: 'var(--pk-radius-card)' }}>
                <i className="ri-check-line" style={{ fontSize: 17, color: 'var(--pk-success-ink)', flexShrink: 0 }} />
                <span style={{ fontSize: 12, color: 'var(--pk-success-ink)', lineHeight: 1.4 }}>Série de sortie complète · 6 photos au dossier.</span>
              </div>
            )}
            <button type="button" onClick={onDone} style={{ minHeight: 52, borderRadius: 'var(--pk-radius-pill)', border: 'none', background: 'var(--pk-accent)', color: '#000', fontSize: 15, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' }}>Encaisser et restituer</button>
            <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600 }}>Restituer sans paiement</span>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center' }}>Libère le pont 1 et propose le créneau de jeudi pour les plaquettes.</span>
          </div>
        </aside>
      </div>
    </div>
  );
}
Object.assign(window, { HandoverScreen });
