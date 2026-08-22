/* Coquille du portail client — recréation de `client-frontend/layouts/default.vue`.
   Barre de nav collante avec sa bande de course en tête, contenu à 960 px,
   pied légal. Les valeurs sont celles du code : padding 14/28, gap 20, la bande
   de 140 px en dégradé jaune coupée en biais de 8 px. */
const clNavLink = { position: 'relative', color: 'var(--content-3)', textDecoration: 'none', padding: '10px 4px', fontWeight: 500, fontSize: 14, background: 'none', border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const clCard = { padding: '14px 16px', background: 'var(--surface-1)', border: '1px solid var(--border-2)', borderRadius: 12 };
const clSectionTitle = { fontSize: 13, fontWeight: 700, textTransform: 'uppercase', color: 'var(--content-3)', margin: '0 0 10px' };
const clH1 = { fontSize: 20, fontWeight: 800, marginBottom: 16 };
const clPrimaryBtn = { padding: '8px 14px', borderRadius: 8, background: 'var(--accent)', color: 'var(--accent-ink)', fontSize: 13, fontWeight: 700, textDecoration: 'none', whiteSpace: 'nowrap', border: 'none', fontFamily: 'inherit', cursor: 'pointer' };
const clField = { display: 'flex', flexDirection: 'column', gap: 6 };
const clInput = { minHeight: 40, display: 'flex', alignItems: 'center', padding: '0 12px', background: 'var(--surface-2)', border: '1px solid var(--border-control)', borderRadius: 8, fontSize: 14, color: 'var(--content-1)' };
const clLabel = { fontSize: 12, fontWeight: 600, color: 'var(--content-3)' };

const clNum = (n) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

const CL_NAV = [
  { id: 'dashboard', label: 'Tableau de bord' },
  { id: 'rdvs', label: 'Mes RDV' },
  { id: 'historique', label: 'Historique' },
  { id: 'motos', label: 'Mes motos' },
  { id: 'profil', label: 'Mon profil' },
];

function LegalFooter() {
  return (
    <footer style={{ textAlign: 'center', padding: 16, fontSize: 12, color: 'var(--content-3)' }}>
      <span>Mentions légales</span><span style={{ margin: '0 8px' }}>|</span><span>Politique de confidentialité</span>
    </footer>
  );
}

function ThemeToggle({ dark, onToggle, floating }) {
  const base = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 34, height: 34, borderRadius: 8, border: '1px solid var(--border-2)', background: 'var(--surface-1)', color: 'var(--content-3)', cursor: 'pointer', fontFamily: 'inherit' };
  const pos = floating ? { position: 'absolute', top: 16, right: 16, zIndex: 60 } : {};
  return (
    <button type="button" onClick={onToggle} title="Changer de thème" style={{ ...base, ...pos }}>
      <i className={dark ? 'ri-sun-line' : 'ri-moon-line'} style={{ fontSize: 17 }} />
    </button>
  );
}

function ClientLayout({ screen, onNav, dark, onToggleTheme, onLogout, logo, children }) {
  return (
    <div style={{ minHeight: '100%', color: 'var(--content-1)', background: 'var(--surface-0)', fontFamily: 'var(--mb-font-montserrat)' }}>
      <nav style={{ position: 'relative', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, padding: '14px 28px', background: 'var(--surface-1)', borderBottom: '1px solid var(--border-2)' }}>
        <span style={{ position: 'absolute', top: 0, left: 0, height: 3, width: 140, background: 'linear-gradient(90deg, var(--accent) 60%, transparent)', clipPath: 'polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%)' }} />
        <div style={{ display: 'flex', alignItems: 'center', color: 'var(--accent-content)' }}>
          <img src={logo} alt="Paddock" width="28" height="28" style={{ display: 'block', objectFit: 'contain', verticalAlign: 'middle', marginRight: 8 }} />
          <span style={{ fontFamily: 'var(--mb-font-montserrat)', fontWeight: 800, fontSize: 20, letterSpacing: '0.04em', textTransform: 'uppercase' }}>Mon Atelier</span>
        </div>
        <div style={{ display: 'flex', gap: 20, alignItems: 'center', fontSize: 14, marginLeft: 24, flexWrap: 'wrap' }}>
          {CL_NAV.map((n) => {
            const on = screen === n.id || (n.id === 'rdvs' && (screen === 'rdv' || screen === 'booking'));
            return (
              <button type="button" key={n.id} onClick={() => onNav(n.id)} style={{ ...clNavLink, color: on ? 'var(--accent-content)' : 'var(--content-3)' }}>
                {n.label}
                <span style={{ position: 'absolute', left: 0, bottom: -2, height: 2, width: '100%', background: 'var(--accent)', transform: on ? 'scaleX(1)' : 'scaleX(0)', transformOrigin: 'left', transition: 'transform 0.22s var(--pk-easing)' }} />
              </button>
            );
          })}
          <ThemeToggle dark={dark} onToggle={onToggleTheme} />
          <button type="button" onClick={onLogout} style={{ background: 'none', border: '1px solid var(--error)', color: 'var(--error-content)', padding: '6px 14px', borderRadius: 8, cursor: 'pointer', fontSize: 13, fontFamily: 'inherit' }}>Déconnexion</button>
        </div>
      </nav>
      <main style={{ maxWidth: 960, margin: '0 auto', padding: '28px 24px 56px' }}>{children}</main>
      <LegalFooter />
    </div>
  );
}

/* Carte de RDV — `components/RdvCard.vue`. Le statut porte le code, pas le
   libellé : la classe se déduit du code. */
const CL_STATUT_LABEL = {
  prevu: 'Prévu', confirme: 'Confirmé', receptionne: 'Réceptionné', en_cours: 'En cours',
  attente_pieces: 'Attente pièces', attente_accord: 'Attente accord', termine: 'Terminé',
  restitue: 'Restitué', facture: 'Facturé', paye: 'Payé', annule: 'Annulé', no_show: 'Non présenté',
};
function rdvStatutLabel(s) { return CL_STATUT_LABEL[s] || s; }
function statusTone(s) {
  if (['termine', 'restitue', 'restitue_partiel', 'facture', 'paye', 'livre'].indexOf(s) !== -1) return { background: 'var(--success-soft)', color: 'var(--success-content)' };
  if (['annule', 'no_show'].indexOf(s) !== -1) return { background: 'var(--error-soft)', color: 'var(--error-content)' };
  return { background: 'var(--info-soft)', color: 'var(--info-content)' };
}
const clStatusPill = { fontSize: 12, fontWeight: 700, padding: '4px 10px', borderRadius: 20, textTransform: 'uppercase', whiteSpace: 'nowrap' };

function RdvCard({ rdv, onOpen }) {
  return (
    <button type="button" onClick={onOpen} style={{ ...clCard, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, width: '100%', textAlign: 'left', color: 'inherit', fontFamily: 'inherit', cursor: 'pointer' }}>
      <div>
        <div style={{ fontWeight: 600, fontSize: 14 }}>{rdv.date}</div>
        {rdv.vehicule ? <div style={{ marginTop: 2, fontSize: 12, color: 'var(--content-3)' }}>{rdv.vehicule}</div> : null}
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {rdv.annulationDemandee ? <span style={{ fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 6, background: 'var(--warning-soft)', border: '1px solid var(--warning)', color: 'var(--warning-content)', whiteSpace: 'nowrap' }}>Annulation demandée</span> : null}
        <span style={{ ...clStatusPill, ...statusTone(rdv.statut) }}>{rdvStatutLabel(rdv.statut)}</span>
      </div>
    </button>
  );
}

/* Frise de suivi — `components/RdvTimeline.vue`. Le dernier point est courant :
   pastille jaune et halo. */
function RdvTimeline({ steps }) {
  return (
    <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column' }}>
      {steps.map((s, i) => {
        const current = i === steps.length - 1;
        return (
          <li key={s.statut + i} style={{ position: 'relative', display: 'flex', gap: 12, padding: current ? 0 : '0 0 18px 0' }}>
            {current ? null : <span style={{ position: 'absolute', left: 5, top: 14, bottom: 0, width: 2, background: 'var(--surface-3)' }} />}
            <span style={{ flex: 'none', width: 12, height: 12, marginTop: 3, borderRadius: '50%', background: current ? 'var(--accent)' : 'var(--surface-3)', border: '2px solid ' + (current ? 'var(--accent-graphic)' : 'var(--border-1)'), boxShadow: current ? '0 0 10px rgba(241,171,0,0.55)' : 'none' }} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
              <span style={{ fontSize: 14, fontWeight: 600, color: current ? 'var(--accent-content)' : 'var(--content-2)' }}>{rdvStatutLabel(s.statut)}</span>
              <time style={{ fontSize: 12, color: 'var(--content-3)' }}>{s.date}</time>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

Object.assign(window, { clNum, ClientLayout, LegalFooter, ThemeToggle, RdvCard, RdvTimeline, rdvStatutLabel, statusTone, CL_NAV, clCard, clSectionTitle, clH1, clPrimaryBtn, clField, clInput, clLabel, clStatusPill });
