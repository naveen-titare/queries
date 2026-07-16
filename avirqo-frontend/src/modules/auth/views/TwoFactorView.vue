<template>
  <AuthLayout>
    <h2>Enter your authenticator code</h2>
    <p v-if="auth.pendingReauth">
      Your session timed out. No password needed — just your 6-digit code.
    </p>
    <p v-else>
      Open Google Authenticator and enter the current code for Avirqo.
    </p>

    <form class="avirqo-auth-form" @submit.prevent="submit">
      <input
        v-model="code"
        maxlength="6"
        inputmode="numeric"
        pattern="[0-9]*"
        autocomplete="one-time-code"
        placeholder="123456"
        required
      />
      <button class="avirqo-auth-button" type="submit" :disabled="loading">
        {{ loading ? 'Verifying…' : 'Verify' }}
      </button>
    </form>

    <p v-if="error" class="avirqo-auth-error">{{ error }}</p>
    <p v-if="attemptsRemaining !== null" class="avirqo-auth-hint">
      {{ attemptsRemaining }} attempt(s) remaining before you're logged out.
    </p>

    <div v-if="!auth.pendingReauth" class="avirqo-auth-hint">
      <span v-if="!resetSent">
        Lost your authenticator device?
        <button
          type="button"
          class="avirqo-auth-link-button"
          @click="handleRequestReset"
          :disabled="resetLoading"
        >
          Email me a reset link
        </button>
      </span>
      <span v-else>Check your email for a link to set up a new authenticator.</span>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/authStore';
import AuthLayout from '../components/AuthLayout.vue';

const auth = useAuthStore();
const router = useRouter();

const code = ref('');
const error = ref('');
const loading = ref(false);
const attemptsRemaining = ref(null);
const resetSent = ref(false);
const resetLoading = ref(false);

onMounted(() => {
  if (!auth.challengeToken && !auth.pendingReauth) {
    router.push('/login');
  }
});

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    if (auth.pendingReauth) {
      await auth.completeReauth(code.value);
    } else {
      await auth.completeLogin(code.value);
    }
    router.push('/dashboard');
  } catch (err) {
    const status = err.response?.status;
    if (status === 423) {
      error.value = err.response.data.message;
      auth.logout();
      router.push('/login');
    } else if (status === 401) {
      error.value = err.response.data.message;
      router.push('/login');
    } else {
      error.value = err.response?.data?.message || 'Verification failed';
      attemptsRemaining.value = err.response?.data?.attempts_remaining ?? null;
    }
  } finally {
    loading.value = false;
    code.value = '';
  }
}

async function handleRequestReset() {
  resetLoading.value = true;
  error.value = '';
  try {
    await auth.requestReset();
    resetSent.value = true;
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not send reset email';
  } finally {
    resetLoading.value = false;
  }
}
</script>
