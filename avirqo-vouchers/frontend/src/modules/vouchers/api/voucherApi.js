import axios from 'axios';

const api = () => axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${localStorage.getItem('avirqo_access_token')}`,
  },
});

export default {
  getCatalog(params = {}) { return api().get('/vouchers/catalog', { params }); },
  getProduct(id) { return api().get(`/vouchers/catalog/${id}`); },
  validateCart(items) { return api().post('/vouchers/cart/validate', { items }); },
  placeOrder(data) { return api().post('/vouchers/orders', data); },
  getOrders(params = {}) { return api().get('/vouchers/orders', { params }); },
  getOrder(id) { return api().get(`/vouchers/orders/${id}`); },
};
