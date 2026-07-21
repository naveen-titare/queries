<template>
  <div class="delivery-page">
    <div class="delivery-card">
      <div class="delivery-header">
        <div class="delivery-logo">avirq<span>o</span></div>
        <h1>Download Vouchers</h1>
        <p>Provide the details to Download Voucher codes.</p>
      </div>

      <div v-if="orderInfo" class="delivery-order">
        <div><span>Order</span><strong>{{ orderInfo.order_number }}</strong></div>
        <div><span>Status</span><strong>{{ orderInfo.status }}</strong></div>
        <div><span>SPOC</span><strong>{{ orderInfo.spoc_name || '—' }}</strong></div>
        <div><span>Email</span><strong>{{ orderInfo.spoc_email || '—' }}</strong></div>
      </div>

      <div v-if="error" class="alert error">{{ error }}</div>
      <div v-if="success" class="alert success">{{ success }}</div>

      <div class="steps">
        <section class="step">
          <h3>1. Order details</h3>
          <div class="grid">
            <label class="field">
              <span class="field-label">Order Number</span>
              <small class="field-help">Details available in the mail, please check</small>
              <input v-model="form.orderNumber" :disabled="loading" placeholder="AVQ-SEND-2026-000001" />
            </label>
            <label class="field">
              <span class="field-label">Your Email</span>
              <small class="field-help">Must be same as in order</small>
              <input v-model="form.email" :disabled="loading" placeholder="abc@your-company.com" />
            </label>
            <label class="field full">
              <span class="field-label">Secret Key</span>
              <input v-model="form.secretKey" :disabled="loading" placeholder="XXXX-XXXX-XXXX" />
            </label>
          </div>
          <button class="primary" :disabled="loading || !canRequestOtp" @click="requestOtp">
            {{ loading && action === 'request' ? 'Sending OTP...' : 'Submit' }}
          </button>
          <p class="hint">If the secret key is compromised, request your Avirqo account manager to send an updated one.</p>
        </section>

        <section class="step" :class="{ disabled: !otpRequested }">
          <h3>2. Verify Your Identity</h3>
          <label class="field">
            <span class="field-label">OTP</span>
            <input v-model="form.otp" :disabled="loading || !otpRequested" placeholder="123456" maxlength="6" />
          </label>
          <div class="button-row">
            <button class="secondary" :disabled="loading || !otpRequested" @click="verifyOtp">
              {{ loading && action === 'verify' ? 'Verifying…' : 'Verify OTP' }}
            </button>
            <button class="ghost" :disabled="loading || !otpRequested" @click="requestOtp">
              Resend OTP
            </button>
          </div>
          <p class="hint">The OTP is valid for 5 minutes and is sent to the email saved against the order.</p>
        </section>

        <section class="step" :class="{ disabled: !otpVerified }">
          <h3>3. Download Excel</h3>
          <p class="hint">Once your download is completed, the secret key is no more valid. In case you face any issue with voucher download, request your Avirqo account manager to send an updated one. Any other technical issues, please reach out to support@avirqo.com</p>
          <button class="primary download" :disabled="loading || !otpVerified" @click="downloadExcel">
            {{ loading && action === 'download' ? 'Preparing file...' : 'Download Excel' }}
          </button>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import deliveryApi from '../api/voucherDeliveryApi';

const route = useRoute();

const form = reactive({
  orderNumber: route.params.orderNumber || '',
  email: '',
  secretKey: '',
  otp: '',
});

const loading = ref(false);
const action = ref('');
const error = ref('');
const success = ref('');
const otpRequested = ref(false);
const otpVerified = ref(false);
const orderInfo = ref(null);

const canRequestOtp = computed(() => form.orderNumber.trim() && form.email.trim() && form.secretKey.trim());

onMounted(async () => {
  if (!form.orderNumber) return;
  await loadOrder();
});

async function loadOrder() {
  try {
    const { data } = await deliveryApi.getOrder(form.orderNumber.trim());
    orderInfo.value = data;
    if (!form.email && data.spoc_email) form.email = data.spoc_email;
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to load delivery details.';
  }
}

async function requestOtp() {
  if (!canRequestOtp.value) return;
  loading.value = true;
  action.value = 'request';
  error.value = '';
  success.value = '';
  try {
    const { data } = await deliveryApi.requestOtp(form.orderNumber.trim(), {
      email: form.email.trim(),
      secret_key: form.secretKey.trim(),
    });
    otpRequested.value = true;
    otpVerified.value = false;
    success.value = data.message || 'OTP sent successfully.';
    if (!orderInfo.value) await loadOrder();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to send OTP.';
  } finally {
    loading.value = false;
    action.value = '';
  }
}

async function verifyOtp() {
  if (!otpRequested.value || !form.otp.trim()) return;
  loading.value = true;
  action.value = 'verify';
  error.value = '';
  success.value = '';
  try {
    const { data } = await deliveryApi.verifyOtp(form.orderNumber.trim(), {
      email: form.email.trim(),
      secret_key: form.secretKey.trim(),
      otp: form.otp.trim(),
    });
    otpVerified.value = true;
    success.value = data.message || 'OTP verified successfully.';
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to verify OTP.';
  } finally {
    loading.value = false;
    action.value = '';
  }
}

async function downloadExcel() {
  if (!otpVerified.value) return;
  loading.value = true;
  action.value = 'download';
  error.value = '';
  success.value = '';
  try {
    const response = await deliveryApi.download(form.orderNumber.trim(), {
      email: form.email.trim(),
      secret_key: form.secretKey.trim(),
      otp: form.otp.trim(),
    });

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `Avirqo-Send-Vouchers-${form.orderNumber.trim()}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    success.value = 'Excel downloaded successfully. The secret key is now marked as used.';
    otpVerified.value = false;
    otpRequested.value = false;
    form.otp = '';
  } catch (e) {
    if (e.response?.data instanceof Blob) {
      try {
        const payload = JSON.parse(await e.response.data.text());
        error.value = payload.message || 'Failed to download Excel.';
      } catch {
        error.value = 'Failed to download Excel.';
      }
    } else {
      error.value = e.response?.data?.message || 'Failed to download Excel.';
    }
  } finally {
    loading.value = false;
    action.value = '';
  }
}
</script>

<style scoped>
.delivery-page {
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
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(160deg, #ffffff 50%, #eaf6f1 100%);
  font-family: var(--fb);
  color: var(--ink);
}

.delivery-card {
  width: min(760px, 100%);
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  box-shadow: 0 24px 80px rgba(8, 80, 65, 0.12), 0 4px 16px rgba(0, 0, 0, 0.06);
  padding: 40px 36px;
}

.delivery-header {
  text-align: center;
  margin-bottom: 26px;
}

.delivery-logo {
  font-size: 34px;
  font-weight: 700;
  letter-spacing: -0.04em;
  color: var(--ink);
  margin-bottom: 18px;
}

.delivery-logo span {
  color: var(--teal-mid);
}

.delivery-header h1 {
  margin: 0 0 8px;
  font-family: var(--fd);
  font-size: 24px;
  font-weight: 600;
  color: var(--ink);
}

.delivery-header p {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

.delivery-order {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  padding: 16px;
  background: var(--surface-2);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  margin-bottom: 22px;
}

.delivery-order span {
  display: block;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--ink-muted);
  margin-bottom: 4px;
}

.delivery-order strong {
  font-size: 14px;
  color: var(--ink);
}

.alert {
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 16px;
  font-size: 14px;
}

.alert.error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.alert.success {
  background: var(--teal-pale);
  color: var(--teal-deep);
  border: 1px solid var(--border);
}

.steps {
  display: grid;
  gap: 18px;
}

.step {
  border: 1px solid var(--border-2);
  border-radius: 12px;
  padding: 22px;
  background: var(--surface-2);
}

.step.disabled {
  opacity: 0.6;
}

.step h3 {
  margin: 0 0 12px;
  font-family: var(--fd);
  font-size: 16px;
  font-weight: 600;
  color: var(--ink);
}

.step h3 span {
  display: block;
  margin-top: 3px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-muted);
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.full {
  grid-column: 1 / -1;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink-soft);
  min-width: 0;
}

.field-label {
  display: block;
  min-height: 18px;
  line-height: 18px;
}

.field-help {
  display: block;
  min-height: 34px;
  font-size: 12px;
  font-weight: 500;
  color: var(--ink-muted);
  line-height: 1.4;
}

.full .field-help {
  display: none;
}

input {
  width: 100%;
  box-sizing: border-box;
  min-height: 48px;
  padding: 12px 14px;
  border: 1.5px solid var(--border-2);
  border-radius: 8px;
  font-family: var(--fb);
  font-size: 15px;
  color: var(--ink);
  background: #fff;
  outline: none;
  transition: border-color 0.2s;
}

input:focus {
  border-color: var(--teal-mid);
}

input:disabled {
  background: #fff;
  opacity: 0.65;
}

.button-row {
  display: flex;
  gap: 10px;
  margin-top: 12px;
  flex-wrap: wrap;
}

button {
  border: none;
  border-radius: 10px;
  padding: 14px;
  font-family: var(--fb);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.1s ease, opacity 0.1s ease;
}

button:active {
  transform: translateY(1px);
}

button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.primary {
  margin-top: 12px;
  background: var(--teal-deep);
  color: white;
}

.primary:hover:not(:disabled) {
  background: var(--teal-mid);
}

.secondary {
  background: var(--teal-deep);
  color: #fff;
}

.secondary:hover:not(:disabled) {
  background: var(--teal-mid);
}

.ghost {
  background: #fff;
  color: var(--teal-deep);
  border: 1.5px solid var(--border-2);
}

.ghost:hover:not(:disabled) {
  border-color: var(--teal-mid);
}

.download {
  min-width: 220px;
}

.hint {
  margin: 10px 0 0;
  font-size: 12px;
  color: var(--ink-muted);
  line-height: 1.5;
  text-align: center;
}

@media (max-width: 720px) {
  .delivery-card {
    padding: 20px;
  }

  .grid,
  .delivery-order {
    grid-template-columns: 1fr;
  }

  .field-help {
    min-height: auto;
  }
}
</style>
