<template>
  <AppLayout>
    <div class="na-page">
      <div class="na-card">
        <div class="na-icon">⛔</div>
        <h1>Not Authorized</h1>
        <p>You don’t have permission to open this section.</p>
        <div class="na-actions">
          <RouterLink class="avq-btn-primary" to="/dashboard">Go to Dashboard</RouterLink>
          <RouterLink class="avq-btn-ghost" to="/">Back</RouterLink>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { RouterLink } from 'vue-router';
import AppLayout from '../components/AppLayout.vue';
</script>

<style scoped>
.na-page {
  min-height: calc(100vh - 64px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
}
.na-card {
  max-width: 480px;
  width: 100%;
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 20px;
  padding: 36px;
  text-align: center;
  box-shadow: 0 18px 50px rgba(8, 14, 16, 0.08);
}
.na-icon { font-size: 44px; margin-bottom: 14px; }
.na-card h1 { font-family: var(--fd); font-size: 30px; margin: 0 0 10px; }
.na-card p { color: var(--ink-muted); margin: 0 0 24px; }
.na-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.na-actions a { text-decoration: none; }
</style>
