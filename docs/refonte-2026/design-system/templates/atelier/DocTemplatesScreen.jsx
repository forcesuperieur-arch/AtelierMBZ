/* Administration › Modèles de documents — tour 17. Les clauses à gauche,
   l'aperçu A4 réel à droite, et la publication bloquée par la clause absente. */
const dcOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const dcCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' };
const dcClause = { display: 'flex', alignItems: 'flex-start', gap: 12, padding: '13px 16px', borderBottom: '1px solid var(--pk-border-quiet)' };
const dcTag = { padding: '2px 8px', background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 600, color: 'var(--pk-ink-quiet)' };
const dcPaperRow = { display: 'grid', gridTemplateColumns: '1fr 40px 62px 62px', padding: '6px 0', borderBottom: '1px solid #ececec', fontSize: 10 };
const dcTotal = { display: 'flex', justifyContent: 'space-between', fontSize: 10 };

const DC_CLAUSES = [
  { name: 'Durée de validité', required: true, text: 'Le présent devis est valable 30 jours à compter de sa date d’émission.', locked: true },
  { name: 'Travaux complémentaires', required: true, text: 'Aucun travail non prévu au devis ne sera engagé sans accord préalable du client.', locked: true, raised: true },
  { name: 'Pièces remplacées', text: 'Les pièces déposées sont tenues à disposition du client pendant 15 jours.', on: true },
  { name: 'Immobilisation', text: 'Les délais annoncés sont indicatifs et peuvent varier selon la disponibilité des pièces.', on: true, raised: true },
];

function DcToggle({ on, locked }) {
  return (
    <span style={{ display: 'inline-flex', width: 38, height: 21, padding: 2, background: on ? 'var(--pk-accent)' : 'var(--pk-border)', borderRadius: 'var(--pk-radius-pill)', flexShrink: 0, opacity: locked ? 0.9 : 1 }}>
      <span style={{ width: 17, height: 17, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: 'auto' }} />
    </span>
  );
}

function DocTemplatesScreen({ logo }) {
  const [tab, setTab] = React.useState('Devis');
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0 }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 20, padding: '0 20px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        {['Devis', 'Ordre de réparation', 'État des lieux', 'Facture', 'Restitution'].map((x) => (
          <button type="button" key={x} onClick={() => setTab(x)}
            style={{ padding: '13px 2px', background: 'transparent', border: 'none', borderBottomStyle: 'solid', borderBottomWidth: 2, borderBottomColor: tab === x ? '#000' : 'transparent', fontSize: 14, fontWeight: tab === x ? 600 : 400, color: tab === x ? 'var(--pk-ink)' : 'var(--pk-ink-quiet)', cursor: 'pointer' }}>{x}</button>
        ))}
        <div style={{ flex: 1 }} />
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-warning-ink-soft)', fontWeight: 600 }}><i className="ri-edit-line" style={{ fontSize: 15 }} />Modifications non publiées</span>
      </div>

      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        <div style={{ width: 560, flexShrink: 0, padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14, overflow: 'hidden', borderRight: '1px solid var(--pk-border)' }}>
          <div style={dcCard}>
            <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 15, fontWeight: 600 }}>En-tête et coordonnées</div>
            <div style={{ padding: '14px 16px', display: 'flex', alignItems: 'center', gap: 14 }}>
              <div style={{ width: 84, height: 60, background: '#000', borderRadius: 'var(--pk-radius-tile)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><img src={logo} alt="" style={{ width: 34, height: 34, display: 'block' }} /></div>
              <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Logo de l’atelier</span>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Remplace le logo Paddock sur tous les documents</span>
              </div>
              <button type="button" style={{ padding: '7px 13px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Remplacer</button>
            </div>
            <div style={{ padding: '0 16px 14px', display: 'flex', flexDirection: 'column', gap: 5 }}>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Coordonnées imprimées en pied de page</span>
              <div style={{ minHeight: 62, padding: '10px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 13, lineHeight: 1.5 }}>
                Paddock Atelier Principal · 12 rue de la Gare, 59000 Lille<br />SIRET 812 445 907 00023 · TVA FR32812445907
              </div>
            </div>
          </div>

          <div style={{ ...dcCard, flex: 1, minHeight: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderBottom: '1px solid var(--pk-border)' }}>
              <span style={{ fontSize: 15, fontWeight: 600 }}>Clauses du devis</span>
              <div style={{ flex: 1 }} />
              <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}><i className="ri-add-line" style={{ fontSize: 15 }} />Ajouter</button>
            </div>
            {DC_CLAUSES.map((c) => (
              <div key={c.name} style={{ ...dcClause, background: c.raised ? 'var(--pk-surface-raised)' : 'transparent' }}>
                <i className={c.locked ? 'ri-lock-line' : 'ri-draggable'} style={{ fontSize: 17, color: c.locked ? 'var(--pk-ink-quiet)' : '#a5a5a5', marginTop: 1 }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ fontSize: 13, fontWeight: 600 }}>{c.name}</span>
                    {c.required ? <span style={dcTag}>Obligatoire</span> : null}
                  </div>
                  <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45, marginTop: 3 }}>{c.text}</div>
                </div>
                <DcToggle on={!c.locked} locked={c.locked} />
              </div>
            ))}
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '13px 16px', borderLeft: '3px solid var(--pk-error-line)', background: 'var(--pk-error-surface)' }}>
              <i className="ri-error-warning-fill" style={{ fontSize: 17, color: 'var(--pk-error-ink)', marginTop: 1 }} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-error-ink)' }}>Médiation de la consommation</span>
                  <span style={{ padding: '2px 8px', background: 'var(--pk-error-line)', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700 }}>Manquante</span>
                </div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45, marginTop: 3 }}>Obligatoire sur tout devis à destination d’un particulier. Le nom et l’adresse du médiateur doivent être renseignés.</div>
                <button type="button" style={{ display: 'inline-flex', marginTop: 8, padding: '7px 13px', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}>Renseigner le médiateur</button>
              </div>
            </div>
            <div style={{ flex: 1 }} />
          </div>
        </div>

        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, background: 'var(--pk-border)' }}>
          <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 10, padding: '12px 18px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 13, fontWeight: 600 }}>Aperçu · {tab === 'Devis' ? 'devis DV-2431' : tab === 'Ordre de réparation' ? 'OR 2431' : tab === 'État des lieux' ? 'état des lieux OR 2431' : tab === 'Facture' ? 'facture FA-1231' : 'restitution'}</span>
            <div style={{ flex: 1 }} />
            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-quiet)' }}><i className="ri-file-pdf-line" style={{ fontSize: 15 }} />A4 · rendu réel</span>
            <button type="button" style={{ padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Ouvrir en grand</button>
          </div>

          <div style={{ flex: 1, padding: '20px 24px', display: 'flex', justifyContent: 'center', minHeight: 0, overflow: 'hidden' }}>
            {tab === 'Ordre de réparation' ? <window.WorkOrderPaper logo={logo} /> : null}
            {tab === 'État des lieux' ? <window.ConditionPaper logo={logo} /> : null}
            {tab === 'Facture' ? <window.InvoicePaper logo={logo} /> : null}
            {tab === 'Restitution' ? (
              <div style={{ width: 452, background: 'var(--pk-surface)', border: '1px dashed var(--pk-border-control)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 10, padding: 28, textAlign: 'center' }}>
                <i className="ri-file-text-line" style={{ fontSize: 26, color: 'var(--pk-ink-muted)' }} />
                <div style={{ fontSize: 14, fontWeight: 600 }}>Document de restitution</div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.5 }}>Le prototype décrit ce document — état des lieux de sortie comparé à l’entrée, mention des défauts refusés, signature — mais n’en donne pas le rendu A4. Laissé vide plutôt qu’inventé.</div>
              </div>
            ) : null}
            {tab === 'Devis' ? (
            <div style={{ width: 452, background: '#fff', border: '1px solid #a5a5a5', padding: '26px 28px', display: 'flex', flexDirection: 'column', gap: 14, color: '#1a1a1a' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <div style={{ width: 46, height: 46, background: '#000', borderRadius: 5, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><img src={logo} alt="" style={{ width: 30, height: 30, display: 'block' }} /></div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 700, letterSpacing: '0.12em' }}>PADDOCK</div>
                  <div style={{ fontSize: 9, color: '#5e5e5e', lineHeight: 1.5 }}>Atelier Principal · 12 rue de la Gare, 59000 Lille<br />SIRET 812 445 907 00023 · TVA FR32812445907</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: 15, fontWeight: 700 }}>DEVIS</div>
                  <div style={{ fontSize: 9, color: '#5e5e5e' }}>DV-2431 · 12 août 2026</div>
                </div>
              </div>
              <div style={{ height: 2, background: 'var(--pk-accent)' }} />
              <div style={{ display: 'flex', gap: 20 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 8, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#6f6e6e' }}>Client</div>
                  <div style={{ fontSize: 10, lineHeight: 1.5, marginTop: 2 }}>Nadia Belkacem<br />07 88 21 63 40</div>
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 8, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#6f6e6e' }}>Véhicule</div>
                  <div style={{ fontSize: 10, lineHeight: 1.5, marginTop: 2 }}>Yamaha Tracer 9 · 2022<br />GT-908-ZK · 28 412 km</div>
                </div>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 40px 62px 62px', padding: '5px 0', borderBottom: '1px solid #1a1a1a', fontSize: 8, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#6f6e6e' }}>
                  <span>Désignation</span><span style={{ textAlign: 'right' }}>Qté</span><span style={{ textAlign: 'right' }}>P.U. HT</span><span style={{ textAlign: 'right' }}>Total HT</span>
                </div>
                <div style={dcPaperRow}><span>Main d’œuvre · 2 h 30</span><span style={{ textAlign: 'right' }}>1</span><span style={{ textAlign: 'right' }}>172,50</span><span style={{ textAlign: 'right' }}>172,50</span></div>
                <div style={dcPaperRow}><span>Pièces et consommables</span><span style={{ textAlign: 'right' }}>1</span><span style={{ textAlign: 'right' }}>50,83</span><span style={{ textAlign: 'right' }}>50,83</span></div>
                <div style={{ ...dcPaperRow, borderBottom: 'none', color: '#6f6e6e' }}><span>Plaquettes avant · en attente d’accord, non comprises</span><span style={{ textAlign: 'right' }}>—</span><span style={{ textAlign: 'right' }}>62,42</span><span style={{ textAlign: 'right' }}>0,00</span></div>
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <div style={{ width: 176, display: 'flex', flexDirection: 'column', gap: 3 }}>
                  <div style={dcTotal}><span style={{ color: '#5e5e5e' }}>Total HT</span><span>223,33</span></div>
                  <div style={dcTotal}><span style={{ color: '#5e5e5e' }}>TVA 20 %</span><span>44,67</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', paddingTop: 4, borderTop: '1px solid #1a1a1a' }}><span style={{ fontSize: 11, fontWeight: 700 }}>Total TTC</span><span style={{ fontSize: 14, fontWeight: 700 }}>268,00 €</span></div>
                </div>
              </div>
              <div style={{ padding: '9px 11px', background: '#f6f6f6', borderLeft: '2px solid var(--pk-accent)', display: 'flex', flexDirection: 'column', gap: 3 }}>
                <div style={{ fontSize: 8, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#6f6e6e' }}>Conditions</div>
                <div style={{ fontSize: 8, lineHeight: 1.55, color: '#2f2f2f' }}>Le présent devis est valable 30 jours à compter de sa date d’émission. Aucun travail non prévu au devis ne sera engagé sans accord préalable du client. Les pièces déposées sont tenues à disposition du client pendant 15 jours. Les délais annoncés sont indicatifs et peuvent varier selon la disponibilité des pièces.</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 2, fontSize: 8, fontWeight: 700, color: '#b00219' }}><i className="ri-error-warning-line" style={{ fontSize: 10 }} />Mention de médiation manquante</div>
              </div>
              <div style={{ display: 'flex', gap: 20, marginTop: 2 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 8, color: '#6f6e6e' }}>Bon pour accord, le</div>
                  <div style={{ height: 36, borderBottom: '1px solid #a5a5a5' }} />
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 8, color: '#6f6e6e' }}>Signature du client</div>
                  <div style={{ height: 36, borderBottom: '1px solid #a5a5a5' }} />
                </div>
              </div>
            </div>
            ) : null}
          </div>

          <div style={{ flexShrink: 0, padding: '14px 18px', background: 'var(--pk-surface)', borderTop: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 12 }}>
            <i className="ri-error-warning-fill" style={{ fontSize: 19, color: 'var(--pk-error-ink)' }} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--pk-error-ink)' }}>Publication bloquée</div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Une clause obligatoire est absente. Les devis déjà envoyés gardent la version 4.</div>
            </div>
            <button type="button" style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 18px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Enregistrer en brouillon</button>
            <span style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 20px', background: 'var(--pk-canvas)', color: '#a5a5a5', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600 }}>Publier la version 5</span>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { DocTemplatesScreen });
