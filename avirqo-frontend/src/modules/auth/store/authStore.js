import { defineStore } from 'pinia';
import authApi from '../api/authApi';
import { resolveReauthQueue, rejectReauthQueue } from '../../../shared/apiClient';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('avirqo_user') || 'null'),
    accessToken: localStorage.getItem('avirqo_access_token') || null,
    accessTokenExpiresAt: localStorage.getItem('avirqo_access_expires') || null,
    reauthToken: localStorage.getItem('avirqo_reauth_token') || null,

    challengeToken: null,
    pendingReauth: false,

    setupToken: null,
    otpauthUrl: null,
  }),

  getters: {
    isAccessTokenExpired: (state) => {
      if (!state.accessTokenExpiresAt) return true;
      return new Date(state.accessTokenExpiresAt) <= new Date();
    },
    isAuthenticated(state) {
      return !!state.accessToken && !this.isAccessTokenExpired;
    },
  },

  actions: {
    async login(email, password) {
      const { data } = await authApi.login(email, password);
      if (data.setup_required) {
        this.setupToken = data.setup_token;
        this.otpauthUrl = data.otpauth_url;
      } else {
        this.challengeToken = data.challenge_token;
      }
      return data;
    },

    async completeLogin(code) {
      const { data } = await authApi.verifyLogin2fa(this.challengeToken, code);
      this._persistSession(data);
      this.challengeToken = null;
      return data;
    },

    async completeSetup(code) {
      const { data } = await authApi.confirmSetup(this.setupToken, code);
      this._persistSession(data);
      this.setupToken = null;
      this.otpauthUrl = null;
      return data;
    },

    async requestReset() {
      await authApi.requestReset(this.challengeToken);
    },

    async verifyResetToken(token) {
      const { data } = await authApi.verifyResetToken(token);
      this.setupToken = data.setup_token;
      this.otpauthUrl = data.otpauth_url;
      return data;
    },

    async triggerReauth() {
      await authApi.reauthRequest(this.reauthToken);
      this.pendingReauth = true;
    },

    async completeReauth(code) {
      const { data } = await authApi.reauthVerify(this.reauthToken, code);
      this._persistSession(data);
      this.pendingReauth = false;

      // ← Retry all queued GET requests that failed with 401
      resolveReauthQueue();

      return data;
    },

    async logout() {
      try {
        if (this.accessToken) await authApi.logout();
      } finally {
        // ← Reject all queued GET requests on logout
        rejectReauthQueue(new Error('Logged out'));
        this._clearSession();
      }
    },

    _persistSession(data) {
      if (data.user) {
        this.user = data.user;
        localStorage.setItem('avirqo_user', JSON.stringify(data.user));
      }
      this.accessToken = data.access_token;
      this.accessTokenExpiresAt = data.access_token_expires_at;
      this.reauthToken = data.reauth_token;

      localStorage.setItem('avirqo_access_token', data.access_token);
      localStorage.setItem('avirqo_access_expires', data.access_token_expires_at);
      localStorage.setItem('avirqo_reauth_token', data.reauth_token);
    },

    _clearSession() {
      this.user = null;
      this.accessToken = null;
      this.accessTokenExpiresAt = null;
      this.reauthToken = null;
      this.pendingReauth = false;
      this.setupToken = null;
      this.otpauthUrl = null;
      localStorage.removeItem('avirqo_user');
      localStorage.removeItem('avirqo_access_token');
      localStorage.removeItem('avirqo_access_expires');
      localStorage.removeItem('avirqo_reauth_token');
    },
  },
});
