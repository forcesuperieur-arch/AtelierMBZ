/* Affichage mural (tour 47a) — 1920 × 1080, écran au-dessus du comptoir, aucune
   interaction, rien sous 20 px. Trois colonnes : ce qui sort, ce qui est sur les
   ponts, ce qui attend une réponse. L'heure du rafraîchissement est affichée,
   parce qu'un écran figé ressemble à un écran à jour. */
const wbCol = { display: 'flex', flexDirection: 'column', minHeight: 0 };
const wbTitle = { display: 'flex', alignItems: 'baseline', gap: 14, padding: '18px 26px 12px' };
const wbLabel = { fontSize: 24, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-accent)' };
const wbTile = { background: '#1f1f1f', padding: '14px 18px' };
const wbBar = { height: 8, background: '#333', marginTop: 8 };
const wbFoot = { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' };

function WallOut({ name, sub, right, edge, subTone, dim }) {
  return (
    <div style={{ ...wbTile, borderLeft: edge ? '5px solid ' + edge : 'none', display: 'flex', alignItems: 'center', gap: 16, opacity: dim ? 0.75 : 1 }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 28, fontWeight: 600 }}>{name}</div>
        <div style={{ fontSize: 20, color: subTone || '#a5a5a5', marginTop: 2 }}>{sub}</div>
      </div>
      <span style={{ fontSize: 24, fontWeight: 700, color: edge || '#a5a5a5' }}>{right}</span>
    </div>
  );
}

function WallBay({ bay, bike, who, time, pct, tone }) {
  return (
    <div style={wbTile}>
      <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
        <span style={{ fontSize: 22, fontWeight: 700, color: 'var(--pk-accent)' }}>{bay}</span>
        <span style={{ fontSize: 26, fontWeight: 600 }}>{bike}</span>
      </div>
      <div style={{ ...wbFoot, marginTop: 6 }}>
        <span style={{ fontSize: 20, color: '#a5a5a5' }}>{who}</span>
        <span style={{ fontSize: 22, fontWeight: 600, color: tone === '#7ee08a' ? 'inherit' : tone }}>{time}</span>
      </div>
      <div style={wbBar}><div style={{ width: pct + '%', height: '100%', background: tone }} /></div>
    </div>
  );
}

function WallboardScreen({ logo }) {
  return (
    <div style={{ width: 1920, height: 1080, background: '#141414', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: '#f6f6f6', fontFamily: 'var(--mb-font-montserrat)' }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 20, padding: '20px 28px', borderBottom: '1px solid #2f2f2f' }}>
        <img src={logo} alt="Paddock" style={{ width: 40, height: 40, display: 'block', flex: 'none' }} />
        <div style={{ fontSize: 30, fontWeight: 600, letterSpacing: '-0.01em' }}>Atelier Principal · jeudi 21 août</div>
        <div style={{ flex: 1 }} />
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 14 }}>
          <span style={{ fontSize: 40, fontWeight: 700, lineHeight: 1, letterSpacing: '-0.02em' }}>16:42</span>
          <span style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 20, color: '#a5a5a5' }}><span style={{ width: 10, height: 10, borderRadius: 'var(--pk-radius-pill)', background: '#7ee08a', display: 'block' }} />à jour</span>
        </div>
      </div>

      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1.15fr 1fr 0.95fr', minHeight: 0 }}>
        <div style={{ ...wbCol, borderRight: '1px solid #2f2f2f' }}>
          <div style={wbTitle}><span style={wbLabel}>Sortent aujourd’hui</span><span style={{ fontSize: 24, fontWeight: 700, color: '#a5a5a5' }}>6</span></div>
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10, padding: '0 26px 20px', minHeight: 0 }}>
            <WallOut name="Tiger 900 · AB-234-CD" sub="Roche · révision 20 000" right="Prête" edge="#7ee08a" />
            <WallOut name="MT-07 · EF-771-GH" sub="Belkacem · plaquettes + pneu AR" right="Prête" edge="#7ee08a" />
            <WallOut name="Z900 · JK-118-LM" sub="Fontaine · essai en cours" right="17:15" edge="#f1ab00" />
            <WallOut name="V-Strom · NP-902-QR" sub="Pièce en retard · client prévenu" right="Demain" edge="#ff8095" subTone="#ff8095" />
            <WallOut name="CB650R · ST-455-UV" sub="Restituée à 14:20" right="Partie" dim />
          </div>
        </div>

        <div style={{ ...wbCol, borderRight: '1px solid #2f2f2f' }}>
          <div style={wbTitle}><span style={wbLabel}>Sur les ponts</span><span style={{ fontSize: 24, fontWeight: 700, color: '#a5a5a5' }}>4 / 5</span></div>
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10, padding: '0 26px 20px' }}>
            <WallBay bay="P1" bike="Tiger 900" who="Karim" time="1 h 40 / 2 h" pct={83} tone="#7ee08a" />
            <WallBay bay="P2" bike="Z900" who="Sonia" time="3 h 10 / 2 h 30" pct={100} tone="#ff8095" />
            <WallBay bay="P3" bike="MT-07" who="Karim" time="0 h 25 / 1 h" pct={42} tone="#7ee08a" />
            <WallBay bay="P4" bike="V-Strom" who="Attente pièce" time="en pause" pct={60} tone="#f1ab00" />
            <div style={{ border: '2px dashed #333', padding: '14px 18px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <span style={{ fontSize: 22, fontWeight: 700, color: '#6f6e6e' }}>P5</span>
              <span style={{ fontSize: 20, color: '#6f6e6e' }}>libre depuis 15:30</span>
            </div>
          </div>
        </div>

        <div style={wbCol}>
          <div style={wbTitle}><span style={wbLabel}>Attente réponse</span><span style={{ fontSize: 24, fontWeight: 700, color: '#ff8095' }}>2</span></div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '0 26px 20px' }}>
            <div style={{ ...wbTile, borderLeft: '5px solid #ff8095' }}>
              <div style={{ fontSize: 26, fontWeight: 600 }}>Z900 · Fontaine</div>
              <div style={{ fontSize: 20, color: '#a5a5a5', marginTop: 3 }}>Disque AR à remplacer · 168 €</div>
              <div style={{ fontSize: 22, fontWeight: 700, color: '#ff8095', marginTop: 6 }}>sans réponse depuis 1 h 20</div>
            </div>
            <div style={{ ...wbTile, borderLeft: '5px solid #f1ab00' }}>
              <div style={{ fontSize: 26, fontWeight: 600 }}>V-Strom · Nowak</div>
              <div style={{ fontSize: 20, color: '#a5a5a5', marginTop: 3 }}>Devis pneus envoyé</div>
              <div style={{ fontSize: 22, fontWeight: 700, color: '#f1ab00', marginTop: 6 }}>relance prévue demain</div>
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '18px 26px', borderTop: '1px solid #2f2f2f', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={{ fontSize: 20, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#a5a5a5' }}>Demain</span>
            <div style={wbFoot}><span style={{ fontSize: 24 }}>Rendez-vous</span><span style={{ fontSize: 26, fontWeight: 700 }}>9</span></div>
            <div style={wbFoot}><span style={{ fontSize: 24 }}>Charge des ponts</span><span style={{ fontSize: 26, fontWeight: 700, color: 'var(--pk-accent)' }}>88 %</span></div>
            <div style={wbFoot}><span style={{ fontSize: 24 }}>Mécaniciens</span><span style={{ fontSize: 26, fontWeight: 700 }}>2 sur 3</span></div>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { WallboardScreen });
