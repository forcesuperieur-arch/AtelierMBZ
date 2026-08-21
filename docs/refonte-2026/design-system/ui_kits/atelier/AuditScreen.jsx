/* Administration › Journal d'audit — tour 26. Lecture seule, les actions
   sensibles en rouge, et le détail avant / après de la ligne choisie. */
const AD_GRID = '126px 24px 1fr 190px 130px';
const adOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const adRow = { display: 'grid', gridTemplateColumns: AD_GRID, alignItems: 'center', gap: 12, padding: '9px 14px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13, cursor: 'pointer' };
const adCtx = { display: 'flex', alignItems: 'center', gap: 9, fontSize: 13 };

const AD_ROWS = [
  { when: 'Aujourd’hui 14:52', icon: 'ri-percent-line', title: 'Remise de 12 % appliquée', sub: 'Motif saisi : « client fidèle, geste commercial »', object: 'Devis DV-2418', by: 'Julie D.', sensitive: true, selected: true },
  { when: 'Aujourd’hui 14:18', icon: 'ri-check-line', title: 'Intervention clôturée', sub: 'Temps pointé 1 h 35 pour 1 h 40 vendu', object: 'OR 2431', by: 'Karim M.' },
  { when: 'Aujourd’hui 11:04', icon: 'ri-hammer-line', title: 'Travail supplémentaire proposé au client', sub: 'Plaquettes avant · 74,90 € · SMS envoyé', object: 'OR 2431', by: 'Karim M.' },
  { when: 'Aujourd’hui 09:41', icon: 'ri-delete-bin-line', title: 'Rendez-vous supprimé', sub: 'Doublon de saisie · aucun SMS envoyé au client', object: 'RDV du 22 août', by: 'Julie D.', sensitive: true },
  { when: 'Aujourd’hui 08:34', icon: 'ri-inbox-line', title: 'Moto réceptionnée · état des lieux signé', sub: '28 412 km · 1 dégât relevé · 4 photos', object: 'OR 2431', by: 'Julie D.' },
  { when: 'Hier 17:22', icon: 'ri-download-2-line', title: 'Export de la base clients', sub: '1 842 fiches · CSV · motif : sauvegarde mensuelle', object: 'Clients', by: 'Pascal M.', sensitive: true },
  { when: 'Hier 16:40', icon: 'ri-bank-card-line', title: 'Facture encaissée par carte', sub: '412,50 € · restitution du même jour', object: 'FA-1231', by: 'Julie D.' },
  { when: 'Hier 10:05', icon: 'ri-user-settings-line', title: 'Droits modifiés', sub: 'Adrien L. passé d’Apprenti à Mécanicien', object: 'Adrien Lambert', by: 'Pascal M.', sensitive: true },
  { when: '13 août 15:12', icon: 'ri-time-line', title: 'Fermeture exceptionnelle ajoutée', sub: 'Du 10 au 24 août · congés d’été · aucun RDV concerné', object: 'Horaires', by: 'Julie D.' },
  { when: '12 août 09:30', icon: 'ri-draft-line', title: 'Devis accepté par le client en ligne', sub: 'Signature électronique · IP enregistrée', object: 'DV-2431', by: 'Client' },
  { when: '11 août 08:02', icon: 'ri-login-circle-line', title: 'Connexion refusée', sub: 'Compte désactivé · t.roche@paddock.fr', object: 'Accès', by: '—' },
];

function AuditScreen() {
  const [sel, setSel] = React.useState(0);
  const row = AD_ROWS[sel];
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            <div style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Journal d’audit</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>1 284 actions sur les 30 derniers jours · 9 sensibles</div>
          </div>
          <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 7, minHeight: 38, padding: '0 14px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}><i className="ri-download-2-line" style={{ fontSize: 16 }} />Exporter la sélection</button>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, width: 240 }}><i className="ri-search-line" style={{ fontSize: 16 }} />Objet, client, numéro…</div>
          <span style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600 }}>Toutes · 1 284</span>
          <span style={{ whiteSpace: 'nowrap', flexShrink: 0, display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', border: '1px solid var(--pk-error-line)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, color: 'var(--pk-error-ink)', fontWeight: 600 }}><i className="ri-alert-line" style={{ fontSize: 14 }} />Sensibles · 9</span>
          <span style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12 }}>Julie D.</span>
          <span style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12 }}>30 derniers jours</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Le journal ne peut être ni modifié ni purgé</span>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'grid', gridTemplateColumns: AD_GRID, alignItems: 'center', gap: 12, padding: '8px 14px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span>Quand</span><span /><span>Action</span><span>Objet</span><span>Par</span>
          </div>
          {AD_ROWS.map((r, i) => (
            <div key={r.when + r.title} onClick={() => setSel(i)}
              style={{ ...adRow, background: sel === i && r.sensitive ? 'var(--pk-error-surface)' : sel === i ? 'var(--pk-accent-soft)' : i % 2 === 1 ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: r.sensitive ? '3px solid var(--pk-error-line)' : 'none' }}>
              <span style={{ fontWeight: 600 }}>{r.when}</span>
              <i className={r.icon} style={{ fontSize: 16, color: r.sensitive ? 'var(--pk-error-ink)' : 'var(--pk-ink-quiet)' }} />
              <div><div style={{ fontWeight: 600 }}>{r.title}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{r.sub}</div></div>
              <span style={{ color: 'var(--pk-ink-quiet)' }}>{r.object}</span>
              <span style={{ color: 'var(--pk-ink-quiet)' }}>{r.by}</span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>{row.title}</span>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{row.when}:07 · {row.sensitive ? 'action sensible' : 'action courante'}</span>
        </div>
        {sel === 0 ? (
          <>
            <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
              <span style={adOverline}>Ce qui a changé</span>
              <div style={{ border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', overflow: 'hidden' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '9px 12px', background: 'var(--pk-error-surface)', borderBottom: '1px solid var(--pk-border)' }}>
                  <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--pk-error-ink)', width: 52 }}>Avant</span>
                  <span style={{ fontSize: 13, textDecoration: 'line-through', color: 'var(--pk-ink-quiet)' }}>Remise 0 % · 412,50 € TTC</span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '9px 12px', background: 'var(--pk-success-surface)' }}>
                  <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--pk-success-ink)', width: 52 }}>Après</span>
                  <span style={{ fontSize: 13, fontWeight: 600 }}>Remise 12 % · 363,00 € TTC</span>
                </div>
              </div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Écart de 49,50 €. La marge du devis passe de 52 % à 45 %.</div>
            </div>
            <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
              <span style={adOverline}>Motif saisi</span>
              <div style={{ padding: '10px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', fontSize: 13, lineHeight: 1.5 }}>« Client fidèle depuis 2019, quatrième passage cette année. Geste commercial sur la main d’œuvre. »</div>
              <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Obligatoire au-delà de 10 %.</div>
            </div>
          </>
        ) : (
          <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
            <span style={adOverline}>Détail</span>
            <div style={{ fontSize: 13, lineHeight: 1.5 }}>{row.sub}</div>
            <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Objet : {row.object}</div>
          </div>
        )}
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <span style={adOverline}>Contexte</span>
          <div style={adCtx}><i className="ri-user-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />Julie Dubois<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-quiet)' }}>Responsable atelier</span></div>
          <div style={adCtx}><i className="ri-store-2-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />Atelier Principal<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-quiet)' }}>poste comptoir</span></div>
          <div style={adCtx}><i className="ri-shield-keyhole-line" style={{ fontSize: 16, color: 'var(--pk-ink-quiet)' }} />Session SSO<div style={{ flex: 1 }} /><span style={{ color: 'var(--pk-ink-quiet)' }}>ouverte à 07:58</span></div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)' }}>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Une entrée du journal ne peut pas être corrigée. Une erreur se répare par une nouvelle action, elle-même tracée.</span>
        </div>
      </aside>
    </div>
  );
}
Object.assign(window, { AuditScreen });
