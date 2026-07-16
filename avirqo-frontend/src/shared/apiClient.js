import axios from 'axios';
import { getActivePinia } from 'pinia';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: { 'Content-Type': 'application/json' },
});

// --- Retry queue for GET requests that failed with 401 ---
let pendingQueue = [];
let isReauthing = false;

// Called by authStore.completeReauth() after successful token refresh
export function resolveReauthQueue() {
  isReauthing = false;
  const queue = [...pendingQueue];
  pendingQueue = [];
  queue.forEach(({ resolve, reject, config }) => {
    const token = localStorage.getItem('avirqo_access_token');
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
    // Remove the retry flag so it doesn't loop
    delete config._retried;
    resolve(apiClient(config));
  });
}

// Called if reauth fails (logout)
export function rejectReauthQueue(error) {
  isReauthing = false;
  const queue = [...pendingQueue];
  pendingQueue = [];
  queue.forEach(({ reject }) => reject(error));
}

// Attach token dynamically on every request
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('avirqo_access_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Handle 401
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const originalConfig = error.config;

    if (error.response?.status !== 401) return Promise.reject(error);
    if (originalConfig._retried) return Promise.reject(error);

    if (!getActivePinia()) return Promise.reject(error);

    return import('../modules/auth/store/authStore').then(({ useAuthStore }) => {
      const auth = useAuthStore();

      if (!auth.reauthToken) return Promise.reject(error);

      // Trigger the reauth modal
      auth.pendingReauth = true;

      // GET requests: queue and retry after reauth
      if (originalConfig.method === 'get') {
        originalConfig._retried = true;
        if (!isReauthing) isReauthing = true;

        return new Promise((resolve, reject) => {
          pendingQueue.push({ resolve, reject, config: originalConfig });
        });
      }

      // Non-GET (POST/PUT/PATCH/DELETE): just show modal, don't auto-retry
      return Promise.reject(error);
    });
  }
);

export default apiClient;
