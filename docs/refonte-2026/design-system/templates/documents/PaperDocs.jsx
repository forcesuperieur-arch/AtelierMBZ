/* Les documents papier A4 — tour 18. Un composant par document, à 452 px de
   large comme le rendu réel : ordre de réparation, état des lieux, facture. */
const paSheet = { width: 452, background: '#fff', border: '1px solid #a5a5a5', padding: '26px 28px', display: 'flex', flexDirection: 'column', gap: 14, color: '#1a1a1a' };
const paLabel = { fontSize: 8, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#6f6e6e' };
const paColHead = { padding: '5px 0', borderBottom: '1px solid #1a1a1a', fontSize: 8, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#6f6e6e' };
const paSignLine = { height: 36, borderBottom: '1px solid #a5a5a5' };
const paLegal = { padding: '9px 11px', background: '#f6f6f6', borderLeft: '2px solid var(--pk-accent)', display: 'flex', flexDirection: 'column', gap: 3 };
const paLegalText = { fontSize: 8, lineHeight: 1.55, color: '#2f2f2f' };

function PaperHead({ logo, kind, docRef, siret }) {
  return (
    <>
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
        <div style={{ width: 46, height: 46, background: '#000', borderRadius: 5, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><img src={logo} alt="" style={{ width: 30, height: 30, display: 'block' }} /></div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 13, fontWeight: 700, letterSpacing: '0.12em' }}>PADDOCK</div>
          <div style={{ fontSize: 9, color: '#5e5e5e', lineHeight: 1.5 }}>Atelier Principal · 12 rue de la Gare, 59000 Lille{siret ? <><br />SIRET 812 445 907 00023 · TVA FR32812445907</> : null}</div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ fontSize: 15, fontWeight: 700 }}>{kind}</div>
          <div style={{ fontSize: 9, color: '#5e5e5e' }}>{docRef}</div>
        </div>
      </div>
      <div style={{ height: 2, background: 'var(--pk-accent)' }} />
    </>
  );
}

function PaperParties({ client, clientSub, vehicle, vehicleSub }) {
  return (
    <div style={{ display: 'flex', gap: 20 }}>
      <div style={{ flex: 1 }}>
        <div style={paLabel}>Client</div>
        <div style={{ fontSize: 10, lineHeight: 1.5, marginTop: 2 }}>{client}<br />{clientSub}</div>
      </div>
      <div style={{ flex: 1 }}>
        <div style={paLabel}>Véhicule</div>
        <div style={{ fontSize: 10, lineHeight: 1.5, marginTop: 2 }}>{vehicle}<br />{vehicleSub}</div>
      </div>
    </div>
  );
}

const WO_TASKS = [
  { name: 'Révision 20 000 km', sub: 'Huile Motul 7100 10W40 · filtre HF204 · filtre à air · bougies contrôlées', time: '1 h 40' },
  { name: 'Contrôle et réglage de chaîne', sub: 'Tension, alignement, graissage', time: '0 h 20' },
  { name: 'Plaquettes de frein avant', badge: 'Pièce à vérifier au magasin', time: '1 h 00' },
];

function WorkOrderPaper({ logo }) {
  return (
    <div style={paSheet}>
      <PaperHead logo={logo} kind="ORDRE DE RÉPARATION" docRef="OR 2431 · 15 août 2026" />
      <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '12px 14px', background: '#000', color: '#fff' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 9, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Véhicule</div>
          <div style={{ fontSize: 15, fontWeight: 600, marginTop: 2 }}>Yamaha Tracer 9 · 2022</div>
        </div>
        <div style={{ padding: '6px 12px', border: '2px solid var(--pk-accent)', borderRadius: 4 }}>
          <span style={{ fontSize: 20, fontWeight: 700, letterSpacing: '0.08em' }}>GT-908-ZK</span>
        </div>
      </div>
      <div style={{ display: 'flex', gap: 20 }}>
        {[{ l: 'Compteur à l’entrée', v: '28 412 km' }, { l: 'Sortie annoncée', v: 'Aujourd’hui 17:00' }, { l: 'Temps alloué', v: '3 h 00' }].map((x) => (
          <div key={x.l} style={{ flex: 1 }}>
            <div style={paLabel}>{x.l}</div>
            <div style={{ fontSize: 13, fontWeight: 600, marginTop: 2 }}>{x.v}</div>
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', flexDirection: 'column' }}>
        <div style={paColHead}>Travaux à effectuer</div>
        {WO_TASKS.map((t) => (
          <div key={t.name} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, padding: '9px 0', borderBottom: '1px solid #ececec' }}>
            <div style={{ width: 15, height: 15, border: '1.5px solid #1a1a1a', flexShrink: 0, marginTop: 1 }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 11, fontWeight: 600 }}>{t.name}</div>
              {t.sub ? <div style={{ fontSize: 9, color: '#5e5e5e', lineHeight: 1.5 }}>{t.sub}</div> : null}
              {t.badge ? <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4, marginTop: 3, padding: '2px 7px', border: '1px solid #1a1a1a', fontSize: 8, fontWeight: 700, letterSpacing: '0.04em', textTransform: 'uppercase' }}>{t.badge}</div> : null}
            </div>
            <span style={{ fontSize: 10, color: '#5e5e5e' }}>{t.time}</span>
          </div>
        ))}
      </div>
      <div style={{ padding: '9px 11px', border: '1px solid #1a1a1a', display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div style={{ fontSize: 8, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase' }}>Consigne cliente</div>
        <div style={{ fontSize: 10, lineHeight: 1.5 }}>Bruit de frein signalé à l’avant depuis deux semaines. Rappeler avant tout travail supplémentaire — accord obligatoire.</div>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div style={{ ...paLabel, letterSpacing: '0.06em' }}>Observations du mécanicien</div>
        <div style={{ height: 22, borderBottom: '1px solid #d4d4d4' }} />
        <div style={{ height: 22, borderBottom: '1px solid #d4d4d4' }} />
        <div style={{ height: 22, borderBottom: '1px solid #d4d4d4' }} />
      </div>
      <div style={{ display: 'flex', gap: 20, marginTop: 2 }}>
        <div style={{ flex: 1 }}><div style={{ fontSize: 8, color: '#6f6e6e' }}>Compteur à la sortie</div><div style={{ ...paSignLine, height: 32 }} /></div>
        <div style={{ flex: 1 }}><div style={{ fontSize: 8, color: '#6f6e6e' }}>Essai routier · visa mécanicien</div><div style={{ ...paSignLine, height: 32 }} /></div>
      </div>
    </div>
  );
}

function ConditionPaper({ logo }) {
  return (
    <div style={paSheet}>
      <PaperHead logo={logo} kind="ÉTAT DES LIEUX" docRef="Entrée · 15 août 2026, 08:34" />
      <PaperParties client="Nadia Belkacem" clientSub="07 88 21 63 40" vehicle="Yamaha Tracer 9 · GT-908-ZK" vehicleSub="28 412 km · ½ réservoir" />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <div style={{ ...paLabel, letterSpacing: '0.06em' }}>Photos à l’entrée · horodatées</div>
        <div style={{ display: 'flex', gap: 6 }}>
          {['08:31 · face', '08:32 · droite ①', '08:33 · gauche', '08:33 · compteur'].map((c) => (
            <div key={c} style={{ flex: 1, aspectRatio: '4 / 3', background: '#ececec', border: '1px solid #d4d4d4', display: 'flex', alignItems: 'flex-end', justifyContent: 'flex-start', padding: 4, color: '#6f6e6e' }}>
              <span style={{ fontSize: 8, background: '#fff', padding: '1px 4px' }}>{c}</span>
            </div>
          ))}
        </div>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column' }}>
        <div style={paColHead}>Dégâts constatés à l’entrée</div>
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 9, padding: '8px 0', borderBottom: '1px solid #ececec' }}>
          <span style={{ width: 15, height: 15, border: '1.5px solid #1a1a1a', borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 9, fontWeight: 700, flexShrink: 0 }}>1</span>
          <div style={{ flex: 1 }}><div style={{ fontSize: 11, fontWeight: 600 }}>Rayure réservoir, côté droit</div><div style={{ fontSize: 9, color: '#5e5e5e' }}>Environ 6 cm, sous la selle · antérieure à l’intervention</div></div>
        </div>
        <div style={{ padding: '8px 0', fontSize: 10, color: '#5e5e5e', fontStyle: 'italic' }}>Aucun autre dégât relevé lors du tour du véhicule.</div>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column' }}>
        <div style={paColHead}>Effets laissés avec la moto</div>
        <div style={{ display: 'flex', gap: 16, padding: '8px 0', fontSize: 10 }}>
          {[{ n: 'Top-case', on: true }, { n: 'Casque' }, { n: 'Antivol' }, { n: '2e clé' }].map((e) => (
            <span key={e.n} style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
              <span style={{ width: 11, height: 11, border: '1.5px solid #1a1a1a', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 9, fontWeight: 700 }}>{e.on ? '×' : ''}</span>{e.n}
            </span>
          ))}
        </div>
      </div>
      <div style={paLegal}>
        <div style={{ ...paLabel, letterSpacing: '0.06em' }}>Portée de cet état des lieux</div>
        <div style={paLegalText}>Le client reconnaît l’exactitude des constatations et des photographies ci-dessus. Seuls les dégâts qui y figurent sont réputés antérieurs à l’intervention. Un état des lieux de sortie sera établi à la restitution et comparé au présent document.</div>
      </div>
      <div style={{ display: 'flex', gap: 20, marginTop: 2 }}>
        <div style={{ flex: 1 }}><div style={{ fontSize: 8, color: '#6f6e6e' }}>Le client · lu et approuvé</div><div style={{ ...paSignLine, height: 38 }} /></div>
        <div style={{ flex: 1 }}><div style={{ fontSize: 8, color: '#6f6e6e' }}>L’atelier · Julie Dubois</div><div style={{ ...paSignLine, height: 38 }} /></div>
      </div>
    </div>
  );
}

const IN_LINES = [
  { name: 'Révision 20 000 km · main d’œuvre', time: '1 h 35', pu: '109,25', total: '109,25' },
  { name: 'Huile, filtres et consommables', time: '—', pu: '121,80', total: '121,80' },
  { name: 'Bougies remplacées · pièces et pose', time: '0 h 25', pu: '92,00', total: '92,00' },
  { name: 'Contrôle et réglage de chaîne', time: '0 h 18', pu: '20,70', total: '20,70' },
  { name: 'Plaquettes avant · non montées, pièce indisponible', time: '—', pu: '—', total: '0,00', muted: true },
];

function InvoicePaper({ logo }) {
  const grid = { display: 'grid', gridTemplateColumns: '1fr 52px 62px 62px', padding: '6px 0', borderBottom: '1px solid #ececec', fontSize: 10 };
  return (
    <div style={paSheet}>
      <PaperHead logo={logo} kind="FACTURE" docRef="FA-1231 · 15 août 2026" siret />
      <PaperParties client="Ludovic Renard" clientSub="06 12 44 98 07" vehicle="Yamaha MT-09 · EX-421-QR" vehicleSub="OR 2418 · 28 419 km" />
      <div style={{ display: 'flex', flexDirection: 'column' }}>
        <div style={{ ...grid, ...paColHead, padding: '5px 0' }}>
          <span>Désignation</span><span style={{ textAlign: 'right' }}>Temps</span><span style={{ textAlign: 'right' }}>P.U. HT</span><span style={{ textAlign: 'right' }}>Total HT</span>
        </div>
        {IN_LINES.map((l, i) => (
          <div key={l.name} style={{ ...grid, borderBottom: i === IN_LINES.length - 1 ? 'none' : '1px solid #ececec', color: l.muted ? '#6f6e6e' : undefined }}>
            <span>{l.name}</span><span style={{ textAlign: 'right' }}>{l.time}</span><span style={{ textAlign: 'right' }}>{l.pu}</span><span style={{ textAlign: 'right' }}>{l.total}</span>
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
        <div style={{ width: 176, display: 'flex', flexDirection: 'column', gap: 3 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10 }}><span style={{ color: '#5e5e5e' }}>Total HT</span><span>343,75</span></div>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10 }}><span style={{ color: '#5e5e5e' }}>TVA 20 %</span><span>68,75</span></div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', paddingTop: 4, borderTop: '1px solid #1a1a1a' }}><span style={{ fontSize: 11, fontWeight: 700 }}>Total TTC</span><span style={{ fontSize: 14, fontWeight: 700 }}>412,50 €</span></div>
        </div>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '9px 11px', background: '#000', color: '#fff' }}>
        <i className="ri-checkbox-circle-fill" style={{ fontSize: 15, color: '#4dbb3a' }} />
        <span style={{ fontSize: 10, fontWeight: 600 }}>Payée le 15 août 2026 par carte bancaire</span>
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 10, color: '#d4d4d4' }}>Reste dû 0,00 €</span>
      </div>
      <div style={paLegal}>
        <div style={{ ...paLabel, letterSpacing: '0.06em' }}>Mentions légales</div>
        <div style={paLegalText}>Paiement à réception. En cas de retard, pénalités au taux d’intérêt légal majoré de 10 points et indemnité forfaitaire de recouvrement de 40 € (art. L441-10 du code de commerce). Les pièces remplacées sont tenues à disposition du client pendant 15 jours. Médiateur de la consommation : AME Conso, 11 place Dauphine, 75001 Paris.</div>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 2 }}>
        <div style={{ width: 44, height: 44, border: '1px solid #d4d4d4', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#6f6e6e', flexShrink: 0 }}><i className="ri-qr-code-line" style={{ fontSize: 24 }} /></div>
        <div style={{ fontSize: 8, color: '#5e5e5e', lineHeight: 1.55 }}>Retrouvez le détail de l’intervention, les photos et l’historique de votre moto sur votre espace client.</div>
      </div>
    </div>
  );
}
Object.assign(window, { WorkOrderPaper, ConditionPaper, InvoicePaper, PaperHead, PaperParties });
