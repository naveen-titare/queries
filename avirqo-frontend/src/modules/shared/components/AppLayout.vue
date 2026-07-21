<template>
  <div class="avq-app">

    <!-- Global Loader Overlay -->
    <Teleport to="body">
      <Transition name="avq-loader-fade">
        <div v-if="loader.isLoading" class="avq-loader-overlay">
          <div class="avq-loader-box">
            <div class="avq-loader-spinner"></div>
            <div class="avq-loader-text">{{ loader.message || 'Please wait…' }}</div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <aside class="avq-sidebar">
      <div class="avq-sidebar-logo">avirq<span>o</span></div>
      <nav class="avq-sidebar-nav">
        <RouterLink class="avq-nav-item" to="/dashboard" active-class="active">
          <span class="avq-nav-icon">🏠</span> Dashboard
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/customers" active-class="active">
          <span class="avq-nav-icon">🏢</span> Customers
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/voucher-campaigns" active-class="active">
          <span class="avq-nav-icon">🏷️</span> Voucher Campaigns
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/voucher-inventory" active-class="active">
          <span class="avq-nav-icon">🎁</span> Voucher Inventory
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/send-vouchers" active-class="active">
          <span class="avq-nav-icon">📨</span> Send Vouchers
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/order-history" active-class="active">
          <span class="avq-nav-icon">📋</span> Order History
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/billing" active-class="active">
          <span class="avq-nav-icon">🧾</span> Billing
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/settings" active-class="active">
          <span class="avq-nav-icon">⚙️</span> Settings
        </RouterLink>
      </nav>
      <button class="avq-sidebar-logout" @click="handleLogout">↩ Log out</button>
    </aside>

    <div class="avq-main">
      <header class="avq-topbar">
        <input class="avq-search" type="text" placeholder="Search…" />
        <div class="avq-topbar-right">
          <span class="avq-bell">🔔</span>
          <span class="avq-avatar">{{ initials }}</span>
        </div>
      </header>
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../auth/store/authStore';
import { useLoaderStore } from '../../shared/store/loaderStore';

const auth = useAuthStore();
const loader = useLoaderStore();
const router = useRouter();

const initials = computed(() => {
  const name = auth.user?.name || '?';
  return name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();
});

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

.avq-app {
  --teal-deep: #085041; --teal-mid: #1D9E75; --teal-light: #9FE1CB;
  --teal-pale: #E8F7F2; --ink: #0D0D0C; --ink-soft: #3A3A38;
  --ink-muted: #6B6A67; --ink-faint: #B4B2A9; --surface-2: #F7FAF9;
  --border-2: #E4EDE9; --fd: 'Fraunces', Georgia, serif;
  --fb: 'DM Sans', system-ui, sans-serif;
  display: flex; min-height: 100vh; background: var(--surface-2);
  font-family: var(--fb); color: var(--ink);
}

/* ── Global Loader ─────────────────────────────────────────── */
.avq-loader-overlay {
  position: fixed;
  inset: 0;
  background: rgba(8, 80, 65, 0.45);
  backdrop-filter: blur(3px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  /* Blocks ALL clicks underneath */
  pointer-events: all;
}

.avq-loader-box {
  background: #fff;
  border-radius: 16px;
  padding: 32px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  box-shadow: 0 24px 60px rgba(8, 80, 65, 0.2);
  min-width: 200px;
}

.avq-loader-spinner {
  width: 40px;
  height: 40px;
  border: 3.5px solid #E4EDE9;
  border-top-color: #085041;
  border-radius: 50%;
  animation: avq-spin 0.75s linear infinite;
}

@keyframes avq-spin {
  to { transform: rotate(360deg); }
}

.avq-loader-text {
  font-family: 'DM Sans', system-ui, sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: #085041;
  text-align: center;
}

/* Fade transition */
.avq-loader-fade-enter-active,
.avq-loader-fade-leave-active {
  transition: opacity 0.2s ease;
}
.avq-loader-fade-enter-from,
.avq-loader-fade-leave-to {
  opacity: 0;
}

/* ── Existing styles ───────────────────────────────────────── */
.avq-sidebar {
  width: 220px; flex-shrink: 0; background: var(--teal-deep); color: #fff;
  display: flex; flex-direction: column; padding: 24px 16px;
  position: sticky; top: 0; height: 100vh; overflow-y: auto;
}
.avq-sidebar-logo { font-weight: 700; font-size: 20px; letter-spacing: -0.04em; margin-bottom: 32px; padding-left: 8px; }
.avq-sidebar-logo span { color: var(--teal-light); }
.avq-sidebar-nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.avq-nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,0.75); text-decoration: none; font-size: 14px; font-weight: 500; transition: background 0.15s ease; }
.avq-nav-item:hover { background: rgba(255,255,255,0.08); }
.avq-nav-item.active { background: rgba(255,255,255,0.14); color: #fff; font-weight: 600; }
.avq-sidebar-logout { background: rgba(255,255,255,0.1); color: #fff; border: none; border-radius: 8px; padding: 10px 12px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s ease; text-align: left; }
.avq-sidebar-logout:hover { background: rgba(255,255,255,0.18); }
.avq-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.avq-topbar { height: 64px; background: #fff; border-bottom: 1px solid var(--border-2); display: flex; align-items: center; justify-content: space-between; padding: 0 28px; position: sticky; top: 0; z-index: 10; }
.avq-search { border: 1px solid var(--border-2); border-radius: 8px; padding: 9px 14px; font-size: 13px; width: 240px; outline: none; font-family: var(--fb); }
.avq-search:focus { border-color: var(--teal-mid); }
.avq-topbar-right { display: flex; align-items: center; gap: 16px; }
.avq-bell { font-size: 16px; }
.avq-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--teal-deep); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }

/* ── Shared modal system ─────────────────────────────────── */
.avq-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(8, 14, 16, 0.5);
  backdrop-filter: blur(2px);
  z-index: 120;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.avq-modal {
  background: #fff;
  border-radius: 18px;
  padding: 32px;
  width: 100%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  overflow-x: hidden;
  box-sizing: border-box;
  box-shadow: 0 28px 80px rgba(8, 14, 16, 0.18);
  border: 1px solid rgba(228, 237, 233, 0.9);
}

.avq-modal-sm { max-width: 460px; }
.avq-modal-lg { max-width: 1100px; }

.avq-modal h3 {
  font-family: var(--fd);
  font-size: 22px;
  margin-bottom: 18px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

.avq-btn-primary {
  background: var(--teal-deep);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  font-family: var(--fb);
}

.avq-btn-primary:hover {
  background: var(--teal-mid);
}

.avq-btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.avq-btn-ghost {
  background: transparent;
  color: var(--ink-soft);
  border: 1.5px solid var(--border-2);
  border-radius: 10px;
  padding: 10px 18px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  font-family: var(--fb);
}

.avq-btn-ghost:hover {
  background: var(--surface-2);
}

.avq-input {
  padding: 10px 14px;
  border: 1.5px solid var(--border-2);
  border-radius: 10px;
  font-size: 14px;
  outline: none;
  font-family: var(--fb);
  background: #fff;
  color: var(--ink);
}

.avq-input:focus {
  border-color: var(--teal-mid);
}
</style>
