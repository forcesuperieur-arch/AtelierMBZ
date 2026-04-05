:root {
  --orange: #FFD200;
  --orange2: #FFC400;
  --dark: #0F0F10;
  --dark2: #171719;
  --dark3: #212126;
  --dark4: #2D2D33;
  --gray: #8B8B96;
  --gray2: #C8C8CF;
  --green: #22C55E;
  --amber: #F59E0B;
  --red: #EF4444;
  --blue: #3B82F6;
  --purple: #8B5CF6;
  --teal: #14B8A6;
}

body {
  font-family: 'Barlow', sans-serif;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 6px;
  border: 1px solid transparent;
  cursor: pointer;
  font-family: 'Barlow', sans-serif;
  font-weight: 600;
  line-height: 1;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, opacity 0.2s ease;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--orange);
  color: #111;
}

.btn-primary:hover {
  background: var(--orange2);
}

.btn-secondary {
  background: var(--dark3);
  color: #fff;
  border-color: var(--dark4);
}

.btn-secondary:hover {
  background: var(--dark4);
}

.btn-outline {
  background: transparent;
  color: var(--orange);
  border-color: var(--orange);
}

.btn-outline:hover {
  background: rgba(255, 210, 0, 0.16);
}

.card {
  background: var(--dark2);
  border: 1px solid var(--dark4);
  border-radius: 10px;
  padding: 16px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
}

.card-title {
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 10px;
  color: #fff;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.badge-success { background: rgba(34, 197, 94, 0.15); color: #86efac; }
.badge-warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
.badge-danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
.badge-info { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }

.table {
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
  color: #fff;
}

.table th,
.table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--dark4);
  text-align: left;
}

.table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--gray2);
  background: var(--dark2);
}

.table tr:hover td {
  background: rgba(255, 255, 255, 0.02);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.form-label {
  font-size: 13px;
  color: var(--gray2);
  font-weight: 600;
}

.form-control,
input.form-control,
select.form-control,
textarea.form-control {
  width: 100%;
  background: var(--dark3);
  border: 1px solid var(--dark4);
  border-radius: 8px;
  color: #fff;
  padding: 10px 12px;
  font-family: 'Barlow', sans-serif;
}

.form-control:focus {
  outline: none;
  border-color: var(--orange);
  box-shadow: 0 0 0 2px rgba(232, 72, 10, 0.2);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 40;
}

.modal {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  z-index: 50;
}

.modal-content {
  width: min(640px, 100%);
  background: var(--dark2);
  border: 1px solid var(--dark4);
  border-radius: 12px;
  padding: 18px;
  color: #fff;
}
