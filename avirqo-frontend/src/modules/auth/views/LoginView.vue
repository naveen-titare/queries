<template>
  <AuthLayout>
    <h2>Sign in to Avirqo</h2>
    <p>Enterprise reward platform login</p>
    <form class="avirqo-auth-form" @submit.prevent="submit">
      <input v-model="email" type="email" placeholder="Work email" required autocomplete="username" />
      <input v-model="password" type="password" placeholder="Password" required autocomplete="current-password" />
      <button class="avirqo-auth-button" type="submit" :disabled="loading">
        {{ loading ? 'Checking…' : 'Continue' }}
      </button>
    </form>
    <p v-if="error" class="avirqo-auth-error">{{ error }}</p>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/authStore';
import AuthLayout from '../components/AuthLayout.vue';

const auth = useAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    const data = await auth.login(email.value, password.value);
    if (data.setup_required) {
      router.push('/2fa-setup');
    } else {
      router.push('/2fa');
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Login failed. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
