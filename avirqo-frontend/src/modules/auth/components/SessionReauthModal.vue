cat > ~/avirqo/avirqo-frontend/src/modules/auth/components/SessionReauthModal.vue << 'EOF'
<template>
  <Teleport to="body">
    <div v-if="bannerVisible && !modalVisible" class="sreauth-banner" @click="showModal">
      🔒 Session expired — click to resume
    </div>

    <div v-if="modalVisible" class="sreauth-overlay">
      <div class="sreauth-card">
        <div class="sreauth-brand">avirq<span>o</span></div>
        <div class="sreauth-icon">🔒</div>
        <h2>Session expired</h2>
        <p>
          Your 6-hour session has timed out. Enter your Google Authenticator
          code to continue — no data or progress will be lost.
        </p>
        <form @submit.prevent="submit" class="sreauth-form">
          <input
            v-model="code"
            ref="codeInput"
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="one-time-code"
            placeholder="6-digit code"
            required
          />
          <button type="submit" :disabled="loading">
            {{ loading ? 'Verifying…' : 'Resume session' }}
          </button>
        </form>
        <p v-if="error" class="sreauth-error">{{ error }}</p>
        <p v-if="attemptsRemaining !== null" class="sreauth-hint">
          {{ attemptsRemaining }} attempt(s) remaining before full logout.
        </p>
        <div class="sreauth-footer">
          <button class="sreauth-dismiss" @click="dismiss">Remind me later</button>
          <button class="sreauth-logout" @click="handleLogout">Log out</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/authStore';

const auth = useAuthStore();
const router = useRouter();

const modalVisible = ref(false);
const bannerVisible = ref(false);
const dismissed = ref(false);
const code = ref('');
const error = ref('');
const loading = ref(false);
const attemptsRemaining = ref(null);
const codeInput = ref(null);
let interval = null;
let alreadyTriggered = false;

function triggerExpiry() {
  if (!modalVisible.value && !dismissed.value) {
    modalVisible.value = true;
    bannerVisible.value = false;
    nextTick(() => codeInput.value?.focus());
  } else if (dismissed.value) {
    bannerVisible.value = true;
  }
}

function showModal() {
  dismissed.value = false;
  modalVisible.value = true;
  bannerVisible.value = false;
  nextTick(() => codeInput.value?.focus());
}

function dismiss() {
  dismissed.value = true;
  modalVisible.value = false;
  bannerVisible.value = true;
}

// Watch for HTTP interceptor setting pendingReauth
watch(() => auth.pendingReauth, (val) => {
  if (val) {
    dismissed.value = false;
    triggerExpiry();
  }
});

// Single onMounted — checks pendingReauth immediately AND starts the poll
onMounted(() => {
  // Check if interceptor already fired before this component mounted
  if (auth.pendingReauth) {
    dismissed.value = false;
    triggerExpiry();
  }

  // Poll every 60 seconds for proactive expiry detection
  interval = setInterval(() => {
    if (!auth.accessToken) return;
    if (auth.isAccessTokenExpired && auth.reauthToken) {
      if (!alreadyTriggered) {
        alreadyTriggered = true;
        auth.pendingReauth = true;
        triggerExpiry();
      }
    } else {
      alreadyTriggered = false;
      dismissed.value = false;
      bannerVisible.value = false;
    }
  }, 60000);
});

onUnmounted(() => {
  if (interval) clearInterval(interval);
});

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    await auth.completeReauth(code.value);
    modalVisible.value = false;
    bannerVisible.value = false;
    dismissed.value = false;
    alreadyTriggered = false;
    auth.pendingReauth = false;
    code.value = '';
    attemptsRemaining.value = null;
  } catch (err) {
    const status = err.response?.status;
    if (status === 423 || status === 401) {
      await auth.logout();
      router.push('/login');
    } else {
      error.value = err.response?.data?.message || 'Incorrect code.';
      attemptsRemaining.value = err.response?.data?.attempts_remaining ?? null;
    }
  } finally {
    loading.value = false;
    code.value = '';
  }
}

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

<style>
.sreauth-banner {
  position: fixed; top: 0; left: 0; right: 0;
  background: #085041; color: #fff; text-align: center;
  padding: 10px 16px; font-size: 13px; font-weight: 600;
  font-family: 'DM Sans', system-ui, sans-serif;
  cursor: pointer; z-index: 9998; transition: background 0.2s ease;
}
.sreauth-banner:hover { background: #1D9E75; }

.sreauth-overlay {
  position: fixed; inset: 0; background: rgba(8, 80, 65, 0.55);
  backdrop-filter: blur(4px); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 24px; font-family: 'DM Sans', system-ui, sans-serif;
}

.sreauth-card {
  background: #fff; border-radius: 20px; padding: 40px 36px;
  width: 100%; max-width: 380px; text-align: center;
  box-shadow: 0 32px 80px rgba(8, 80, 65, 0.25);
}

.sreauth-brand { font-weight: 700; font-size: 20px; letter-spacing: -0.04em; color: #0D0D0C; margin-bottom: 16px; }
.sreauth-brand span { color: #1D9E75; }
.sreauth-icon { font-size: 36px; margin-bottom: 12px; }
.sreauth-card h2 { font-family: 'Fraunces', Georgia, serif; font-size: 22px; font-weight: 600; color: #0D0D0C; margin: 0 0 10px; }
.sreauth-card p { color: #6B6A67; font-size: 14px; line-height: 1.6; margin: 0 0 20px; }

.sreauth-form input {
  width: 100%; box-sizing: border-box; padding: 14px;
  border: 1.5px solid #E4EDE9; border-radius: 8px;
  font-size: 22px; font-weight: 600; text-align: center;
  letter-spacing: 0.4em; outline: none; margin-bottom: 12px;
  font-family: 'DM Sans', system-ui, sans-serif;
}
.sreauth-form input:focus { border-color: #1D9E75; }

.sreauth-form button {
  width: 100%; padding: 13px; background: #085041; color: #fff;
  border: none; border-radius: 10px; font-size: 15px; font-weight: 600;
  cursor: pointer; font-family: 'DM Sans', system-ui, sans-serif;
  transition: background 0.2s ease;
}
.sreauth-form button:hover:not(:disabled) { background: #1D9E75; }
.sreauth-form button:disabled { opacity: 0.55; cursor: not-allowed; }

.sreauth-error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin: 10px 0 0; }
.sreauth-hint { color: #6B6A67; font-size: 13px; margin: 8px 0 0; }
.sreauth-footer { display: flex; justify-content: center; gap: 20px; margin-top: 16px; }
.sreauth-dismiss { background: none; border: none; color: #085041; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', system-ui, sans-serif; text-decoration: underline; }
.sreauth-logout { background: none; border: none; color: #6B6A67; font-size: 13px; cursor: pointer; font-family: 'DM Sans', system-ui, sans-serif; text-decoration: underline; }
</style>
EOF