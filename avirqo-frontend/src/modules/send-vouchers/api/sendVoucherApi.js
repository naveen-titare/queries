import apiClient from '../../../shared/apiClient';

export default {
  getCatalog(params = {}) { return apiClient.get('/send-vouchers/catalog', { params }); },
  getProduct(id) { return apiClient.get(`/send-vouchers/catalog/${id}`); },
  validateCart(items) { return apiClient.post('/send-vouchers/cart/validate', { items }); },
  
  // Legacy direct order (without OTP)
  placeOrder(data) { return apiClient.post('/send-vouchers/orders', data); },
  
  // NEW OTP Flow
  // Step 1: Initiate order & send OTP
  initiateOrder(data) { return apiClient.post('/send-vouchers/orders/initiate', data); },
  // Step 2: Verify OTP
  verifyOrderOtp(orderNumber, otp) { return apiClient.post(`/send-vouchers/orders/${orderNumber}/verify-otp`, { otp }); },
  // Resend OTP
  resendOrderOtp(orderNumber) { return apiClient.post(`/send-vouchers/orders/${orderNumber}/resend-otp`); },
  // Cancel order
  cancelOrder(orderNumber) { return apiClient.post(`/send-vouchers/orders/${orderNumber}/cancel`); },
  
  getOrders(params = {}) { return apiClient.get('/send-vouchers/orders', { params }); },
  getOrder(id) { return apiClient.get(`/send-vouchers/orders/${id}`); },
};