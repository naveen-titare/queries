import apiClient from '../../../shared/apiClient';
export default {
  list: () => apiClient.get('/voucher-campaigns'), create: (data) => apiClient.post('/voucher-campaigns', data),
  products: (id) => apiClient.get(`/voucher-campaigns/${id}/products`), saveProducts: (id, products) => apiClient.put(`/voucher-campaigns/${id}/products`, { products }),
  customers: (id) => apiClient.get(`/voucher-campaigns/${id}/customers`), saveCustomers: (id, customer_ids) => apiClient.put(`/voucher-campaigns/${id}/customers`, { customer_ids }),
  allCustomers: () => apiClient.get('/customers', { params: { status: 'active' } }),
  update: (id, data) => apiClient.put(`/voucher-campaigns/${id}`, data),
  verifyCampaignOtp: (id, requestId, otp) => apiClient.post(`/voucher-campaigns/${id}/otp/verify`, { request_id: requestId, otp }),
  resendCampaignOtp: (id, requestId) => apiClient.post(`/voucher-campaigns/${id}/otp/resend`, { request_id: requestId }),
  globalMargins: () => apiClient.get('/send-vouchers/global-margins'),
  saveGlobalMargins: (products) => apiClient.put('/send-vouchers/global-margins', { products }),
  verifyGlobalMarginsOtp: (requestId, otp) => apiClient.post('/send-vouchers/global-margins/verify', { request_id: requestId, otp }),
  resendGlobalMarginsOtp: (requestId) => apiClient.post('/send-vouchers/global-margins/resend-otp', { request_id: requestId }),
};
