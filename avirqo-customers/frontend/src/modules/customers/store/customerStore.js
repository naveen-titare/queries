import { defineStore } from 'pinia';
import customerApi from '../api/customerApi';

export const useCustomerStore = defineStore('customers', {
  state: () => ({
    list: [],
    pagination: null,
    current: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchList(params = {}) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await customerApi.list(params);
        this.list = data.data;
        this.pagination = { total: data.total, per_page: data.per_page, current_page: data.current_page, last_page: data.last_page };
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load customers.';
      } finally {
        this.loading = false;
      }
    },

    async fetchOne(id) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await customerApi.get(id);
        this.current = data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load customer.';
      } finally {
        this.loading = false;
      }
    },

    async create(payload) {
      const { data } = await customerApi.create(payload);
      this.list.unshift(data);
      return data;
    },

    async update(id, payload) {
      const { data } = await customerApi.update(id, payload);
      this.current = data;
      const idx = this.list.findIndex((c) => c.id === id);
      if (idx !== -1) this.list[idx] = { ...this.list[idx], ...data };
      return data;
    },

    async setStatus(id, status) {
      const { data } = await customerApi.setStatus(id, status);
      if (this.current?.id === id) this.current.status = data.status;
      const idx = this.list.findIndex((c) => c.id === id);
      if (idx !== -1) this.list[idx].status = data.status;
    },

    async adjustBalance(id, payload) {
      const { data } = await customerApi.adjustBalance(id, payload);
      if (this.current?.id === id) {
        this.current.balance = data.balance;
        this.current.balance_logs = [data.log, ...(this.current.balance_logs || [])];
      }
      const idx = this.list.findIndex((c) => c.id === id);
      if (idx !== -1) this.list[idx].balance = data.balance;
      return data;
    },

    async uploadDocument(id, file) {
      const { data } = await customerApi.uploadDocument(id, file);
      if (this.current?.id === id) {
        this.current.documents = [data, ...(this.current.documents || [])];
      }
      return data;
    },

    async deleteDocument(customerId, docId) {
      await customerApi.deleteDocument(customerId, docId);
      if (this.current?.id === customerId) {
        this.current.documents = this.current.documents.filter((d) => d.id !== docId);
      }
    },
  },
});
