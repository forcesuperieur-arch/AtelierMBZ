/* Les trois e-mails clients, dans leur gouttière de 600 px. */
function PaddockEmailsApp(props) {
  const p = props || {};
  const logo = p.logo || '../../assets/paddock-logo-favicon.svg';
  const one = p.mail;
  const all = [
    { id: 'devis', el: <window.QuoteMail logo={logo} /> },
    { id: 'facture', el: <window.InvoiceMail logo={logo} /> },
    { id: 'rappel', el: <window.ReminderMail logo={logo} /> },
  ];
  const shown = one && one !== 'les trois' ? all.filter((m) => m.id === one) : all;
  return <div style={{ display: 'flex', gap: 24, alignItems: 'flex-start', fontFamily: 'var(--mb-font-montserrat)', color: 'var(--pk-ink)' }}>{shown.map((m) => <React.Fragment key={m.id}>{m.el}</React.Fragment>)}</div>;
}
Object.assign(window, { PaddockEmailsApp });
