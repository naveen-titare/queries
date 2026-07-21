import { createRouter, createWebHistory } from 'vue-router';
import authRoutes from '../modules/auth/router';
import customerRoutes from '../modules/customers/router';
import voucherInventoryRoutes from '../modules/voucher-inventory/router';
import { useAuthStore } from '../modules/auth/store/authStore';
import sendVoucherRoutes from '../modules/send-vouchers/router'; // NEW
import orderHistoryRoutes from '../modules/order-history/router';
import campaignRoutes from '../modules/campaigns/router';
import settingsRoutes from '../modules/settings/router';
import billingRoutes from '../modules/billing/router';


const routes = [
  ...authRoutes,
  ...customerRoutes,
  ...voucherInventoryRoutes,
  ...sendVoucherRoutes,
  ...orderHistoryRoutes,
  ...campaignRoutes,
  ...settingsRoutes,
  ...billingRoutes,

  {
    path: '/not-authorized',
    name: 'not-authorized',
    component: () => import('../modules/shared/views/NotAuthorizedView.vue'),
    meta: { requiresAuth: true, title: 'Not Authorized' },
  },

  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../modules/dashboard/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },

  // Public voucher verification page (no auth required)
  {
    path: '/verify/:codeId?',
    name: 'voucher-verify',
    component: () => import('../modules/public/views/VoucherVerifyView.vue'),
    meta: { requiresAuth: false },
  },

  {
    path: '/download-vouchers/:orderNumber?',
    name: 'voucher-delivery',
    component: () => import('../modules/public/views/VoucherDeliveryView.vue'),
    meta: { requiresAuth: false },
  },

  { path: '/', redirect: '/dashboard' },

  // Catch-all 404
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('../modules/shared/views/NotFoundView.vue'),
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!to.meta.requiresAuth) return true;

  if (auth.accessToken && auth.isAccessTokenExpired && auth.reauthToken) {
    auth.pendingReauth = true;
    return true;
  }

  if (!auth.isAuthenticated) {
    return { path: '/login' };
  }

  const requiredModule = to.meta.requiredModule;
  if (!requiredModule) return true;

  try {
    await auth.ensureUser();
    if (hasModuleAccess(auth.user, requiredModule)) return true;
    return { path: '/not-authorized', query: { module: requiredModule } };
  } catch {
    return { path: '/login' };
  }

  function hasModuleAccess(user, moduleKey) {
    if (!user) return false;
    if (user.is_admin) return true;
    if (['dashboard', 'settings'].includes(moduleKey)) return true;
    const modules = Array.isArray(user.module_access) ? user.module_access : [];
    return modules.some((module) => module?.key === moduleKey && module?.permissions?.view);
  }
});

export default router;
