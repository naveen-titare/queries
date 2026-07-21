import VoucherCampaignsView from './views/VoucherCampaignsView.vue';

export default [
  {
    path: '/voucher-campaigns',
    name: 'voucher-campaigns',
    component: VoucherCampaignsView,
    meta: { requiresAuth: true, requiredModule: 'campaigns', title: 'Voucher Campaigns' },
  },
];
