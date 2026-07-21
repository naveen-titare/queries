import CustomerProductsView from './views/CustomerProductsView.vue';

export default [{
  path: '/customer-products',
  name: 'customer-products',
  component: CustomerProductsView,
  meta: { requiresAuth: true },
}];
