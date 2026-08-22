/* Demandes de travaux complémentaires — aligne `frontend/pages/demandes-travaux-supp.vue`.

   Le code actuel empile des cartes toutes identiques et laisse l'utilisateur
   lire chaque ligne pour savoir laquelle réclame un geste. Ici la page est
   ordonnée par ce qu'il y a à faire : à envoyer, en attente client, à faire
   signer. Le vocabulaire de statut du code est conservé tel quel — les cinq
   valeurs de l'API (`en_attente_validation`, `en_attente_decision_client`,
   `accepte`, `refuse`, plus la signature en attente) sont ce que le back
   renvoie ; on ne les renomme pas. */

const { Button: DtButton } = window.PaddockDesignSystem_8059f4;

const dtKpi = { flex: 1, padding: '14px 22px', borderRight: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 3 };
const dtOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const dtTab = { whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer', border: '1px solid transparent' };
const dtAct = { whiteSpace: 'nowrap', minHeight: 32, display: 'flex', alignItems: 'center', gap: 6, padding: '0 13px', fontSize: 12, fontWeight: 600, border: '1px solid var(--pk-border-control)', background: 'transparent', color: 'inherit', cursor: 'pointer' };

/* Les statuts, avec le libellé métier et le geste qu'ils réclament. */
const DT_STATUS = {
  en_attente_validation: { label: 'À envoyer', tone: 'accent', geste: 'Le mécanicien a chiffré, personne n’a encore prévenu le client.' },
  en_attente_decision_client: { label: 'En attente client', tone: 'warn', geste: 'Le client a le lien, il n’a pas répondu.' },
  signature: { label: 'Signature en attente', tone: 'warn', geste: 'Accord donné au téléphone, il manque la signature.' },
  accepte: { label: 'Acceptée', tone: 'ok', geste: null },
  refuse: { label: 'Refusée', tone: 'off', geste: null },
};

const DT_ROWS = [
  { id: 3412, statut: 'en_attente_validation', urgent: true, client: 'Ludovic Renard', bike: 'Yamaha MT-09 · EX-421-QR', rdv: 8841,
    desc: 'Disque arrière voilé, constaté au démontage de la roue. La moto est sur le pont, le refaire plus tard demande une nouvelle immobilisation.',
    lignes: [['Disque arrière', 96.0], ['Main-d’œuvre 0,5 h', 42.0]], prix: 138.0, temps: 30, par: 'Karim B.' },
  { id: 3410, statut: 'en_attente_validation', client: 'Sabrina Amrani', bike: 'Kawasaki Z650 · BJ-778-LM', rdv: 8836,
    desc: 'Fuite au niveau du joint spi de fourche gauche. Visible, pas immédiatement dangereux.',
    lignes: [['Joint spi ×2', 34.5], ['Main-d’œuvre 1,5 h', 126.0]], prix: 160.5, temps: 90, par: 'Julie D.' },
  { id: 3405, statut: 'en_attente_decision_client', client: 'Céline Marchand', bike: 'Suzuki SV650 · CD-119-PT', rdv: 8828,
    desc: 'Plaquettes avant à 1,5 mm. Tiennent le retour, pas le prochain mois.',
    lignes: [['Plaquettes avant', 48.9], ['Main-d’œuvre 0,5 h', 42.0]], prix: 90.9, temps: 30, par: 'Karim B.', envoye: 'SMS envoyé hier à 16 h 12', relance: 'Relancé ce matin' },
  { id: 3401, statut: 'signature', client: 'Hugo Lacroix', bike: 'Triumph Trident · FG-902-VN', rdv: 8819,
    desc: 'Durite de frein arrière craquelée.',
    lignes: [['Durite arrière', 62.0], ['Main-d’œuvre 0,7 h', 58.8]], prix: 120.8, temps: 42, par: 'Julie D.', accord: 'Accord tél. enregistré par Nadia B. le 20/08 à 11 h 04' },
  { id: 3396, statut: 'accepte', client: 'Marc Delaunay', bike: 'Honda CB650R · AV-908-RT', rdv: 8812,
    desc: 'Bougies hors tolérance au diagnostic.',
    lignes: [['Bougies ×4', 38.0], ['Main-d’œuvre 0,4 h', 33.6]], prix: 71.6, temps: 24, par: 'Karim B.', decide: 'Accepté en ligne le 19/08 à 20 h 47', or: 4471 },
  { id: 3390, statut: 'refuse', client: 'Amine Ouali', bike: 'KTM Duke 390 · HK-330-BC', rdv: 8804,
    desc: 'Pneu arrière au témoin.',
    lignes: [['Pneu arrière', 142.0], ['Montage', 28.0]], prix: 170.0, temps: 45, par: 'Julie D.', decide: 'Refusé en ligne le 18/08 à 9 h 30 — « je le fais ailleurs »' },
];

function DtBadge({ statut }) {
  const s = DT_STATUS[statut];
  const bg = s.tone === 'accent' ? 'var(--pk-accent-soft)' : s.tone === 'warn' ? 'var(--pk-warn-soft)' : s.tone === 'ok' ? 'var(--pk-ok-soft)' : 'var(--pk-surface-raised)';
  const fg = s.tone === 'accent' ? 'var(--pk-accent-ink)' : s.tone === 'warn' ? 'var(--pk-warn-ink)' : s.tone === 'ok' ? 'var(--pk-ok-ink)' : 'var(--pk-ink-muted)';
  return <span style={{ whiteSpace: 'nowrap', flexShrink: 0, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 'var(--pk-radius-pill)', background: bg, color: fg }}>{s.label}</span>;
}

function DtCard({ d, canal, onCanal, onSend, envoye }) {
  const s = DT_STATUS[d.statut];
  const actionnable = d.statut === 'en_attente_validation' || d.statut === 'en_attente_decision_client' || d.statut === 'signature';
  return (
    <div style={{ background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderLeft: d.urgent ? '3px solid var(--pk-error)' : '1px solid var(--pk-border)' }}>
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 20, padding: '14px 16px' }}>
        <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 7 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 14, fontWeight: 700 }}>#{d.id} — {d.client}</span>
            <DtBadge statut={d.statut} />
            {d.urgent ? <span style={{ whiteSpace: 'nowrap', fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-error-soft)', color: 'var(--pk-error-ink)' }}>URGENT</span> : null}
          </div>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>{d.bike} · RDV #{d.rdv} · chiffré par {d.par}</span>
          <p style={{ margin: 0, fontSize: 13, lineHeight: 1.5, color: 'var(--pk-ink-quiet)', textWrap: 'pretty' }}>« {d.desc} »</p>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {d.lignes.map(([l, p]) => (
              <span key={l} style={{ whiteSpace: 'nowrap', fontSize: 11, padding: '3px 9px', borderRadius: 6, background: 'var(--pk-surface-raised)', color: 'var(--pk-ink-quiet)' }}>{l} — {p.toFixed(2).replace('.', ',')} €</span>
            ))}
          </div>
        </div>
        <div style={{ flexShrink: 0, textAlign: 'right', display: 'flex', flexDirection: 'column', gap: 3, minWidth: 168 }}>
          <span style={{ fontSize: 18, fontWeight: 700, fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{d.prix.toFixed(2).replace('.', ',')} €</span>
          <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>≈ {d.temps} min de pont</span>
          {d.envoye ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{d.envoye}</span> : null}
          {d.relance ? <span style={{ fontSize: 11, color: 'var(--pk-warn-ink)' }}>{d.relance}</span> : null}
          {d.accord ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', textWrap: 'pretty' }}>{d.accord}</span> : null}
          {d.decide ? <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', textWrap: 'pretty' }}>{d.decide}</span> : null}
        </div>
      </div>

      {actionnable ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', padding: '10px 16px', borderTop: '1px solid var(--pk-border-quiet)' }}>
          {s.geste ? <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', marginRight: 'auto', textWrap: 'pretty' }}>{s.geste}</span> : null}
          {d.statut === 'en_attente_validation' ? (
            canal ? (
              <>
                <DtButton size="sm" onClick={onSend}><i className="ri-mail-line" style={{ fontSize: 15 }} />E-mail</DtButton>
                <DtButton size="sm" onClick={onSend}><i className="ri-smartphone-line" style={{ fontSize: 15 }} />SMS</DtButton>
                <button style={dtAct} onClick={() => onCanal(null)}>Annuler</button>
              </>
            ) : envoye ? (
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: 'var(--pk-ok-ink)' }}><i className="ri-check-line" style={{ fontSize: 16 }} />Envoyé au client</span>
            ) : (
              <DtButton size="sm" onClick={() => onCanal(d.id)}><i className="ri-upload-line" style={{ fontSize: 15 }} />Envoyer au client</DtButton>
            )
          ) : null}
          {d.statut === 'en_attente_decision_client' ? (
            <>
              <button style={dtAct}><i className="ri-links-line" style={{ fontSize: 15 }} />Copier le lien client</button>
              <button style={dtAct}><i className="ri-mail-send-line" style={{ fontSize: 15 }} />Relancer</button>
            </>
          ) : null}
          {d.statut === 'signature' ? <DtButton size="sm"><i className="ri-quill-pen-line" style={{ fontSize: 15 }} />Faire signer au comptoir</DtButton> : null}
          <button style={dtAct}><i className="ri-phone-line" style={{ fontSize: 15 }} />Décision téléphonique</button>
        </div>
      ) : d.or ? (
        <div style={{ padding: '10px 16px', borderTop: '1px solid var(--pk-border-quiet)', display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, color: 'var(--pk-ink-muted)' }}>
          <i className="ri-file-text-line" style={{ fontSize: 15 }} />OR complémentaire n° {d.or} — les lignes sont passées sur l’ordre de réparation.
        </div>
      ) : null}
    </div>
  );
}

function ExtraWorkScreen() {
  const [tab, setTab] = React.useState('À traiter · 4');
  const [canal, setCanal] = React.useState(null);
  const [envoyes, setEnvoyes] = React.useState([]);

  const tabs = ['À traiter · 4', 'Toutes · 6', 'À envoyer · 2', 'En attente client · 1', 'Acceptées', 'Refusées'];
  const filtre = (d) => {
    if (tab === 'Toutes · 6') return true;
    if (tab === 'À traiter · 4') return d.statut !== 'accepte' && d.statut !== 'refuse';
    if (tab === 'À envoyer · 2') return d.statut === 'en_attente_validation';
    if (tab === 'En attente client · 1') return d.statut === 'en_attente_decision_client';
    if (tab === 'Acceptées') return d.statut === 'accepte';
    return d.statut === 'refuse';
  };
  const rows = DT_ROWS.filter(filtre);

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0, background: 'var(--pk-canvas)' }}>
      <div style={{ flexShrink: 0, display: 'flex', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)' }}>
        <div style={dtKpi}><span style={dtOverline}>À envoyer</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>2</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>299 € · 2 h de pont engagées</span></div>
        <div style={dtKpi}><span style={dtOverline}>En attente client</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>1</span><span style={{ fontSize: 12, color: 'var(--pk-warn-ink)' }}>relancé une fois</span></div>
        <div style={dtKpi}><span style={dtOverline}>Signature manquante</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>1</span><span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>accord téléphonique donné</span></div>
        <div style={{ ...dtKpi, borderRight: 'none' }}><span style={dtOverline}>Acceptées ce mois</span><span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1 }}>23</span><span style={{ fontSize: 12, color: 'var(--pk-ok-ink)' }}>68 % des demandes envoyées</span></div>
      </div>

      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', overflowX: 'auto' }}>
        {tabs.map((t) => (
          <span key={t} onClick={() => setTab(t)} style={{ ...dtTab, background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'var(--pk-ink-quiet)', borderColor: tab === t ? '#000' : 'var(--pk-border-control)' }}>{t}</span>
        ))}
        <button style={{ ...dtAct, marginLeft: 'auto' }}><i className="ri-refresh-line" style={{ fontSize: 15 }} />Rafraîchir</button>
      </div>

      <div style={{ flex: 1, overflowY: 'auto', padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
        {rows.length === 0 ? (
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 10, textAlign: 'center', padding: 48 }}>
            <i className="ri-inbox-line" style={{ fontSize: 26, color: 'var(--pk-ink-muted)' }} />
            <span style={{ fontSize: 15, fontWeight: 700 }}>Rien dans cette file</span>
            <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)', maxWidth: 380, textWrap: 'pretty' }}>Une demande arrive ici quand un mécanicien constate un travail non prévu et le chiffre depuis son poste.</span>
          </div>
        ) : rows.map((d) => (
          <DtCard key={d.id} d={d} canal={canal === d.id} envoye={envoyes.indexOf(d.id) !== -1}
            onCanal={setCanal} onSend={() => { setEnvoyes((v) => v.concat(d.id)); setCanal(null); }} />
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { ExtraWorkScreen });
