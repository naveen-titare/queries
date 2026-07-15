import VoucherCatalogView from './views/VoucherCatalogView.vue';
import VoucherSendView from './views/VoucherSendView.vue';
import VoucherConfirmView from './views/VoucherConfirmView.vue';

export default [
  {
    path: '/vouchers',
    name: 'vouchers',
    component: VoucherCatalogView,
    meta: { requiresAuth: true },
  },
  {
    path: '/vouchers/send',
    name: 'vouchers-send',
    component: VoucherSendView,
    meta: { requiresAuth: true },
  },
  {
    path: '/vouchers/confirm',
    name: 'vouchers-confirm',
    component: VoucherConfirmView,
    meta: { requiresAuth: true },
  },
];
