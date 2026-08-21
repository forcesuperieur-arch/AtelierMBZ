/* Enveloppe Administration — le module a sa propre navigation, groupée par
   Atelier / Personnes / Documents / Système, et son fil d'Ariane. */
const ADM_GROUPS = [
  { label: 'Atelier', items: [
    { id: 'hours', icon: 'ri-time-line', name: 'Horaires et fermetures' },
    { id: 'team', icon: 'ri-user-follow-line', name: 'Disponibilité équipe' },
    { id: 'bays', icon: 'ri-tools-line', name: 'Ponts' },
    { id: 'services', icon: 'ri-price-tag-3-line', name: 'Prestations' },
  ] },
  { label: 'Personnes', items: [{ id: 'users', icon: 'ri-user-settings-line', name: 'Utilisateurs et accès' }] },
  { label: 'Documents', items: [
    { id: 'docs', icon: 'ri-file-text-line', name: 'Modèles de documents' },
    { id: 'notifications', icon: 'ri-notification-3-line', name: 'Notifications' },
  ] },
  { label: 'Système', items: [
    { id: 'config', icon: 'ri-settings-3-line', name: 'Configuration' },
    { id: 'audit', icon: 'ri-history-line', name: 'Journal d’audit' },
  ] },
];

const ADM_TITLES = { config: 'Configuration', users: 'Utilisateurs et accès', notifications: 'Notifications', audit: 'Journal d’audit', docs: 'Modèles de documents', hours: 'Horaires et fermetures', team: 'Disponibilité de l’équipe', services: 'Prestations', bays: 'Ponts et mécaniciens' };
const ADM_META = {
  config: { icon: 'ri-history-line', text: 'Taux horaire modifié le 2 janvier' },
  users: { icon: 'ri-history-line', text: 'Dernier accès révoqué le 4 août' },
  notifications: { icon: 'ri-send-plane-line', text: '412 envois ce mois · 18,54 €' },
  audit: { icon: 'ri-lock-line', text: 'Lecture seule · conservation 3 ans' },
  docs: { icon: 'ri-history-line', text: 'Version 4 · publiée le 2 juillet' },
  hours: { icon: 'ri-history-line', text: 'Modifié le 2 juillet par Julie D.' },
  team: { icon: 'ri-calendar-line', text: 'Semaine du 17 au 22 août' },
  services: { icon: 'ri-error-warning-line', text: '2 forfaits mal calibrés', alert: true },
  bays: { icon: 'ri-tools-line', text: '6 ponts · 1 en maintenance' },
};
const ADM_BUILT = ['config', 'users', 'notifications', 'audit', 'docs', 'hours', 'team', 'services'];

function AdminScreen({ page, onPage, onExit, logo }) {
  const meta = ADM_META[page] || ADM_META.config;
  return (
    <div style={{ width: 1440, height: 900, display: 'flex', background: 'var(--pk-canvas)', overflow: 'hidden', color: 'var(--pk-ink)' }}>
      <nav style={{ width: 224, flexShrink: 0, background: 'var(--pk-surface)', borderRight: '1px solid var(--pk-border)', display: 'flex', flexDirection: 'column', padding: '12px 0', overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 12px 16px', padding: '8px 10px', background: '#000', borderRadius: 'var(--pk-radius-card)' }}>
          <img src={logo} alt="Paddock" style={{ width: 32, height: 32, display: 'block', flex: 'none' }} />
          <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#fff', letterSpacing: '0.14em' }}>PADDOCK</span>
            <span style={{ fontSize: 11, color: '#d4d4d4' }}>Atelier Principal</span>
          </div>
        </div>
        {ADM_GROUPS.map((g, gi) => (
          <React.Fragment key={g.label}>
            <div style={{ padding: gi === 0 ? '0 20px 6px' : '14px 20px 6px', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--pk-ink-muted)' }}>{g.label}</div>
            {g.items.map((it) => {
              const active = page === it.id;
              const built = ADM_BUILT.indexOf(it.id) !== -1;
              return (
                <button type="button" key={it.id} onClick={() => (built ? onPage(it.id) : null)}
                  style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px', background: active ? 'var(--pk-accent)' : 'transparent', color: active ? '#000' : 'inherit', border: 'none', borderRadius: active ? 'var(--pk-radius-card)' : 0, fontSize: 13, fontWeight: active ? 600 : 400, textAlign: 'left', cursor: built ? 'pointer' : 'default', opacity: built ? 1 : 0.75 }}>                  <i className={it.icon} style={{ fontSize: 17, color: active ? '#000' : 'var(--pk-ink-quiet)' }} />{it.name}
                </button>
              );
            })}
          </React.Fragment>
        ))}
        <div style={{ flex: 1 }} />
        <button type="button" onClick={onExit} style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '0 8px', padding: '8px 12px', fontSize: 13, background: 'transparent', color: 'inherit', border: 'none', borderTopStyle: 'solid', borderTopWidth: 1, borderTopColor: 'var(--pk-border-quiet)', textAlign: 'left', cursor: 'pointer' }}>
          <i className="ri-arrow-left-line" style={{ fontSize: 17, color: 'var(--pk-ink-quiet)' }} />Retour à l’atelier
        </button>
      </nav>

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <header style={{ height: 52, flexShrink: 0, background: 'var(--pk-surface)', borderBottom: '1px solid var(--pk-border)', display: 'flex', alignItems: 'center', gap: 10, padding: '0 20px' }}>
          <span style={{ fontSize: 13, color: 'var(--pk-ink-muted)' }}>Administration</span>
          <i className="ri-arrow-right-s-line" style={{ fontSize: 16, color: '#a5a5a5' }} />
          <span style={{ fontSize: 15, fontWeight: 600 }}>{ADM_TITLES[page]}</span>
          <div style={{ flex: 1 }} />
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: meta.alert ? 600 : 400, color: meta.alert ? 'var(--pk-error-ink)' : 'var(--pk-ink-muted)', whiteSpace: 'nowrap' }}><i className={meta.icon} style={{ fontSize: 15 }} />{meta.text}</span>
          {page === 'services' ? <span style={{ minHeight: 34, display: 'flex', alignItems: 'center', padding: '0 14px', background: 'var(--pk-accent)', color: '#000', borderRadius: 6, fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap' }}>Nouvelle prestation</span> : null}
          <div style={{ width: 32, height: 32, borderRadius: 'var(--pk-radius-pill)', background: 'var(--pk-info-line)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 600 }}>JD</div>
        </header>

        <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
          {page === 'config' ? <window.ConfigScreen /> : null}
          {page === 'users' ? <window.UsersScreen /> : null}
          {page === 'notifications' ? <window.NotificationsScreen /> : null}
          {page === 'audit' ? <window.AuditScreen /> : null}
          {page === 'docs' ? <window.DocTemplatesScreen logo={logo} /> : null}
          {page === 'hours' ? <window.HoursScreen /> : null}
          {page === 'team' ? <window.TeamScreen /> : null}
          {page === 'services' ? <window.ServicesScreen /> : null}
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { AdminScreen });
