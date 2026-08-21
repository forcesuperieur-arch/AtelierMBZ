const { PlanningGrid, AppointmentBlock, FilterPill, SidePanel, PanelSection, Button, Field, Callout } = window.PaddockDesignSystem_8059f4;

function ReceptionPanel({ onClose }) {
  return (
    <SidePanel icon="ri-inbox-line" title="Réception · 08:30" subtitle="Tracer 9 · GT-908-ZK · N. Belkacem" onClose={onClose}
      footer={<>
        <Button variant="primary" tone="accent" fullWidth onClick={onClose}>Réceptionner et placer sur le pont 2</Button>
        <div style={{ display: 'flex', gap: 8 }}>
          <Button variant="secondary" tone="neutral" size="small" fullWidth>Client absent</Button>
          <Button variant="secondary" tone="neutral" size="small" fullWidth style={{ borderColor: 'var(--pk-border-control)', fontWeight: 400 }}>Reporter</Button>
        </div>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center' }}>Le panneau se referme sur le planning, la case passe en « en cours ».</span>
      </>}>
      <PanelSection label="Travaux prévus">
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 }}>
          <i className="ri-check-line" style={{ fontSize: 16, color: 'var(--pk-success-line)' }} />
          <span style={{ flex: 1 }}>Révision 20 000 km</span><span style={{ color: 'var(--pk-ink-quiet)' }}>1 h 40</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13 }}>
          <i className="ri-alert-line" style={{ fontSize: 16, color: 'var(--pk-warning-ink-soft)' }} />
          <span style={{ flex: 1 }}>Plaquettes avant<span style={{ color: 'var(--pk-error-ink)' }}> · pièce en rupture</span></span>
          <span style={{ color: 'var(--pk-ink-quiet)' }}>1 h</span>
        </div>
        <Callout tone="warning">À dire au client maintenant : les plaquettes arrivent lundi, la moto ressort ce soir sans ce travail.</Callout>
      </PanelSection>

      <PanelSection label="État des lieux d’entrée">
        <div style={{ display: 'flex', gap: 8 }}>
          <PhotoSlot filled /><PhotoSlot filled />
          <PhotoSlot icon="ri-camera-line" label="Ajouter" /><PhotoSlot icon="ri-qr-code-line" label="Téléphone" />
        </div>
        <div style={{ display: 'flex', gap: 10 }}>
          <Field dense label="Compteur" value="28 412 km" style={{ flex: 1 }} />
          <Field dense label="Carburant" value="½ réservoir" endIcon="ri-arrow-down-s-line" style={{ flex: 1 }} />
        </div>
        <Field dense label="Remarques et dégâts constatés" value="Rayure réservoir côté droit. Top-case laissé sur la moto." />
        <div style={{ display: 'flex', gap: 7, flexWrap: 'wrap' }}>
          <FilterPill label="Top-case" selected /><FilterPill label="Casque" /><FilterPill label="Antivol" /><FilterPill label="2e clé" />
        </div>
      </PanelSection>

      <PanelSection label="Signature du client">
        <div style={{ height: 80, border: '1px dashed var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', background: 'var(--pk-surface-raised)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3, color: 'var(--pk-ink-muted)' }}>
          <i className="ri-pen-nib-line" style={{ fontSize: 22 }} />
          <span style={{ fontSize: 12 }}>Signer sur l’écran</span>
        </div>
      </PanelSection>
    </SidePanel>
  );
}

function HandoverPanel({ onClose }) {
  const lines = [
    ['Révision 20 000 km', '231,05 €'], ['Bougies · accord téléphone', '92,00 €'], ['Réglage de chaîne', '20,70 €'],
  ];
  return (
    <SidePanel icon="ri-key-2-line" title="Restitution · 15:30" subtitle="MT-09 · EX-421-QR · L. Renard" onClose={onClose}
      footer={<>
        <Callout tone="warning" icon="ri-calendar-check-line">Proposer jeudi 21 à 09:00 pour les plaquettes · le créneau est réservé 24 h</Callout>
        <Button variant="primary" tone="accent" fullWidth onClick={onClose}>Encaisser, restituer et poser le RDV</Button>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)', textAlign: 'center' }}>Libère le pont 1 dans la grille et écrit le nouveau RDV de jeudi.</span>
      </>}>
      <PanelSection label="Ce qui a été fait · montants HT">
        {lines.map(([l, v]) => (
          <div key={l} style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13 }}>
            <i className="ri-check-line" style={{ fontSize: 16, color: 'var(--pk-success-line)' }} />
            <span style={{ flex: 1 }}>{l}</span><span style={{ fontWeight: 600 }}>{v}</span>
          </div>
        ))}
        <div style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13, color: 'var(--pk-ink-muted)' }}>
          <i className="ri-close-circle-line" style={{ fontSize: 16 }} />
          <span style={{ flex: 1 }}>Plaquettes avant · non montées</span><span style={{ fontWeight: 600 }}>retiré</span>
        </div>
        <div style={{ height: 1, background: 'var(--pk-border-quiet)' }} />
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>Total HT</span><span style={{ fontWeight: 600 }}>343,75 €</span></div>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}><span>TVA 20 %</span><span style={{ fontWeight: 600 }}>68,75 €</span></div>
        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
          <span style={{ fontSize: 14, fontWeight: 600 }}>À encaisser</span><span style={{ fontSize: 22, fontWeight: 700 }}>412,50 €</span>
        </div>
      </PanelSection>

      <PanelSection label="Entrée / sortie" aside="28 412 → 28 419 km">
        <div style={{ display: 'flex', gap: 8 }}>
          <PhotoSlot filled /><PhotoSlot filled /><PhotoSlot icon="ri-camera-line" label="Sortie" /><PhotoSlot icon="ri-camera-line" label="Sortie" />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: 'var(--pk-ink-quiet)' }}>
          <i className="ri-alert-line" style={{ fontSize: 15, color: 'var(--pk-warning-ink-soft)' }} />
          Rayure réservoir déjà relevée à l’entrée · top-case rendu
        </div>
      </PanelSection>

      <PanelSection label="Encaissement et signature">
        <div style={{ display: 'flex', gap: 7 }}>
          <Button variant="primary" tone="accent" size="small" shape="square" startIcon="ri-bank-card-line" fullWidth>CB</Button>
          <Button variant="secondary" size="small" shape="square" fullWidth style={{ borderColor: 'var(--pk-border-control)', fontWeight: 400 }}>Espèces</Button>
          <Button variant="secondary" size="small" shape="square" fullWidth style={{ borderColor: 'var(--pk-border-control)', fontWeight: 400 }}>Chèque</Button>
        </div>
        <div style={{ height: 72, border: '1px dashed var(--pk-border-control)', borderRadius: 'var(--pk-radius-card)', background: 'var(--pk-surface-raised)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3, color: 'var(--pk-ink-muted)' }}>
          <i className="ri-pen-nib-line" style={{ fontSize: 20 }} />
          <span style={{ fontSize: 12 }}>Signature du client</span>
        </div>
      </PanelSection>
    </SidePanel>
  );
}

function PhotoSlot({ filled, icon, label }) {
  return filled ? (
    <div style={{ flex: 1, aspectRatio: '4 / 3', background: 'var(--pk-neutral-surface)', border: '1px solid var(--pk-border)', borderRadius: 'var(--pk-radius-tile)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--pk-ink-muted)' }}>
      <i className="ri-image-line" style={{ fontSize: 20 }} />
    </div>
  ) : (
    <div style={{ flex: 1, aspectRatio: '4 / 3', border: '1px dashed var(--pk-border-control)', borderRadius: 'var(--pk-radius-tile)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 2, color: 'var(--pk-ink-quiet)' }}>
      <i className={icon} style={{ fontSize: 18 }} />
      <span style={{ fontSize: 10 }}>{label}</span>
    </div>
  );
}

function PlanningScreen({ panel, onOpen, onClose }) {
  return (
    <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
      <div style={{ flex: 1, padding: '16px 18px', display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
          <FilterPill label="À réceptionner" count={5} selected />
          <FilterPill label="En cours" count={4} />
          <FilterPill label="À restituer" count={2} />
          <FilterPill label="Sans pont" count={1} dashed />
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--pk-ink-quiet)' }}>Charge du jour <strong style={{ fontWeight: 700 }}>6 h 20</strong> · 11 RDV</span>
        </div>
        <PlanningGrid hours={window.HOURS} bays={window.BAYS}>
          {window.APPOINTMENTS.map((a) => {
            const open = a.panel && a.panel === panel;
            return (
              <AppointmentBlock key={a.id} {...a}
                state={open ? 'open' : a.state}
                icon={open ? (a.panel === 'reception' ? 'ri-inbox-line' : 'ri-key-2-line') : a.icon}
                statusLabel={open ? (a.panel === 'reception' ? '08:30 · réception en cours' : '15:30 · restitution') : a.statusLabel}
                note={open ? 'Panneau ouvert →' : undefined}
                onClick={a.panel ? () => onOpen(a.panel) : undefined} />
            );
          })}
        </PlanningGrid>
        <span style={{ fontSize: 12, color: 'var(--pk-ink-muted)' }}>Cliquez la case de 08:30 ou celle de 15:30 : le travail se fait dans le panneau, la grille reste lisible derrière.</span>
      </div>
      {panel === 'reception' ? <ReceptionPanel onClose={onClose} /> : null}
      {panel === 'restitution' ? <HandoverPanel onClose={onClose} /> : null}
    </div>
  );
}

Object.assign(window, { PlanningScreen, ReceptionPanel, HandoverPanel, PhotoSlot });
