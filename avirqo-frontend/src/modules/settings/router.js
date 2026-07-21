export default [
  {
    path: '/settings',
    name: 'settings',
    component: () => import('./views/SettingsView.vue'),
    meta: { requiresAuth: true, requiredModule: 'settings', title: 'Settings' },
  },
];
