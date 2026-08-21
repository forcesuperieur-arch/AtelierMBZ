/* Administration › Configuration — tour 27. Les réglages qui changent des prix,
   avec la colonne « ce que ça change » et la garantie de non-rétroactivité. */
const { Button: CfButton } = window.PaddockDesignSystem_8059f4;

const cfOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const cfCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden' };
const cfRow = { display: 'flex', alignItems: 'center', gap: 16, padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)' };
const cfField = { minWidth: 88, minHeight: 36, display: 'flex', alignItems: 'center', padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-tile)', fontSize: 15 };
const cfSmallField = { ...cfField, minHeight: 34, fontSize: 14 };

const CF_THRESHOLDS = [
  { label: 'Devis sans réponse devient critique après', value: '2 jours', note: '4 devis critiques actuellement' },
  { label: 'Travail supplémentaire à relancer après', value: '2 heures', note: 'Déclenche le SMS de relance' },
  { label: 'Stock bas quand il reste moins de', value: '3 unités', note: '4 références sous le seuil' },
  { label: 'Immobilisation anormale au-delà de', value: '3 jours', note: 'En heures ouvrées', last: true },
];

const CF_EFFECTS = [
  { label: 'Révision 20 000 · 1 h 35', was: '109,25', now: '114,00 €' },
  { label: 'Pose plaquettes · 0 h 30', was: '34,50', now: '36,00 €' },
  { label: 'Pose kit chaîne · 1 h 10', was: '80,50', now: '84,00 €' },
];

function ConfigScreen() {
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Configuration</div>
          <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
        </div>

        <div style={cfCard}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Facturation</span>
            <div style={{ flex: 1 }} />
            <span style={{ padding: '3px 9px', background: 'var(--pk-canvas)', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-ink-quiet)' }}>JAMAIS RÉTROACTIF</span>
          </div>
          <div style={{ ...cfRow, background: 'var(--pk-accent-soft)' }}>
            <div style={{ width: 280 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Taux horaire de main d’œuvre</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Base de tous les forfaits et temps facturés</div></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={{ ...cfField, border: '2px solid var(--pk-accent)', fontWeight: 600 }}>72,00 €</span>
              <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>/ h HT</span>
            </div>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 13, color: 'var(--pk-accent-ink)', fontWeight: 600 }}>était 69,00 €</span>
          </div>
          <div style={cfRow}>
            <div style={{ width: 280 }}><div style={{ fontSize: 13, fontWeight: 600 }}>TVA applicable</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Taux normal, prestations et pièces</div></div>
            <span style={cfField}>20 %</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Modification réservée à la direction</span>
          </div>
          <div style={{ ...cfRow, borderBottom: 'none' }}>
            <div style={{ width: 280 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Marge minimale sur pièces</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Alerte à la composition d’un devis sous ce seuil</div></div>
            <span style={cfField}>22 %</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Alerte seulement, jamais bloquant</span>
          </div>
        </div>

        <div style={cfCard}>
          <div style={{ padding: '11px 16px', borderBottom: '1px solid var(--pk-border)', fontSize: 15, fontWeight: 600 }}>Acompte</div>
          <div style={cfRow}>
            <div style={{ width: 280 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Demandé au-delà de</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Montant TTC du devis</div></div>
            <span style={cfField}>400 €</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>18 devis concernés ce mois</span>
          </div>
          <div style={{ ...cfRow, borderBottom: 'none' }}>
            <div style={{ width: 280 }}><div style={{ fontSize: 13, fontWeight: 600 }}>Part demandée</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Réglable à la signature du devis</div></div>
            <span style={cfField}>30 %</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Le solde est dû à la restitution</span>
          </div>
        </div>

        <div style={{ ...cfCard, flex: 1, minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 16px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Seuils d’alerte</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Alimentent la file à traiter de Stat</span>
          </div>
          {CF_THRESHOLDS.map((t) => (
            <div key={t.label} style={{ display: 'flex', alignItems: 'center', gap: 16, padding: '11px 16px', borderBottom: t.last ? 'none' : '1px solid var(--pk-border-quiet)' }}>
              <div style={{ width: 280, fontSize: 13, fontWeight: 600 }}>{t.label}</div>
              <span style={cfSmallField}>{t.value}</span>
              <div style={{ flex: 1 }} />
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{t.note}</span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Ce que ça change</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>1 modification</span>
        </div>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={cfOverline}>Taux horaire</span>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
            <span style={{ fontSize: 15, color: 'var(--pk-ink-muted)', textDecoration: 'line-through' }}>69,00 €</span>
            <i className="ri-arrow-right-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
            <span style={{ fontSize: 24, fontWeight: 700 }}>72,00 €</span>
          </div>
          <span style={{ fontSize: 13, color: 'var(--pk-accent-ink)' }}>+ 4,3 % · soit + 3 € par heure facturée</span>
        </div>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <span style={cfOverline}>Effet sur les forfaits</span>
          {CF_EFFECTS.map((e) => (
            <div key={e.label} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 }}>
              <span style={{ flex: 1 }}>{e.label}</span>
              <span style={{ color: 'var(--pk-ink-muted)', textDecoration: 'line-through' }}>{e.was}</span>
              <span style={{ fontWeight: 700 }}>{e.now}</span>
            </div>
          ))}
          <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>31 prestations du catalogue sont recalculées à l’enregistrement.</div>
        </div>
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-success-surface)', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
          <i className="ri-shield-check-line" style={{ fontSize: 18, color: 'var(--pk-success-ink)', flexShrink: 0 }} />
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink)' }}><strong style={{ fontWeight: 600 }}>Rien de rétroactif.</strong> Les 11 devis en attente de signature et les factures émises gardent 69 €/h. Un client ne paiera jamais un montant différent de celui qu’il a signé.</div>
        </div>
        <div style={{ padding: '12px 18px', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <span style={cfOverline}>Effet annuel estimé</span>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
            <span style={{ fontSize: 22, fontWeight: 700 }}>+ 4 200 €</span>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>de chiffre atelier</span>
          </div>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Sur la base des 1 400 heures facturées l’an dernier, à volume constant.</span>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <CfButton variant="primary" tone="accent" size="medium" fullWidth>Enregistrer le taux horaire</CfButton>
          <button type="button" style={{ minHeight: 44, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}>Annuler</button>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center' }}>Écrit au journal d’audit avec votre nom.</span>
        </div>
      </aside>
    </div>
  );
}
Object.assign(window, { ConfigScreen });
