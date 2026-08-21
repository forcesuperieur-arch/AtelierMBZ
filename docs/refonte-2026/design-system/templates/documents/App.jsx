/* Les trois documents A4 qui sortent de l’atelier : OR, état des lieux, facture. */
const DOC_TABS = [
  { id: 'or', label: 'Ordre de réparation' },
  { id: 'etat', label: 'État des lieux' },
  { id: 'facture', label: 'Facture' },
];
const docTab = { padding: '7px 14px', borderRadius: 'var(--pk-radius-pill)', cursor: 'pointer', fontSize: 12, whiteSpace: 'nowrap', fontFamily: 'inherit' };

function PaddockDocumentsApp(props) {
  const p = props || {};
  const logo = p.logo || '../../assets/paddock-logo-stacked.svg';
  const [doc, setDoc] = React.useState(p.document || 'or');
  const El = { or: window.WorkOrderPaper, etat: window.ConditionPaper, facture: window.InvoicePaper }[doc] || window.WorkOrderPaper;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, alignItems: 'flex-start', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)' }}>
      <div style={{ display: 'flex', gap: 6 }}>
        {DOC_TABS.map((t) => (
          <button key={t.id} type="button" onClick={() => setDoc(t.id)}
            style={{ ...docTab, fontWeight: t.id === doc ? 600 : 400, background: t.id === doc ? '#000' : 'transparent', color: t.id === doc ? '#fff' : 'var(--pk-ink)', border: '1px solid ' + (t.id === doc ? '#000' : 'var(--pk-border-control)') }}>{t.label}</button>
        ))}
      </div>
      <El logo={logo} />
    </div>
  );
}
Object.assign(window, { PaddockDocumentsApp });
