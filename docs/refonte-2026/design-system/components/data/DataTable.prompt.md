One-line: the dense record list — Explorer results, clients, invoices, audit log.

```jsx
<DataTable
  columns={[
    { key: 'client', header: 'Client', width: '1.3fr', strong: true },
    { key: 'moto', header: 'Moto', width: '1.2fr', quiet: true },
    { key: 'spend', header: 'Dépensé', width: '120px', align: 'right', strong: true },
  ]}
  rows={rows}
  footer={<><span>59 autres clients</span><div style={{ flex: 1 }} /><Button variant="secondary" size="small">Exporter en CSV</Button></>} />
```

- Any selection must be exportable and turnable into an action list — otherwise it is one more number.
