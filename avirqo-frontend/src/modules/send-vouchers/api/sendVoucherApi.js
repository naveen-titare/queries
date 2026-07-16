import apiClient from '../../../shared/apiClient';

export default {
  getCatalog(params = {}) { return apiClient.get('/send-vouchers/catalog', { params }); },
  getProduct(id) { return apiClient.get(`/send-vouchers/catalog/${id}`); },
  validateCart(items) { return apiClient.post('/send-vouchers/cart/validate', { items }); },
  placeOrder(data) { return apiClient.post('/send-vouchers/orders', data); },
  getOrders(params = {}) { return apiClient.get('/send-vouchers/orders', { params }); },
  getOrder(id) { return apiClient.get(`/send-vouchers/orders/${id}`); },
};
