import VoucherInventoryView from './views/VoucherInventoryView.vue';

export default [
  {
    path: '/voucher-inventory',
    name: 'voucher-inventory',
    component: VoucherInventoryView,
    meta: { requiresAuth: true, requiredModule: 'voucher_inventory', title: 'Voucher Inventory' },
  },
];
