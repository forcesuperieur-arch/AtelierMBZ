/* Stat › Période et Analyse — tour 33. Deux onglets manquants : la période
   comparée à l'an dernier, et l'écart temps vendu / temps passé. */
const stOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const stKpi = { flex: 1, padding: '15px 20px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 4 };
const stCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' };
const stTab = { padding: '13px 2px', fontSize: 14, background: 'transparent', border: 'none', cursor: 'pointer' };
const AN_GRID = '1fr 90px 90px 130px 96px';

const ST_KPIS = [
  { label: 'Motos passées', value: '64', delta: '+ 9 · 55 en août 2025', up: true },
  { label: 'Heures facturées', value: '118 h', delta: '+ 6 % · 111 h', up: true },
  { label: 'Panier moyen', value: '241 €', delta: '− 12 € · 253 €' },
  { label: 'Taux d’occupation', value: '79 %', delta: '+ 4 pts · 75 %', up: true },
  { label: 'Devis acceptés', value: '68 %', delta: '− 5 pts · 73 %', last: true },
];

const ST_DAYS = [
  { d: 'lun', a: 72, b: 64 }, { d: 'mar', a: 86, b: 70 }, { d: 'mer', a: 58, b: 76 },
  { d: 'jeu', a: 92, b: 81 }, { d: 'ven', a: 96, b: 88 }, { d: 'sam', a: 44, b: 41 },
];

const ST_MIX = [
  { label: 'Révisions', pct: 52, n: '33' }, { label: 'Pneus', pct: 25, n: '16' },
  { label: 'Freinage', pct: 14, n: '9' }, { label: 'Diagnostics', pct: 9, n: '6' },
];

const AN_ROWS = [
  { name: 'Diagnostic électrique', sub: 'Forfait à revoir', sold: '1 h 00', spent: '1 h 38', gap: '+ 63 %', level: 'bad', n: '14' },
  { name: 'Kit chaîne', sub: 'Variable selon le modèle', sold: '1 h 10', spent: '1 h 26', gap: '+ 23 %', level: 'warn', n: '22' },
  { name: 'Révision 20 000 km', sold: '1 h 40', spent: '1 h 44', gap: '+ 4 %', n: '61' },
  { name: 'Plaquettes avant', sold: '0 h 30', spent: '0 h 31', gap: '+ 3 %', n: '48' },
  { name: 'Révision 10 000 km', sold: '1 h 20', spent: '1 h 15', gap: '− 6 %', n: '57' },
  { name: 'Pneu arrière + équilibrage', sub: 'Marge à reprendre', sold: '1 h 00', spent: '0 h 42', gap: '− 30 %', level: 'good', n: '39' },
  { name: 'Vidange simple', sold: '0 h 40', spent: '0 h 38', gap: '− 5 %', n: '77' },
];

function StatPeriodScreen({ tab, onTab }) {
  const t = tab || 'Période';
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0, background: 'var(--pk-canvas)' }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        {['Atelier', 'Période', 'Analyse', 'Explorer'].map((x) => (
          <button type="button" key={x} onClick={() => onTab && onTab(x)}
            style={{ ...stTab, borderBottom: t === x ? '2px solid #000' : '2px solid transparent', fontWeight: t === x ? 600 : 400, color: t === x ? 'var(--pk-ink)' : 'var(--pk-ink-quiet)' }}>{x}</button>
        ))}
        <div style={{ flex: 1 }} />
        {t === 'Période' ? (
          <>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 13 }}><i className="ri-calendar-line" style={{ fontSize: 15, color: 'var(--pk-ink-quiet)' }} />1 – 15 août 2026<i className="ri-arrow-down-s-line" style={{ fontSize: 15, color: 'var(--pk-ink-muted)' }} /></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-card)', fontSize: 13, fontWeight: 600 }}>comparé à août 2025<i className="ri-arrow-down-s-line" style={{ fontSize: 15 }} /></div>
          </>
        ) : (
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 13 }}><i className="ri-calendar-line" style={{ fontSize: 15, color: 'var(--pk-ink-quiet)' }} />6 derniers mois<i className="ri-arrow-down-s-line" style={{ fontSize: 15, color: 'var(--pk-ink-muted)' }} /></div>
        )}
      </div>

      {t === 'Analyse' ? (
        <>
          <div style={{ flexShrink: 0, padding: '18px 22px 14px', display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 20 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
              <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.015em' }}>Temps vendu contre temps passé</div>
              <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>318 interventions · les forfaits hors de la marge de 10 % sont signalés</div>
            </div>
            <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 7, minHeight: 38, padding: '0 14px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}><i className="ri-download-2-line" style={{ fontSize: 16 }} />Exporter</button>
          </div>
          <div style={{ flex: 1, padding: '0 22px 18px', display: 'flex', gap: 14, minHeight: 0 }}>
            <div style={{ ...stCard, flex: 1, minWidth: 0 }}>
              <div style={{ display: 'grid', gridTemplateColumns: AN_GRID, alignItems: 'center', gap: 12, padding: '8px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
                <span>Prestation</span><span style={{ textAlign: 'right' }}>Vendu</span><span style={{ textAlign: 'right' }}>Passé</span><span style={{ textAlign: 'right' }}>Écart moyen</span><span style={{ textAlign: 'right' }}>Passages</span>
              </div>
              {AN_ROWS.map((r, i) => (
                <div key={r.name} style={{ display: 'grid', gridTemplateColumns: AN_GRID, alignItems: 'center', gap: 12, padding: '10px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13, background: r.level === 'bad' ? 'var(--pk-error-surface)' : i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: r.level === 'bad' ? '3px solid var(--pk-error-line)' : r.level === 'warn' ? '3px solid var(--pk-warning-line)' : r.level === 'good' ? '3px solid var(--pk-success-line)' : 'none' }}>
                  <div><div style={{ fontWeight: 600 }}>{r.name}</div>{r.sub ? <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{r.sub}</div> : null}</div>
                  <span style={{ textAlign: 'right' }}>{r.sold}</span>
                  <span style={{ textAlign: 'right', fontWeight: 600 }}>{r.spent}</span>
                  <span style={{ textAlign: 'right', fontWeight: r.level ? 700 : 600, color: r.level === 'bad' ? 'var(--pk-error-ink)' : r.level === 'warn' ? 'var(--pk-warning-ink-soft)' : 'var(--pk-success-ink)' }}>{r.gap}</span>
                  <span style={{ textAlign: 'right', color: 'var(--pk-ink-quiet)' }}>{r.n}</span>
                </div>
              ))}
              <div style={{ flex: 1 }} />
              <div style={{ padding: '11px 16px', borderTop: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
                <i className="ri-shield-user-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Aucun temps n’est attribué nominativement dans cet écran.</span>
              </div>
            </div>
            <aside style={{ ...stCard, width: 320, flexShrink: 0 }}>
              <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 14, fontWeight: 600 }}>Deux décisions à prendre</div>
              <div style={{ padding: '13px 16px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-error-ink)' }}>Diagnostic électrique sous-facturé</div>
                <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>38 min de perte moyenne sur 14 passages, soit environ 640 € de main d’œuvre non facturée sur six mois.</div>
                <button type="button" style={{ minHeight: 40, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Porter le forfait à 1 h 30</button>
              </div>
              <div style={{ padding: '13px 16px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-success-ink)' }}>Pneu arrière trop généreux</div>
                <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>18 min d’avance moyenne sur 39 passages. Descendre à 0 h 45 libère un créneau par semaine sans rien changer au geste.</div>
                <button type="button" style={{ minHeight: 40, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Ramener à 0 h 45</button>
              </div>
              <div style={{ flex: 1 }} />
              <div style={{ padding: '13px 16px', borderTop: '1px solid var(--pk-border)', display: 'flex', gap: 9 }}>
                <i className="ri-information-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Modifier un forfait ne touche ni les devis en attente ni les factures émises.</span>
              </div>
            </aside>
          </div>
        </>
      ) : (
        <>
          <div style={{ flexShrink: 0, display: 'flex', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
            {ST_KPIS.map((k) => (
              <div key={k.label} style={{ ...stKpi, borderRight: k.last ? 'none' : '1px solid var(--pk-border-quiet)' }}>
                <span style={stOverline}>{k.label}</span>
                <span style={{ fontSize: 28, fontWeight: 700, lineHeight: 1 }}>{k.value}</span>
                <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, fontWeight: 600, color: k.up ? 'var(--pk-success-ink)' : 'var(--pk-warning-ink-soft)' }}>
                  <i className={k.up ? 'ri-arrow-up-line' : 'ri-arrow-down-line'} style={{ fontSize: 14 }} />{k.delta}
                </span>
              </div>
            ))}
          </div>

          <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div style={stCard}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
                <span style={{ fontSize: 14, fontWeight: 600 }}>Charge par jour</span>
                <div style={{ flex: 1 }} />
                <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-quiet)' }}>
                  <span style={{ width: 10, height: 10, background: 'var(--pk-accent)' }} />2026
                  <span style={{ width: 10, height: 10, background: 'var(--pk-border)', marginLeft: 8 }} />2025
                </span>
              </div>
              <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, padding: 16, height: 168 }}>
                {ST_DAYS.map((d) => (
                  <div key={d.d} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, height: '100%' }}>
                    <div style={{ flex: 1, width: '100%', display: 'flex', alignItems: 'flex-end', gap: 3 }}>
                      <div style={{ flex: 1, height: d.a + '%', background: 'var(--pk-accent)' }} />
                      <div style={{ flex: 1, height: d.b + '%', background: 'var(--pk-border)' }} />
                    </div>
                    <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{d.d}</span>
                  </div>
                ))}
              </div>
              <div style={{ padding: '11px 16px', borderTop: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'center', gap: 9 }}>
                <i className="ri-information-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />
                <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Le mercredi est passé sous 2025 : la fermeture à 17 h coûte environ 1 h 30 de charge par semaine.</span>
              </div>
            </div>

            <div style={{ display: 'flex', gap: 14 }}>
              <div style={{ ...stCard, flex: 1 }}>
                <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 14, fontWeight: 600 }}>Répartition des passages</div>
                {ST_MIX.map((m, i) => (
                  <div key={m.label} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 16px', borderBottom: i === ST_MIX.length - 1 ? 'none' : '1px solid var(--pk-border-quiet)', fontSize: 13 }}>
                    <span style={{ width: 150 }}>{m.label}</span>
                    <div style={{ flex: 1, height: 10, background: 'var(--pk-canvas)' }}><div style={{ width: m.pct + '%', height: '100%', background: '#000' }} /></div>
                    <span style={{ width: 46, textAlign: 'right', fontWeight: 600 }}>{m.n}</span>
                  </div>
                ))}
              </div>
              <div style={{ ...stCard, width: 340, flexShrink: 0 }}>
                <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 14, fontWeight: 600 }}>Ce qui a bougé</div>
                <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', gap: 10 }}>
                  <i className="ri-arrow-down-line" style={{ fontSize: 17, color: 'var(--pk-warning-ink-soft)', flexShrink: 0 }} />
                  <div style={{ fontSize: 13, lineHeight: 1.5 }}><strong style={{ fontWeight: 600 }}>Panier moyen en baisse.</strong> Plus de passages, mais davantage de petites interventions : les révisions passent de 61 % à 52 % du volume.</div>
                </div>
                <div style={{ padding: '12px 16px', display: 'flex', gap: 10 }}>
                  <i className="ri-arrow-down-line" style={{ fontSize: 17, color: 'var(--pk-warning-ink-soft)', flexShrink: 0 }} />
                  <div style={{ fontSize: 13, lineHeight: 1.5 }}><strong style={{ fontWeight: 600 }}>Devis moins acceptés.</strong> Le délai moyen de réponse est passé de 2,1 à 3,4 jours. Les relances partent plus tard qu’avant.</div>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
Object.assign(window, { StatPeriodScreen });
