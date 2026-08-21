/* Concepteur de modèle (tour 48a) — ouvert depuis la bibliothèque des modèles.
   Les blocs se réordonnent à gauche, la page se recompose à droite, remplie avec
   un vrai OR. Un champ inconnu ne casse pas la page : il s'affiche en creux avec
   son nom, et le bandeau compte les manques. */
const tdBlock = { display: 'flex', alignItems: 'center', gap: 12, padding: '13px 18px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13, background: 'transparent', border: 'none', width: '100%', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' };
const tdChip = { display: 'flex', alignItems: 'center', gap: 6, minHeight: 32, padding: '0 11px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap', border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const tdChipOff = { display: 'flex', alignItems: 'center', gap: 6, minHeight: 32, padding: '0 11px', border: '1px dashed #6f6e6e', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, color: 'var(--pk-ink-quiet)', whiteSpace: 'nowrap', background: 'transparent', fontFamily: 'inherit', cursor: 'pointer' };
const tdHead = { fontSize: 9, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const tdHole = { display: 'inline-block', padding: '1px 5px', background: 'var(--pk-canvas)', color: 'var(--pk-ink-muted)', fontStyle: 'italic' };
const tdRow = { display: 'grid', gap: 8, padding: '7px 0', borderBottom: '1px solid var(--pk-border-quiet)' };

function TemplateDesignerScreen({ logoStacked }) {
  const [cols, setCols] = React.useState(['Désignation', 'Temps', 'Qté', 'PU HT']);
  const extra = ['Remise', 'Référence pièce'];
  const grid = 'minmax(0,1fr) ' + cols.slice(1).map(() => '54px').join(' ');
  const tight = cols.length > 5;
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 10, padding: '14px 24px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Modèles de documents</span>
        <i className="ri-arrow-right-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Ordre de réparation</span>
        <span style={{ padding: '3px 9px', background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-quiet)', whiteSpace: 'nowrap' }}>Brouillon v5</span>
        <div style={{ flex: 1 }} />
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}><i className="ri-save-line" style={{ fontSize: 15 }} />Enregistré il y a 40 s</span>
      </div>

      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 14, padding: '12px 24px', background: '#fff5d9', borderBottom: '1px solid var(--pk-accent)' }}>
        <i className="ri-error-warning-line" style={{ fontSize: 20, color: 'var(--pk-warning-ink-soft)' }} />
        <div style={{ flex: 1, fontSize: 13, lineHeight: 1.45, color: '#4a3000' }}>Deux champs de ce brouillon n’existent pas dans les données : <strong style={{ fontWeight: 600 }}>moto.couleur</strong> et <strong style={{ fontWeight: 600 }}>client.tva_intra</strong>. Ils sortiront vides sur les 340 OR à venir. La publication reste possible, l’aperçu les montre en creux.</div>
        <span style={{ minHeight: 36, display: 'flex', alignItems: 'center', padding: '0 13px', border: '1px solid var(--pk-warning-ink-soft)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, color: '#4a3000', whiteSpace: 'nowrap' }}>Voir les deux champs</span>
      </div>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ width: 400, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column' }}>
          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ fontSize: 14, fontWeight: 600 }}>Blocs de la page</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '6px 11px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-add-line" style={{ fontSize: 15 }} />Ajouter</span>
          </div>
          <div style={tdBlock}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>En-tête atelier</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Logo, raison sociale, SIRET, TVA</div></div>
            <i className="ri-eye-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />
          </div>
          <div style={tdBlock}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>Client et véhicule</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>4 champs · 1 champ inconnu</div></div>
            <i className="ri-error-warning-line" style={{ fontSize: 16, color: 'var(--pk-warning-ink-soft)' }} />
          </div>
          <div style={{ ...tdBlock, background: '#fff5d9', borderLeft: '3px solid var(--pk-accent)' }}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-warning-ink-soft)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>Lignes de prestation</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Tableau · en cours d’édition</div></div>
            <i className="ri-pencil-line" style={{ fontSize: 16, color: 'var(--pk-warning-ink-soft)' }} />
          </div>
          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-surface-raised)', display: 'flex', flexDirection: 'column', gap: 11 }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' }}>Colonnes du tableau</span>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7 }}>
              {cols.map((c) => (
                <button type="button" key={c} onClick={() => cols.length > 1 && setCols(cols.filter((x) => x !== c))} style={tdChip}>{c}<i className="ri-close-line" style={{ fontSize: 14 }} /></button>
              ))}
              {extra.filter((e) => !cols.includes(e)).map((e) => (
                <button type="button" key={e} onClick={() => setCols(cols.concat([e]))} style={tdChipOff}><i className="ri-add-line" style={{ fontSize: 14 }} />{e}</button>
              ))}
            </div>
            <div style={{ fontSize: 12, lineHeight: 1.45, color: tight ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-quiet)', fontWeight: tight ? 600 : 400 }}>{tight ? 'Six colonnes : le tableau passera en corps 8 pt à l’impression.' : 'Au-delà de cinq colonnes, le tableau passe en corps 8 pt à l’impression. L’aperçu le signale avant la publication.'}</div>
          </div>
          <div style={tdBlock}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>Totaux et acompte</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>HT, TVA 20 %, TTC, acompte 30 %</div></div>
            <i className="ri-eye-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />
          </div>
          <div style={tdBlock}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>Clauses légales</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Bibliothèque · 3 clauses liées</div></div>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>v4</span>
          </div>
          <div style={tdBlock}>
            <i className="ri-draggable" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            <div style={{ flex: 1 }}><div style={{ fontWeight: 600 }}>Signature client</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Cadre de signature + mention manuscrite</div></div>
            <i className="ri-eye-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '16px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', gap: 8 }}>
            <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 16px', background: 'var(--pk-accent)', color: '#000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}>Publier la version 5</span>
            <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', padding: '0 14px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}>Comparer à la v4</span>
          </div>
        </div>

        <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', background: 'var(--pk-canvas)' }}>
          <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 12, padding: '12px 24px', background: 'var(--pk-surface-raised)', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 13, fontWeight: 600 }}>Aperçu</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 7, minHeight: 34, padding: '0 12px', background: 'var(--pk-surface)', border: '1px solid #6f6e6e', borderRadius: 6, fontSize: 13, whiteSpace: 'nowrap' }}>OR 2431 · MT-07 Belkacem<i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} /></span>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>rempli avec un dossier réel</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}><i className="ri-file-list-2-line" style={{ fontSize: 15 }} />1 page A4 · tient en une feuille</span>
          </div>
          <div style={{ flex: 1, overflow: 'hidden', display: 'flex', justifyContent: 'center', padding: 24 }}>
            <div style={{ width: 560, background: 'var(--pk-surface)', border: '1px solid #b9b9b9', padding: '32px 36px', display: 'flex', flexDirection: 'column', gap: 18, fontSize: tight ? 10 : 11, color: 'var(--pk-ink)' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 16, paddingBottom: 14, borderBottom: '2px solid #000' }}>
                <img src={logoStacked} alt="Paddock" style={{ width: 64, display: 'block', flex: 'none' }} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 15, fontWeight: 700 }}>Motoblouz Atelier — Dunkerque</div>
                  <div style={{ color: 'var(--pk-ink-quiet)', lineHeight: 1.5, marginTop: 3 }}>14 rue de la Fonderie, 59140 Dunkerque · 03 28 00 00 00<br />SIRET 812 445 990 00021 · TVA FR 40 812445990</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: 14, fontWeight: 700 }}>OR 2431</div>
                  <div style={{ color: 'var(--pk-ink-quiet)' }}>19 août 2026</div>
                </div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                <div>
                  <div style={tdHead}>Client</div>
                  <div style={{ lineHeight: 1.55, marginTop: 4 }}>Nadia Belkacem<br />7 rue Jean Bart, 59140 Dunkerque<br />06 12 34 56 78<br /><span style={tdHole}>client.tva_intra</span></div>
                </div>
                <div>
                  <div style={tdHead}>Véhicule</div>
                  <div style={{ lineHeight: 1.55, marginTop: 4 }}>Yamaha MT-07 · EF-771-GH<br />VIN JYARM33E0KA000123<br />19 842 km<br /><span style={tdHole}>moto.couleur</span></div>
                </div>
              </div>
              <div>
                <div style={{ display: 'grid', gridTemplateColumns: grid, gap: 8, paddingBottom: 6, borderBottom: '1px solid #000', ...tdHead }}>
                  {cols.map((c, i) => <span key={c} style={{ textAlign: i === cols.length - 1 ? 'right' : 'left' }}>{c}</span>)}
                </div>
                {[['Remplacement plaquettes avant', '0 h 30', '1', '37,50'], ['Plaquettes Brembo 07BB19', '—', '1', '42,00'], ['Pneu arrière Michelin Road 6', '0 h 45', '1', '168,00'], ['Contrôle et réglage chaîne', '0 h 15', '1', '18,75']].map((r, ri) => (
                  <div key={r[0]} style={{ ...tdRow, gridTemplateColumns: grid, borderBottom: ri === 3 ? 'none' : '1px solid var(--pk-border-quiet)' }}>
                    {cols.map((c, i) => <span key={c} style={{ textAlign: i === cols.length - 1 ? 'right' : 'left', color: i > 3 ? 'var(--pk-ink-muted)' : 'inherit' }}>{i < 4 ? r[i] : i === 4 ? '—' : 'REF-' + (7100 + ri)}</span>)}
                  </div>
                ))}
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <div style={{ width: 210, display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>Total HT</span><span>266,25 €</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--pk-ink-quiet)' }}>TVA 20 %</span><span>53,25 €</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 5, borderTop: '1px solid #000', fontSize: 13, fontWeight: 700 }}><span>Total TTC</span><span>319,50 €</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--pk-ink-quiet)' }}><span>Acompte 30 %</span><span>95,85 €</span></div>
                </div>
              </div>
              <div style={{ padding: '10px 12px', background: 'var(--pk-page)', border: '1px solid var(--pk-border-quiet)', lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Les travaux non prévus au présent ordre feront l’objet d’un accord préalable du client. Le véhicule est assuré par son propriétaire pendant son séjour à l’atelier. Délai de conservation des pièces remplacées : 15 jours.</div>
              <div style={{ display: 'flex', gap: 20, alignItems: 'flex-end' }}>
                <div style={{ flex: 1 }}>
                  <div style={tdHead}>Signature du client</div>
                  <div style={{ marginTop: 4, color: 'var(--pk-ink-quiet)', lineHeight: 1.5 }}>Précédée de la mention « bon pour accord »</div>
                  <div style={{ height: 46, borderBottom: '1px solid #000' }} />
                </div>
                <div style={{ width: 150 }}>
                  <div style={tdHead}>Atelier</div>
                  <div style={{ marginTop: 4, color: 'var(--pk-ink-quiet)', lineHeight: 1.5 }}>Léa Marchand</div>
                  <div style={{ height: 46, borderBottom: '1px solid #000' }} />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { TemplateDesignerScreen });
