import axios from 'axios';

const api = () => axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${localStorage.getItem('avirqo_access_token')}`,
  },
});

export default {
  list(params = {}) { return api().get('/customers', { params }); },
  get(id) { return api().get(`/customers/${id}`); },
  create(data) { return api().post('/customers', data); },
  update(id, data) { return api().put(`/customers/${id}`, data); },
  setStatus(id, status) { return api().patch(`/customers/${id}/status`, { status }); },
  adjustBalance(id, data) { return api().post(`/customers/${id}/balance`, data); },

  uploadDocument(id, file) {
    const form = new FormData();
    form.append('document', file);
    return api().post(`/customers/${id}/documents`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  deleteDocument(customerId, docId) {
    return api().delete(`/customers/${customerId}/documents/${docId}`);
  },
  downloadUrl(customerId, docId) {
    return `${import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'}/customers/${customerId}/documents/${docId}/download`;
  },
};
