import apiClient from '../../../shared/apiClient';

export default {
  users(params = {}) {
    return apiClient.get('/settings/users', { params });
  },
  createUser(payload) {
    return apiClient.post('/settings/users', payload);
  },
  updateUser(id, payload) {
    return apiClient.put(`/settings/users/${id}`, payload);
  },
  resetPassword(id) {
    return apiClient.post(`/settings/users/${id}/reset-password`);
  },
  profile() {
    return apiClient.get('/settings/profile');
  },
  updateProfile(formData) {
    formData.append('_method', 'PUT');
    return apiClient.post('/settings/profile', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  changePassword(payload) {
    return apiClient.post('/settings/profile/password', payload);
  },
};
