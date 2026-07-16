<template>
  <div class="avirqo-auth-page">
    <div class="avirqo-auth-card">
      <div class="avirqo-auth-brand">avirq<span>o</span></div>
      <slot />
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

/* Design tokens copied from avirqo.com's own stylesheet */
.avirqo-auth-page {
  --teal-deep: #085041;
  --teal-mid: #1D9E75;
  --teal-light: #9FE1CB;
  --teal-pale: #E8F7F2;
  --ink: #0D0D0C;
  --ink-soft: #3A3A38;
  --ink-muted: #6B6A67;
  --surface-2: #F7FAF9;
  --border: #D5EAE3;
  --border-2: #E4EDE9;
  --fd: 'Fraunces', Georgia, serif;
  --fb: 'DM Sans', system-ui, sans-serif;

  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(160deg, #ffffff 50%, #eaf6f1 100%);
  font-family: var(--fb);
}

.avirqo-auth-card {
  width: 100%;
  max-width: 400px;
  background: #ffffff;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  padding: 40px 36px;
  box-shadow: 0 24px 80px rgba(8, 80, 65, 0.12), 0 4px 16px rgba(0, 0, 0, 0.06);
}

.avirqo-auth-brand {
  font-family: var(--fb);
  font-size: 24px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.04em;
  margin-bottom: 28px;
}

.avirqo-auth-brand span {
  color: var(--teal-mid);
}

.avirqo-auth-card h2 {
  font-family: var(--fd);
  font-size: 24px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.avirqo-auth-card p {
  color: var(--ink-soft);
  font-size: 14px;
  font-weight: 300;
  line-height: 1.6;
  margin: 0 0 20px;
}

.avirqo-auth-form input {
  width: 100%;
  box-sizing: border-box;
  padding: 12px 14px;
  border: 1.5px solid var(--border-2);
  border-radius: 8px;
  font-family: var(--fb);
  font-size: 15px;
  margin-bottom: 14px;
  outline: none;
  transition: border-color 0.2s ease;
  background: #fff;
  color: var(--ink);
}

.avirqo-auth-form input:focus {
  border-color: var(--teal-mid);
}

.avirqo-auth-form input[inputmode='numeric'] {
  text-align: center;
  letter-spacing: 0.4em;
  font-size: 20px;
  font-weight: 600;
}

.avirqo-auth-button {
  width: 100%;
  padding: 14px;
  background: var(--teal-deep);
  color: #ffffff;
  border: none;
  border-radius: 10px;
  font-family: var(--fb);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.avirqo-auth-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.avirqo-auth-button:not(:disabled):hover {
  background: var(--teal-mid);
}

.avirqo-auth-error {
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 13px;
  margin-top: 14px;
}

.avirqo-auth-hint {
  color: var(--ink-muted);
  font-size: 13px;
  margin-top: 14px;
  text-align: center;
  line-height: 1.6;
}

.avirqo-auth-link-button {
  background: none;
  border: none;
  color: var(--teal-deep);
  font-family: var(--fb);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  text-decoration-color: var(--teal-light);
}

.avirqo-auth-link-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.avirqo-auth-qr {
  display: block;
  width: 200px;
  height: 200px;
  margin: 0 auto 20px;
  border-radius: 12px;
  border: 1px solid var(--border-2);
  padding: 12px;
  background: #fff;
}

.avirqo-auth-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--teal-mid);
  background: var(--teal-pale);
  border: 1px solid var(--border);
  padding: 6px 14px;
  border-radius: 100px;
  margin-bottom: 20px;
}
</style>
