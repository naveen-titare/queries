import apiClient from '../../../shared/apiClient';

export default {
  list(params = {}) { return apiClient.get('/customers', { params }); },
  get(id) { return apiClient.get(`/customers/${id}`); },
  create(data) { return apiClient.post('/customers', data); },
  update(id, data) { return apiClient.put(`/customers/${id}`, data); },
  setStatus(id, status) { return apiClient.patch(`/customers/${id}/status`, { status }); },
  adjustBalance(id, data) { return apiClient.post(`/customers/${id}/balance`, data); },
  uploadDocument(id, file) {
    const form = new FormData();
    form.append('document', file);
    return apiClient.post(`/customers/${id}/documents`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  deleteDocument(customerId, docId) {
    return apiClient.delete(`/customers/${customerId}/documents/${docId}`);
  },
  downloadUrl(customerId, docId) {
    return `${import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'}/customers/${customerId}/documents/${docId}/download`;
  },
};
