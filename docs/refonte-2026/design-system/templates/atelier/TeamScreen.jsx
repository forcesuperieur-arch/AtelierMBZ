/* Admin › Disponibilité de l'équipe (tour 15). Grille mécanicien × jour avec la
   charge déjà planifiée, l'absence posée en hachure, et le panneau qui dit ce
   que l'absence casse : les RDV sans mécanicien et qui peut les reprendre. */
const tmHead = { padding: '10px 12px', borderRight: '1px solid var(--pk-border-quiet)', fontSize: 12, fontWeight: 700 };
const tmGrid = { display: 'grid', gridTemplateColumns: '190px repeat(6, 1fr)' };
const tmName = { padding: '12px 14px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'center', gap: 10 };
const tmAv = { width: 30, height: 30, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 600, flexShrink: 0 };
const tmPill = { flex: 1, minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap', fontFamily: 'inherit', cursor: 'pointer' };

function TeamLoadCell({ value, pct, last }) {
  const border = last ? {} : { borderRight: '1px solid var(--pk-border-quiet)' };
  if (!pct) return <div style={{ padding: '10px 12px', ...border, display: 'flex', flexDirection: 'column', gap: 5 }}><span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>{value}</span></div>;
  return (
    <div style={{ padding: '10px 12px', ...border, display: 'flex', flexDirection: 'column', gap: 5 }}>
      <span style={{ fontSize: 13, fontWeight: 600 }}>{value}</span>
      <div style={{ height: 5, background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', overflow: 'hidden' }}><div style={{ width: pct + '%', height: '100%', background: 'var(--pk-accent)' }} /></div>
    </div>
  );
}

function TeamOffCell({ label, sub, last }) {
  return (
    <div style={{ padding: '10px 12px', borderRight: last ? 'none' : '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 4, background: 'var(--pk-canvas)' }}>
      <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-quiet)' }}>{label}</span>
      {sub ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{sub}</span> : null}
    </div>
  );
}

function TeamScreen() {
  const [absent, setAbsent] = React.useState(true);
  const days = ['Lun. 17', 'Mar. 18', 'Mer. 19', 'Jeu. 20', 'Ven. 21', 'Sam. 22'];
  const unassigned = [
    { when: 'Lundi 17 · 09:00 → 14:00', what: 'Africa Twin · É. Fournier · pont 5' },
    { when: 'Mardi 18 · 08:00 → 11:00', what: 'Z900 · J. Ravel · pont 6' },
    { when: 'Jeudi 20 · 14:00 → 17:00', what: 'Tracer 9 · N. Belkacem · pont 5' },
  ];
  return (
    <React.Fragment>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Disponibilité de l’équipe</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>Glissez sur les jours pour poser une absence. La charge affichée est celle déjà planifiée.</div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
            <div style={{ padding: '10px 20px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 }}>
              <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Capacité de la semaine</span>
              <span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>116 h 25</span>
              <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>assigné + perdu + disponible</span>
            </div>
            <div style={{ padding: '10px 20px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 }}>
              <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Assigné</span>
              <span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>71 h 55</span>
              <span style={{ fontSize: 11, color: 'var(--pk-accent-ink)' }}>{absent ? '+ 11 h sans affectation' : 'toutes les heures affectées'}</span>
            </div>
            <div style={{ padding: '10px 20px', display: 'flex', flexDirection: 'column', gap: 3 }}>
              <span style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-error-ink)' }}>Perdu cette semaine</span>
              <span style={{ fontSize: 22, fontWeight: 700, lineHeight: 1, color: 'var(--pk-error-ink)' }}>{absent ? '37 h 30' : '8 h 00'}</span>
              <span style={{ fontSize: 11, color: 'var(--pk-error-ink)' }}>{absent ? 'dont 29 h 30 sur cette absence' : 'formation de Sophie L. seulement'}</span>
            </div>
          </div>
        </div>

        <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
          <div style={{ ...tmGrid, borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
            <div style={{ padding: '10px 14px', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-muted)', borderRight: '1px solid var(--pk-border-quiet)' }}>Mécanicien</div>
            {days.map((d, i) => <div key={d} style={{ ...tmHead, borderRight: i === 5 ? 'none' : '1px solid var(--pk-border-quiet)' }}>{d}</div>)}
          </div>

          <div style={{ ...tmGrid, borderBottom: '1px solid var(--pk-border-quiet)' }}>
            <div style={tmName}><div style={tmAv}>KM</div><div style={{ minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Karim M.</div><div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>Ponts 1 et 2</div></div></div>
            <TeamLoadCell value="6 h 20" pct={67} /><TeamLoadCell value="8 h 10" pct={86} /><TeamLoadCell value="4 h 45" pct={59} />
            <TeamLoadCell value="7 h 00" pct={74} /><TeamLoadCell value="5 h 30" pct={58} /><TeamLoadCell value="Repos" last />
          </div>

          <div style={{ ...tmGrid, borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-surface-raised)' }}>
            <div style={tmName}><div style={tmAv}>SL</div><div style={{ minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Sophie L.</div><div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>Pont 4</div></div></div>
            <TeamLoadCell value="7 h 40" pct={81} /><TeamLoadCell value="6 h 00" pct={63} />
            <TeamOffCell label="Formation" sub="journée" />
            <TeamLoadCell value="8 h 30" pct={89} /><TeamLoadCell value="4 h 10" pct={44} /><TeamLoadCell value="3 h 00" pct={86} last />
          </div>

          <div style={{ ...tmGrid, borderBottom: '1px solid var(--pk-border-quiet)' }}>
            <div style={tmName}>
              <div style={{ ...tmAv, background: absent ? 'var(--pk-error-surface)' : 'var(--pk-canvas)', border: absent ? '1px solid var(--pk-error-line)' : 'none', color: absent ? 'var(--pk-error-ink)' : 'inherit' }}>TB</div>
              <div style={{ minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Thomas B.</div><div style={{ fontSize: 11, color: absent ? 'var(--pk-error-ink)' : 'var(--pk-ink-muted)' }}>{absent ? 'Temps partiel · absent' : 'Temps partiel'}</div></div>
            </div>
            {absent ? (
              <div style={{ gridColumn: '2 / span 5', padding: '10px 14px', borderRight: '1px solid var(--pk-border-quiet)', background: 'repeating-linear-gradient(45deg, #ffecef, #ffecef 6px, #fbdbe0 6px, #fbdbe0 12px)', display: 'flex', alignItems: 'center', gap: 10 }}>
                <i className="ri-calendar-close-fill" style={{ fontSize: 18, color: 'var(--pk-error-ink)' }} />
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-error-ink)' }}>Arrêt maladie · lundi 17 → vendredi 21</div>
                  <div style={{ fontSize: 11, color: '#8a1020' }}>Saisi à l’instant · 29 h 30 de capacité retirées</div>
                </div>
                <div style={{ flex: 1 }} />
                <button type="button" onClick={() => setAbsent(false)} style={{ padding: '6px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap', fontFamily: 'inherit', color: 'inherit', cursor: 'pointer' }}>Retirer</button>
              </div>
            ) : (
              <React.Fragment>
                <TeamLoadCell value="4 h 00" pct={50} /><TeamLoadCell value="5 h 30" pct={58} /><TeamLoadCell value="3 h 00" pct={38} />
                <TeamLoadCell value="6 h 00" pct={63} />
                <div style={{ padding: '10px 12px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'center' }}>
                  <button type="button" onClick={() => setAbsent(true)} style={{ padding: '5px 10px', border: '1px dashed var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 600, background: 'transparent', color: 'var(--pk-ink-quiet)', fontFamily: 'inherit', whiteSpace: 'nowrap', cursor: 'pointer' }}>Poser une absence</button>
                </div>
              </React.Fragment>
            )}
            <TeamLoadCell value="Repos" last />
          </div>

          <div style={{ ...tmGrid, background: 'var(--pk-surface-raised)' }}>
            <div style={tmName}><div style={tmAv}>AL</div><div style={{ minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Adrien L.</div><div style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>Apprenti · pont 3</div></div></div>
            <TeamOffCell label="École" /><TeamOffCell label="École" />
            <TeamLoadCell value="3 h 20" pct={42} /><TeamLoadCell value="5 h 00" pct={53} /><TeamLoadCell value="2 h 30" pct={26} /><TeamLoadCell value="Repos" last />
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Absences enregistrées</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-add-line" style={{ fontSize: 15 }} />Ajouter</span>
          </div>
          {absent ? (
            <div style={{ display: 'grid', gridTemplateColumns: '190px 1fr 220px 150px', alignItems: 'center', padding: '11px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 }}>
              <span style={{ fontWeight: 600 }}>Thomas B.</span><span style={{ color: 'var(--pk-ink-quiet)' }}>Arrêt maladie</span>
              <span>Lun. 17 → ven. 21 · journées</span><span style={{ textAlign: 'right', fontWeight: 600, color: 'var(--pk-error-ink)' }}>29 h 30 perdues</span>
            </div>
          ) : null}
          <div style={{ display: 'grid', gridTemplateColumns: '190px 1fr 220px 150px', alignItems: 'center', padding: '11px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13, background: 'var(--pk-surface-raised)' }}>
            <span style={{ fontWeight: 600 }}>Sophie L.</span><span style={{ color: 'var(--pk-ink-quiet)' }}>Formation freinage ABS</span>
            <span>Mer. 19 · journée</span><span style={{ textAlign: 'right', fontWeight: 600 }}>8 h 00 perdues</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '190px 1fr 220px 150px', alignItems: 'center', padding: '11px 16px', fontSize: 13 }}>
            <span style={{ fontWeight: 600 }}>Adrien L.</span><span style={{ color: 'var(--pk-ink-quiet)' }}>Centre de formation</span>
            <span>Lun. 17 et mar. 18 · récurrent</span><span style={{ textAlign: 'right', color: 'var(--pk-ink-muted)' }}>Hors capacité</span>
          </div>
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Ce que ça change</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{absent ? 'Absence de Thomas B.' : 'Rien en cours'}</span>
        </div>

        {absent ? (
          <React.Fragment>
            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-error-surface)', display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <i className="ri-error-warning-fill" style={{ fontSize: 18, color: 'var(--pk-error-ink)' }} />
                <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--pk-error-ink)' }}>3 RDV restent sans mécanicien</span>
              </div>
              {unassigned.map((u) => (
                <div key={u.when} style={{ padding: '11px 13px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', gap: 3 }}>
                  <span style={{ fontSize: 13, fontWeight: 600 }}>{u.when}</span>
                  <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{u.what}</span>
                </div>
              ))}
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Ils restent visibles au planning, marqués « sans affectation ». Les ponts 5 et 6 sont libres, pas les mécaniciens.</span>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={{ ...tmPill, background: 'var(--pk-accent)', color: '#000' }}>Réaffecter</span>
                <span style={{ ...tmPill, border: '1px solid var(--pk-border-strong)' }}>Reporter</span>
              </div>
            </div>

            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
              <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>Qui peut les reprendre</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
                <div style={{ ...tmAv, width: 28, height: 28 }}>KM</div>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Karim M.</div><div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>4 h 20 disponibles sur la semaine</div></div>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
                <div style={{ ...tmAv, width: 28, height: 28 }}>SL</div>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Sophie L.</div><div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>2 h 40 disponibles · hors mercredi</div></div>
              </div>
              <span style={{ fontSize: 12, color: 'var(--pk-accent-ink)', lineHeight: 1.45 }}>7 h de disponible pour 11 h à replacer : deux RDV devront être reportés.</span>
            </div>
          </React.Fragment>
        ) : (
          <div style={{ padding: 18, fontSize: 13, color: 'var(--pk-ink-quiet)', lineHeight: 1.5 }}>Aucune absence en cours de saisie. Posez-en une sur la ligne de Thomas B. pour voir les RDV qu’elle laisse sans mécanicien.</div>
        )}

        <div style={{ flex: 1 }} />
        <div style={{ padding: '14px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <button type="button" style={{ minHeight: 52, borderRadius: 'var(--pk-radius-pill)', border: 'none', background: 'var(--pk-accent)', color: '#000', fontSize: 15, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' }}>Enregistrer l’absence</button>
          <button type="button" onClick={() => setAbsent(false)} style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid var(--pk-border-strong)', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>Annuler</button>
        </div>
      </aside>
    </React.Fragment>
  );
}
Object.assign(window, { TeamScreen });
