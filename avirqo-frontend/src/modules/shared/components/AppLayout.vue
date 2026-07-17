<template>
  <div class="avq-app">
    <aside class="avq-sidebar">
      <div class="avq-sidebar-logo">avirq<span>o</span></div>
      <nav class="avq-sidebar-nav">
        <RouterLink class="avq-nav-item" to="/dashboard" active-class="active">
          <span class="avq-nav-icon">🏠</span> Dashboard
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/customers" active-class="active">
          <span class="avq-nav-icon">🏢</span> Customers
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/vouchers" active-class="active">
          <span class="avq-nav-icon">🎁</span> Vouchers
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/send-vouchers" active-class="active">
        <span class="avq-nav-icon">📨</span> Send Vouchers
        </RouterLink>
        <RouterLink class="avq-nav-item" to="/order-history" active-class="active">
        <span class="avq-nav-icon">📋</span> Order History
        </RouterLink>
        <a class="avq-nav-item" href="#"><span class="avq-nav-icon">⚙️</span> Settings</a>
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
    <SessionReauthModal />
  </div>
</template>
<script setup>
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../auth/store/authStore';
import SessionReauthModal from '../../auth/components/SessionReauthModal.vue';
const auth = useAuthStore();
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
</style>
