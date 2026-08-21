One-line: the Ponts & Méca card — pilot a bay (activate, attach a mechanic) and read its day without leaving the screen. `BayCard` is the read-only version used on Stat.

```jsx
<BayControlCard name="Pont 4" state="conflict" spec="Type ATELIER · 350 kg" mechanic="Sophie L."
  programme={[
    { time: '08:00', label: 'MT-07 · Delaunay', status: 'Terminé', state: 'done' },
    { time: '11:00', label: 'R1250 GS · chevauchement', status: 'À arbitrer', tone: 'error' },
  ]} />
<BayControlCard name="Pont 6" state="maintenance" spec="Type ATELIER · 350 kg" mechanic="Thomas B."
  note="Le pont est exclu du taux d'occupation et du planning tant qu'il est désactivé." />
```

- A deactivated bay must still say what happens to its capacity — that is the "montrer l'effet avant d'enregistrer" rule applied to a toggle.
- The status word in the programme is written, never a colour alone.
