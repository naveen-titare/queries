import { defineStore } from 'pinia';
import orderHistoryApi from '../api/orderHistoryApi';

export const useOrderHistoryStore = defineStore('orderHistory', {
  state: () => ({
    orders: [],
    pagination: null,
    currentOrder: null,
    loading: false,
    detailLoading: false,
    error: null,
  }),

  actions: {
    async fetchOrders(params = {}) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await orderHistoryApi.getOrders(params);
        this.orders = data.data;
        this.pagination = {
          total: data.total,
          per_page: data.per_page,
          current_page: data.current_page,
          last_page: data.last_page,
        };
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load orders.';
      } finally {
        this.loading = false;
      }
    },

    async fetchOrder(id) {
      this.detailLoading = true;
      try {
        const { data } = await orderHistoryApi.getOrder(id);
        this.currentOrder = data;
      } finally {
        this.detailLoading = false;
      }
    },

    async resendEmail(id) {
      const { data } = await orderHistoryApi.resendEmail(id);
      // Update the order in the list if present
      const idx = this.orders.findIndex(o => o.id === id);
      if (idx !== -1) this.orders[idx] = { ...this.orders[idx], ...data.order };
      if (this.currentOrder?.id === id) this.currentOrder = data.order;
      return data;
    },
  },
});
