<template>
  <AppLayout>
    <div class="sv-conf-page">
      <div class="sv-conf-header">
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">← Back</button>
        <h1>Confirm & Send</h1>
        <p>Review the order before sending vouchers — balance will be auto-deducted</p>
      </div>

      <div class="sv-conf-layout">
        <!-- Order Summary -->
        <div class="avq-card sv-conf-card">
          <h3>Order Summary — One email per SPOC with all vouchers attached</h3>
          <table class="sv-conf-table">
            <thead>
              <tr>
                <th>Product (Brand)</th>
                <th>Denomination</th>
                <th>Qty</th>
                <th class="num">Total Value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in cart" :key="item.key">
                <td>
                  <div class="sv-conf-product">
                    <img v-if="item.image_url" :src="item.image_url" />
                    <span>{{ item.product_name }} <small style="color:var(--ink-muted)">{{ item.brand }}</small></span>
                  </div>
                </td>
                <td>{{ item.currency_code }} {{ fmt(item.denomination) }}</td>
                <td>{{ item.quantity }}</td>
                <td class="num">₹{{ fmt(item.denomination * item.quantity) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="sv-conf-total-label">Total ({{ cart.length }} brand types, {{ store.cartItemCount }} codes)</td>
                <td class="num sv-conf-total">₹{{ fmt(store.cartTotal) }}</td>
              </tr>
            </tfoot>
          </table>
          <p style="font-size:12px;color:var(--ink-muted);margin-top:10px;">Excel will contain {{ store.cartItemCount }} rows: Brand Name | Product | Denomination | Voucher Code (decrypted) | PIN | Expiry</p>
        </div>

        <!-- Recipient Details -->
        <div class="avq-card sv-conf-card">
          <h3>Recipient (Encrypted codes go to this SPOC)</h3>
          <div class="sv-conf-detail-grid">
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">Company</span>
              <span class="sv-conf-value">{{ customer?.company_name }}</span>
            </div>
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">Location</span>
              <span class="sv-conf-value">{{ customer?.location }}</span>
            </div>
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">SPOC Name</span>
              <span class="sv-conf-value">{{ spoc?.name }}</span>
            </div>
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">Email (Excel attached here)</span>
              <span class="sv-conf-value">{{ spoc?.email }}</span>
            </div>
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">Current Balance</span>
              <span class="sv-conf-value">₹{{ fmt(customer?.balance) }}</span>
            </div>
            <div class="sv-conf-detail-item">
              <span class="sv-conf-label">Balance After deduction</span>
              <span class="sv-conf-value" :class="balanceAfter < 0 ? 'text-red' : 'text-green'">
                ₹{{ fmt(balanceAfter) }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="balanceAfter < 0" class="sv-conf-warning">
          ⚠ This order will make the customer's balance negative (₹{{ fmt(balanceAfter) }}). You can still proceed — balance will go into debit. Or top-up first.
        </div>

        <div v-if="store.cartTotal > 0 && store.cartItemCount > 3000" class="sv-conf-warning" style="background:#FFF7ED;border-color:#FED7AA;color:#9A3412;">
          ⚠ Large order: {{ store.cartItemCount }} codes. Excel size may be ~{{ Math.ceil(store.cartItemCount * 0.002) }} MB. Gmail limit 25 MB, Outlook 20 MB. If exceeded, email will fail but codes will be auto-restored (failsafe). Consider splitting.
        </div>

        <!-- Send button -->
        <div class="sv-conf-actions">
          <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">Cancel</button>
          <button
            class="avq-btn-primary sv-conf-send-btn"
            :disabled="sending"
            @click="send"
          >
            {{ sending ? 'Sending vouchers… (building Excel in memory)' : '✉ Confirm & Send Vouchers (deduct balance)' }}
          </button>
        </div>

        <p v-if="error" class="form-error sv-conf-error">{{ error }}</p>
        <p v-if="error && error.includes('restored')" style="font-size:13px;color:var(--teal-deep);margin-top:8px;">Failsafe active: Balance restored, codes returned to available pool. You can retry.</p>
      </div>

      <!-- Success overlay -->
      <div v-if="success" class="sv-conf-success-overlay">
        <div class="sv-conf-success-card">
          <div class="sv-conf-success-icon">✅</div>
          <h2>Vouchers Sent Securely!</h2>
          <p>
            <strong>{{ store.cartItemCount }} code(s)</strong> across <strong>{{ cart.length }} brand(s)</strong> worth
            <strong>₹{{ fmt(sentTotal) }}</strong> have been sent to
            <strong>{{ spoc?.email }}</strong> as Excel attachment.
          </p>
          <p style="font-size:12px;color:var(--ink-muted);">Codes were decrypted only at send time and marked as sent. Balance deducted: ₹{{ fmt(sentTotal) }}</p>
          <p class="sv-conf-order-num">Order: {{ orderNumber }} | Status: sent | File: in-memory, never on disk</p>
          <div class="sv-conf-success-actions">
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
import AppLayout from '../../../shared/components/AppLayout.vue';

const store = useSendVoucherStore();
const router = useRouter();

const customer = ref(null);
const spoc = ref(null);
const sending = ref(false);
const success = ref(false);
const error = ref('');
const orderNumber = ref('');
const sentTotal = ref(0);
const cart = computed(() => store.cart);

const balanceAfter = computed(() =>
  (customer.value?.balance ?? 0) - store.cartTotal
);

onMounted(() => {
  const c = sessionStorage.getItem('avq_sendv_customer');
  const s = sessionStorage.getItem('avq_sendv_spoc');
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
  sentTotal.value = store.cartTotal;
  try {
    const result = await store.placeOrder(customer.value.id, spoc.value.id);
    orderNumber.value = result.order.order_number;
    success.value = true;
    sessionStorage.removeItem('avq_sendv_customer');
    sessionStorage.removeItem('avq_sendv_spoc');
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to send vouchers. Please try again. If balance was deducted, it will be auto-restored on failure.';
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
.sv-conf-page { padding: 28px; font-family: var(--fb); max-width: 860px; }
.sv-conf-header { margin-bottom: 24px; }
.sv-conf-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 8px 0 4px; }
.sv-conf-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }
.sv-conf-layout { display: flex; flex-direction: column; gap: 20px; }
.sv-conf-card { padding: 24px; }
.sv-conf-card h3 { font-family: var(--fd); font-size: 16px; font-weight: 600; margin: 0 0 16px; line-height: 1.4; }
.sv-conf-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.sv-conf-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); font-weight: 600; padding: 8px 12px; background: var(--surface-2); }
.sv-conf-table td { padding: 12px; border-top: 1px solid var(--border-2); vertical-align: middle; }
.sv-conf-table .num { text-align: right; font-family: var(--fd); }
.sv-conf-product { display: flex; align-items: center; gap: 8px; }
.sv-conf-product img { width: 28px; height: 28px; object-fit: contain; border-radius: 5px; }
.sv-conf-total-label { font-weight: 700; text-align: right; padding-right: 12px; }
.sv-conf-total { font-family: var(--fd); font-size: 18px; font-weight: 700; color: var(--teal-deep); }
.sv-conf-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.sv-conf-detail-item { display: flex; flex-direction: column; gap: 4px; }
.sv-conf-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); }
.sv-conf-value { font-size: 14px; font-weight: 500; color: var(--ink); word-break: break-all; }
.text-red { color: #b91c1c !important; }
.text-green { color: var(--teal-deep) !important; }
.sv-conf-warning { background: #fef6ec; border: 1px solid #f3e2c7; border-radius: 10px; padding: 14px 16px; font-size: 14px; color: #b45309; line-height: 1.5; }
.sv-conf-actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; }
.sv-conf-send-btn { padding: 14px 28px; font-size: 15px; min-width: 320px; }
.sv-conf-send-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.sv-conf-error { background: #FEF2F2; border: 1px solid #FECACA; padding: 12px; border-radius: 8px; margin-top: 12px; }

/* Success overlay */
.sv-conf-success-overlay { position: fixed; inset: 0; background: rgba(8,80,65,0.6); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 24px; }
.sv-conf-success-card { background: #fff; border-radius: 20px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 32px 80px rgba(8,80,65,0.25); }
.sv-conf-success-icon { font-size: 56px; margin-bottom: 16px; }
.sv-conf-success-card h2 { font-family: var(--fd); font-size: 26px; margin: 0 0 12px; }
.sv-conf-success-card p { color: var(--ink-soft); font-size: 14px; line-height: 1.6; margin: 0 0 8px; }
.sv-conf-order-num { font-size: 11px; color: var(--ink-muted); font-family: monospace; background: var(--surface-2); padding: 6px 10px; border-radius: 6px; display: inline-block; margin-top: 8px; }
.sv-conf-success-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 24px; }
.sv-conf-success-actions button { width: 100%; padding: 13px; font-size: 14px; }
</style>
