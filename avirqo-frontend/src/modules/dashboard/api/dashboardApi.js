import apiClient from '../../../shared/apiClient';

export default {
  summary(params = {}) {
    return apiClient.get('/dashboard/summary', { params });
  },
};
