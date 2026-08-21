/* Poste mécanicien — les trois écrans secondaires (tour 49a), 1024 × 768, tactile.
   Depuis l'OR ouvert : demander une pièce, signaler un blocage, finir sa journée.
   Chacun se termine par une phrase qui dit qui a été prévenu. Cibles 56 px, pas
   de saisie libre obligatoire. */
const mkShell = { width: 1024, height: 768, background: '#141414', border: '1px solid #333', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: '#f6f6f6', fontFamily: 'var(--mb-font-montserrat)' };
const mkBar = { flexShrink: 0, display: 'flex', alignItems: 'center', gap: 16, padding: '16px 22px', borderBottom: '1px solid #2f2f2f' };
const mkBack = { width: 56, height: 56, border: '1px solid #4a4a4a', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none', background: 'transparent', color: 'inherit', cursor: 'pointer' };
const mkTile = { background: '#1f1f1f' };
const mkBtn = { minHeight: 56, display: 'flex', alignItems: 'center', padding: '0 20px', fontSize: 17, fontWeight: 600, whiteSpace: 'nowrap' };
const mkPrimary = { ...mkBtn, background: 'var(--pk-accent)', color: '#000', fontWeight: 700, border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const mkGhost = { ...mkBtn, border: '1px solid #4a4a4a', background: 'transparent', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' };
const mkStat = { flex: 1, ...mkTile, padding: 18, display: 'flex', flexDirection: 'column', gap: 4 };

function MechPartRequest() {
  const [asked, setAsked] = React.useState(false);
  return (
    <div style={mkShell}>
      <div style={mkBar}>
        <span style={mkBack}><i className="ri-arrow-left-line" style={{ fontSize: 26 }} /></span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 22, fontWeight: 600 }}>OR 2431 · MT-07 EF-771-GH</div>
          <div style={{ fontSize: 16, color: '#a5a5a5' }}>Karim · pont P3 · pointage en cours 0 h 25</div>
        </div>
        <span style={{ fontSize: 18, color: '#a5a5a5' }}>16:44</span>
      </div>
      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ flex: 1, padding: 22, display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
          <div style={{ fontSize: 26, fontWeight: 600 }}>Quelle pièce vous manque ?</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, minHeight: 60, padding: '0 16px', background: '#1f1f1f', border: '1px solid #4a4a4a' }}>
            <i className="ri-search-line" style={{ fontSize: 24, color: '#a5a5a5' }} />
            <span style={{ fontSize: 20 }}>plaquettes</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, minHeight: 72, padding: '12px 16px', ...mkTile, borderLeft: '4px solid #7ee08a' }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 20, fontWeight: 600 }}>Plaquettes avant Brembo 07BB19</div>
                <div style={{ fontSize: 16, color: '#7ee08a' }}>2 en magasin · rayon B4</div>
              </div>
              <button type="button" onClick={() => setAsked(true)} style={{ ...mkPrimary, background: asked ? '#1f1f1f' : 'var(--pk-accent)', color: asked ? '#7ee08a' : '#000', border: asked ? '1px solid #7ee08a' : 'none' }}>{asked ? 'Demandée' : 'Demander'}</button>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, minHeight: 72, padding: '12px 16px', ...mkTile }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 20, fontWeight: 600 }}>Plaquettes arrière Brembo 07BB20</div>
                <div style={{ fontSize: 16, color: '#a5a5a5' }}>4 en magasin · rayon B4</div>
              </div>
              <span style={mkGhost}>Demander</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, minHeight: 72, padding: '12px 16px', ...mkTile }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 20, fontWeight: 600 }}>Plaquettes avant origine Yamaha</div>
                <div style={{ fontSize: 16, color: '#ff8095' }}>0 en magasin · commande 48 h</div>
              </div>
              <span style={{ ...mkGhost, color: '#a5a5a5' }}>À commander</span>
            </div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, minHeight: 68, padding: '12px 16px', border: '2px dashed #4a4a4a' }}>
            <i className="ri-mic-line" style={{ fontSize: 26, color: '#a5a5a5' }} />
            <span style={{ fontSize: 18, color: '#a5a5a5' }}>Pas dans la liste : dicter la référence au comptoir</span>
          </div>
        </div>
        <div style={{ width: 340, flexShrink: 0, borderLeft: '1px solid #2f2f2f', display: 'flex', flexDirection: 'column' }}>
          <div style={{ padding: '18px 20px', borderBottom: '1px solid #2f2f2f' }}><div style={{ fontSize: 18, fontWeight: 600 }}>Demandes de cet OR</div></div>
          {asked ? (
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #2f2f2f' }}>
              <div style={{ fontSize: 18, fontWeight: 600 }}>Plaquettes avant Brembo 07BB19</div>
              <div style={{ fontSize: 16, color: 'var(--pk-accent)', marginTop: 3 }}>Demandée à l’instant · comptoir prévenu</div>
            </div>
          ) : null}
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #2f2f2f' }}>
            <div style={{ fontSize: 18, fontWeight: 600 }}>Pneu Michelin Road 6</div>
            <div style={{ fontSize: 16, color: '#7ee08a', marginTop: 3 }}>Apporté par Léa à 15:52</div>
          </div>
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #2f2f2f' }}>
            <div style={{ fontSize: 18, fontWeight: 600 }}>Kit chaîne DID 525</div>
            <div style={{ fontSize: 16, color: 'var(--pk-accent)', marginTop: 3 }}>Demandé à 16:10 · comptoir prévenu</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '18px 20px', borderTop: '1px solid #2f2f2f', fontSize: 16, lineHeight: 1.5, color: '#a5a5a5' }}>Une demande apparaît immédiatement en Réception. Elle n’arrête pas votre pointage.</div>
        </div>
      </div>
    </div>
  );
}

function MechBlocker() {
  const [kind, setKind] = React.useState('unplanned');
  const kinds = [
    { id: 'unplanned', icon: 'ri-search-eye-line', name: 'Travail non prévu découvert', sub: 'Le client doit donner son accord avant qu’on continue' },
    { id: 'part', icon: 'ri-box-3-line', name: 'Pièce manquante', sub: 'Ouvre la demande de pièce' },
    { id: 'tool', icon: 'ri-hammer-line', name: 'Outil indisponible', sub: 'Précise lequel, et à qui il est' },
    { id: 'advice', icon: 'ri-question-line', name: 'Besoin d’un avis', sub: 'Appelle le chef d’atelier sur son poste' },
  ];
  return (
    <div style={mkShell}>
      <div style={mkBar}>
        <span style={mkBack}><i className="ri-arrow-left-line" style={{ fontSize: 26 }} /></span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 22, fontWeight: 600 }}>OR 2427 · Z900 JK-118-LM</div>
          <div style={{ fontSize: 16, color: '#a5a5a5' }}>Sonia · pont P2 · pointage 3 h 10 sur 2 h 30 vendues</div>
        </div>
        <span style={{ fontSize: 18, color: '#a5a5a5' }}>16:44</span>
      </div>
      <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 12, minHeight: 0 }}>
        <div style={{ fontSize: 26, fontWeight: 600 }}>Qu’est-ce qui vous bloque ?</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12 }}>
          {kinds.map((k) => (
            <button type="button" key={k.id} onClick={() => setKind(k.id)}
              style={{ minHeight: 78, padding: '13px 16px', ...mkTile, border: kind === k.id ? '2px solid var(--pk-accent)' : '1px solid #4a4a4a', display: 'flex', flexDirection: 'column', gap: 5, alignItems: 'flex-start', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}><i className={k.icon} style={{ fontSize: 24, color: kind === k.id ? 'var(--pk-accent)' : '#a5a5a5' }} /><span style={{ fontSize: 20, fontWeight: 600 }}>{k.name}</span></div>
              <span style={{ fontSize: 16, color: '#a5a5a5' }}>{k.sub}</span>
            </button>
          ))}
        </div>
        {kind === 'unplanned' ? (
          <div style={{ ...mkTile, border: '1px solid #4a4a4a', padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10 }}>
            <span style={{ fontSize: 16, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#a5a5a5' }}>Travail non prévu — ce qui part au comptoir</span>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <span style={{ flex: 1, minHeight: 60, display: 'flex', alignItems: 'center', padding: '0 16px', background: '#141414', border: '1px solid #4a4a4a', fontSize: 19 }}>Disque arrière voilé, à remplacer</span>
              <span style={{ minHeight: 60, display: 'flex', alignItems: 'center', padding: '0 16px', background: '#141414', border: '1px solid #4a4a4a', fontSize: 19 }}>1 h 00</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <span style={{ fontSize: 18, color: '#a5a5a5' }}>Photo du constat</span>
              <span style={{ ...mkGhost, gap: 10 }}><i className="ri-camera-line" style={{ fontSize: 22 }} />1 photo prise</span>
            </div>
            <div style={{ fontSize: 17, lineHeight: 1.5, color: '#a5a5a5' }}>Le comptoir chiffre et demande l’accord au client. Vous n’avez pas à connaître le prix : le temps suffit.</div>
          </div>
        ) : (
          <div style={{ ...mkTile, border: '1px solid #4a4a4a', padding: '14px 16px', fontSize: 18, lineHeight: 1.5, color: '#a5a5a5' }}>{kinds.find((k) => k.id === kind).sub}. Le comptoir reçoit le signalement avec l’OR, le pont et l’heure.</div>
        )}
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <span style={{ flex: 1, minHeight: 60, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 20, fontWeight: 700 }}>Envoyer et mettre l’OR en attente</span>
          <span style={{ minHeight: 60, display: 'flex', alignItems: 'center', padding: '0 22px', border: '1px solid #4a4a4a', fontSize: 19, fontWeight: 600 }}>Annuler</span>
        </div>
        <div style={{ fontSize: 17, color: '#a5a5a5' }}>Votre pointage s’arrête à l’envoi. Le temps d’attente est compté à part, pas sur votre intervention.</div>
      </div>
    </div>
  );
}

function MechDayEnd({ logo }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div style={mkShell}>
      <div style={mkBar}>
        <img src={logo} alt="Paddock" style={{ width: 40, height: 40, display: 'block', flex: 'none' }} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 22, fontWeight: 600 }}>Karim · jeudi 21 août</div>
          <div style={{ fontSize: 16, color: '#a5a5a5' }}>Poste P3 · débauche prévue 17:30</div>
        </div>
        <span style={{ fontSize: 18, color: '#a5a5a5' }}>17:24</span>
      </div>
      <div style={{ flex: 1, padding: 22, display: 'flex', flexDirection: 'column', gap: 18, minHeight: 0 }}>
        <div style={{ fontSize: 26, fontWeight: 600 }}>Avant de partir</div>
        <div style={{ display: 'flex', gap: 12 }}>
          <div style={mkStat}><span style={{ fontSize: 16, color: '#a5a5a5' }}>Pointé aujourd’hui</span><span style={{ fontSize: 34, fontWeight: 700, lineHeight: 1 }}>{open ? '6 h 55' : '8 h 00'}</span></div>
          <div style={mkStat}><span style={{ fontSize: 16, color: '#a5a5a5' }}>Interventions closes</span><span style={{ fontSize: 34, fontWeight: 700, lineHeight: 1 }}>{open ? 4 : 5}</span></div>
          <div style={mkStat}><span style={{ fontSize: 16, color: '#a5a5a5' }}>Encore ouvertes</span><span style={{ fontSize: 34, fontWeight: 700, lineHeight: 1, color: open ? 'var(--pk-accent)' : '#7ee08a' }}>{open ? 1 : 0}</span></div>
        </div>
        {open ? (
          <div style={{ ...mkTile, borderLeft: '4px solid var(--pk-accent)', padding: 18, display: 'flex', alignItems: 'center', gap: 18 }}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 21, fontWeight: 600 }}>OR 2431 · MT-07 · pointage encore ouvert</div>
              <div style={{ fontSize: 17, color: '#a5a5a5', marginTop: 3 }}>Démarré à 16:19 · 1 h 05 si vous clôturez maintenant</div>
            </div>
            <button type="button" onClick={() => setOpen(false)} style={{ ...mkPrimary, minHeight: 60, fontSize: 18 }}>Clôturer</button>
            <span style={{ ...mkGhost, minHeight: 60, fontSize: 18 }}>Laisser en pause</span>
          </div>
        ) : (
          <div style={{ ...mkTile, borderLeft: '4px solid #7ee08a', padding: 18, display: 'flex', alignItems: 'center', gap: 14 }}>
            <i className="ri-check-double-line" style={{ fontSize: 26, color: '#7ee08a' }} />
            <div style={{ fontSize: 21, fontWeight: 600 }}>Aucun pointage ouvert · le comptoir a l’OR 2431 en « terminé »</div>
          </div>
        )}
        <div style={{ ...mkTile, padding: 18, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <span style={{ fontSize: 16, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#a5a5a5' }}>À reprendre demain</span>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, fontSize: 19 }}>
            <i className="ri-arrow-right-line" style={{ fontSize: 22, color: 'var(--pk-accent)' }} /><span style={{ flex: 1 }}>Z900 · attente accord client sur le disque arrière</span><span style={{ color: '#a5a5a5' }}>depuis 1 h 20</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, fontSize: 19 }}>
            <i className="ri-arrow-right-line" style={{ fontSize: 22, color: 'var(--pk-accent)' }} /><span style={{ flex: 1 }}>V-Strom · pièce annoncée pour demain 10 h</span><span style={{ color: '#a5a5a5' }}>pont P4 occupé</span>
          </div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <span style={{ flex: 1, minHeight: 72, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', fontSize: 21, fontWeight: 700 }}>Terminer ma journée</span>
          <span style={{ minHeight: 72, display: 'flex', alignItems: 'center', padding: '0 24px', border: '1px solid #4a4a4a', fontSize: 19, fontWeight: 600 }}>Rester connecté</span>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { MechPartRequest, MechBlocker, MechDayEnd });
