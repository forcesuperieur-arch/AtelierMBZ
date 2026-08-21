/* Cockpit SRC (tour 49b, arbitré en 52a : c'est un étage à part). Nav noire,
   ateliers comme entrées, lecture seule — aucune action de production ici.
   « Ouvrir » bascule dans l'app d'atelier avec un bandeau jaune qui dit d'où
   l'on vient et un filtre hérité. */
const scGrid = { display: 'grid', gridTemplateColumns: '220px repeat(4, 1fr) 170px', alignItems: 'center', padding: '15px 20px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const scKpi = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: '16px 18px', display: 'flex', flexDirection: 'column', gap: 4 };
const scNavItem = { display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px', fontSize: 13, background: 'transparent', border: 'none', color: 'inherit', fontFamily: 'inherit', textAlign: 'left', cursor: 'pointer' };
const scOpen = { justifySelf: 'end', minHeight: 40, display: 'flex', alignItems: 'center', gap: 6, padding: '0 14px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap', fontFamily: 'inherit', cursor: 'pointer' };

const SC_SITES = [
  { id: 'lille', name: 'Lille Lomme', sub: '6 ponts · 4 mécaniciens', load: 89, loadTone: 'var(--pk-success-ink)', gap: '+2 %', gapSub: 'au plus près du vendu', gapTone: 'var(--pk-success-ink)', acc: '71 %', acc2: 'médiane 64 %', rep: '42 min', rep2: 'médiane 1 h 10' },
  { id: 'dunkerque', name: 'Dunkerque', sub: '5 ponts · 3 mécaniciens', load: 84, loadTone: 'var(--pk-success-ink)', gap: '+6 %', gapSub: 'médiane réseau', acc: '66 %', acc2: 'médiane 64 %', rep: '58 min', rep2: 'médiane 1 h 10' },
  { id: 'vda', name: 'Villeneuve-d’Ascq', sub: '4 ponts · 3 mécaniciens', load: 80, loadTone: '#000', gap: '+9 %', gapSub: 'forfaits à recalibrer', gapTone: 'var(--pk-warning-ink-soft)', acc: '61 %', acc2: 'médiane 64 %', rep: '1 h 05', rep2: 'médiane 1 h 10' },
  { id: 'amiens', name: 'Amiens', sub: '4 ponts · 2 mécaniciens', load: 76, loadTone: '#000', gap: '+5 %', gapSub: 'médiane réseau', acc: '68 %', acc2: 'médiane 64 %', rep: '1 h 20', rep2: 'au-dessus de la médiane', repTone: 'var(--pk-warning-ink-soft)' },
  { id: 'rouen', name: 'Rouen', sub: '5 ponts · 2 mécaniciens · 1 arrêt', load: 58, loadTone: 'var(--pk-error-ink)', bad: true, gap: '+4 %', gapSub: 'médiane réseau', acc: '52 %', acc2: '12 pts sous la médiane', accTone: 'var(--pk-error-ink)', rep: '3 h 40', rep2: 'accords qui traînent', repTone: 'var(--pk-error-ink)' },
];

function CockpitScreen({ logo, onOpenSite }) {
  return (
    <div style={{ width: 1440, height: 900, display: 'flex', background: 'var(--pk-canvas)', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' }}>
      <nav style={{ width: 224, flexShrink: 0, background: '#141414', color: '#f6f6f6', display: 'flex', flexDirection: 'column', padding: '12px 0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 12px 16px', padding: '8px 10px', background: '#000', borderRadius: 8 }}>
          <img src={logo} alt="Paddock" style={{ width: 32, height: 32, display: 'block', flex: 'none' }} />
          <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#fff', letterSpacing: '0.14em' }}>PADDOCK</span>
            <span style={{ fontSize: 11, color: '#d4d4d4' }}>Cockpit réseau</span>
          </div>
        </div>
        <div style={{ padding: '0 20px 6px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#6f6e6e' }}>Réseau</div>
        <div style={{ ...scNavItem, background: 'var(--pk-accent)', color: '#000', borderRadius: 8, fontWeight: 600 }}><i className="ri-radar-line" style={{ fontSize: 17 }} />Vue d’ensemble</div>
        <div style={{ padding: '14px 20px 6px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#6f6e6e' }}>Ateliers</div>
        {SC_SITES.map((s) => (
          <button type="button" key={s.id} onClick={() => onOpenSite && onOpenSite(s.name)} style={scNavItem}><i className="ri-store-2-line" style={{ fontSize: 17, color: '#a5a5a5' }} />{s.name}</button>
        ))}
        <div style={{ flex: 1 }} />
        <div style={{ margin: '0 12px', padding: 12, border: '1px solid #2f2f2f', fontSize: 12, lineHeight: 1.45, color: '#a5a5a5' }}>Lecture seule. Aucune action de production depuis le cockpit : on entre dans l’atelier pour agir.</div>
      </nav>

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 12, padding: '14px 24px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Vue d’ensemble</span>
          <span style={{ display: 'flex', alignItems: 'center', gap: 7, minHeight: 34, padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid #6f6e6e', borderRadius: 6, fontSize: 13, whiteSpace: 'nowrap' }}>Août 2026<i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} /></span>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>comparé à juillet · médiane réseau en repère</span>
          <div style={{ flex: 1 }} />
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}><i className="ri-download-2-line" style={{ fontSize: 15 }} />Export CSV du tableau</span>
        </div>

        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 18, padding: '22px 24px', minHeight: 0, overflow: 'hidden' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Cinq ateliers, un mois</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>Rouen décroche sur deux indicateurs liés : la charge des ponts et le délai de réponse aux travaux complémentaires. Les deux se règlent au même endroit.</div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
            <div style={scKpi}><span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-muted)' }}>Charge des ponts</span><span style={{ fontSize: 30, fontWeight: 700, lineHeight: 1 }}>81 %</span><span style={{ fontSize: 12, color: 'var(--pk-success-ink)', fontWeight: 600 }}>+3 pts sur juillet</span></div>
            <div style={scKpi}><span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-muted)' }}>Écart vendu / pointé</span><span style={{ fontSize: 30, fontWeight: 700, lineHeight: 1 }}>+6 %</span><span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>par prestation, jamais par personne</span></div>
            <div style={scKpi}><span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-muted)' }}>Devis acceptés</span><span style={{ fontSize: 30, fontWeight: 700, lineHeight: 1 }}>64 %</span><span style={{ fontSize: 12, color: 'var(--pk-error-ink)', fontWeight: 600 }}>−2 pts sur juillet</span></div>
            <div style={scKpi}><span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-ink-muted)' }}>Immobilisation moyenne</span><span style={{ fontSize: 30, fontWeight: 700, lineHeight: 1 }}>1,4 j</span><span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>en heures ouvrées</span></div>
          </div>

          <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', minHeight: 0 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '220px repeat(4, 1fr) 170px', padding: '10px 20px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
              <span>Atelier</span><span>Charge ponts</span><span>Écart vendu / pointé</span><span>Devis acceptés</span><span>Réponse travaux compl.</span><span />
            </div>
            {SC_SITES.map((s, i) => (
              <div key={s.id} style={{ ...scGrid, background: i % 2 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: s.bad ? '3px solid var(--pk-error-line)' : 'none', borderBottom: s.bad ? 'none' : '1px solid var(--pk-border-quiet)' }}>
                <div><div style={{ fontWeight: 600 }}>{s.name}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{s.sub}</div></div>
                <div><div style={{ fontSize: 17, fontWeight: 700, color: s.bad ? 'var(--pk-error-ink)' : 'inherit' }}>{s.load} %</div><div style={{ height: 6, background: 'var(--pk-canvas)', marginTop: 5 }}><div style={{ width: s.load + '%', height: '100%', background: s.loadTone }} /></div></div>
                <div><div style={{ fontSize: 17, fontWeight: 700 }}>{s.gap}</div><div style={{ fontSize: 12, color: s.gapTone || 'var(--pk-ink-muted)' }}>{s.gapSub}</div></div>
                <div><div style={{ fontSize: 17, fontWeight: 700, color: s.accTone || 'inherit' }}>{s.acc}</div><div style={{ fontSize: 12, color: s.accTone || 'var(--pk-ink-muted)' }}>{s.acc2}</div></div>
                <div><div style={{ fontSize: 17, fontWeight: 700, color: s.repTone || 'inherit' }}>{s.rep}</div><div style={{ fontSize: 12, color: s.repTone || 'var(--pk-ink-muted)' }}>{s.rep2}</div></div>
                <button type="button" onClick={() => onOpenSite && onOpenSite(s.name)}
                  style={{ ...scOpen, background: s.bad ? 'var(--pk-accent)' : 'transparent', border: s.bad ? 'none' : '1px solid #000', color: '#000' }}>Ouvrir<i className="ri-arrow-right-up-line" style={{ fontSize: 15 }} /></button>
              </div>
            ))}
            <div style={{ flex: 1 }} />
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '16px 20px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)' }}>
              <i className="ri-lightbulb-line" style={{ fontSize: 18, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
              <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', maxWidth: 1000 }}>Rouen : un mécanicien en arrêt depuis le 4 août, et des accords clients qui mettent 3 h 40. Les deux se tiennent — moins de bras, donc moins de temps pour relancer. Ouvrir l’atelier mène sur son Stat d’août, filtré sur les travaux complémentaires.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* L'atelier ouvert depuis le cockpit : même app, bandeau jaune de provenance. */
function CockpitSiteView({ logo, site, onBack }) {
  const nav = [
    ['Pilotage', [['ri-bar-chart-2-line', 'Stat', true]]],
    ['Atelier', [['ri-calendar-line', 'Prise de RDV'], ['ri-calendar-2-line', 'Planning'], ['ri-inbox-line', 'Réception'], ['ri-hourglass-line', 'En atelier'], ['ri-tools-line', 'Ponts & Méca'], ['ri-hammer-line', 'Travaux compl.', false, 4]]],
    ['Commerce', [['ri-draft-line', 'Devis'], ['ri-group-line', 'Clients'], ['ri-motorbike-line', 'VO']]],
  ];
  return (
    <div style={{ width: 1440, height: 900, display: 'flex', flexDirection: 'column', background: 'var(--pk-canvas)', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 12, padding: '10px 24px', background: 'var(--pk-accent)', color: '#000' }}>
        <i className="ri-arrow-left-line" style={{ fontSize: 18 }} />
        <span style={{ fontSize: 13, fontWeight: 600 }}>Vous êtes dans l’atelier de {site} · ouvert depuis le cockpit</span>
        <div style={{ flex: 1 }} />
        <button type="button" onClick={onBack} style={{ minHeight: 32, display: 'flex', alignItems: 'center', padding: '0 13px', background: '#000', color: 'var(--pk-accent)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700, border: 'none', fontFamily: 'inherit', cursor: 'pointer', whiteSpace: 'nowrap' }}>Revenir au cockpit</button>
      </div>
      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <nav style={{ width: 224, flexShrink: 0, background: 'var(--pk-surface-raised)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', padding: '12px 0' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 12px 16px', padding: '8px 10px', background: '#000', borderRadius: 8 }}>
            <img src={logo} alt="Paddock" style={{ width: 32, height: 32, display: 'block', flex: 'none' }} />
            <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
              <span style={{ fontSize: 12, fontWeight: 700, color: '#fff', letterSpacing: '0.14em' }}>PADDOCK</span>
              <span style={{ fontSize: 11, color: '#d4d4d4' }}>{site}</span>
            </div>
          </div>
          {nav.map(([group, items]) => (
            <React.Fragment key={group}>
              <div style={{ padding: group === 'Pilotage' ? '0 20px 6px' : '14px 20px 6px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{group}</div>
              {items.map(([icon, label, active, badge]) => (
                <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px', fontSize: 13, background: active ? 'var(--pk-accent)' : 'transparent', color: active ? '#000' : 'inherit', borderRadius: active ? 8 : 0, fontWeight: active ? 600 : 400 }}>
                  <i className={icon} style={{ fontSize: 17, color: active ? 'inherit' : 'var(--pk-ink-quiet)' }} />{label}
                  {badge ? <span style={{ marginLeft: 'auto', minWidth: 20, height: 20, padding: '0 6px', background: 'var(--pk-error-line)', color: '#fff', fontSize: 11, fontWeight: 700, borderRadius: 'var(--pk-radius-pill)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{badge}</span> : null}
                </div>
              ))}
            </React.Fragment>
          ))}
          <div style={{ flex: 1 }} />
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px', fontSize: 13 }}><i className="ri-settings-3-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Administration</div>
        </nav>
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
          <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 10, padding: '14px 24px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Stat · {site} · août</span>
            <span style={{ padding: '3px 9px', background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-quiet)', whiteSpace: 'nowrap' }}>filtré : travaux complémentaires</span>
            <div style={{ flex: 1 }} />
            <i className="ri-contrast-2-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>s.ruiz@motoblouz.com · SRC</span>
          </div>
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-page)' }}>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10, color: 'var(--pk-ink-quiet)', textAlign: 'center', maxWidth: 460 }}>
              <i className="ri-bar-chart-2-line" style={{ fontSize: 40, color: 'var(--pk-ink-muted)' }} />
              <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--pk-ink)' }}>Le Stat de {site}, tel que le voit l’atelier</div>
              <div style={{ fontSize: 13, lineHeight: 1.5 }}>Même écran, mêmes droits de lecture. Le SRC arrive avec le filtre hérité du cockpit — ici les travaux complémentaires d’août — et le bandeau jaune reste tant qu’il est « chez » {site}.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function CockpitApp({ logo }) {
  const [site, setSite] = React.useState(null);
  return site
    ? <CockpitSiteView logo={logo} site={site} onBack={() => setSite(null)} />
    : <CockpitScreen logo={logo} onOpenSite={setSite} />;
}
Object.assign(window, { CockpitScreen, CockpitSiteView, CockpitApp });
