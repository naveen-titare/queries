import BillingView from './views/BillingView.vue';

const billingMeta = { requiresAuth: true, requiredModule: 'billing', title: 'Billing' };

export default [
  {
    path: '/billing',
    redirect: '/billing/proforma-invoices',
  },
  {
    path: '/billing/proforma-invoices',
    name: 'billing-proformas',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'proformas' },
  },
  {
    path: '/billing/tax-invoices',
    name: 'billing-tax-invoices',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'tax' },
  },
  {
    path: '/billing/payments',
    name: 'billing-payments',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'payments' },
  },
  {
    path: '/billing/credit-debit-notes',
    name: 'billing-notes',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'notes' },
  },
  {
    path: '/billing/reports',
    name: 'billing-reports',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'reports' },
  },
  {
    path: '/billing/otp-approvers',
    name: 'billing-approvers',
    component: BillingView,
    meta: { ...billingMeta, billingTab: 'approvers' },
  },
];
