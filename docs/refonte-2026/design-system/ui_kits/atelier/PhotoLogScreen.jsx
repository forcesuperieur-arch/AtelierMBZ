/* État des lieux photo (tour 47b) — panneau ouvert depuis la Réception. Six
   angles imposés, même ordre à l'entrée et à la sortie : c'est la comparaison
   qui fait preuve. Les réserves de l'entrée sont reprises telles quelles sur
   l'OR et le bon de sortie. */
const plAngles = ['Face avant', 'Flanc droit', 'Flanc gauche', 'Arrière', 'Compteur · 19 842 km', 'Rayure carénage · notée'];
const plOutAngles = ['Face avant', 'Flanc droit', 'Flanc gauche', 'Arrière', 'Compteur', 'Rayure carénage'];
const plShot = { aspectRatio: '4 / 3', background: 'var(--pk-border)', border: '1px solid #b9b9b9', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 4, color: 'var(--pk-ink-quiet)' };
const plTodo = { aspectRatio: '4 / 3', background: 'var(--pk-surface-raised)', border: '2px dashed var(--pk-ink-muted)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 5, color: 'var(--pk-ink-quiet)', fontFamily: 'inherit', cursor: 'pointer' };
const plGrid = { display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 10 };
const plNum = { width: 26, height: 26, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 700, flexShrink: 0 };
const plNote = { padding: '14px 20px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 12 };

function PhotoLogScreen() {
  const [out, setOut] = React.useState(2);
  return (
    <React.Fragment>
      <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 18, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em' }}>État des lieux · Yamaha Tracer 9</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>GT-908-ZK · N. Belkacem · OR 2431</div>
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '8px 14px', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-printer-line" style={{ fontSize: 16 }} />Imprimer l’état des lieux</span>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' }}>Entrée · 19 août 08:38 · Léa au comptoir</span>
            <span style={{ flex: 1, height: 1, background: 'var(--pk-border)' }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, fontWeight: 600, color: 'var(--pk-success-ink)' }}><i className="ri-check-double-line" style={{ fontSize: 15 }} />6 / 6 · signé</span>
          </div>
          <div style={plGrid}>
            {plAngles.map((a, i) => (
              <div key={a} style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                <div style={{ ...plShot, border: i === 5 ? '2px solid #d96500' : '1px solid #b9b9b9' }}><i className="ri-image-line" style={{ fontSize: 22 }} /><span style={{ fontSize: 11 }}>photo</span></div>
                <span style={{ fontSize: 12, fontWeight: 600, color: i === 5 ? 'var(--pk-warning-ink-soft)' : 'inherit' }}>{a}</span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' }}>Sortie · à prendre avant restitution</span>
            <span style={{ flex: 1, height: 1, background: 'var(--pk-border)' }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, fontWeight: 600, color: out === 6 ? 'var(--pk-success-ink)' : 'var(--pk-warning-ink-soft)' }}><i className={out === 6 ? 'ri-check-double-line' : 'ri-time-line'} style={{ fontSize: 15 }} />{out} / 6</span>
          </div>
          <div style={plGrid}>
            {plOutAngles.map((a, i) => (i < out
              ? <div key={a} style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                  <div style={plShot}><i className="ri-image-line" style={{ fontSize: 22 }} /><span style={{ fontSize: 11 }}>photo</span></div>
                  <span style={{ fontSize: 12, fontWeight: 600 }}>{a}</span>
                </div>
              : <div key={a} style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                  <button type="button" onClick={() => setOut(i + 1)} style={plTodo}><i className="ri-camera-line" style={{ fontSize: 22 }} /><span style={{ fontSize: 11, fontWeight: 600 }}>à prendre</span></button>
                  <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{a}</span>
                </div>))}
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '14px 16px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)' }}>
          <i className="ri-information-line" style={{ fontSize: 18, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>La restitution reste possible avec une série de sortie incomplète, mais l’écran le dit et l’inscrit sur le bon : « {out} photos de sortie sur 6 ». Bloquer la sortie d’une moto pour une photo manquante ferait perdre le client, pas gagner le litige.</div>
        </div>
        <div style={{ flex: 1 }} />
      </div>

      <aside style={{ width: 340, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '16px 20px', borderBottom: '1px solid var(--pk-border)' }}>
          <div style={{ fontSize: 15, fontWeight: 600 }}>Réserves notées à l’entrée</div>
          <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)', marginTop: 2 }}>Reprises telles quelles sur l’OR et le bon de sortie</div>
        </div>
        <div style={plNote}>
          <span style={{ ...plNum, background: '#d96500', color: '#fff' }}>1</span>
          <div><div style={{ fontSize: 14, fontWeight: 600 }}>Rayure carénage droit, 8 cm</div><div style={{ fontSize: 13, lineHeight: 1.45, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Signalée par le client à l’arrivée. Photo à l’appui.</div></div>
        </div>
        <div style={plNote}>
          <span style={{ ...plNum, background: '#000', color: 'var(--pk-accent)' }}>2</span>
          <div><div style={{ fontSize: 14, fontWeight: 600 }}>Top-case laissé sur la moto</div><div style={{ fontSize: 13, lineHeight: 1.45, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Vide. Clé remise au comptoir, casier 4.</div></div>
        </div>
        <div style={plNote}>
          <span style={{ ...plNum, background: '#000', color: 'var(--pk-accent)' }}>3</span>
          <div><div style={{ fontSize: 14, fontWeight: 600 }}>Niveau d’essence : réserve</div><div style={{ fontSize: 13, lineHeight: 1.45, color: 'var(--pk-ink-quiet)', marginTop: 2 }}>Essai routier limité à 5 km.</div></div>
        </div>
        <div style={{ padding: '14px 20px', borderBottom: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' }}>Ajouter une réserve</span>
          <div style={{ minHeight: 40, display: 'flex', alignItems: 'center', padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid #6f6e6e', borderRadius: 6, fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Ce que l’atelier constate…</div>
          <div style={{ display: 'flex', gap: 8 }}>
            <span style={{ minHeight: 40, display: 'flex', alignItems: 'center', gap: 6, padding: '0 13px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-camera-line" style={{ fontSize: 15 }} />Joindre une photo</span>
            <span style={{ minHeight: 40, display: 'flex', alignItems: 'center', padding: '0 14px', background: 'var(--pk-accent)', color: '#000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}>Ajouter</span>
          </div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ padding: '16px 20px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' }}>Signature</span>
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Entrée signée par <strong style={{ fontWeight: 600 }}>N. Belkacem</strong> le 19 août à 08:42, sur la tablette du comptoir.</div>
          <div style={{ height: 56, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, color: 'var(--pk-ink-quiet)' }}>tracé de signature</div>
        </div>
      </aside>
    </React.Fragment>
  );
}
Object.assign(window, { PhotoLogScreen });
