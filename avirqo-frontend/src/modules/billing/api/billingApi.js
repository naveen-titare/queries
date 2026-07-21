import apiClient from '../../../shared/apiClient';

export default {
  customers(params = {}) { return apiClient.get('/billing/customers', { params }); },
  customerCampaignDiscounts(customerId) { return apiClient.get(`/billing/customers/${customerId}/campaign-discounts`); },
  customerCreditNotes(customerId, params = {}) { return apiClient.get(`/billing/customers/${customerId}/credit-notes`, { params }); },
  products(params = {}) { return apiClient.get('/billing/products', { params }); },
  proformas(params = {}) { return apiClient.get('/billing/proforma-invoices', { params }); },
  createProforma(data) { return apiClient.post('/billing/proforma-invoices', data); },
  updateProforma(id, data) { return apiClient.put(`/billing/proforma-invoices/${id}`, data); },
  finalizeProforma(id) { return apiClient.post(`/billing/proforma-invoices/${id}/finalize`); },
  cancelProforma(id) { return apiClient.post(`/billing/proforma-invoices/${id}/cancel`); },
  requestCancelProformaOtp(id) { return apiClient.post(`/billing/proforma-invoices/${id}/cancel-otp`); },
  resendCancelProformaOtp(id, requestId) { return apiClient.post(`/billing/proforma-invoices/${id}/cancel-otp/resend`, { request_id: requestId }); },
  verifyCancelProformaOtp(id, payload) { return apiClient.post(`/billing/proforma-invoices/${id}/cancel-otp/verify`, payload); },
  paidProformasForCustomer(customerId) { return apiClient.get(`/billing/proforma-invoices/paid/customer/${customerId}`); },
  payments(params = {}) { return apiClient.get('/billing/payments', { params }); },
  capturePayment(formData) { return apiClient.post('/billing/payments', formData, { headers: { 'Content-Type': 'multipart/form-data' } }); },
  invalidatePayment(id, reason) { return apiClient.post(`/billing/payments/${id}/invalidate`, { reason }); },
  taxInvoices(params = {}) { return apiClient.get('/billing/tax-invoices', { params }); },
  notes(params = {}) { return apiClient.get('/billing/credit-debit-notes', { params }); },
  pendingProformasForCreditNote(id) { return apiClient.get(`/billing/credit-debit-notes/${id}/pending-proformas`); },
  applyCreditNoteToPiBalance(id, payload) { return apiClient.post(`/billing/credit-debit-notes/${id}/apply-to-pi-balance`, payload); },
  approvers() { return apiClient.get('/billing/otp-approvers'); },
  saveApprovers(groups) { return apiClient.put('/billing/otp-approvers', { groups }); },
  emailInternal(type, id, payload) { return apiClient.post(`/billing/documents/${type}/${id}/email-internal`, payload); },
  documentBlob(type, id) { return apiClient.get(`/billing/documents/${type}/${id}/download`, { responseType: 'blob' }); },
  downloadUrl(type, id) {
    return `${import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'}/billing/documents/${type}/${id}/download`;
  },
};
