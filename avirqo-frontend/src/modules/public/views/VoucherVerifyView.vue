<template>
  <div class="verify-page">
    <div class="verify-container">
      <!-- Header -->
      <div class="verify-header">
        <div class="verify-logo">🎁</div>
        <h1>Voucher Verification</h1>
        <p>Enter the Code ID from your voucher to verify its details</p>
      </div>

      <!-- Search Form -->
      <div class="verify-form">
        <div class="input-group">
          <input 
            v-model="codeId" 
            type="text" 
            placeholder="Enter Code ID (e.g., gY7bW19)"
            @keyup.enter="verify"
            :disabled="loading"
          />
          <button @click="verify" :disabled="loading || !codeId.trim()">
            {{ loading ? 'Searching…' : 'Verify' }}
          </button>
        </div>
        <p class="hint">Find the Code ID in your voucher email or message</p>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="result-card error">
        <div class="result-icon">❌</div>
        <h3>Voucher Not Found</h3>
        <p>{{ error }}</p>
      </div>

      <!-- Success Result -->
      <div v-if="voucher && !loading" class="result-card success">
        <!-- Status Badge -->
        <div class="status-badge" :class="getStatusClass(voucher.status)">
          {{ getStatusText(voucher.status) }}
        </div>

        <!-- Brand Header -->
        <div class="voucher-brand">
          <div class="brand-icon">{{ (voucher.brand || voucher.product_name || '?').charAt(0) }}</div>
          <div class="brand-info">
            <h2>{{ voucher.brand || voucher.product_name }}</h2>
            <p>{{ voucher.product_name !== voucher.brand ? voucher.product_name : '' }}</p>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
          <div class="detail-item">
            <span class="detail-label">Code ID</span>
            <span class="detail-value mono">#{{ voucher.code_id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Denomination</span>
            <span class="detail-value amount">{{ voucher.currency_code }} {{ formatAmount(voucher.denomination) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="detail-value">{{ voucher.status }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Expiry Date</span>
            <span class="detail-value" :class="{ 'expired': voucher.is_expired }">
              {{ voucher.expiry_date }}
              <span v-if="voucher.is_expired" class="expired-tag">Expired</span>
            </span>
          </div>
        </div>

        <!-- Status Message -->
        <div class="status-message" :class="{ 'valid': voucher.is_valid, 'invalid': !voucher.is_valid }">
          <span class="status-icon">{{ voucher.is_valid ? '✅' : '⚠️' }}</span>
          {{ voucher.status_message }}
        </div>

        <!-- Validity Badge -->
        <div v-if="voucher.is_valid" class="validity-badge">
          <span>✅</span> This voucher is valid and ready to use!
        </div>
      </div>

      <!-- Footer -->
      <div class="verify-footer">
        <p>Powered by <strong>Avirqo</strong></p>
        <p class="support">Need help? Contact your company's support team.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const codeId = ref('');
const loading = ref(false);
const error = ref('');
const voucher = ref(null);

onMounted(() => {
  // Auto-verify if codeId is provided in URL
  if (route.params.codeId) {
    codeId.value = route.params.codeId;
    verify();
  }
});

async function verify() {
  if (!codeId.value.trim()) return;
  
  loading.value = true;
  error.value = '';
  voucher.value = null;

  try {
    const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
    const response = await fetch(`${baseUrl}/public/send-vouchers/codes/verify/${codeId.value.trim()}`);
    const data = await response.json();
    
    if (!data.success) {
      error.value = data.message || 'Voucher not found. Please check the Code ID.';
    } else {
      voucher.value = data.voucher;
    }
  } catch (e) {
    error.value = 'Unable to verify voucher. Please try again later.';
  } finally {
    loading.value = false;
  }
}

function formatAmount(amount) {
  return Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function getStatusText(status) {
  return { available: 'Available', reserved: 'Reserved', sent: 'Delivered', failed: 'Failed' }[status] || '';
}

function getStatusClass(status) {
  return { available: 'status-available', reserved: 'status-reserved', sent: 'status-sent', failed: 'status-failed' }[status] || '';
}
</script>

<style>
.verify-page {
  --teal-deep: #085041;
  --teal-mid: #1D9E75;
  --teal-light: #9FE1CB;
  --teal-pale: #E8F7F2;
  --ink: #0D0D0C;
  --ink-soft: #3A3A38;
  --ink-muted: #6B6A67;
  --surface-2: #F7FAF9;
  --border: #D5EAE3;
  --border-2: #E4EDE9;
  --fd: 'Fraunces', Georgia, serif;
  --fb: 'DM Sans', system-ui, sans-serif;

  min-height: 100vh;
  background: linear-gradient(160deg, #ffffff 50%, #eaf6f1 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  font-family: var(--fb);
}

.verify-container {
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  padding: 40px 36px;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 24px 80px rgba(8, 80, 65, 0.12), 0 4px 16px rgba(0, 0, 0, 0.06);
}

.verify-header {
  text-align: center;
  margin-bottom: 30px;
}

.verify-logo {
  font-size: 48px;
  margin-bottom: 10px;
}

.verify-header h1 {
  font-family: var(--fd);
  font-size: 24px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 8px;
}

.verify-header p {
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.verify-form {
  margin-bottom: 30px;
}

.input-group {
  display: flex;
  gap: 10px;
}

.input-group input {
  flex: 1;
  padding: 12px 14px;
  border: 1.5px solid var(--border-2);
  border-radius: 8px;
  font-family: var(--fb);
  font-size: 15px;
  outline: none;
  transition: border-color 0.2s;
  color: var(--ink);
}

.input-group input:focus {
  border-color: var(--teal-mid);
}

.input-group button {
  padding: 14px 24px;
  background: var(--teal-deep);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: var(--fb);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.input-group button:hover:not(:disabled) {
  background: var(--teal-mid);
}

.input-group button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.hint {
  font-size: 12px;
  color: var(--ink-muted);
  text-align: center;
  margin-top: 8px;
}

.result-card {
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 20px;
}

.result-card.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  text-align: center;
}

.result-card.error .result-icon {
  font-size: 40px;
  margin-bottom: 10px;
}

.result-card.error h3 {
  color: #b91c1c;
  margin: 0 0 8px;
  font-size: 18px;
}

.result-card.error p {
  color: #7f1d1d;
  margin: 0;
  font-size: 14px;
}

.result-card.success {
  background: var(--surface-2);
  border: 1px solid var(--border-2);
}

.status-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 100px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 16px;
}

.status-available { background: #FEF3E2; color: #B45309; }
.status-reserved { background: #dbeafe; color: #1e40af; }
.status-sent { background: var(--teal-pale); color: var(--teal-deep); }
.status-failed { background: #fee2e2; color: #991b1b; }

.voucher-brand {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border-2);
}

.brand-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, var(--teal-deep) 0%, var(--teal-mid) 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
  font-weight: 700;
}

.brand-info h2 {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
}

.brand-info p {
  font-size: 14px;
  color: var(--ink-muted);
  margin: 0;
}

.details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--ink-muted);
  letter-spacing: 0.05em;
}

.detail-value {
  font-size: 16px;
  font-weight: 600;
  color: var(--ink);
}

.detail-value.mono {
  font-family: 'Courier New', monospace;
}

.detail-value.amount {
  font-size: 20px;
  color: var(--teal-mid);
}

.detail-value.expired {
  color: #dc2626;
}

.expired-tag {
  background: #fee2e2;
  color: #991b1b;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: 8px;
  font-weight: 700;
}

.status-message {
  padding: 14px;
  border-radius: 10px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-message.valid {
  background: var(--teal-pale);
  color: var(--teal-deep);
}

.status-message.invalid {
  background: #fef3c7;
  color: #92400e;
}

.status-icon {
  font-size: 18px;
}

.validity-badge {
  margin-top: 16px;
  padding: 16px;
  background: linear-gradient(135deg, var(--teal-deep) 0%, var(--teal-mid) 100%);
  color: #fff;
  border-radius: 10px;
  text-align: center;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.verify-footer {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--border-2);
}

.verify-footer p {
  margin: 0;
  font-size: 13px;
  color: var(--ink-muted);
}

.verify-footer strong {
  color: var(--teal-mid);
}

.verify-footer .support {
  margin-top: 8px;
  font-size: 12px;
}
</style>
