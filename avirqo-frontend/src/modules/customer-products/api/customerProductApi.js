import apiClient from '../../../shared/apiClient';

export default {
  customers(params = {}) { return apiClient.get('/customers', { params }); },
  products(customerId, params = {}) { return apiClient.get(`/customers/${customerId}/products`, { params }); },
  save(customerId, products) { return apiClient.put(`/customers/${customerId}/products`, { products }); },
  available(customerId) { return apiClient.get(`/customers/${customerId}/products/available`); },
};
