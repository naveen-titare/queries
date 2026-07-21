import SendVoucherCatalogView from './views/SendVoucherCatalogView.vue';
import SendVoucherSendView from './views/SendVoucherSendView.vue';
import SendVoucherConfirmView from './views/SendVoucherConfirmView.vue';

export default [
  {
    path: '/send-vouchers',
    name: 'send-vouchers',
    component: SendVoucherCatalogView,
    meta: { requiresAuth: true, requiredModule: 'send_vouchers', title: 'Send Vouchers' },
  },
  {
    path: '/send-vouchers/send',
    name: 'send-vouchers-send',
    component: SendVoucherSendView,
    meta: { requiresAuth: true, requiredModule: 'send_vouchers', title: 'Send Vouchers - Select Customer' },
  },
  {
    path: '/send-vouchers/confirm',
    name: 'send-vouchers-confirm',
    component: SendVoucherConfirmView,
    meta: { requiresAuth: true, requiredModule: 'send_vouchers', title: 'Send Vouchers - Confirm' },
  },
];
