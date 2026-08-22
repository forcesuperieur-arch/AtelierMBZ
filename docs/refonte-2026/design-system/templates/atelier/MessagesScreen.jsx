/* Messagerie atelier — la contrepartie du fil client.

   Le portail client permet d'écrire à l'atelier. Sans cet écran, personne ne
   répond : c'était le trou le plus net de la refonte. Deux colonnes, la
   convention du kit — la file des conversations à gauche, le fil à droite.

   Le parti pris : une conversation n'est pas un canal de discussion, c'est un
   dossier. Chaque fil est rattaché à un OR et porte le contexte de
   l'intervention en tête ; on répond en sachant de quelle moto on parle, sans
   ouvrir un autre écran. Les réponses fréquentes sont des boutons — l'accueil
   écrit vingt fois par jour « votre moto est prête », le taper vingt fois est
   du travail perdu. */

const msRow = { display: 'flex', flexDirection: 'column', gap: 5, padding: '13px 16px', borderBottom: '1px solid var(--pk-border-quiet)', cursor: 'pointer' };
const msOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const msAct = { whiteSpace: 'nowrap', flexShrink: 0, minHeight: 32, display: 'flex', alignItems: 'center', gap: 6, padding: '0 12px', fontSize: 12, fontWeight: 600, border: '1px solid var(--pk-border-control)', background: 'transparent', color: 'inherit', cursor: 'pointer', borderRadius: 'var(--pk-radius-pill)' };
const msTab = { whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600, cursor: 'pointer', border: '1px solid transparent' };

/* Les réponses toutes faites. Elles couvrent ce que l'accueil écrit le plus
   souvent ; le texte est modifiable avant l'envoi. */
const MS_TYPES = [
  { label: 'Moto prête', texte: 'Bonjour, votre moto est prête. Vous pouvez venir la récupérer aux horaires de l’atelier, du lundi au vendredi de 8 h à 18 h.' },
  { label: 'Devis envoyé', texte: 'Bonjour, nous venons de vous transmettre un devis dans votre espace. Il attend votre accord pour que nous puissions continuer.' },
  { label: 'Pièce en attente', texte: 'Bonjour, la pièce nécessaire à votre intervention est en commande. Nous vous prévenons dès sa réception, la moto reste chez nous entre-temps.' },
  { label: 'Retard', texte: 'Bonjour, nous avons pris du retard sur votre intervention. La nouvelle date de restitution est prévue pour demain en fin de journée.' },
];

const MS_FILS = [
  { id: 1, client: 'Thomas Berthier', bike: 'Yamaha MT-07 · AV-908-RT', or: 'OR-2431', etat: 'attente', nonlus: 2, dernier: 'il y a 12 min',
    apercu: 'Est-ce que la révision comprend le contrôle de la chaîne ?',
    contexte: 'Révision 20 000 km · sur le pont 3 · restitution prévue aujourd’hui 17 h',
    fil: [
      { de: 'atelier', auteur: 'Julie D.', date: 'aujourd’hui, 08 h 14', texte: 'Bonjour Thomas, votre moto est bien arrivée. L’état des lieux est signé, vous le retrouvez dans le suivi.' },
      { de: 'client', auteur: 'Thomas Berthier', date: 'aujourd’hui, 08 h 40', texte: 'Merci. Est-ce que la révision comprend le contrôle de la chaîne ?' },
      { de: 'client', auteur: 'Thomas Berthier', date: 'aujourd’hui, 11 h 22', texte: 'Et j’ai vu passer un devis pour le disque arrière, je le valide ce soir.' },
    ] },
  { id: 2, client: 'Ludovic Renard', bike: 'Yamaha MT-09 · EX-421-QR', or: 'OR-2428', etat: 'attente', nonlus: 1, dernier: 'il y a 1 h',
    apercu: 'Je peux passer la récupérer samedi matin ?',
    contexte: 'Disque arrière voilé · travaux complémentaires à valider · immobilisée',
    fil: [
      { de: 'atelier', auteur: 'Karim B.', date: 'hier, 16 h 02', texte: 'Bonjour, nous avons constaté un disque arrière voilé au démontage de la roue. Un devis complémentaire vous attend dans votre espace.' },
      { de: 'client', auteur: 'Ludovic Renard', date: 'aujourd’hui, 09 h 47', texte: 'Je peux passer la récupérer samedi matin ?' },
    ] },
  { id: 3, client: 'Céline Marchand', bike: 'Suzuki SV650 · CD-119-PT', or: 'OR-2419', etat: 'repondu', nonlus: 0, dernier: 'hier',
    apercu: 'Julie D. — Bonjour, votre moto est prête.',
    contexte: 'Plaquettes avant · terminée · à récupérer',
    fil: [
      { de: 'client', auteur: 'Céline Marchand', date: 'hier, 10 h 15', texte: 'Bonjour, est-ce que c’est prêt pour ce soir ?' },
      { de: 'atelier', auteur: 'Julie D.', date: 'hier, 10 h 31', texte: 'Bonjour, votre moto est prête. Vous pouvez venir la récupérer aux horaires de l’atelier, du lundi au vendredi de 8 h à 18 h.' },
    ] },
  { id: 4, client: 'Hugo Lacroix', bike: 'Triumph Trident · FG-902-VN', or: 'OR-2402', etat: 'clos', nonlus: 0, dernier: '18 août',
    apercu: 'Hugo Lacroix — Parfait, merci beaucoup.',
    contexte: 'Révision 10 000 km · restituée le 18 août',
    fil: [
      { de: 'atelier', auteur: 'Nadia B.', date: '18 août, 14 h 20', texte: 'Bonjour, la facture de votre passage est disponible dans votre espace, rubrique Documents.' },
      { de: 'client', auteur: 'Hugo Lacroix', date: '18 août, 18 h 55', texte: 'Parfait, merci beaucoup.' },
    ] },
];

function MsEtat({ etat }) {
  const map = {
    attente: ['Attend une réponse', 'var(--pk-warn-soft)', 'var(--pk-warn-ink)'],
    repondu: ['Répondu', 'var(--pk-ok-soft)', 'var(--pk-ok-ink)'],
    clos: ['Clos', 'var(--pk-surface-raised)', 'var(--pk-ink-muted)'],
  };
  const [l, bg, fg] = map[etat];
  return <span style={{ whiteSpace: 'nowrap', flexShrink: 0, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 'var(--pk-radius-pill)', background: bg, color: fg }}>{l}</span>;
}

function MessagesScreen() {
  const [actif, setActif] = React.useState(1);
  const [tab, setTab] = React.useState('À répondre · 2');
  const [fils, setFils] = React.useState(MS_FILS);
  const [txt, setTxt] = React.useState('');
  const bas = React.useRef(null);

  const f = fils.find((x) => x.id === actif);
  const tabs = ['À répondre · 2', 'Toutes · 4', 'Répondues', 'Closes'];
  const liste = fils.filter((x) => tab === 'Toutes · 4' ? true : tab === 'À répondre · 2' ? x.etat === 'attente' : tab === 'Répondues' ? x.etat === 'repondu' : x.etat === 'clos');

  const envoyer = (texte) => {
    const t = (texte || txt).trim();
    if (!t) return;
    setFils((v) => v.map((x) => x.id !== actif ? x : { ...x, etat: 'repondu', nonlus: 0, dernier: 'à l’instant', apercu: 'Julie D. — ' + t, fil: x.fil.concat({ de: 'atelier', auteur: 'Julie D.', date: 'à l’instant', texte: t }) }));
    setTxt('');
  };

  React.useEffect(() => { const n = bas.current; if (n && n.parentElement) n.parentElement.scrollTop = n.parentElement.scrollHeight; }, [fils, actif]);

  return (
    <div style={{ flex: 1, display: 'flex', minWidth: 0, minHeight: 0, background: 'var(--pk-canvas)' }}>
      {/* La file des conversations */}
      <div style={{ width: 340, flexShrink: 0, display: 'flex', flexDirection: 'column', minHeight: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)' }}>
        <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 7, padding: '10px 14px', borderBottom: '1px solid var(--pk-border)', overflowX: 'auto' }}>
          {tabs.map((t) => (
            <span key={t} onClick={() => setTab(t)} style={{ ...msTab, background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'var(--pk-ink-quiet)', borderColor: tab === t ? '#000' : 'var(--pk-border-control)' }}>{t}</span>
          ))}
        </div>
        <div style={{ flex: 1, overflowY: 'auto' }}>
          {liste.length === 0 ? (
            <div style={{ padding: '32px 20px', textAlign: 'center', display: 'flex', flexDirection: 'column', gap: 8 }}>
              <i className="ri-chat-3-line" style={{ fontSize: 22, color: 'var(--pk-ink-muted)' }} />
              <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)', textWrap: 'pretty' }}>Rien dans cette file.</span>
            </div>
          ) : liste.map((x) => {
            const on = x.id === actif;
            return (
              <div key={x.id} onClick={() => { setActif(x.id); setFils((v) => v.map((y) => y.id === x.id ? { ...y, nonlus: 0 } : y)); }}
                style={{ ...msRow, background: on ? 'var(--pk-surface-raised)' : 'transparent', borderLeft: on ? '3px solid var(--pk-accent)' : '3px solid transparent' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ fontSize: 14, fontWeight: 700, flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{x.client}</span>
                  {x.nonlus ? <span style={{ whiteSpace: 'nowrap', flexShrink: 0, minWidth: 20, height: 20, padding: '0 6px', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 10, background: 'var(--pk-accent)', color: '#000', fontSize: 11, fontWeight: 700 }}>{x.nonlus}</span> : null}
                  <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', flexShrink: 0 }}>{x.dernier}</span>
                </div>
                <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{x.bike} · {x.or}</span>
                <span style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{x.apercu}</span>
              </div>
            );
          })}
        </div>
      </div>

      {/* Le fil */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0 }}>
        <div style={{ flexShrink: 0, padding: '13px 20px', background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
          <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 9, flexWrap: 'wrap' }}>
              <span style={{ fontSize: 15, fontWeight: 700 }}>{f.client}</span>
              <MsEtat etat={f.etat} />
            </div>
            <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)', textWrap: 'pretty' }}>{f.bike} · {f.contexte}</span>
          </div>
          <div style={{ display: 'flex', gap: 8, flexShrink: 0 }}>
            <button style={msAct}><i className="ri-file-text-line" style={{ fontSize: 15 }} />Ouvrir {f.or}</button>
            <button style={msAct}><i className="ri-phone-line" style={{ fontSize: 15 }} />Appeler</button>
          </div>
        </div>

        <div style={{ flex: 1, overflowY: 'auto', padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
          {f.fil.map((m, i) => (
            <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: m.de === 'atelier' ? 'flex-end' : 'flex-start' }}>
              <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)' }}>{m.auteur} · {m.date}</span>
              <div style={{ maxWidth: '68%', padding: '12px 15px', fontSize: 14, lineHeight: 1.5, textWrap: 'pretty', background: m.de === 'atelier' ? 'var(--pk-accent-soft)' : 'var(--pk-surface)', border: '1px solid ' + (m.de === 'atelier' ? 'var(--pk-accent-line)' : 'var(--pk-border)') }}>{m.texte}</div>
            </div>
          ))}
          <div ref={bas} />
        </div>

        <div style={{ flexShrink: 0, background: 'var(--pk-surface)', borderTop: '1px solid var(--pk-border)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '9px 16px 0', overflowX: 'auto' }}>
            <span style={{ ...msOverline, flexShrink: 0, marginRight: 2 }}>Réponses</span>
            {MS_TYPES.map((t) => (
              <button key={t.label} onClick={() => setTxt(t.texte)} style={msAct}>{t.label}</button>
            ))}
          </div>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, padding: '10px 16px 12px' }}>
            <textarea value={txt} onChange={(e) => setTxt(e.target.value)} rows="2" placeholder={'Répondre à ' + f.client.split(' ')[0]}
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); envoyer(); } }}
              style={{ flex: 1, font: 'inherit', fontSize: 14, lineHeight: 1.5, minHeight: 56, padding: '10px 13px', resize: 'none', background: 'var(--pk-surface-raised)', color: 'inherit', border: '1px solid var(--pk-border-control)' }} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, flexShrink: 0 }}>
              <button onClick={() => envoyer()} style={{ minHeight: 40, display: 'flex', alignItems: 'center', gap: 7, padding: '0 16px', background: 'var(--pk-accent)', color: '#000', border: 'none', borderRadius: 'var(--pk-radius-pill)', fontSize: 13, fontWeight: 700, cursor: 'pointer', font: 'inherit' }}><i className="ri-send-plane-fill" style={{ fontSize: 16 }} />Envoyer</button>
              <span style={{ fontSize: 11, color: 'var(--pk-ink-muted)', textAlign: 'center' }}>Entrée pour envoyer</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { MessagesScreen });
