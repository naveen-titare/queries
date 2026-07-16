<template>
  <div class="avq-404-page">
    <div class="avq-404-card">
      <div class="avq-404-brand">avirq<span>o</span></div>
      <div class="avq-404-code">404</div>
      <h1 class="avq-404-title">Page not found</h1>
      <p class="avq-404-desc">
        The page you're looking for doesn't exist or has been moved.
      </p>
      <RouterLink v-if="isLoggedIn" to="/dashboard" class="avq-404-btn">
        ← Back to Dashboard
      </RouterLink>
      <RouterLink v-else to="/login" class="avq-404-btn">
        ← Back to Login
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../auth/store/authStore';

const auth = useAuthStore();
const isLoggedIn = computed(() => auth.isAuthenticated);
</script>

<style>
.avq-404-page {
  --teal-deep: #085041;
  --teal-mid: #1D9E75;
  --teal-pale: #E8F7F2;
  --ink: #0D0D0C;
  --ink-muted: #6B6A67;
  --border-2: #E4EDE9;
  --fd: 'Fraunces', Georgia, serif;
  --fb: 'DM Sans', system-ui, sans-serif;

  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(160deg, #ffffff 50%, #eaf6f1 100%);
  font-family: var(--fb);
}

.avq-404-card {
  text-align: center;
  max-width: 440px;
  width: 100%;
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 20px;
  padding: 48px 40px;
  box-shadow: 0 24px 80px rgba(8, 80, 65, 0.10);
}

.avq-404-brand {
  font-weight: 700;
  font-size: 22px;
  letter-spacing: -0.04em;
  color: var(--ink);
  margin-bottom: 28px;
}

.avq-404-brand span { color: var(--teal-mid); }

.avq-404-code {
  font-family: var(--fd);
  font-size: 96px;
  font-weight: 600;
  color: var(--teal-pale);
  line-height: 1;
  margin-bottom: 8px;
  background: linear-gradient(135deg, var(--teal-deep), var(--teal-mid));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.avq-404-title {
  font-family: var(--fd);
  font-size: 24px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 12px;
}

.avq-404-desc {
  color: var(--ink-muted);
  font-size: 15px;
  line-height: 1.6;
  margin: 0 0 28px;
}

.avq-404-btn {
  display: inline-block;
  background: var(--teal-deep);
  color: #fff;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.2s ease;
}

.avq-404-btn:hover { background: var(--teal-mid); }
</style>
