<template>
  <AuthLayout>
    <h2>Verifying reset link</h2>
    <p v-if="loading">One moment while we set up your new authenticator…</p>
    <p v-if="error" class="avirqo-auth-error">{{ error }}</p>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../store/authStore';
import AuthLayout from '../components/AuthLayout.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loading = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    await auth.verifyResetToken(route.params.token);
    router.replace('/2fa-setup');
  } catch (err) {
    error.value = err.response?.data?.message || 'This link is invalid or has expired.';
  } finally {
    loading.value = false;
  }
});
</script>
