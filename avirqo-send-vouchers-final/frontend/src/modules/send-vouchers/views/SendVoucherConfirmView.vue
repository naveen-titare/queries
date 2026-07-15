<template>
  <AppLayout>
    <div class="vconf-page">
      <div class="vconf-header">
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">← Back</button>
        <h1>Confirm & Send</h1>
        <p>Review the order before sending vouchers</p>
      </div>

      <div class="vconf-layout">
        <!-- Order Summary -->
        <div class="avq-card vconf-card">
          <h3>Order Summary</h3>
          <table class="vconf-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Denomination</th>
                <th>Qty</th>
                <th class="num">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in cart" :key="item.key">
                <td>
                  <div class="vconf-product">
                    <img v-if="item.image_url" :src="item.image_url" />
                    <span>{{ item.product_name }}</span>
                  </div>
                </td>
                <td>{{ item.currency_code }} {{ fmt(item.denomination) }}</td>
                <td>{{ item.quantity }}</td>
                <td class="num">₹{{ fmt(item.denomination * item.quantity) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="vconf-total-label">Total</td>
                <td class="num vconf-total">₹{{ fmt(store.cartTotal) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Recipient Details -->
        <div class="avq-card vconf-card">
          <h3>Recipient</h3>
          <div class="vconf-detail-grid">
            <div class="vconf-detail-item">
              <span class="vconf-label">Company</span>
              <span class="vconf-value">{{ customer?.company_name }}</span>
            </div>
            <div class="vconf-detail-item">
              <span class="vconf-label">Location</span>
              <span class="vconf-value">{{ customer?.location }}</span>
            </div>
            <div class="vconf-detail-item">
              <span class="vconf-label">SPOC Name</span>
              <span class="vconf-value">{{ spoc?.name }}</span>
            </div>
            <div class="vconf-detail-item">
              <span class="vconf-label">Email</span>
              <span class="vconf-value">{{ spoc?.email }}</span>
            </div>
            <div class="vconf-detail-item">
              <span class="vconf-label">Current Balance</span>
              <span class="vconf-value">₹{{ fmt(customer?.balance) }}</span>
            </div>
            <div class="vconf-detail-item">
              <span class="vconf-label">Balance After</span>
              <span class="vconf-value" :class="balanceAfter < 0 ? 'text-red' : 'text-green'">
                ₹{{ fmt(balanceAfter) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Warning if negative balance -->
        <div v-if="balanceAfter < 0" class="vconf-warning">
          ⚠ This order will make the customer's balance negative (₹{{ fmt(balanceAfter) }}).
          You can still proceed — the balance will go into debit.
        </div>

        <!-- Send button -->
        <div class="vconf-actions">
          <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">Cancel</button>
          <button
            class="avq-btn-primary vconf-send-btn"
            :disabled="sending"
            @click="send"
          >
            {{ sending ? 'Sending vouchers…' : '✉ Confirm & Send Vouchers' }}
          </button>
        </div>

        <p v-if="error" class="form-error">{{ error }}</p>
      </div>

      <!-- Success overlay -->
      <div v-if="success" class="vconf-success-overlay">
        <div class="vconf-success-card">
          <div class="vconf-success-icon">✅</div>
          <h2>Vouchers Sent!</h2>
          <p>
            <strong>{{ cart.length }} item(s)</strong> worth
            <strong>₹{{ fmt(store.cartTotal) }}</strong> have been sent to
            <strong>{{ spoc?.email }}</strong>
          </p>
          <p class="vconf-order-num">Order: {{ orderNumber }}</p>
          <div class="vconf-success-actions">
            <button class="avq-btn-primary" @click="goToCatalog">Send more vouchers</button>
            <button class="avq-btn-ghost" @click="router.push('/dashboard')">Go to Dashboard</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const store = useSendVoucherStore();
const router = useRouter();

const customer = ref(null);
const spoc = ref(null);
const sending = ref(false);
const success = ref(false);
const error = ref('');
const orderNumber = ref('');
const cart = computed(() => store.cart);

const balanceAfter = computed(() =>
  (customer.value?.balance ?? 0) - store.cartTotal
);

onMounted(() => {
  const c = sessionStorage.getItem('vsend_customer');
  const s = sessionStorage.getItem('vsend_spoc');
  if (!c || !s || !store.cart.length) {
    router.push('/send-vouchers');
    return;
  }
  customer.value = JSON.parse(c);
  spoc.value = JSON.parse(s);
});

async function send() {
  sending.value = true;
  error.value = '';
  try {
    const result = await store.placeOrder(customer.value.id, spoc.value.id);
    orderNumber.value = result.order.order_number;
    success.value = true;
    sessionStorage.removeItem('vsend_customer');
    sessionStorage.removeItem('vsend_spoc');
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to send vouchers. Please try again.';
  } finally {
    sending.value = false;
  }
}

function goToCatalog() {
  success.value = false;
  router.push('/send-vouchers');
}

function fmt(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
</script>

<style>
.vconf-page { padding: 28px; font-family: var(--fb); max-width: 860px; }
.vconf-header { margin-bottom: 24px; }
.vconf-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 8px 0 4px; }
.vconf-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }
.vconf-layout { display: flex; flex-direction: column; gap: 20px; }
.vconf-card { padding: 24px; }
.vconf-card h3 { font-family: var(--fd); font-size: 18px; font-weight: 600; margin: 0 0 16px; }
.vconf-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.vconf-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); font-weight: 600; padding: 8px 12px; background: var(--surface-2); }
.vconf-table td { padding: 12px; border-top: 1px solid var(--border-2); vertical-align: middle; }
.vconf-table .num { text-align: right; font-family: var(--fd); }
.vconf-product { display: flex; align-items: center; gap: 8px; }
.vconf-product img { width: 28px; height: 28px; object-fit: contain; border-radius: 5px; }
.vconf-total-label { font-weight: 700; text-align: right; padding-right: 12px; }
.vconf-total { font-family: var(--fd); font-size: 18px; font-weight: 700; color: var(--teal-deep); }
.vconf-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.vconf-detail-item { display: flex; flex-direction: column; gap: 4px; }
.vconf-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); }
.vconf-value { font-size: 14px; font-weight: 500; color: var(--ink); }
.text-red { color: #b91c1c !important; }
.text-green { color: var(--teal-deep) !important; }
.vconf-warning { background: #fef6ec; border: 1px solid #f3e2c7; border-radius: 10px; padding: 14px 16px; font-size: 14px; color: #b45309; }
.vconf-actions { display: flex; justify-content: flex-end; gap: 12px; }
.vconf-send-btn { padding: 14px 28px; font-size: 15px; }
.vconf-send-btn:disabled { opacity: 0.55; cursor: not-allowed; }

/* Success overlay */
.vconf-success-overlay {
  position: fixed; inset: 0; background: rgba(8,80,65,0.6);
  backdrop-filter: blur(4px); z-index: 9999;
  display: flex; align-items: center; justify-content: center; padding: 24px;
}
.vconf-success-card {
  background: #fff; border-radius: 20px; padding: 48px 40px;
  max-width: 440px; width: 100%; text-align: center;
  box-shadow: 0 32px 80px rgba(8,80,65,0.25);
}
.vconf-success-icon { font-size: 56px; margin-bottom: 16px; }
.vconf-success-card h2 { font-family: var(--fd); font-size: 28px; margin: 0 0 12px; }
.vconf-success-card p { color: var(--ink-soft); font-size: 15px; line-height: 1.6; margin: 0 0 8px; }
.vconf-order-num { font-size: 13px; color: var(--ink-muted); font-family: monospace; }
.vconf-success-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 24px; }
.vconf-success-actions button { width: 100%; padding: 13px; font-size: 14px; }
</style>
