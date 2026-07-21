import apiClient from '../../../shared/apiClient';

export default {
  login(email, password) {
    return apiClient.post('/auth/login', { email, password });
  },
  me() {
    return apiClient.get('/user');
  },
  verifyLogin2fa(challengeToken, code) {
    return apiClient.post('/auth/verify-2fa', { challenge_token: challengeToken, code });
  },
  reauthRequest(reauthToken) {
    return apiClient.post('/auth/reauth/request', { reauth_token: reauthToken });
  },
  reauthVerify(reauthToken, code) {
    return apiClient.post('/auth/reauth/verify', { reauth_token: reauthToken, code });
  },
  logout() {
    return apiClient.post('/auth/logout', {});
  },
  confirmSetup(setupToken, code) {
    return apiClient.post('/auth/2fa/setup/confirm', { setup_token: setupToken, code });
  },
  requestReset(challengeToken) {
    return apiClient.post('/auth/2fa/reset/request', { challenge_token: challengeToken });
  },
  verifyResetToken(token) {
    return apiClient.post(`/auth/2fa/reset/${token}/verify`);
  },
};
