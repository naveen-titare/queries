import { defineStore } from 'pinia';
import voucherApi from '../api/voucherApi';

export const useVoucherStore = defineStore('vouchers', {
  state: () => ({
    balance: null,        // { value, currency }
    catalog: [],          // normalised Xoxoday products
    inventory: [],        // grouped-by-brand inventory
    grandTotal: 0,
    history: [],
    loadingBalance: false,
    loadingCatalog: false,
    loadingInventory: false,
    importing: false,
    error: null,
  }),

  actions: {
    async fetchBalance() {
      this.loadingBalance = true;
      this.error = null;
      try {
        const { data } = await voucherApi.balance();
        this.balance = data.data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to fetch balance.';
      } finally {
        this.loadingBalance = false;
      }
    },

    async fetchCatalog() {
      this.loadingCatalog = true;
      this.error = null;
      try {
        const { data } = await voucherApi.catalog({ limit: 100 });
        this.catalog = data.data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to fetch vouchers from Xoxoday.';
      } finally {
        this.loadingCatalog = false;
      }
    },

    async importVoucher(selection) {
      this.importing = true;
      this.error = null;
      try {
        const { data } = await voucherApi.import(selection);
        await this.fetchInventory();
        await this.fetchBalance();
        return { ok: true, message: data.message };
      } catch (e) {
        const msg = e.response?.data?.message || 'Import failed.';
        this.error = msg;
        return { ok: false, message: msg };
      } finally {
        this.importing = false;
      }
    },

    async fetchInventory(q = '') {
      this.loadingInventory = true;
      try {
        const { data } = await voucherApi.inventory(q ? { q } : {});
        this.inventory = data.data;
        this.grandTotal = data.grand_total;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load inventory.';
      } finally {
        this.loadingInventory = false;
      }
    },

    async fetchHistory() {
      try {
        const { data } = await voucherApi.history({ per_page: 50 });
        this.history = data.data || data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load history.';
      }
    },
  },
});