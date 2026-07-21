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
  resendOtp(id) {
    return apiClient.post(`/send-vouchers/orders/${id}/resend-otp`);
  },
  verifyOtp(id, otp) {
    return apiClient.post(`/send-vouchers/orders/${id}/verify-otp`, { otp });
  },
  cancelOrder(id) {
    return apiClient.post(`/send-vouchers/orders/${id}/cancel`);
  },
  initiateSpocSwitch(orderNumber, spocId) {
    return apiClient.post(`/send-vouchers/orders/${orderNumber}/switch-spoc/initiate`, { spoc_id: spocId });
  },
  verifySpocSwitch(orderNumber, requestId, otp) {
    return apiClient.post(`/send-vouchers/orders/${orderNumber}/switch-spoc/verify`, { request_id: requestId, otp });
  },
};
