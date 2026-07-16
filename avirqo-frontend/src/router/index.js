import { createRouter, createWebHistory } from 'vue-router';
import authRoutes from '../modules/auth/router';
import customerRoutes from '../modules/customers/router';
import voucherRoutes from '../modules/vouchers/router';
import { useAuthStore } from '../modules/auth/store/authStore';
import sendVoucherRoutes from '../modules/send-vouchers/router'; // NEW


const routes = [
  ...authRoutes,
  ...customerRoutes,
  ...voucherRoutes,
  ...sendVoucherRoutes,

  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../modules/dashboard/views/DashboardView.vue'),
    meta: { requiresAuth: true },
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

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (!to.meta.requiresAuth) return true;
  if (auth.isAuthenticated) return true;
  if (auth.reauthToken) {
    auth.pendingReauth = true;
    return { path: '/2fa' };
  }
  return { path: '/login' };
});

export default router;
