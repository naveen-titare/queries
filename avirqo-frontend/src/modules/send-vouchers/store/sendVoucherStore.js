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
    cart: JSON.parse(sessionStorage.getItem('avirqo_send_vouchers_cart') || '[]'),

    // Pending OTP order — persisted so the banner shows if the user navigates away
    // from the confirm screen before verifying the OTP.
    pendingOrder: JSON.parse(sessionStorage.getItem('avirqo_pending_order') || 'null'),
    selectedPi: JSON.parse(sessionStorage.getItem('avq_sendv_pi') || 'null'),
  }),

  getters: {
    cartGrossTotal: (state) => state.cart.reduce((sum, i) => sum + i.denomination * i.quantity, 0),
    cartBaseTotal: (state) => state.cart.reduce((sum, i) => sum + i.denomination * i.quantity, 0),
    cartDiscountTotal: (state) => state.cart.reduce((sum, i) => sum + i.denomination * i.quantity * (i.discount_percentage || 0) / 100, 0),
    cartTotal: (state) => state.cart.reduce((sum, i) => sum + i.denomination * i.quantity * (1 - (i.discount_percentage || 0) / 100), 0),
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
          discount_percentage: Number(product.customer_discount_percentage || 0),
          global_margin_percentage: Number(product.global_margin_percentage || 0),
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

    setPiCart(pi) {
      this.selectedPi = pi;
      sessionStorage.setItem('avq_sendv_pi', JSON.stringify(pi));
      this.cart = (pi.items || [])
        .filter(item => Number(item.pending_quantity || 0) > 0)
        .map(item => {
          const available = Number(item.available_stock || 0);
          const pending = Number(item.pending_quantity || 0);
          const quantity = Math.min(pending, available);
          return {
            key: `${item.product_id}-${item.denomination}`,
            product_id: item.product_id,
            product_name: item.product_name,
            brand: item.brand,
            image_url: item.product?.image_url,
            denomination: Number(item.denomination),
            currency_code: item.currency_code || item.product?.currency_code || 'INR',
            quantity,
            available,
            pi_pending_quantity: pending,
            discount_percentage: Number(item.discount_percentage || 0),
            global_margin_percentage: Number(item.product?.global_margin_percentage || 0),
          };
        });
      this._saveCart();
    },

    clearSelectedPi() {
      this.selectedPi = null;
      sessionStorage.removeItem('avq_sendv_pi');
    },

    _saveCart() {
      sessionStorage.setItem('avirqo_send_vouchers_cart', JSON.stringify(this.cart));
    },

    async validateCart(customerId, pricingMode = 'product', proformaInvoiceId = null) {
      const items = this.cart.filter(i => Number(i.quantity || 0) > 0).map(i => ({
        product_id: i.product_id,
        denomination: i.denomination,
        quantity: i.quantity,
      }));
      return await sendVoucherApi.validateCart(items, customerId, pricingMode, proformaInvoiceId);
    },

    // Legacy direct order placement (without OTP) - kept for backward compatibility
    async placeOrder(customerId, spocId) {
      const items = this.cart.filter(i => Number(i.quantity || 0) > 0).map(i => ({
        product_id: i.product_id,
        denomination: i.denomination,
        quantity: i.quantity,
      }));
      const { data } = await sendVoucherApi.placeOrder({ customer_id: customerId, spoc_id: spocId, items });
      this.clearCart();
      return data;
    },

    // NEW: Step 1 - Initiate Order & Send OTP
    async initiateOrder(customerId, spocId, items, pricingMode = 'product', invoiceDiscountPercentage = 0, proformaInvoiceId = null) {
      const { data } = await sendVoucherApi.initiateOrder({ customer_id: customerId, spoc_id: spocId, proforma_invoice_id: proformaInvoiceId, items, pricing_mode: pricingMode, invoice_discount_percentage: invoiceDiscountPercentage });
      // Persist pending order so the banner shows if the user navigates away
      this.setPendingOrder({
        orderNumber: data.order.order_number,
        orderId: data.order.id,
        customerName: data.order.customer?.company_name,
        spocEmail: data.order.email_sent_to,
        total: data.order.total_amount,
      });
      return data;
    },

    // NEW: Step 2 - Verify OTP & Complete Order
    async verifyOrderOtp(orderNumber, otp) {
      const { data } = await sendVoucherApi.verifyOrderOtp(orderNumber, otp);
      this.clearCart();
      this.clearSelectedPi();
      this.clearPendingOrder();
      return data;
    },

    // NEW: Resend OTP
    async resendOrderOtp(orderNumber) {
      const { data } = await sendVoucherApi.resendOrderOtp(orderNumber);
      return data;
    },

    // NEW: Cancel order and restore balance
    async cancelOrder(orderNumber) {
      const { data } = await sendVoucherApi.cancelOrder(orderNumber);
      this.clearPendingOrder();
      return data;
    },

    // Persist pending order for cross-page banner
    setPendingOrder(order) {
      this.pendingOrder = order;
      sessionStorage.setItem('avirqo_pending_order', JSON.stringify(order));
    },

    clearPendingOrder() {
      this.pendingOrder = null;
      sessionStorage.removeItem('avirqo_pending_order');
    },

    // Check if pending order is still pending_otp
    async checkPendingOrderStatus() {
      if (!this.pendingOrder?.orderId) return null;
      try {
        const { data } = await sendVoucherApi.getOrder(this.pendingOrder.orderId);
        return data.status;
      } catch (e) {
        return null;
      }
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
