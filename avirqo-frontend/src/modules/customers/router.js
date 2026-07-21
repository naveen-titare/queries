import CustomersView from './views/CustomersView.vue';

export default [
  {
    path: '/customers',
    name: 'customers',
    component: CustomersView,
    meta: { requiresAuth: true, requiredModule: 'customers', title: 'Customers' },
  },
];
