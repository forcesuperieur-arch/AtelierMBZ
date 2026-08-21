/* Suivi public par lien (tour 50b) — ouvert depuis le SMS de réception. Pas
   d'identifiant : le lien porte le jeton et expire quinze jours après la
   restitution. La page répond d'abord « où en est ma moto » en une ligne, puis
   ce qui est fait et ce qui attend une réponse. */
const tkShell = { width: 390, height: 844, background: 'var(--pk-page)', display: 'flex', flexDirection: 'column', overflow: 'hidden', color: 'var(--pk-ink)', fontFamily: 'var(--mb-font-montserrat)' };
const tkBody = { flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 12, minHeight: 0, overflow: 'hidden' };
const tkKicker = { fontSize: 13, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-quiet)' };
const tkH1 = { fontSize: 26, fontWeight: 600, lineHeight: 1.2, letterSpacing: '-0.01em', marginTop: 6 };
const tkCard = { background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', padding: 15 };
const tkBtn = { minHeight: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' };

function TrackStep({ label, sub, tone, last, quiet }) {
  return (
    <div style={{ display: 'flex', gap: 14 }}>
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 'none' }}>
        <span style={{ width: 12, height: 12, borderRadius: 'var(--pk-radius-pill)', background: tone || 'transparent', border: tone ? 'none' : '2px solid var(--pk-ink-muted)', display: 'block' }} />
        {last ? null : <span style={{ flex: 1, width: 2, background: 'var(--pk-border)' }} />}
      </div>
      <div style={{ paddingBottom: last ? 0 : 11 }}>
        <div style={{ fontSize: 15, fontWeight: 600, color: quiet ? 'var(--pk-ink-muted)' : 'inherit' }}>{label}</div>
        <div style={{ fontSize: 14, color: quiet ? 'var(--pk-ink-muted)' : 'var(--pk-ink-quiet)', marginTop: 2 }}>{sub}</div>
      </div>
    </div>
  );
}

function TrackHeader({ logo }) {
  return (
    <div style={{ flexShrink: 0, background: '#000', color: '#f6f6f6', padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 4 }}>
      <img src={logo} alt="Paddock" style={{ height: 18, display: 'block' }} />
      <div style={{ fontSize: 13, color: '#a5a5a5', marginTop: 6 }}>Atelier de Dunkerque · 03 28 00 00 00</div>
    </div>
  );
}

function TrackScreen({ logo }) {
  const [answer, setAnswer] = React.useState(null);
  return (
    <div style={tkShell}>
      <TrackHeader logo={logo} />
      <div style={tkBody}>
        <div>
          <div style={tkKicker}>Votre MT-07 · EF-771-GH</div>
          <div style={tkH1}>En atelier depuis ce matin</div>
          <div style={{ fontSize: 15, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', marginTop: 6 }}>Prête aujourd’hui vers 17 h, sauf si vous nous demandez autre chose.</div>
        </div>

        {answer === null ? (
          <div style={{ background: '#fff5d9', border: '2px solid var(--pk-accent)', padding: 14, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ fontSize: 17, fontWeight: 600 }}>Nous attendons votre réponse</div>
            <div style={{ fontSize: 15, lineHeight: 1.5, color: '#4a3000' }}>Le disque arrière est voilé. Le remplacer coûte 168 € et ajoute une heure. Sans réponse, nous terminons le reste et vous récupérez la moto à 17 h.</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 2 }}>
              <button type="button" onClick={() => setAnswer('yes')} style={{ ...tkBtn, background: 'var(--pk-accent)', color: '#000', border: 'none', fontWeight: 700 }}>J’accepte · 168 €</button>
              <button type="button" onClick={() => setAnswer('no')} style={{ ...tkBtn, background: 'var(--pk-surface)', border: '1px solid #000', color: 'inherit' }}>Non merci</button>
            </div>
          </div>
        ) : (
          <div style={{ background: answer === 'yes' ? 'var(--pk-success-surface)' : 'var(--pk-surface)', border: '1px solid ' + (answer === 'yes' ? 'var(--pk-success-line)' : 'var(--pk-border)'), padding: 14, display: 'flex', gap: 11 }}>
            <i className={answer === 'yes' ? 'ri-checkbox-circle-fill' : 'ri-close-circle-line'} style={{ fontSize: 20, color: answer === 'yes' ? 'var(--pk-success-ink)' : 'var(--pk-ink-quiet)', flexShrink: 0 }} />
            <div style={{ fontSize: 15, lineHeight: 1.5 }}>{answer === 'yes' ? 'Accord enregistré à l’instant. Le disque est remplacé : la moto sera prête vers 18 h.' : 'Noté, le disque n’est pas remplacé. La moto reste prête à 17 h.'}</div>
          </div>
        )}

        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <TrackStep label="Moto réceptionnée · 8 h 06" sub="État des lieux signé, 6 photos prises." tone="var(--pk-success-ink)" />
          <TrackStep label="Révision faite · 11 h 40" sub="Huile, filtres, bougies, 22 points contrôlés." tone="var(--pk-success-ink)" />
          <TrackStep label={answer === null ? 'En attente de votre réponse · 15 h 24' : 'Votre réponse enregistrée · à l’instant'} sub="Disque arrière." tone={answer === null ? 'var(--pk-accent)' : 'var(--pk-success-ink)'} />
          <TrackStep label="Restitution" sub={answer === 'yes' ? 'À partir de 18 h, jusqu’à 19 h.' : 'À partir de 17 h, jusqu’à 19 h.'} quiet last />
        </div>

        <div style={{ flex: 1 }} />
        <div style={{ ...tkCard, padding: '12px 15px', display: 'flex', alignItems: 'baseline', gap: 10 }}>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 14, color: 'var(--pk-ink-quiet)' }}>Estimation à ce jour</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-muted)', marginTop: 2 }}>{answer === 'yes' ? 'Disque arrière compris' : 'Hors disque arrière'}</div>
          </div>
          <span style={{ fontSize: 22, fontWeight: 700 }}>{answer === 'yes' ? '457 €' : '289 €'}</span>
        </div>
        <span style={{ fontSize: 12, textAlign: 'center', color: 'var(--pk-ink-muted)' }}>Ce lien est personnel et expire 15 jours après la restitution.</span>
      </div>
    </div>
  );
}

/* L'écran « prête » : le seul que le client veut voir. */
function TrackReadyScreen({ logo }) {
  return (
    <div style={tkShell}>
      <TrackHeader logo={logo} />
      <div style={{ ...tkBody, gap: 14 }}>
        <div>
          <div style={tkKicker}>Votre MT-07 · EF-771-GH</div>
          <div style={{ ...tkH1, fontSize: 30 }}>Elle est prête</div>
          <div style={{ fontSize: 15, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', marginTop: 6 }}>Vous pouvez venir la chercher jusqu’à 19 h ce soir, ou demain à partir de 8 h.</div>
        </div>
        <div style={{ ...tkCard, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, fontSize: 15 }}><span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>Révision 20 000 km</span><span style={{ fontWeight: 600 }}>289,00 €</span></div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, fontSize: 15 }}><span style={{ flex: 1, color: 'var(--pk-ink-quiet)' }}>Disque arrière remplacé</span><span style={{ fontWeight: 600 }}>168,00 €</span></div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, paddingTop: 8, borderTop: '1px solid var(--pk-border-quiet)' }}><span style={{ flex: 1, fontSize: 15, fontWeight: 600 }}>À régler</span><span style={{ fontSize: 24, fontWeight: 700 }}>457,00 €</span></div>
          <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Carte, espèces ou chèque, au comptoir. Rien à payer en ligne.</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <TrackStep label="Moto réceptionnée · 8 h 06" sub="État des lieux signé, 6 photos prises." tone="var(--pk-success-ink)" />
          <TrackStep label="Travaux terminés · 16 h 52" sub="Essai routier validé sur 7 km." tone="var(--pk-success-ink)" />
          <TrackStep label="Prête à être récupérée" sub="Depuis 16 h 58." tone="var(--pk-success-ink)" last />
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ ...tkCard, display: 'flex', gap: 11 }}>
          <i className="ri-map-pin-line" style={{ fontSize: 20, color: 'var(--pk-ink-quiet)', flexShrink: 0 }} />
          <div style={{ fontSize: 14, lineHeight: 1.55 }}><strong style={{ fontWeight: 600 }}>Atelier de Dunkerque</strong><br />14 rue de la Fonderie, 59140 Dunkerque<br />Ouvert jusqu’à 19 h</div>
        </div>
        <span style={{ ...tkBtn, minHeight: 56, background: 'var(--pk-accent)', color: '#000', fontSize: 17, fontWeight: 700 }}>Itinéraire vers l’atelier</span>
        <span style={{ fontSize: 12, textAlign: 'center', color: 'var(--pk-ink-muted)' }}>Ce lien est personnel et expire 15 jours après la restitution.</span>
      </div>
    </div>
  );
}

/* Mentions et confidentialité (tour 50c) — page publique liée depuis chaque e-mail. */
function LegalScreen({ logo }) {
  return (
    <div style={tkShell}>
      <TrackHeader logo={logo} />
      <div style={{ ...tkBody, gap: 16, overflow: 'auto' }}>
        <div>
          <div style={tkKicker}>Page publique</div>
          <div style={tkH1}>Mentions et confidentialité</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ fontSize: 16, fontWeight: 600 }}>Qui édite ce service</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Motoblouz SAS, 14 rue de la Fonderie, 59140 Dunkerque. SIRET 812 445 990 00021 · TVA FR 40 812445990. Directeur de la publication : le président de Motoblouz SAS.</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ fontSize: 16, fontWeight: 600 }}>Ce que nous conservons</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Vos coordonnées, votre moto et l’historique de ses passages à l’atelier. Les photos d’état des lieux sont conservées trois ans, durée de la garantie légale sur nos interventions.</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ fontSize: 16, fontWeight: 600 }}>Ce que nous ne faisons pas</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Aucune revente de vos données. Aucun message commercial sans votre accord : les SMS que vous recevez concernent uniquement votre rendez-vous en cours.</div>
        </div>
        <div style={{ ...tkCard, display: 'flex', flexDirection: 'column', gap: 7 }}>
          <div style={{ fontSize: 15, fontWeight: 600 }}>Vos droits</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Accès, rectification, effacement : écrivez à dpo@motoblouz.com ou demandez au comptoir. Réponse sous un mois.</div>
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--pk-ink-quiet)' }}>Médiateur de la consommation : AME Conso, 11 place Dauphine, 75001 Paris.</div>
        </div>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Dernière mise à jour : 2 juillet 2026.</span>
      </div>
    </div>
  );
}
Object.assign(window, { TrackScreen, TrackReadyScreen, LegalScreen, TrackHeader, TrackStep });
