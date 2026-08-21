/* Administration › Utilisateurs et accès — tour 16. La liste des comptes et,
   à droite, le rôle sélectionné dit en clair : peut faire / ne peut pas. */
const { Button: UsButton } = window.PaddockDesignSystem_8059f4;

const US_GRID = '1.5fr 1.5fr 200px 1fr 130px';
const usOverline = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' };
const usRow = { display: 'grid', gridTemplateColumns: US_GRID, alignItems: 'center', padding: '12px 16px', borderBottom: '1px solid var(--pk-border-quiet)', fontSize: 13 };
const usAvatar = { width: 30, height: 30, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-canvas)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 600 };
const usRolePill = { whiteSpace: 'nowrap', padding: '4px 11px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600 };
const usOtherRole = { display: 'flex', alignItems: 'center', gap: 10, minHeight: 40, padding: '0 12px', background: 'var(--pk-surface-raised)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', width: '100%', textAlign: 'left', color: 'inherit', cursor: 'pointer' };

const US_PEOPLE = [
  { initials: 'JD', name: 'Julie Dubois', sub: 'vous', mail: 'j.dubois@paddock.fr', role: 'Responsable atelier', me: true, sites: 'Atelier Principal', last: 'À l’instant' },
  { initials: 'KM', name: 'Karim Mansouri', mail: 'k.mansouri@paddock.fr', role: 'Mécanicien', sites: 'Atelier Principal', last: 'Il y a 12 min' },
  { initials: 'SL', name: 'Sophie Leroy', mail: 's.leroy@paddock.fr', role: 'Mécanicien', sites: 'Atelier Principal', last: 'Il y a 40 min' },
  { initials: 'AL', name: 'Adrien Lambert', mail: 'a.lambert@paddock.fr', role: 'Apprenti', sites: 'Atelier Principal', last: 'Hier' },
  { initials: 'AB', name: 'Amel Benali', mail: 'a.benali@paddock.fr', role: 'Service client', multi: '4 ateliers', last: 'Il y a 5 min' },
  { initials: 'PM', name: 'Pascal Morel', mail: 'p.morel@paddock.fr', role: 'Direction', multi: '4 ateliers', last: 'Il y a 3 jours' },
  { invited: true, name: 'Léa Fontaine', sub: 'Invitation envoyée il y a 6 jours', mail: 'l.fontaine@paddock.fr', role: 'Service client', sites: 'Atelier Principal', last: 'Relancer' },
  { initials: 'TB', name: 'Thibault Roche', sub: 'Désactivé le 4 août', mail: 't.roche@paddock.fr', role: 'Mécanicien', sites: 'Atelier Principal', last: 'Il y a 3 semaines', off: true },
];

const US_CAN = [
  'Ouvrir, déplacer et annuler tous les rendez-vous de son atelier.',
  'Réceptionner, restituer, encaisser, et éditer devis et factures avec une remise jusqu’à 15 %.',
  'Poser une absence et modifier les horaires.',
];
const US_CANNOT = [
  'Voir les autres ateliers du réseau.',
  'Modifier le catalogue des prestations ni le taux horaire.',
  'Supprimer une facture émise — seul un avoir est possible.',
];
const US_OTHERS = [
  { name: 'Mécanicien', count: '3 personnes' },
  { name: 'Apprenti', count: '1 personne' },
  { name: 'Service client', count: '2 personnes' },
  { name: 'Direction', count: '1 personne' },
];

function UsersScreen() {
  const [tab, setTab] = React.useState('Tous · 13');
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0, minWidth: 0 }}>
      <div style={{ flex: 1, padding: 20, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 16 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ fontSize: 28, fontWeight: 500, letterSpacing: '-0.015em', lineHeight: 1.1 }}>Utilisateurs et accès</div>
            <div style={{ width: 44, height: 4, background: 'var(--pk-accent)' }} />
            <div style={{ fontSize: 13, color: 'var(--pk-ink-quiet)', marginTop: 4 }}>9 comptes actifs sur cet atelier · 2 comptes couvrent plusieurs sites.</div>
          </div>
          <UsButton variant="primary" tone="accent" size="small" startIcon="ri-add-line">Inviter quelqu’un</UsButton>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 14px', background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 34, padding: '0 12px', border: '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', color: 'var(--pk-ink-muted)', fontSize: 13, width: 260 }}><i className="ri-search-line" style={{ fontSize: 16 }} />Nom ou adresse e-mail</div>
          {['Tous · 13', 'Actifs · 9', 'Invités · 1', 'Désactivés · 3'].map((t) => (
            <button type="button" key={t} onClick={() => setTab(t)}
              style={{ whiteSpace: 'nowrap', flexShrink: 0, padding: '6px 12px', background: tab === t ? '#000' : 'transparent', color: tab === t ? '#fff' : 'inherit', border: tab === t ? 'none' : '1px solid var(--pk-border-control)', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: tab === t ? 600 : 400, cursor: 'pointer' }}>{t}</button>
          ))}
          <div style={{ flex: 1 }} />
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--pk-ink-quiet)' }}><i className="ri-shield-check-line" style={{ fontSize: 15 }} />Toute modification est tracée au journal d’audit</span>
        </div>

        <div style={{ flex: 1, background: 'var(--pk-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-card)', display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ display: 'grid', gridTemplateColumns: US_GRID, padding: '9px 16px', borderBottom: '1px solid var(--pk-border)', background: 'var(--pk-surface-raised)', fontSize: 10, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>
            <span>Personne</span><span>Adresse e-mail</span><span>Rôle</span><span>Ateliers</span><span style={{ textAlign: 'right' }}>Dernière activité</span>
          </div>
          {US_PEOPLE.map((p, i) => (
            <div key={p.name} style={{ ...usRow, background: p.me ? 'var(--pk-accent-soft)' : i % 2 === 0 ? 'transparent' : 'var(--pk-surface-raised)', borderLeft: p.me ? '3px solid var(--pk-accent)' : 'none', color: p.off ? 'var(--pk-ink-muted)' : undefined }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                {p.invited
                  ? <div style={{ ...usAvatar, background: 'transparent', border: '1px dashed var(--pk-border-control)', color: 'var(--pk-ink-muted)' }}><i className="ri-mail-send-line" style={{ fontSize: 14 }} /></div>
                  : <div style={{ ...usAvatar, background: p.me ? 'var(--pk-info-line)' : 'var(--pk-canvas)', color: p.me ? '#fff' : 'inherit' }}>{p.initials}</div>}
                <div><div style={{ fontWeight: 600 }}>{p.name}</div>{p.sub ? <div style={{ fontSize: 12, color: p.invited ? 'var(--pk-warning-ink-soft)' : 'var(--pk-ink-muted)' }}>{p.sub}</div> : null}</div>
              </div>
              <span style={{ color: p.off ? 'inherit' : 'var(--pk-ink-quiet)' }}>{p.mail}</span>
              <span>{p.me
                ? <span style={{ padding: '4px 11px', background: '#000', color: '#fff', borderRadius: 'var(--pk-radius-pill)', fontSize: 12, fontWeight: 600 }}>{p.role}</span>
                : <span style={{ ...usRolePill, border: p.off ? '1px solid var(--pk-border)' : '1px solid var(--pk-border-control)', fontWeight: p.off ? 400 : 600 }}>{p.role}</span>}</span>
              {p.multi
                ? <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><i className="ri-store-2-line" style={{ fontSize: 15, color: 'var(--pk-accent-ink)' }} /><span style={{ fontWeight: 600, color: 'var(--pk-accent-ink)' }}>{p.multi}</span></span>
                : <span style={{ color: p.off ? 'inherit' : 'var(--pk-ink-quiet)' }}>{p.sites}</span>}
              <span style={{ textAlign: 'right', fontSize: p.last === 'Relancer' ? 12 : 13, fontWeight: p.last === 'Relancer' ? 600 : 400, color: p.last === 'Relancer' ? 'var(--pk-link)' : p.off ? 'inherit' : 'var(--pk-ink-quiet)' }}>{p.last}</span>
            </div>
          ))}
          <div style={{ flex: 1 }} />
        </div>
      </div>

      <aside style={{ width: 372, flexShrink: 0, background: 'var(--pk-surface)', borderLeft: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontSize: 15, fontWeight: 600 }}>Responsable atelier</span>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>2 personnes portent ce rôle</span>
        </div>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 10 }}>
          <span style={{ ...usOverline, color: 'var(--pk-success-ink)' }}>Peut faire</span>
          {US_CAN.map((c) => (
            <div key={c} style={{ display: 'flex', alignItems: 'flex-start', gap: 9, fontSize: 13, lineHeight: 1.45 }}><i className="ri-check-line" style={{ fontSize: 17, color: 'var(--pk-success-line)', flexShrink: 0 }} />{c}</div>
          ))}
        </div>
        <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--pk-border-quiet)', display: 'flex', flexDirection: 'column', gap: 10 }}>
          <span style={{ ...usOverline, color: 'var(--pk-error-ink)' }}>Ne peut pas</span>
          {US_CANNOT.map((c) => (
            <div key={c} style={{ display: 'flex', alignItems: 'flex-start', gap: 9, fontSize: 13, lineHeight: 1.45 }}><i className="ri-close-line" style={{ fontSize: 17, color: 'var(--pk-error-ink)', flexShrink: 0 }} />{c}</div>
          ))}
        </div>
        <div style={{ padding: '14px 18px', display: 'flex', flexDirection: 'column', gap: 9 }}>
          <span style={usOverline}>Les autres rôles</span>
          {US_OTHERS.map((r) => (
            <button type="button" key={r.name} style={usOtherRole}>
              <span style={{ flex: 1, fontSize: 13, fontWeight: 600 }}>{r.name}</span>
              <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>{r.count}</span>
              <i className="ri-arrow-right-s-line" style={{ fontSize: 17, color: 'var(--pk-ink-muted)' }} />
            </button>
          ))}
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ padding: '12px 18px', borderTop: '1px solid var(--pk-border)' }}>
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', lineHeight: 1.45 }}>Un rôle se modifie pour tout l’atelier, jamais pour une seule personne.</span>
        </div>
      </aside>
    </div>
  );
}
Object.assign(window, { UsersScreen });
