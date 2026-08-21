/* Fournisseurs d'envoi (tour 48b) — les tuyaux, pas les messages : clés, quotas,
   DKIM, et surtout les messages non aboutis avec ce qui les débloque. Un message
   non abouti n'annule pas l'événement : le rendez-vous existe, le client ne le
   sait pas. */
const spCard = { flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column' };
const spStat = { padding: '14px 18px', borderRight: '1px solid var(--pk-border-quiet)' };
const spKey = { minHeight: 38, display: 'flex', alignItems: 'center', padding: '0 13px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, whiteSpace: 'nowrap' };
const spFail = { display: 'grid', gridTemplateColumns: '150px 200px 1fr 260px', alignItems: 'center', padding: '13px 18px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };

function ProvidersScreen() {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 10, padding: '14px 24px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Notifications</span>
        <i className="ri-arrow-right-s-line" style={{ fontSize: 16, color: 'var(--pk-ink-muted)' }} />
        <span style={{ fontSize: 15, fontWeight: 600 }}>Fournisseurs d’envoi</span>
        <div style={{ flex: 1 }} />
        <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}><i className="ri-links-line" style={{ fontSize: 15 }} />Les messages eux-mêmes se règlent dans Notifications</span>
      </div>

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 18, padding: '22px 24px', minHeight: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Fournisseurs d’envoi</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>Hier, 47 messages sont partis et 3 n’ont pas abouti. Les trois sont listés plus bas, avec le motif et ce qui les débloque.</div>
          </div>
          <span style={{ minHeight: 40, display: 'flex', alignItems: 'center', gap: 7, padding: '0 14px', border: '1px solid #000', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}><i className="ri-send-plane-line" style={{ fontSize: 16 }} />Envoyer un message de test</span>
        </div>

        <div style={{ display: 'flex', gap: 16 }}>
          <div style={spCard}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '15px 18px', borderBottom: '1px solid var(--pk-border-quiet)' }}>
              <i className="ri-message-2-line" style={{ fontSize: 20, color: 'var(--pk-ink-quiet)' }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 15, fontWeight: 600 }}>SMS · Brevo</div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Expéditeur affiché : MOTOBLOUZ</div>
              </div>
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '5px 11px', background: '#e6f4e6', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700, color: 'var(--pk-success-ink)', whiteSpace: 'nowrap' }}><span style={{ width: 8, height: 8, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-success-ink)', display: 'block' }} />En service</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', borderBottom: '1px solid var(--pk-border-quiet)' }}>
              <div style={spStat}><div style={{ fontSize: 22, fontWeight: 700 }}>31</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>envoyés hier</div></div>
              <div style={spStat}><div style={{ fontSize: 22, fontWeight: 700, color: 'var(--pk-error-ink)' }}>2</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>non aboutis</div></div>
              <div style={{ padding: '14px 18px' }}><div style={{ fontSize: 22, fontWeight: 700 }}>1 840</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>crédits restants</div></div>
            </div>
            <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <span style={{ width: 90, flex: 'none', fontSize: 12, color: 'var(--pk-ink-muted)' }}>Clé d’API</span>
                <span style={{ flex: 1, minHeight: 38, display: 'flex', alignItems: 'center', padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 6, fontFamily: 'ui-monospace, monospace', fontSize: 12, color: 'var(--pk-ink-quiet)' }}>xkeysib-••••••••••••••••3f9a</span>
                <span style={spKey}>Remplacer</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <span style={{ width: 90, flex: 'none', fontSize: 12, color: 'var(--pk-ink-muted)' }}>Quota mensuel</span>
                <div style={{ flex: 1 }}>
                  <div style={{ height: 8, background: 'var(--pk-canvas)' }}><div style={{ width: '38%', height: '100%', background: '#000' }} /></div>
                  <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)', marginTop: 4 }}>760 SMS sur 2 000 · alerte à 90 %, par e-mail au responsable</div>
                </div>
              </div>
              <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)' }}>Remplacer la clé n’interrompt rien : la nouvelle est testée sur un numéro interne, et ne devient active qu’après un envoi réussi.</div>
            </div>
          </div>

          <div style={spCard}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '15px 18px', borderBottom: '1px solid var(--pk-border-quiet)' }}>
              <i className="ri-mail-line" style={{ fontSize: 20, color: 'var(--pk-ink-quiet)' }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 15, fontWeight: 600 }}>E-mail · SMTP Motoblouz</div>
                <div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>atelier.dunkerque@motoblouz.com</div>
              </div>
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '5px 11px', background: '#fff5d9', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 700, color: 'var(--pk-warning-ink-soft)', whiteSpace: 'nowrap' }}><span style={{ width: 8, height: 8, borderRadius: 'var(--pk-radius-pill)', background: '#d96500', display: 'block' }} />Signature à finir</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', borderBottom: '1px solid var(--pk-border-quiet)' }}>
              <div style={spStat}><div style={{ fontSize: 22, fontWeight: 700 }}>16</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>envoyés hier</div></div>
              <div style={spStat}><div style={{ fontSize: 22, fontWeight: 700, color: 'var(--pk-error-ink)' }}>1</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>non abouti</div></div>
              <div style={{ padding: '14px 18px' }}><div style={{ fontSize: 22, fontWeight: 700 }}>—</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>pas de quota</div></div>
            </div>
            <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 }}>
                <i className="ri-checkbox-circle-fill" style={{ fontSize: 17, color: 'var(--pk-success-ink)' }} /><span style={{ flex: 1 }}>SPF publié pour motoblouz.com</span><span style={{ fontFamily: 'ui-monospace, monospace', fontSize: 12, color: 'var(--pk-ink-muted)' }}>vérifié</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 }}>
                <i className="ri-error-warning-fill" style={{ fontSize: 17, color: '#d96500' }} /><span style={{ flex: 1 }}>DKIM absent · les e-mails partent en indésirable chez certains clients</span><span style={{ fontFamily: 'ui-monospace, monospace', fontSize: 12, color: 'var(--pk-warning-ink-soft)' }}>à publier</span>
              </div>
              <div style={{ padding: '12px 14px', background: '#fff5d9', border: '1px solid var(--pk-accent)', fontSize: 13, lineHeight: 1.5, color: '#4a3000' }}>L’enregistrement DKIM se publie chez l’hébergeur du domaine, pas ici. L’écran donne la valeur à copier et vérifie toutes les heures.</div>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={spKey}>Copier la valeur DKIM</span>
                <span style={spKey}>Revérifier</span>
              </div>
            </div>
          </div>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', minHeight: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '14px 18px', borderBottom: '1px solid var(--pk-border)' }}>
            <span style={{ fontSize: 15, fontWeight: 600 }}>Messages non aboutis</span>
            <span style={{ padding: '3px 9px', background: '#ffe8ec', borderRadius: 'var(--pk-radius-pill)', fontSize: 11, fontWeight: 700, color: 'var(--pk-error-ink)', whiteSpace: 'nowrap' }}>3 depuis hier</span>
            <div style={{ flex: 1 }} />
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Un message non abouti n’annule pas l’événement : le rendez-vous existe, le client ne le sait pas.</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '150px 200px 1fr 260px', padding: '8px 18px', borderBottom: '1px solid var(--pk-border-quiet)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span>Quand</span><span>Destinataire</span><span>Motif</span><span>Ce qui débloque</span>
          </div>
          <div style={{ ...spFail, borderLeft: '3px solid var(--pk-error-line)' }}>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>hier 17:04</span>
            <div><div style={{ fontWeight: 600 }}>06 12 34 56 78</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>N. Belkacem · rappel J-1</div></div>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>Numéro invalide chez l’opérateur — un chiffre de trop dans la fiche client.</span>
            <span style={{ fontWeight: 600, color: '#7d5600' }}>Corriger le numéro, puis renvoyer</span>
          </div>
          <div style={{ ...spFail, borderLeft: '3px solid var(--pk-error-line)', background: 'var(--pk-surface-raised)' }}>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>hier 14:20</span>
            <div><div style={{ fontWeight: 600 }}>t.fontaine@free.fr</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Devis 1188 · pièce jointe 2,4 Mo</div></div>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>Refusé par le serveur du destinataire : boîte pleine. Trois tentatives sur 24 h.</span>
            <span style={{ fontWeight: 600, color: '#7d5600' }}>Appeler, ou envoyer le lien sans pièce jointe</span>
          </div>
          <div style={{ ...spFail, borderLeft: '3px solid #d96500', borderBottom: 'none' }}>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>hier 09:12</span>
            <div><div style={{ fontWeight: 600 }}>07 88 22 11 05</div><div style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Moto prête · OR 2418</div></div>
            <span style={{ color: 'var(--pk-ink-quiet)' }}>Le client s’est désinscrit des SMS le 4 juillet. Aucun SMS ne lui sera envoyé.</span>
            <span style={{ fontWeight: 600, color: '#7d5600' }}>Basculer ce client en e-mail</span>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ padding: '14px 18px', borderTop: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', display: 'flex', alignItems: 'center', gap: 12 }}>
            <i className="ri-information-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />
            <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)' }}>Ces trois lignes remontent aussi dans Stat, colonne « à traiter » : un client non joint est un client qui va appeler.</span>
          </div>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { ProvidersScreen });
