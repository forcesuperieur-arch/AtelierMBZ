/* Tour 41 — les cas payés par un tiers. Trois panneaux ouverts depuis la
   réception ou la fiche : retour SAV sous garantie, prise en charge assurance,
   véhicule de prêt. Chacun sépare qui paie quoi et bloque ce qui doit l'être. */
const tpPanel = { width: 496, minHeight: 720, background: 'var(--pk-surface-raised)', border: '1px solid #6f6e6e', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' };
const tpBar = { display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px', background: '#000', color: '#f6f6f6' };
const tpSec = { padding: '16px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 10 };
const tpLabel = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const tpLine = { display: 'flex', justifyContent: 'space-between', fontSize: 13 };
const tpOpt = { display: 'flex', alignItems: 'center', gap: 11, padding: '12px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', fontFamily: 'inherit', textAlign: 'left', color: 'inherit', cursor: 'pointer' };
const tpFoot = { padding: '16px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 };
const tpCta = { minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 15, fontWeight: 600, borderRadius: 'var(--pk-radius-pill)', border: 'none', fontFamily: 'inherit', cursor: 'pointer' };

function WarrantyPanel() {
  const [payer, setPayer] = React.useState('atelier');
  const payers = [
    { id: 'atelier', name: 'L’atelier · geste de garantie', sub: 'Diagnostic et reprise gratuits pour le client' },
    { id: 'fournisseur', name: 'Le fournisseur de la pièce', sub: 'Brembo · demande de prise en charge à ouvrir' },
    { id: 'client', name: 'Le client · hors garantie', sub: 'Si le diagnostic écarte notre intervention' },
  ];
  return (
    <div style={tpPanel}>
      <div style={tpBar}>
        <i className="ri-arrow-go-back-line" style={{ fontSize: 19, color: 'var(--pk-accent)' }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Retour SAV</span>
        <div style={{ flex: 1 }} />
        <i className="ri-close-line" style={{ fontSize: 21 }} />
      </div>
      <div style={{ ...tpSec, gap: 8 }}>
        <span style={tpLabel}>Intervention d’origine</span>
        <div style={{ padding: '12px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 3 }}>
          <div style={{ fontSize: 14, fontWeight: 600 }}>OR 2318 · plaquettes avant + purge</div>
          <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Camille Perrot · Honda CB500F · 12 juin 2026</div>
          <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Karim M. · 168,00 € payés</div>
        </div>
      </div>
      <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 11, background: 'var(--pk-success-surface)' }}>
        <i className="ri-shield-check-line" style={{ fontSize: 19, color: 'var(--pk-success-ink)', flexShrink: 0 }} />
        <div style={{ fontSize: 13, lineHeight: 1.5 }}><strong style={{ fontWeight: 600 }}>Sous garantie · encore 4 mois.</strong> Pièces et main d’œuvre couvertes 12 mois sur nos interventions. Reste 4 mois et 8 jours.</div>
      </div>
      <div style={tpSec}>
        <span style={tpLabel}>Ce que dit le client</span>
        <div style={{ minHeight: 72, padding: '11px 13px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', fontSize: 14, lineHeight: 1.5 }}>Le bruit de frein est revenu au bout de trois semaines. Grincement à froid, surtout le matin.</div>
      </div>
      <div style={tpSec}>
        <span style={tpLabel}>Qui prend en charge</span>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
          {payers.map((p) => (
            <button type="button" key={p.id} onClick={() => setPayer(p.id)}
              style={{ ...tpOpt, background: payer === p.id ? '#fff6e0' : 'var(--pk-surface)', border: payer === p.id ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border)' }}>
              {payer === p.id ? <i className="ri-check-line" style={{ fontSize: 18, color: 'var(--pk-warning-ink-soft)' }} /> : <span style={{ width: 18 }} />}
              <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{p.name}</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>{p.sub}</div></div>
            </button>
          ))}
        </div>
      </div>
      <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 11 }}>
        <i className="ri-information-line" style={{ fontSize: 18, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
        <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Le diagnostic est fait avant de trancher. Si l’usure vient de l’usage et non de la pose, le client est prévenu <strong style={{ fontWeight: 600, color: 'var(--pk-ink)' }}>avant</strong> tout travail payant.</div>
      </div>
      <div style={{ flex: 1 }} />
      <div style={tpFoot}>
        <span style={tpCta}>Ouvrir l’OR de garantie</span>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>Le nouvel OR est lié à l’OR 2318 : les deux se voient dans l’historique de la moto.</span>
      </div>
    </div>
  );
}

function InsurancePanel() {
  return (
    <div style={tpPanel}>
      <div style={tpBar}>
        <i className="ri-file-shield-2-line" style={{ fontSize: 19, color: 'var(--pk-accent)' }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Dossier assurance</span>
        <div style={{ flex: 1 }} />
        <span style={{ padding: '3px 9px', border: '1px solid var(--pk-accent)', fontSize: 11, fontWeight: 700, color: 'var(--pk-accent)', whiteSpace: 'nowrap' }}>EN ATTENTE</span>
      </div>
      <div style={{ ...tpSec, gap: 4 }}>
        <div style={{ fontSize: 17, fontWeight: 600 }}>Suzuki SV650 · BX-772-LM</div>
        <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Marc Delaunay · chute sans tiers · sinistre du 12 août</div>
      </div>
      <div style={tpSec}>
        <span style={tpLabel}>Tiers payeur</span>
        <div style={{ border: '1px solid var(--pk-border)' }}>
          <div style={{ ...tpLine, padding: '10px 13px', borderBottom: '1px solid var(--pk-border-quiet)' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Assureur</span><span style={{ fontWeight: 600 }}>AMV · contrat 4471903</span></div>
          <div style={{ ...tpLine, padding: '10px 13px', borderBottom: '1px solid var(--pk-border-quiet)' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>N° de sinistre</span><span style={{ fontWeight: 600 }}>SIN-2026-08812</span></div>
          <div style={{ ...tpLine, padding: '10px 13px', borderBottom: '1px solid var(--pk-border-quiet)' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Expert</span><span style={{ fontWeight: 600 }}>à convoquer</span></div>
          <div style={{ ...tpLine, padding: '10px 13px' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Franchise</span><span style={{ fontWeight: 600 }}>180,00 € au client</span></div>
        </div>
      </div>
      <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 11, background: 'var(--pk-error-surface)' }}>
        <i className="ri-error-warning-fill" style={{ fontSize: 19, color: 'var(--pk-error-ink)', flexShrink: 0 }} />
        <div style={{ fontSize: 13, lineHeight: 1.5 }}><strong style={{ fontWeight: 600 }}>Aucun travail ne peut commencer.</strong> L’expert doit voir la moto en l’état. Toute réparation avant son passage n’est plus remboursable.</div>
      </div>
      <div style={{ ...tpSec, gap: 9 }}>
        <span style={tpLabel}>Devis estimatif · DV-2451</span>
        <div style={tpLine}><span style={{ color: 'var(--pk-ink-quiet)' }}>Carénage droit, rétroviseur, levier</span><span style={{ fontWeight: 600 }}>642,00 €</span></div>
        <div style={tpLine}><span style={{ color: 'var(--pk-ink-quiet)' }}>Main d’œuvre · 3 h 20</span><span style={{ fontWeight: 600 }}>240,00 €</span></div>
        <div style={tpLine}><span style={{ color: 'var(--pk-ink-quiet)' }}>TVA 20 %</span><span style={{ fontWeight: 600 }}>176,40 €</span></div>
        <div style={{ height: 1, background: 'var(--pk-border)' }} />
        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}><span style={{ fontSize: 14, fontWeight: 600 }}>Total TTC</span><span style={{ fontSize: 20, fontWeight: 700 }}>1 058,40 €</span></div>
      </div>
      <div style={{ ...tpSec, gap: 9 }}>
        <span style={tpLabel}>Qui paie quoi</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: '#000', color: '#f6f6f6' }}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>AMV · à facturer à l’assureur</div><div style={{ fontSize: 12, color: '#d4d4d4' }}>Sous réserve du rapport d’expertise</div></div>
          <span style={{ fontSize: 19, fontWeight: 700 }}>878,40 €</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: '#fff6e0', border: '1px solid var(--pk-accent)' }}>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Marc Delaunay · franchise</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>À encaisser à la restitution</div></div>
          <span style={{ fontSize: 19, fontWeight: 700 }}>180,00 €</span>
        </div>
      </div>
      <div style={{ flex: 1 }} />
      <div style={tpFoot}>
        <span style={tpCta}>Envoyer le devis à AMV</span>
        <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600 }}>Convoquer l’expert</span>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>La moto reste en attente, hors planning, jusqu’à l’accord écrit.</span>
      </div>
    </div>
  );
}

function LoanPanel() {
  const [shots, setShots] = React.useState(false);
  return (
    <div style={tpPanel}>
      <div style={tpBar}>
        <i className="ri-key-2-line" style={{ fontSize: 19, color: 'var(--pk-accent)' }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Prêter une moto</span>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 13, color: '#a5a5a5' }}>2 disponibles sur 3</span>
      </div>
      <div style={tpSec}>
        <span style={tpLabel}>Le parc de prêt</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: '#fff6e0', border: '2px solid var(--pk-accent)' }}>
          <i className="ri-motorbike-fill" style={{ fontSize: 22, color: 'var(--pk-warning-ink-soft)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Yamaha MT-125 · PR-118-AA</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Permis A1 ou A2 · 21 400 km</div></div>
          <i className="ri-check-line" style={{ fontSize: 19, color: 'var(--pk-warning-ink-soft)' }} />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)' }}>
          <i className="ri-motorbike-line" style={{ fontSize: 22, color: 'var(--pk-ink-quiet)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Honda PCX 125 · PR-204-BC</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Scooter · permis B accepté</div></div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: '#f4f4f4', border: '1px solid var(--pk-border)', color: 'var(--pk-ink-muted)' }}>
          <i className="ri-motorbike-line" style={{ fontSize: 22 }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Yamaha MT-07 · PR-330-CD</div><div style={{ fontSize: 13 }}>Prêtée jusqu’à lundi · M. Bahri</div></div>
        </div>
      </div>
      <div style={tpSec}>
        <span style={tpLabel}>Avant de remettre la clé</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)' }}>
          <i className="ri-check-line" style={{ fontSize: 18, color: 'var(--pk-success-ink)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Permis A2 vérifié</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Photo au dossier · valide</div></div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)' }}>
          <i className="ri-check-line" style={{ fontSize: 18, color: 'var(--pk-success-ink)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>Attestation d’assurance</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Le prêt est couvert par notre flotte</div></div>
        </div>
        <button type="button" onClick={() => setShots(!shots)}
          style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', background: 'var(--pk-surface)', border: shots ? '1px solid var(--pk-border)' : '2px solid var(--pk-error-line)', width: '100%', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>
          <i className={shots ? 'ri-check-line' : 'ri-camera-line'} style={{ fontSize: 18, color: shots ? 'var(--pk-success-ink)' : 'var(--pk-error-ink)' }} />
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600, color: shots ? 'inherit' : 'var(--pk-error-ink)' }}>État des lieux de départ</div><div style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>{shots ? '4 photos et le compteur enregistrés' : '4 photos et le compteur, comme à la réception'}</div></div>
        </button>
      </div>
      <div style={{ ...tpSec, gap: 9 }}>
        <span style={tpLabel}>Durée et conditions</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 14 }}><i className="ri-calendar-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Jusqu’au retour de sa moto<div style={{ flex: 1 }} /><span style={{ fontWeight: 600 }}>18 août</span></div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 14 }}><i className="ri-gas-station-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Rendue avec le plein<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-muted)' }}>réservoir plein au départ</span></div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 14 }}><i className="ri-money-euro-circle-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Franchise en cas de dommage<div style={{ flex: 1 }} /><span style={{ fontWeight: 600 }}>400,00 €</span></div>
      </div>
      <div style={{ flex: 1 }} />
      <div style={tpFoot}>
        <div style={{ minHeight: 68, padding: '11px 13px', background: 'var(--pk-surface)', border: '1px dashed #6f6e6e', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Signature de l’emprunteur</div>
        <span style={{ minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', background: shots ? 'var(--pk-accent)' : 'var(--pk-canvas)', color: shots ? '#000' : 'var(--pk-ink-muted)', fontSize: 15, fontWeight: 600 }}>Remettre la clé</span>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center', lineHeight: 1.45 }}>{shots ? 'Tout est en règle : la clé peut sortir.' : 'L’état des lieux de départ manque : il protège autant le client que l’atelier.'}</span>
      </div>
    </div>
  );
}
Object.assign(window, { WarrantyPanel, InsurancePanel, LoanPanel });
