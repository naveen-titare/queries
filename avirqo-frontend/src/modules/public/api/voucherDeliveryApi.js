import apiClient from '../../../shared/apiClient';

export default {
  getOrder(orderNumber) {
    return apiClient.get(`/public/send-vouchers/orders/${orderNumber}/delivery`);
  },
  requestOtp(orderNumber, payload) {
    return apiClient.post(`/public/send-vouchers/orders/${orderNumber}/delivery/request-otp`, payload);
  },
  verifyOtp(orderNumber, payload) {
    return apiClient.post(`/public/send-vouchers/orders/${orderNumber}/delivery/verify-otp`, payload);
  },
  download(orderNumber, payload) {
    return apiClient.post(`/public/send-vouchers/orders/${orderNumber}/delivery/download`, payload, {
      responseType: 'blob',
    });
  },
};
