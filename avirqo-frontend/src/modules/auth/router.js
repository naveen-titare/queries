import LoginView from './views/LoginView.vue';
import TwoFactorView from './views/TwoFactorView.vue';
import TwoFactorSetupView from './views/TwoFactorSetupView.vue';
import TwoFactorResetConfirmView from './views/TwoFactorResetConfirmView.vue';

export default [
  { path: '/login', name: 'login', component: LoginView },
  { path: '/2fa', name: '2fa', component: TwoFactorView },
  { path: '/2fa-setup', name: '2fa-setup', component: TwoFactorSetupView },
  { path: '/2fa/reset/:token', name: '2fa-reset-confirm', component: TwoFactorResetConfirmView },
];
