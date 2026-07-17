import OrderHistoryView from './views/OrderHistoryView.vue';

export default [
  {
    path: '/order-history',
    name: 'order-history',
    component: OrderHistoryView,
    meta: { requiresAuth: true },
  },
];
