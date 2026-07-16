import apiClient from '../../../shared/apiClient';

export default {
  getCatalog(params = {}) { return apiClient.get('/vouchers/catalog', { params }); },
  getProduct(id) { return apiClient.get(`/vouchers/catalog/${id}`); },
  validateCart(items) { return apiClient.post('/vouchers/cart/validate', { items }); },
  placeOrder(data) { return apiClient.post('/vouchers/orders', data); },
  getOrders(params = {}) { return apiClient.get('/vouchers/orders', { params }); },
  getOrder(id) { return apiClient.get(`/vouchers/orders/${id}`); },
};
