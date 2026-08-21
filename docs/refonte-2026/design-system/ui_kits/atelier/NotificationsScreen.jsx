/* Administration › Notifications — tour 20. Colonne des messages du parcours,
   éditeur en phrase, aperçu sombre de ce que le client voit. */
const { Button: NfButton } = window.PaddockDesignSystem_8059f4;

const nfOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const nfGroup = { padding: '14px 18px 8px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const nfItem = { display: 'flex', alignItems: 'center', gap: 12, padding: '11px 18px', background: 'transparent', width: '100%', textAlign: 'left', color: 'inherit', border: 'none', borderTopStyle: 'solid', borderTopWidth: 1, borderTopColor: 'var(--pk-border-quiet)', cursor: 'pointer' };
const nfSlot = { display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontWeight: 600 };
const nfVar = { padding: '2px 7px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-block)', fontSize: 13, fontWeight: 600 };
const nfInsert = { whiteSpace: 'nowrap', flexShrink: 0, display: 'inline-flex', alignItems: 'center', gap: 5, padding: '6px 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, background: 'transparent', color: 'inherit', cursor: 'pointer' };
const nfDarkLine = { display: 'flex', alignItems: 'center', gap: 9, fontSize: 12, color: '#d4d4d4' };

const NF_GROUPS = [
  { label: 'Avant la venue', items: [
    { name: 'Rendez-vous confirmé', sub: 'SMS · à la prise de RDV', on: true },
    { name: 'Rappel la veille', sub: 'SMS · J−1 à 18:00', on: true },
  ] },
  { label: 'Pendant l’intervention', items: [
    { name: 'Moto prise en charge', sub: 'SMS · à la réception', on: true },
    { name: 'Travail supplémentaire à valider', sub: 'SMS · relance après 2 h', on: true, reply: true },
    { name: 'Devis à signer', sub: 'SMS et e-mail · relance J+3 puis J+7', on: true, reply: true },
  ] },
  { label: 'Après l’intervention', items: [
    { name: 'Moto prête à récupérer', sub: 'En cours de modification', on: true, editing: true },
    { name: 'Facture disponible', sub: 'E-mail · J+1', on: true },
    { name: 'Rappel de révision annuelle', sub: 'Désactivé' },
    { name: 'Enquête de satisfaction', sub: 'Désactivé' },
  ] },
];

function NfToggle({ on }) {
  return (
    <span style={{ display: 'inline-flex', width: 38, height: 21, padding: 2, background: on ? 'var(--pk-accent)' : 'var(--pk-border)', borderRadius: 'var(--pk-radius-pill)', flexShrink: 0 }}>
      <span style={{ width: 17, height: 17, borderRadius: 'var(--pk-radius-pill)', background: '#fff', marginLeft: on ? 'auto' : 0 }} />
    </span>
  );
}

function NotificationsScreen() {
  const [selected, setSelected] = React.useState('Moto prête à récupérer');
  const [channel, setChannel] = React.useState('SMS');
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ width: 400, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '16px 18px 12px', display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ fontSize: 20, fontWeight: 500, letterSpacing: '-0.01em' }}>Messages du parcours</div>
          <div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Dans l’ordre où le client les reçoit.</div>
        </div>
        {NF_GROUPS.map((g, gi) => (
          <React.Fragment key={g.label}>
            <div style={{ ...nfGroup, paddingTop: gi === 0 ? 0 : 14 }}>{g.label}</div>
            {g.items.map((it) => {
              const active = selected === it.name;
              return (
                <button type="button" key={it.name} onClick={() => setSelected(it.name)}
                  style={{ ...nfItem, background: active ? 'var(--pk-accent-soft)' : 'transparent', borderLeft: active ? '3px solid var(--pk-accent)' : it.reply ? '3px solid var(--pk-error-line)' : 'none', color: it.on ? 'inherit' : 'var(--pk-ink-muted)' }}>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                      <span style={{ fontSize: 13, fontWeight: 600 }}>{it.name}</span>
                      {it.reply ? <i className="ri-reply-line" style={{ fontSize: 14, color: 'var(--pk-error-ink)' }} /> : null}
                    </div>
                    <div style={{ fontSize: 12, color: active ? 'var(--pk-accent-ink)' : it.on ? 'var(--pk-ink-muted)' : 'inherit' }}>{it.sub}</div>
                  </div>
                  <NfToggle on={it.on} />
                </button>
              );
            })}
          </React.Fragment>
        ))}
        <div style={{ flex: 1 }} />
        <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 9 }}>
          <span style={{ width: 8, height: 8, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-success-line)' }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Envoi SMS et e-mail opérationnel</span>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--pk-link)' }}>Réglages techniques</span>
        </div>
      </div>

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, overflow: 'hidden' }}>
        <div style={{ flexShrink: 0, padding: '18px 22px 14px', display: 'flex', alignItems: 'flex-start', gap: 16, borderBottom: '1px solid var(--pk-border)' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, minWidth: 0 }}>
            <div style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.015em' }}>{selected}</div>
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Le message le plus lu de tous : il déclenche la venue du client.</div>
          </div>
          <div style={{ flex: 1 }} />
          <button type="button" style={{ display: 'flex', alignItems: 'center', gap: 7, minHeight: 41, padding: '0 15px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, background: 'transparent', color: 'inherit', cursor: 'pointer' }}><i className="ri-send-plane-line" style={{ fontSize: 16 }} />M’envoyer un test</button>
          <NfButton variant="primary" tone="accent" size="small">Enregistrer</NfButton>
        </div>

        <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
          <div style={{ flex: 1, padding: '18px 22px', display: 'flex', flexDirection: 'column', gap: 18, minWidth: 0, overflow: 'hidden' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <span style={nfOverline}>Quand l’envoyer</span>
              <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 8, padding: '14px 16px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', fontSize: 15, lineHeight: 2 }}>
                <span>Envoyer</span>
                <span style={nfSlot}>tout de suite<i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} /></span>
                <span>quand</span>
                <span style={nfSlot}>la moto passe en « prête »<i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} /></span>
                <span>,</span>
                <span style={nfSlot}>jamais avant 8 h ni après 19 h<i className="ri-arrow-down-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} /></span>
                <span>.</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: 'var(--pk-ink-quiet)' }}><i className="ri-information-line" style={{ fontSize: 15 }} />Un message déclenché à 19 h 40 partira le lendemain à 8 h.</div>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <span style={nfOverline}>Par quel moyen</span>
              <div style={{ display: 'flex', gap: 10 }}>
                {[{ n: 'SMS', i: 'ri-message-2-fill', s: 'Lu dans les 3 minutes' }, { n: 'E-mail', i: 'ri-mail-line', s: 'Gratuit, moins lu' }, { n: 'Les deux', i: 'ri-checkbox-multiple-line', s: 'Pour les montants' }].map((c) => {
                  const on = channel === c.n;
                  return (
                    <button type="button" key={c.n} onClick={() => setChannel(c.n)}
                      style={{ flex: 1, padding: '13px 15px', background: on ? 'var(--pk-accent-soft)' : 'var(--pk-surface)', border: on ? '2px solid var(--pk-accent)' : '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', alignItems: 'center', gap: 11, textAlign: 'left', color: 'inherit', cursor: 'pointer' }}>
                      <i className={c.i} style={{ fontSize: 20, color: on ? 'var(--pk-accent-ink)' : 'var(--pk-ink-quiet)' }} />
                      <div style={{ minWidth: 0 }}><div style={{ fontSize: 14, fontWeight: 600 }}>{c.n}</div><div style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{c.s}</div></div>
                    </button>
                  );
                })}
              </div>
            </div>

            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={nfOverline}>Ce qu’il dit</span>
                <div style={{ flex: 1 }} />
                <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-success-ink)' }}><i className="ri-checkbox-circle-line" style={{ fontSize: 15 }} />120 sur 160 caractères · tient en 1 SMS</span>
              </div>
              <div style={{ minHeight: 96, padding: '13px 15px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', fontSize: 14, lineHeight: 1.6 }}>
                Bonjour <span style={nfVar}>prénom</span>, votre <span style={nfVar}>moto</span> est prête. Vous pouvez la récupérer jusqu’à <span style={nfVar}>heure de fermeture</span>. Détail et facture : <span style={nfVar}>lien de suivi</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Insérer :</span>
                {['plaque', 'montant à régler', 'nom de l’atelier', 'adresse', 'téléphone'].map((v) => (
                  <button type="button" key={v} style={nfInsert}><i className="ri-add-line" style={{ fontSize: 14 }} />{v}</button>
                ))}
              </div>
            </div>
          </div>

          <aside style={{ width: 340, flexShrink: 0, background: '#2f2f2f', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', alignItems: 'center', padding: 18, gap: 14, overflow: 'hidden' }}>
            <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Ce que le client verra</span>
            <div style={{ width: 276, background: '#141414', border: '1px solid #6f6e6e', borderRadius: 20, padding: '16px 14px', display: 'flex', flexDirection: 'column', gap: 12, color: '#f6f6f6' }}>
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 13, color: '#a5a5a5' }}>vendredi 15 août</div>
                <div style={{ fontSize: 40, fontWeight: 300, letterSpacing: '-0.02em', lineHeight: 1.1 }}>15:32</div>
              </div>
              <div style={{ background: 'rgba(255,255,255,0.11)', borderRadius: 14, padding: '12px 13px', display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                  <div style={{ width: 17, height: 17, borderRadius: 4, background: '#4dbb3a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><i className="ri-message-2-fill" style={{ fontSize: 11, color: '#fff' }} /></div>
                  <span style={{ fontSize: 12, fontWeight: 600, color: '#d4d4d4' }}>PADDOCK</span>
                  <div style={{ flex: 1 }} />
                  <span style={{ fontSize: 11, color: '#a5a5a5' }}>maintenant</span>
                </div>
                <div style={{ fontSize: 13, lineHeight: 1.45 }}>Bonjour Ludovic, votre Yamaha MT-09 est prête. Vous pouvez la récupérer jusqu’à 18h30. Détail et facture : pdk.fr/s/8Kq2</div>
              </div>
            </div>
            <div style={{ width: 276, padding: '12px 14px', background: '#1c1c1c', border: '1px solid #6f6e6e', borderRadius: 10, display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={nfDarkLine}><i className="ri-money-euro-circle-line" style={{ fontSize: 16, color: '#f4bc33' }} />0,045 € par envoi · environ 15 € par mois</div>
              <div style={nfDarkLine}><i className="ri-links-line" style={{ fontSize: 16, color: '#f4bc33' }} />Lien ouvert par 71 % des clients</div>
              <div style={nfDarkLine}><i className="ri-time-line" style={{ fontSize: 16, color: '#f4bc33' }} />Venue moyenne 1 h 40 après l’envoi</div>
            </div>
            <div style={{ width: 276, padding: '11px 14px', background: '#2b1b00', border: '1px solid #7d5600', borderRadius: 10, display: 'flex', alignItems: 'flex-start', gap: 9 }}>
              <i className="ri-information-line" style={{ fontSize: 16, color: '#f4bc33', flexShrink: 0 }} />
              <span style={{ fontSize: 12, color: '#f9dd99', lineHeight: 1.45 }}>Au-delà de 160 caractères le message part en deux SMS et coûte le double. Le compteur passe au rouge avant.</span>
            </div>
            <div style={{ flex: 1 }} />
            <div style={{ width: 276, display: 'flex', flexDirection: 'column', gap: 7 }}>
              <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#a5a5a5' }}>Derniers envois</span>
              <div style={nfDarkLine}><i className="ri-check-double-line" style={{ fontSize: 15, color: '#4dbb3a' }} />N. Belkacem · reçu à 14:12</div>
              <div style={nfDarkLine}><i className="ri-check-double-line" style={{ fontSize: 15, color: '#4dbb3a' }} />M. Delaunay · reçu hier 17:40</div>
              <div style={{ ...nfDarkLine, color: '#ff8095' }}><i className="ri-error-warning-line" style={{ fontSize: 15 }} />C. Perrot · numéro invalide</div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { NotificationsScreen });
