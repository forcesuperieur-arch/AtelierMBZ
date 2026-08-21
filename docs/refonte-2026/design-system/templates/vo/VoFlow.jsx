/* Compagnon VO (tour 31) — l'expertise d'une occasion, téléphone en main dans
   la cour. Trois étapes : carte grise relue, série guidée de 9 prises de vue,
   défauts chiffrés et offre. Les deux premières étapes sont sombres (dehors,
   plein soleil), la troisième repasse en clair : c'est un écran de chiffres. */
const voShell = { width: 390, height: 844, display: 'flex', flexDirection: 'column', overflow: 'hidden', fontFamily: 'var(--mb-font-montserrat)' };
const voBar = { flexShrink: 0, display: 'flex', alignItems: 'center', gap: 12, padding: '14px 16px', background: '#000', color: '#f6f6f6' };
const voField = { display: 'flex', alignItems: 'center', gap: 12, padding: 14, background: '#1c1c1c', border: '1px solid #6f6e6e' };
const voCta = { minHeight: 56, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 9, background: 'var(--pk-accent)', color: '#000', fontSize: 16, fontWeight: 600, border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const voGhostDark = { minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #6f6e6e', fontSize: 15, fontWeight: 600, color: '#f6f6f6', background: 'transparent', fontFamily: 'inherit', cursor: 'pointer' };
const voThumb = { width: 78, height: 62, flexShrink: 0, display: 'flex', alignItems: 'flex-end', padding: 4 };

const VO_SHOTS = ['Face', 'Droite', 'Gauche', 'Arrière', 'Compteur', 'VIN cadre', 'Pneu AV', 'Pneu AR', 'Chaîne'];

function VoRegistration({ onNext }) {
  const [vin, setVin] = React.useState(false);
  return (
    <div style={{ ...voShell, background: '#141414', color: '#f6f6f6' }}>
      <div style={voBar}>
        <i className="ri-close-line" style={{ fontSize: 24 }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Nouveau rachat</span>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, color: '#a5a5a5' }}>1 / 3</span>
      </div>

      <div style={{ flexShrink: 0, padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 6 }}>
        <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.015em' }}>Photographiez la carte grise</div>
        <div style={{ fontSize: 14, lineHeight: 1.45, color: '#a5a5a5' }}>Les champs sont remplis pour vous. Vous corrigez seulement ce qui est mal lu.</div>
      </div>

      <div style={{ flexShrink: 0, margin: '0 16px', display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', background: '#1c1c1c', border: '2px solid var(--pk-accent)' }}>
        <div style={{ width: 48, height: 34, background: '#2f2f2f', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><i className="ri-file-text-line" style={{ fontSize: 17, color: '#a5a5a5' }} /></div>
        <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Carte grise lue</div><div style={{ fontSize: 12, color: '#a5a5a5' }}>Photo conservée au dossier</div></div>
        <i className="ri-check-double-line" style={{ fontSize: 22, color: '#4dbb3a' }} />
      </div>

      <div style={{ flex: 1, padding: '18px 16px 0', display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
        <div style={voField}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 12, color: '#a5a5a5' }}>Immatriculation</div><div style={{ fontSize: 18, fontWeight: 700, letterSpacing: '0.06em' }}>MP-604-GT</div></div>
          <i className="ri-check-line" style={{ fontSize: 20, color: '#4dbb3a' }} />
        </div>
        <div style={voField}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 12, color: '#a5a5a5' }}>Modèle</div><div style={{ fontSize: 15, fontWeight: 600 }}>KTM 390 Duke · 2023</div></div>
          <i className="ri-check-line" style={{ fontSize: 20, color: '#4dbb3a' }} />
        </div>
        <button type="button" onClick={() => setVin(true)}
          style={{ ...voField, background: vin ? '#1c1c1c' : '#2b1b00', border: vin ? '1px solid #6f6e6e' : '2px solid var(--pk-accent)', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 12, color: vin ? '#a5a5a5' : '#f4bc33' }}>{vin ? 'Numéro VIN · confirmé' : 'Numéro VIN · à confirmer'}</div>
            <div style={{ fontSize: 15, fontWeight: 600, letterSpacing: '0.04em' }}>VBKJMD40 8PM 123456</div>
          </div>
          <i className={vin ? 'ri-check-line' : 'ri-error-warning-line'} style={{ fontSize: 20, color: vin ? '#4dbb3a' : '#f4bc33' }} />
        </button>
        <div style={voField}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 12, color: '#a5a5a5' }}>1re immatriculation</div><div style={{ fontSize: 15, fontWeight: 600 }}>08/06/2023</div></div>
          <i className="ri-check-line" style={{ fontSize: 20, color: '#4dbb3a' }} />
        </div>
        <div style={voField}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 12, color: '#a5a5a5' }}>Titulaire</div><div style={{ fontSize: 15, fontWeight: 600 }}>M. Théo Lemaire</div></div>
          <i className="ri-check-line" style={{ fontSize: 20, color: '#4dbb3a' }} />
        </div>
        <div style={{ display: 'flex', gap: 10, padding: '12px 14px', background: '#1c1c1c', marginTop: 2 }}>
          <i className="ri-information-line" style={{ fontSize: 18, color: '#f4bc33', flexShrink: 0 }} />
          <span style={{ fontSize: 13, lineHeight: 1.45, color: '#d4d4d4' }}>Le VIN sera recoupé avec celui frappé sur le cadre à l’étape suivante.</span>
        </div>
      </div>

      <div style={{ flexShrink: 0, padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
        <button type="button" onClick={onNext} style={voCta}>Confirmer et photographier<i className="ri-arrow-right-line" style={{ fontSize: 20 }} /></button>
        <button type="button" style={voGhostDark}>Reprendre la photo</button>
      </div>
    </div>
  );
}

function VoShots({ onBack, onNext }) {
  const [done, setDone] = React.useState(4);
  const current = Math.min(done, VO_SHOTS.length - 1);
  const label = ['Face, moto de trois quarts', 'Flanc droit complet', 'Flanc gauche complet', 'Arrière, feu visible', 'Compteur, moteur allumé', 'VIN frappé sur le cadre', 'Pneu avant, témoin d’usure', 'Pneu arrière, témoin d’usure', 'Chaîne et couronne'][current];
  const hint = current === 4 ? 'Cadrez le cadran dans le rectangle. Le kilométrage sera relu automatiquement.' : 'Cadrez dans le rectangle. La prise est conservée au dossier de revente.';
  return (
    <div style={{ ...voShell, background: '#141414', color: '#f6f6f6' }}>
      <div style={voBar}>
        <button type="button" onClick={onBack} style={{ background: 'transparent', border: 'none', color: 'inherit', padding: 0, cursor: 'pointer', display: 'flex' }}><i className="ri-arrow-left-line" style={{ fontSize: 24 }} /></button>
        <span style={{ fontSize: 15, fontWeight: 600 }}>MP-604-GT</span>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, color: '#a5a5a5' }}>2 / 3</span>
      </div>

      <div style={{ flexShrink: 0, padding: '16px 20px 14px', display: 'flex', flexDirection: 'column', gap: 5 }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
          <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-accent)' }}>Prise {current + 1} sur 9</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 13, color: '#a5a5a5' }}>{done} faites</span>
        </div>
        <div style={{ height: 5, background: 'rgba(255,255,255,0.14)' }}><div style={{ width: (done / 9 * 100) + '%', height: '100%', background: 'var(--pk-accent)' }} /></div>
      </div>

      <div style={{ flexShrink: 0, margin: '0 16px', aspectRatio: '4 / 3', background: '#1c1c1c', border: '1px solid #6f6e6e', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: 14 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 9, alignSelf: 'flex-start', padding: '7px 12px', background: 'rgba(0,0,0,0.75)' }}>
          <i className="ri-focus-3-line" style={{ fontSize: 18, color: 'var(--pk-accent)' }} />
          <span style={{ fontSize: 14, fontWeight: 600 }}>{label}</span>
        </div>
        <div style={{ alignSelf: 'center', width: '62%', aspectRatio: '16 / 9', border: '2px dashed rgba(241,171,0,0.8)' }} />
        <div style={{ padding: '10px 12px', background: 'rgba(0,0,0,0.75)', fontSize: 13, lineHeight: 1.4, color: '#d4d4d4' }}>{hint}</div>
      </div>

      <div style={{ flexShrink: 0, padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
        <button type="button" onClick={() => (done >= 8 ? onNext() : setDone(done + 1))}
          style={{ ...voCta, minHeight: 60, fontSize: 17, fontWeight: 700 }}><i className="ri-camera-fill" style={{ fontSize: 24 }} />{done >= 8 ? 'Terminer la série' : 'Photographier'}</button>
        <div style={{ display: 'flex', gap: 10 }}>
          <button type="button" onClick={() => setDone(Math.min(done + 1, 9))} style={{ ...voGhostDark, flex: 1 }}>Passer</button>
          <button type="button" style={{ ...voGhostDark, flex: 1 }}>Signaler un défaut</button>
        </div>
      </div>

      <div style={{ flex: 1, padding: '4px 16px 0', display: 'flex', flexDirection: 'column', gap: 8, minHeight: 0 }}>
        <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>La série</span>
        <div style={{ display: 'flex', flexWrap: 'nowrap', gap: 8, overflowX: 'auto' }}>
          {VO_SHOTS.map((s, i) => {
            const shot = i < done;
            const now = i === current && !shot;
            return (
              <div key={s} style={{ ...voThumb, background: shot ? '#1c1c1c' : now ? '#2b1b00' : 'transparent', border: shot ? '1px solid #4dbb3a' : now ? '2px solid var(--pk-accent)' : '1px dashed #6f6e6e' }}>
                <span style={{ fontSize: 10, fontWeight: shot || now ? (now ? 700 : 600) : 400, color: shot ? '#4dbb3a' : now ? 'var(--pk-accent)' : '#a5a5a5' }}>{s}</span>
              </div>
            );
          })}
        </div>
        <div style={{ fontSize: 13, lineHeight: 1.45, color: '#a5a5a5' }}>Les 9 prises sont celles exigées par le dossier de revente. Une prise passée est notée comme telle.</div>
      </div>

      <div style={{ flexShrink: 0, padding: '14px 16px', display: 'flex', alignItems: 'center', gap: 10, background: '#1c1c1c' }}>
        <i className="ri-wifi-off-line" style={{ fontSize: 18, color: '#f4bc33' }} />
        <span style={{ fontSize: 13, color: '#d4d4d4', lineHeight: 1.4 }}>Hors ligne dans la cour · les photos partent au retour du réseau</span>
      </div>
    </div>
  );
}

function VoOffer({ onBack, onRestart }) {
  const [sent, setSent] = React.useState(false);
  const faults = [
    { n: 1, name: 'Rayure carénage droit', sub: 'Photo 2 · polissage', cut: '− 60 €' },
    { n: 2, name: 'Pneu arrière à 2,0 mm', sub: 'Photo 8 · remplacement', cut: '− 165 €' },
  ];
  return (
    <div style={{ ...voShell, background: 'var(--pk-surface-raised)', color: 'var(--pk-ink)' }}>
      <div style={voBar}>
        <button type="button" onClick={onBack} style={{ background: 'transparent', border: 'none', color: 'inherit', padding: 0, cursor: 'pointer', display: 'flex' }}><i className="ri-arrow-left-line" style={{ fontSize: 24 }} /></button>
        <span style={{ fontSize: 15, fontWeight: 600 }}>MP-604-GT</span>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, color: '#a5a5a5' }}>3 / 3</span>
      </div>

      <div style={{ flexShrink: 0, padding: '18px 16px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 5 }}>
        <div style={{ fontSize: 20, fontWeight: 500, letterSpacing: '-0.015em' }}>KTM 390 Duke · 2023</div>
        <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }}>4 210 km relevés au compteur · 9 photos</div>
      </div>

      <div style={{ flexShrink: 0, padding: 16, display: 'flex', flexDirection: 'column', gap: 10, borderBottom: '1px solid var(--pk-border-quiet)' }}>
        <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Défauts relevés</span>
        {faults.map((f) => (
          <div key={f.n} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '13px 14px', border: '1px solid var(--pk-border)' }}>
            <span style={{ width: 22, height: 22, border: '1.5px solid var(--pk-ink)', borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 700, flexShrink: 0 }}>{f.n}</span>
            <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{f.name}</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>{f.sub}</div></div>
            <span style={{ fontSize: 15, fontWeight: 700 }}>{f.cut}</span>
          </div>
        ))}
        <span style={{ minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, border: '1px dashed var(--pk-border-control)', fontSize: 14, fontWeight: 600, color: 'var(--pk-ink-quiet)' }}><i className="ri-add-line" style={{ fontSize: 18 }} />Ajouter un défaut</span>
      </div>

      <div style={{ flexShrink: 0, padding: 16, display: 'flex', flexDirection: 'column', gap: 8, borderBottom: '1px solid var(--pk-border-quiet)' }}>
        <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Estimation</span>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14 }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Cote du marché · 4 210 km</span><span style={{ fontWeight: 600 }}>5 200 €</span></div>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14 }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Remise en état</span><span style={{ fontWeight: 600 }}>− 225 €</span></div>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14 }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Marge de revente visée</span><span style={{ fontWeight: 600 }}>− 665 €</span></div>
        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', paddingTop: 8, borderTop: '1px solid var(--pk-border)' }}><span style={{ fontSize: 15, fontWeight: 600 }}>Offre de rachat</span><span style={{ fontSize: 24, fontWeight: 700 }}>4 310 €</span></div>
      </div>

      <div style={{ flexShrink: 0, padding: '14px 16px', background: 'var(--pk-accent-soft)', borderLeft: '3px solid var(--pk-accent)', margin: '16px 16px 0', display: 'flex', gap: 10 }}>
        <i className="ri-information-line" style={{ fontSize: 18, color: 'var(--pk-accent-ink)', flexShrink: 0 }} />
        <span style={{ fontSize: 13, lineHeight: 1.45 }}>Le vendeur doit fournir le certificat de non-gage et la carte grise barrée avant tout paiement.</span>
      </div>

      <div style={{ flex: 1 }} />

      <div style={{ flexShrink: 0, padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
        {sent ? (
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '12px 14px', background: 'var(--pk-success-surface)', border: '1px solid var(--pk-success-line)' }}>
            <i className="ri-check-line" style={{ fontSize: 18, color: 'var(--pk-success-ink)' }} />
            <span style={{ fontSize: 13, color: 'var(--pk-success-ink)', lineHeight: 1.4 }}>Offre envoyée à Théo Lemaire · dossier VO créé</span>
          </div>
        ) : null}
        <button type="button" onClick={() => setSent(true)} style={voCta}>Proposer 4 310 € au vendeur</button>
        <button type="button" onClick={onRestart} style={{ minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', fontSize: 15, fontWeight: 600, background: 'transparent', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>Enregistrer sans proposer</button>
        <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>L’offre est valable 7 jours et part au dossier VO avec ses 9 photos.</span>
      </div>
    </div>
  );
}

function VoFlow() {
  const [step, setStep] = React.useState(0);
  if (step === 0) return <VoRegistration onNext={() => setStep(1)} />;
  if (step === 1) return <VoShots onBack={() => setStep(0)} onNext={() => setStep(2)} />;
  return <VoOffer onBack={() => setStep(1)} onRestart={() => setStep(0)} />;
}
Object.assign(window, { VoFlow, VoRegistration, VoShots, VoOffer });
