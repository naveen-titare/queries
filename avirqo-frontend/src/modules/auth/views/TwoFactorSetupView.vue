<template>
  <AuthLayout>
    <h2>Set up Google Authenticator</h2>
    <p>Scan this QR code with the Google Authenticator app, then enter the 6-digit code it shows.</p>

    <img v-if="qrDataUrl" :src="qrDataUrl" alt="Scan with Google Authenticator" class="avirqo-auth-qr" />
    <p v-else class="avirqo-auth-hint">Generating QR code…</p>

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
        {{ loading ? 'Verifying…' : 'Confirm & continue' }}
      </button>
    </form>

    <p v-if="error" class="avirqo-auth-error">{{ error }}</p>
    <p v-if="attemptsRemaining !== null" class="avirqo-auth-hint">
      {{ attemptsRemaining }} attempt(s) remaining.
    </p>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import QRCode from 'qrcode';
import { useAuthStore } from '../store/authStore';
import AuthLayout from '../components/AuthLayout.vue';

const auth = useAuthStore();
const router = useRouter();

const code = ref('');
const error = ref('');
const loading = ref(false);
const attemptsRemaining = ref(null);
const qrDataUrl = ref('');

onMounted(async () => {
  if (!auth.setupToken || !auth.otpauthUrl) {
    router.push('/login');
    return;
  }
  // Rendered entirely in the browser — the secret is never sent to a
  // third-party QR image service.
  qrDataUrl.value = await QRCode.toDataURL(auth.otpauthUrl, { width: 200, margin: 1 });
});

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    await auth.completeSetup(code.value);
    router.push('/dashboard');
  } catch (err) {
    if (err.response?.data?.locked) {
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
</script>
