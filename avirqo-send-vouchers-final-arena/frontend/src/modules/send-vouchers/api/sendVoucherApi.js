import axios from 'axios';

const api = () => axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${localStorage.getItem('avirqo_access_token')}`,
  },
});

export default {
  getCatalog(params = {}) { return api().get('/send-vouchers/catalog', { params }); },
  getProduct(id) { return api().get(`/send-vouchers/catalog/${id}`); },
  validateCart(items) { return api().post('/send-vouchers/cart/validate', { items }); },
  placeOrder(data) { return api().post('/send-vouchers/orders', data); },
  getOrders(params = {}) { return api().get('/send-vouchers/orders', { params }); },
  getOrder(id) { return api().get(`/send-vouchers/orders/${id}`); },
  retryOrder(id) { return api().post(`/send-vouchers/orders/${id}/retry`); },
};
