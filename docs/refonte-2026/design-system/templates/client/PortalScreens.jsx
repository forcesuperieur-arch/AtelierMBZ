/* Les écrans de la refonte du portail client.

   Registre : plus aéré que l'outil métier. Une seule action par écran, du texte
   plus grand, du blanc autour. Les jetons et l'échelle de rayons restent ceux du
   design system — c'est la densité et la hiérarchie qui changent, pas la
   matière. Cibles tactiles à 44 px, action principale à 56 px. */

const ptPage = { display: 'flex', flexDirection: 'column', gap: 20, padding: '28px 20px 40px' };
const ptCard = { background: 'var(--surface-1)', border: '1px solid var(--border-1)', borderRadius: 8, padding: 20 };
const ptLabel = { fontSize: 11, fontWeight: 700, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--content-3)' };
const ptH1 = { fontSize: 26, fontWeight: 700, lineHeight: 1.2, color: 'var(--content-1)', margin: 0, textWrap: 'pretty' };
const ptH2 = { fontSize: 17, fontWeight: 700, color: 'var(--content-1)', margin: 0 };
const ptBody = { fontSize: 15, lineHeight: 1.55, color: 'var(--content-2)', margin: 0, textWrap: 'pretty' };
const ptMeta = { fontSize: 13, color: 'var(--content-3)' };
const ptPrimary = { minHeight: 56, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, width: '100%', padding: '0 20px', background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none', borderRadius: 8, fontSize: 16, fontWeight: 700, cursor: 'pointer' };
const ptGhost = { minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, width: '100%', padding: '0 20px', background: 'transparent', color: 'var(--content-2)', border: '1px solid var(--border-control)', borderRadius: 8, fontSize: 15, fontWeight: 600, cursor: 'pointer' };

function PtChip({ children, tone }) {
  const t = { attention: ['var(--warning-soft)', 'var(--warning-content)'], ok: ['var(--success-soft)', 'var(--success-content)'], neutre: ['var(--surface-2)', 'var(--content-2)'] }[tone || 'neutre'];
  return <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 11px', background: t[0], color: t[1], borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700, whiteSpace: 'nowrap' }}>{children}</span>;
}

function PtSectionLabel({ children }) {
  return <div style={ptLabel}>{children}</div>;
}

/* ---------- Accueil : ce qu'il y a à faire, d'abord ---------- */

function PortalAccueil({ go, devisFait, messagesNonLus }) {
  const m = window.PT_MOTO, s = window.PT_SUIVI, d = window.PT_DEVIS;
  const total = d.lignes.filter((l) => l.requis).reduce((a, l) => a + l.prix, 0);
  return (
    <div style={ptPage}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <h1 style={ptH1}>Bonjour {window.PT_CLIENT.prenom}</h1>
        <p style={ptBody}>Votre {m.marque} {m.modele} est à l’atelier depuis mardi.</p>
      </div>

      {!devisFait ? (
        <div style={{ background: 'var(--accent-soft)', border: '1px solid var(--accent-graphic)', borderRadius: 8, padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
            <PtChip tone="attention"><i className="ri-time-line" style={{ fontSize: 14 }}></i>{d.urgence}</PtChip>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <h2 style={{ ...ptH2, fontSize: 20 }}>Un devis attend votre accord</h2>
            <p style={ptBody}>{d.motif}</p>
          </div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 28, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{window.ptEuro(total)}</span>
            <span style={ptMeta}>pour les deux lignes nécessaires</span>
          </div>
          <button style={ptPrimary} onClick={() => go('devis')}>Voir le devis<i className="ri-arrow-right-line" style={{ fontSize: 18 }}></i></button>
        </div>
      ) : (
        <div style={{ background: 'var(--success-soft)', border: '1px solid var(--success)', borderRadius: 8, padding: 20, display: 'flex', gap: 14, alignItems: 'flex-start' }}>
          <i className="ri-check-line" style={{ fontSize: 22, color: 'var(--success-content)' }}></i>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <h2 style={ptH2}>Accord donné</h2>
            <p style={ptBody}>L’atelier a été prévenu. Le disque est commandé, la restitution reste prévue jeudi.</p>
          </div>
        </div>
      )}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <PtSectionLabel>Où en est ma moto</PtSectionLabel>
        <button style={{ ...ptCard, textAlign: 'left', cursor: 'pointer', display: 'flex', flexDirection: 'column', gap: 14, font: 'inherit' }} onClick={() => go('suivi')}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <span style={ptH2}>{s.intervention}</span>
              <span style={ptMeta}>{m.marque} {m.modele} · {m.plaque} · {s.or}</span>
            </div>
            <PtChip tone="attention">En cours</PtChip>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            {s.etapes.map((e) => <div key={e.cle} style={{ flex: 1, height: 4, borderRadius: 'var(--pk-radius-pill)', background: e.fait ? 'var(--accent-graphic)' : 'var(--border-2)' }}></div>)}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--content-2)', fontSize: 14 }}>
            <i className="ri-calendar-check-line" style={{ fontSize: 16 }}></i>
            <span>Restitution prévue {s.restitution}</span>
          </div>
        </button>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <PtSectionLabel>Le reste</PtSectionLabel>
        <div style={{ display: 'grid', gap: 12 }}>
          <button style={{ ...ptCard, textAlign: 'left', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 14, minHeight: 44, font: 'inherit' }} onClick={() => go('messages')}>
            <i className="ri-chat-3-line" style={{ fontSize: 20, color: 'var(--content-3)' }}></i>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
              <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)' }}>Messages</span>
              <span style={{ ...ptMeta, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>Karim L. — « J’ai trouvé un disque arrière voilé… »</span>
            </div>
            {messagesNonLus > 0 && <span style={{ minWidth: 22, height: 22, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--accent)', color: 'var(--accent-ink)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700 }}>{messagesNonLus}</span>}
          </button>
          <button style={{ ...ptCard, textAlign: 'left', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 14, minHeight: 44, font: 'inherit' }} onClick={() => go('moto')}>
            <i className="ri-oil-line" style={{ fontSize: 20, color: 'var(--warning-content)' }}></i>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 2 }}>
              <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)' }}>Vidange dépassée de {window.ptKm(m.km - m.prochaineVidange.km)}</span>
              <span style={ptMeta}>Elle est comprise dans la révision en cours</span>
            </div>
            <i className="ri-arrow-right-s-line" style={{ fontSize: 20, color: 'var(--content-3)' }}></i>
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Devis : l'acte, ligne par ligne ---------- */

function PortalDevis({ go, onAccord, fait }) {
  const d = window.PT_DEVIS;
  const [pris, setPris] = React.useState(() => d.lignes.filter((l) => l.requis).map((l) => l.id));
  const total = d.lignes.filter((l) => pris.includes(l.id)).reduce((a, l) => a + l.prix, 0);
  const bascule = (l) => { if (l.requis) return; setPris((p) => (p.includes(l.id) ? p.filter((x) => x !== l.id) : [...p, l.id])); };

  if (fait) {
    return (
      <div style={ptPage}>
        <div style={{ background: 'var(--success-soft)', border: '1px solid var(--success)', borderRadius: 8, padding: 24, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <i className="ri-check-line" style={{ fontSize: 28, color: 'var(--success-content)' }}></i>
          <h1 style={ptH1}>Accord donné à 14 h 22</h1>
          <p style={ptBody}>L’atelier a été prévenu tout de suite. Vous recevrez la facture définitive avec la restitution.</p>
        </div>
        <div style={{ ...ptCard, display: 'flex', flexDirection: 'column', gap: 10 }}>
          <PtSectionLabel>Ce que vous avez accepté</PtSectionLabel>
          {d.lignes.filter((l) => l.requis).map((l) => (
            <div key={l.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 12, fontSize: 15, color: 'var(--content-1)' }}>
              <span>{l.designation}</span>
              <span style={{ fontVariantNumeric: 'tabular-nums' }}>{window.ptEuro(l.prix)}</span>
            </div>
          ))}
        </div>
        <button style={ptGhost} onClick={() => go('suivi')}>Suivre l’intervention</button>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100%' }}>
      <div style={{ ...ptPage, flex: 1 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <PtChip tone="attention"><i className="ri-time-line" style={{ fontSize: 14 }}></i>{d.urgence}</PtChip>
          <h1 style={ptH1}>Devis complémentaire</h1>
          <span style={ptMeta}>{d.numero} · émis {d.emis} par {d.par}</span>
        </div>

        <div style={{ ...ptCard, background: 'var(--surface-2)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <PtSectionLabel>Pourquoi</PtSectionLabel>
          <p style={ptBody}>{d.motif}</p>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <PtSectionLabel>Le détail</PtSectionLabel>
          {d.lignes.map((l) => {
            const on = pris.includes(l.id);
            return (
              <button key={l.id} onClick={() => bascule(l)} style={{ ...ptCard, textAlign: 'left', font: 'inherit', cursor: l.requis ? 'default' : 'pointer', display: 'flex', gap: 14, alignItems: 'flex-start', borderColor: on ? 'var(--border-strong)' : 'var(--border-1)' }}>
                <span style={{ width: 22, height: 22, flexShrink: 0, marginTop: 2, borderRadius: 4, border: on ? 'none' : '1px solid var(--border-control)', background: on ? 'var(--content-1)' : 'transparent', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  {on && <i className="ri-check-line" style={{ fontSize: 15, color: 'var(--content-inverse)' }}></i>}
                </span>
                <span style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <span style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                    <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)' }}>{l.designation}</span>
                    <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.ptEuro(l.prix)}</span>
                  </span>
                  <span style={ptMeta}>{l.detail}</span>
                  {l.requis && <span style={{ ...ptMeta, color: 'var(--content-2)' }}>Nécessaire à la réparation</span>}
                </span>
              </button>
            );
          })}
        </div>

        <p style={{ ...ptMeta, lineHeight: 1.5 }}>Vous pouvez décocher ce qui n’est pas nécessaire. Refuser le devis n’annule pas la révision en cours : la moto sera rendue avec le disque tel quel, et nous le noterons sur l’ordre de réparation.</p>
      </div>

      <div style={{ position: 'sticky', bottom: 0, background: 'var(--surface-1)', borderTop: '1px solid var(--border-1)', padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
          <span style={{ fontSize: 15, color: 'var(--content-2)' }}>Total</span>
          <span style={{ fontSize: 24, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{window.ptEuro(total)}</span>
        </div>
        <button style={ptPrimary} onClick={onAccord}>Donner mon accord</button>
        <button style={{ ...ptGhost, minHeight: 44, border: 'none', color: 'var(--content-3)' }} onClick={() => go('accueil')}>Refuser le devis</button>
      </div>
    </div>
  );
}

/* ---------- Suivi : la frise et l'état des lieux ---------- */

function PortalSuivi() {
  const s = window.PT_SUIVI, m = window.PT_MOTO, e = s.etatDesLieux;
  return (
    <div style={ptPage}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <h1 style={ptH1}>{s.intervention}</h1>
        <span style={ptMeta}>{m.marque} {m.modele} · {m.plaque} · {s.or}</span>
      </div>

      <div style={{ ...ptCard, display: 'flex', flexDirection: 'column', gap: 0 }}>
        {s.etapes.map((et, i) => (
          <div key={et.cle} style={{ display: 'flex', gap: 14 }}>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 22 }}>
              <span style={{ width: 22, height: 22, flexShrink: 0, borderRadius: 'var(--pk-radius-pill)', background: et.courant ? 'var(--accent)' : et.fait ? 'var(--content-1)' : 'transparent', border: et.fait || et.courant ? 'none' : '1px solid var(--border-control)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                {et.fait && !et.courant && <i className="ri-check-line" style={{ fontSize: 14, color: 'var(--content-inverse)' }}></i>}
                {et.courant && <span style={{ width: 8, height: 8, borderRadius: 'var(--pk-radius-pill)', background: 'var(--accent-ink)' }}></span>}
              </span>
              {i < s.etapes.length - 1 && <span style={{ flex: 1, width: 2, minHeight: 28, background: et.fait ? 'var(--content-1)' : 'var(--border-2)' }}></span>}
            </div>
            <div style={{ flex: 1, paddingBottom: i < s.etapes.length - 1 ? 20 : 0, display: 'flex', flexDirection: 'column', gap: 3 }}>
              <span style={{ fontSize: 15, fontWeight: et.courant ? 700 : 600, color: et.fait ? 'var(--content-1)' : 'var(--content-3)' }}>{et.titre}</span>
              {et.date && <span style={ptMeta}>{et.date}</span>}
              {et.detail && <span style={{ ...ptBody, fontSize: 14 }}>{et.detail}</span>}
            </div>
          </div>
        ))}
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <PtSectionLabel>État des lieux à la dépose</PtSectionLabel>
        <div style={{ ...ptCard, display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              <span style={ptLabel}>Kilométrage</span>
              <span style={{ fontSize: 17, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.ptKm(e.km)}</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              <span style={ptLabel}>Carburant</span>
              <span style={{ fontSize: 17, fontWeight: 700, color: 'var(--content-1)' }}>{e.carburant}</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              <span style={ptLabel}>Signé</span>
              <span style={{ fontSize: 17, fontWeight: 700, color: 'var(--content-1)' }}>{e.signe}</span>
            </div>
          </div>
          <p style={ptBody}>{e.observations}</p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: 10 }}>
            {e.photos.map((p, i) => (
              <div key={p} style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {/* Le client ne prend pas ces photos, il les consulte : c'est
                    l'atelier qui les dépose au comptoir. */}
                <div style={{ aspectRatio: '4 / 3', position: 'relative', background: 'var(--surface-2)', border: '1px solid var(--border-2)', borderRadius: 6, overflow: 'hidden' }}>
                  <image-slot id={'pt-etat-' + i} shape="rect" fit="cover" placeholder="Photo de l’état des lieux" style={{ position: 'absolute', inset: 0, width: '100%', height: '100%' }}></image-slot>
                </div>
                <span style={{ ...ptMeta, fontSize: 12 }}>{p}</span>
              </div>
            ))}
          </div>
          <span style={{ ...ptMeta, fontSize: 12 }}>Photos prises par l’atelier à la dépose. Aucune image réelle n’est fournie au design system : les cadres sont des emplacements.</span>
        </div>
      </div>
    </div>
  );
}

/* ---------- Messages ---------- */

function PortalMessages({ onLu }) {
  const [fil, setFil] = React.useState(window.PT_MESSAGES);
  const [txt, setTxt] = React.useState('');
  React.useEffect(() => { onLu && onLu(); }, []);
  const envoyer = () => {
    if (!txt.trim()) return;
    setFil((f) => [...f, { de: 'client', auteur: 'Vous', date: 'à l’instant', texte: txt.trim() }]);
    setTxt('');
  };
  return (
    <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100%' }}>
      <div style={{ ...ptPage, flex: 1, gap: 16 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <h1 style={ptH1}>Atelier Paddock Lille</h1>
          <span style={ptMeta}>Julie D. à l’accueil, Karim L. à l’atelier · réponse le jour même</span>
        </div>
        {fil.map((m, i) => (
          <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: m.de === 'client' ? 'flex-end' : 'flex-start' }}>
            <span style={{ ...ptMeta, fontSize: 12 }}>{m.auteur} · {m.date}</span>
            <div style={{ maxWidth: '82%', padding: '14px 16px', borderRadius: 8, background: m.de === 'client' ? 'var(--accent-soft)' : 'var(--surface-1)', border: '1px solid ' + (m.de === 'client' ? 'var(--accent-graphic)' : 'var(--border-1)'), fontSize: 15, lineHeight: 1.5, color: 'var(--content-1)', textWrap: 'pretty' }}>{m.texte}</div>
          </div>
        ))}
      </div>
      <div style={{ position: 'sticky', bottom: 0, background: 'var(--surface-1)', borderTop: '1px solid var(--border-1)', padding: '12px 16px', display: 'flex', gap: 10, alignItems: 'flex-end' }}>
        <input value={txt} onChange={(e) => setTxt(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && envoyer()} placeholder="Écrire à l’atelier" style={{ flex: 1, minHeight: 48, padding: '0 14px', background: 'var(--surface-0)', border: '1px solid var(--border-control)', borderRadius: 8, fontSize: 15, color: 'var(--content-1)', fontFamily: 'inherit' }} />
        <button onClick={envoyer} style={{ width: 48, height: 48, flexShrink: 0, background: 'var(--accent)', color: 'var(--accent-ink)', border: 'none', borderRadius: 8, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }} aria-label="Envoyer"><i className="ri-send-plane-fill" style={{ fontSize: 19 }}></i></button>
      </div>
    </div>
  );
}

/* ---------- Ma moto : le carnet d'entretien ---------- */

function PortalMoto() {
  const m = window.PT_MOTO, e = window.PT_ECHEANCE;
  const [ouvert, setOuvert] = React.useState(window.PT_CARNET[0].or);
  const [rappels, setRappels] = React.useState(e.actif);
  const [canal, setCanal] = React.useState(e.canal);
  const total = window.PT_CARNET.reduce((a, e) => a + e.montant, 0);
  return (
    <div style={ptPage}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <h1 style={ptH1}>{m.marque} {m.modele}</h1>
        <span style={ptMeta}>{m.plaque} · {m.annee} · {m.cylindree}</span>
      </div>

      <div style={{ ...ptCard, display: 'flex', gap: 28, flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          <span style={ptLabel}>Kilométrage</span>
          <span style={{ fontSize: 22, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.ptKm(m.km)}</span>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          <span style={ptLabel}>Passages à l’atelier</span>
          <span style={{ fontSize: 22, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.PT_CARNET.length}</span>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          <span style={ptLabel}>Dépensé depuis 2025</span>
          <span style={{ fontSize: 22, fontWeight: 700, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.ptEuro(total)}</span>
        </div>
      </div>

      {/* L'échéance en tête du carnet : ce qui vient, avant ce qui est passé. */}
      <div style={{ ...ptCard, display: 'flex', flexDirection: 'column', gap: 0, padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14, flexWrap: 'wrap' }}>
            <div style={{ flex: 1, minWidth: 220, display: 'flex', flexDirection: 'column', gap: 5 }}>
              <span style={ptLabel}>Prochain entretien</span>
              <span style={{ fontSize: 19, fontWeight: 700, color: 'var(--content-1)' }}>{e.quoi}</span>
              <span style={{ ...ptBody, fontSize: 14 }}>{e.date} — {e.dans}. {e.estimation}.</span>
            </div>
            <window.PtChip tone={rappels ? 'accent' : 'neutre'}>{rappels ? 'Rappel programmé' : 'Rappels coupés'}</window.PtChip>
          </div>
          <span style={{ ...ptMeta, fontSize: 12, textWrap: 'pretty' }}>{e.base} {e.pose}.</span>
        </div>
        {/* L'interrupteur est ici, pas dans un écran de réglages : on décide au
            moment où on voit l'échéance. */}
        <div style={{ borderTop: '1px solid var(--border-2)', padding: '14px 18px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <label style={{ display: 'flex', alignItems: 'flex-start', gap: 12, cursor: 'pointer', minHeight: 44 }}>
            <input type="checkbox" checked={rappels} onChange={(ev) => setRappels(ev.target.checked)} style={{ width: 20, height: 20, marginTop: 2, flexShrink: 0, accentColor: 'var(--accent)' }} />
            <span style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
              <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--content-1)' }}>Me rappeler quand l’échéance approche</span>
              <span style={{ ...ptMeta, fontSize: 12, textWrap: 'pretty' }}>Un seul message, une semaine avant. Les messages concernant une intervention en cours vous parviennent dans tous les cas.</span>
            </span>
          </label>
          {rappels ? (
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', paddingLeft: 32 }}>
              <span style={{ ...ptMeta, fontSize: 12 }}>Me joindre par</span>
              {['SMS', 'E-mail'].map((c) => (
                <button key={c} onClick={() => setCanal(c)} style={{ font: 'inherit', whiteSpace: 'nowrap', minHeight: 36, padding: '0 14px', fontSize: 13, fontWeight: 600, borderRadius: 'var(--radius-pill)', cursor: 'pointer', background: canal === c ? 'var(--content-1)' : 'transparent', color: canal === c ? 'var(--surface-0)' : 'var(--content-2)', border: '1px solid ' + (canal === c ? 'var(--content-1)' : 'var(--border-2)') }}>{c}</button>
              ))}
            </div>
          ) : null}
        </div>
      </div>

      <div style={{ background: 'var(--warning-soft)', border: '1px solid var(--warning)', borderRadius: 8, padding: 16, display: 'flex', gap: 12, alignItems: 'flex-start' }}>
        <i className="ri-oil-line" style={{ fontSize: 20, color: 'var(--warning-content)', flexShrink: 0 }}></i>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--content-1)' }}>Vidange dépassée de {window.ptKm(m.km - m.prochaineVidange.km)}</span>
          <span style={{ ...ptBody, fontSize: 14 }}>Elle est comprise dans la révision en cours. Rien à faire de votre côté.</span>
        </div>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <PtSectionLabel>Carnet d’entretien</PtSectionLabel>
        {window.PT_CARNET.map((e) => {
          const on = ouvert === e.or;
          return (
            <button key={e.or} onClick={() => setOuvert(on ? null : e.or)} style={{ ...ptCard, textAlign: 'left', font: 'inherit', cursor: 'pointer', display: 'flex', flexDirection: 'column', gap: on ? 12 : 0 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'flex-start' }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                  <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)' }}>{e.titre}</span>
                  <span style={ptMeta}>{e.date} · {window.ptKm(e.km)} · {e.or}</span>
                </div>
                <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{window.ptEuro(e.montant)}</span>
              </div>
              {on && <p style={{ ...ptBody, fontSize: 14 }}>{e.travaux}</p>}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ---------- Documents ---------- */

function PortalDocuments() {
  const icone = { 'Facture': 'ri-receipt-line', 'Ordre de réparation': 'ri-file-text-line', 'État des lieux': 'ri-camera-line' };
  return (
    <div style={ptPage}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        <h1 style={ptH1}>Documents</h1>
        <p style={ptBody}>Tout ce que l’atelier a émis pour vous. Rien n’est supprimé.</p>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        {window.PT_DOCS.map((d) => (
          <div key={d.numero} style={{ ...ptCard, padding: 16, display: 'flex', alignItems: 'center', gap: 14 }}>
            <i className={icone[d.type]} style={{ fontSize: 20, color: 'var(--content-3)', flexShrink: 0 }}></i>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
              <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)' }}>{d.type}</span>
              <span style={ptMeta}>{d.numero} · {d.date} · {d.poids}</span>
            </div>
            {d.montant != null && <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--content-1)', fontVariantNumeric: 'tabular-nums' }}>{window.ptEuro(d.montant)}</span>}
            <button style={{ width: 44, height: 44, flexShrink: 0, background: 'transparent', border: '1px solid var(--border-control)', borderRadius: 8, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--content-1)' }} aria-label={'Télécharger ' + d.numero}><i className="ri-download-2-line" style={{ fontSize: 18 }}></i></button>
          </div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { PortalAccueil, PortalDevis, PortalSuivi, PortalMessages, PortalMoto, PortalDocuments, PtChip, ptPage, ptCard, ptLabel, ptH1, ptH2, ptBody, ptMeta, ptPrimary, ptGhost });
