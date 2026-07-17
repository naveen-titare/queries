import apiClient from '../../../shared/apiClient';

export default {
  getOrders(params = {}) {
    return apiClient.get('/send-vouchers/orders', { params });
  },
  getOrder(id) {
    return apiClient.get(`/send-vouchers/orders/${id}`);
  },
  resendEmail(id) {
    return apiClient.post(`/send-vouchers/orders/${id}/resend-email`);
  },
};
