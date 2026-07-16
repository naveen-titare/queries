import { defineStore } from 'pinia';
import sendVoucherApi from '../api/sendVoucherApi';

export const useSendVoucherStore = defineStore('send-vouchers', {
  state: () => ({
    catalog: [],
    pagination: null,
    currentProduct: null,
    orders: [],
    ordersPagination: null,
    loading: false,
    error: null,

    // Cart — persisted in sessionStorage so it survives page refreshes
    // Renamed key to avoid conflict with old 'vouchers' module
    cart: JSON.parse(sessionStorage.getItem('avirqo_send_vouchers_cart') || '[]'),
  }),

  getters: {
    cartTotal: (state) => state.cart.reduce((sum, i) => sum + i.denomination * i.quantity, 0),
    cartItemCount: (state) => state.cart.reduce((sum, i) => sum + i.quantity, 0),
  },

  actions: {
    async fetchCatalog(params = {}) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await sendVoucherApi.getCatalog(params);
        this.catalog = data.data;
        this.pagination = { total: data.total, per_page: data.per_page, current_page: data.current_page, last_page: data.last_page };
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load Send Vouchers catalog.';
      } finally {
        this.loading = false;
      }
    },

    async fetchProduct(id) {
      this.loading = true;
      try {
        const { data } = await sendVoucherApi.getProduct(id);
        this.currentProduct = data;
      } finally {
        this.loading = false;
      }
    },

    // Cart actions
    addToCart(product, denomination, quantity = 1) {
      const key = `${product.id}-${denomination}`;
      const existing = this.cart.find(i => i.key === key);
      if (existing) {
        existing.quantity += quantity;
      } else {
        this.cart.push({
          key,
          product_id: product.id,
          product_name: product.name,
          brand: product.brand,
          image_url: product.image_url,
          denomination,
          currency_code: product.currency_code,
          quantity,
          available: product.stock?.[denomination]?.available ?? 999,
        });
      }
      this._saveCart();
    },

    updateCartQty(key, quantity) {
      const item = this.cart.find(i => i.key === key);
      if (item) {
        if (quantity <= 0) {
          this.removeFromCart(key);
        } else {
          item.quantity = Math.min(quantity, item.available);
        }
        this._saveCart();
      }
    },

    removeFromCart(key) {
      this.cart = this.cart.filter(i => i.key !== key);
      this._saveCart();
    },

    clearCart() {
      this.cart = [];
      sessionStorage.removeItem('avirqo_send_vouchers_cart');
    },

    _saveCart() {
      sessionStorage.setItem('avirqo_send_vouchers_cart', JSON.stringify(this.cart));
    },

    async validateCart() {
      const items = this.cart.map(i => ({
        product_id: i.product_id,
        denomination: i.denomination,
        quantity: i.quantity,
      }));
      return await sendVoucherApi.validateCart(items);
    },

    async placeOrder(customerId, spocId) {
      const items = this.cart.map(i => ({
        product_id: i.product_id,
        denomination: i.denomination,
        quantity: i.quantity,
      }));
      const { data } = await sendVoucherApi.placeOrder({ customer_id: customerId, spoc_id: spocId, items });
      this.clearCart();
      return data;
    },

    async fetchOrders(params = {}) {
      this.loading = true;
      try {
        const { data } = await sendVoucherApi.getOrders(params);
        this.orders = data.data;
        this.ordersPagination = { total: data.total, current_page: data.current_page, last_page: data.last_page };
      } finally {
        this.loading = false;
      }
    },
  },
});
