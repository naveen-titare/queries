<template>
  <AppLayout>
    <div class="avq-customers" style="max-width:860px">
      <div class="cust-header">
        <div>
          <h2>Confirm & Send</h2>
          <p>Review order before sending - balance will be auto-deducted</p>
        </div>
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers')">← Back to catalog</button>
      </div>

      <div style="display:flex; flex-direction:column; gap:20px">
        <div class="cust-table-wrap" style="padding:20px">
          <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Order Summary - One email per SPOC, all vouchers as Excel attachment</h3>
          <table class="cust-table">
            <thead><tr><th>PRODUCT</th><th>DENOMINATION</th><th>QTY</th><th>DISCOUNT</th><th style="text-align:right">TOTAL</th></tr></thead>
            <tbody>
              <tr v-for="item in cart" :key="item.key">
                <td><div style="display:flex; gap:8px; align-items:center"><div style="width:28px; height:28px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px">{{ item.brand.charAt(0) }}</div><span>{{ item.product_name }} <small style="color:var(--ink-muted)">{{ item.brand }}</small></span></div></td>
                <td>{{ item.currency_code }} {{ fmt(item.denomination) }}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ item.discount_percentage || 0 }}%</td>
                <td style="text-align:right" class="cust-balance">₹{{ fmt(item.denomination*item.quantity*(1-(item.discount_percentage||0)/100)) }}</td>
              </tr>
            </tbody>
            <tfoot><tr><td colspan="4" style="text-align:right; font-weight:700">Campaign discount</td><td style="text-align:right;color:var(--teal-deep)">− ₹{{ fmt(store.cartDiscountTotal) }}</td></tr><tr><td colspan="4" style="text-align:right; font-weight:700">Total after discount ({{ cart.length }} brands, {{ store.cartItemCount }} codes)</td><td style="text-align:right; font-weight:700; font-size:18px" class="cust-balance">₹{{ fmt(store.cartTotal) }}</td></tr></tfoot>
          </table>
          <p style="font-size:12px; color:var(--ink-muted); margin-top:8px">Excel will contain {{ store.cartItemCount }} rows: Brand | Product | Denom | Code (decrypted) | PIN | Expiry</p>
        </div>

        <div class="cust-table-wrap" style="padding:20px">
          <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Recipient (from Customers module)</h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">Company</div><div style="font-size:14px; font-weight:500">{{ customer?.company_name }}</div></div>
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">Location</div><div style="font-size:14px">{{ customer?.location }}</div></div>
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">SPOC Name</div><div style="font-size:14px">{{ spoc?.name }}</div></div>
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">Email</div><div style="font-size:14px">{{ spoc?.email }}</div></div>
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">Current Balance</div><div style="font-size:14px">₹{{ fmt(customer?.balance) }}</div></div>
            <div><div style="font-size:11px; text-transform:uppercase; color:var(--ink-muted); font-weight:600">Balance After</div><div style="font-size:14px; font-weight:700" :style="balanceAfter<0?'color:#b91c1c':'color:var(--teal-deep)'">₹{{ fmt(balanceAfter) }}</div></div>
          </div>
        </div>

        <div v-if="balanceAfter<0" style="background:#fef6ec; border:1px solid #f3e2c7; border-radius:10px; padding:12px; color:#b45309">⚠ This will make balance negative (₹{{ fmt(balanceAfter) }}). Customer module adjustBalance would block, but you can still proceed with warning (configurable).</div>
        <div v-if="store.cartItemCount>3000" style="background:#FFF7ED; border:1px solid #FED7AA; border-radius:10px; padding:12px; color:#9A3412">⚠ Large order: {{ store.cartItemCount }} codes ~{{ Math.ceil(store.cartItemCount*0.002) }} MB. Gmail 25MB limit (~18MB raw). If exceeded, failsafe restores balance.</div>

        <!-- Step 1: Initiate Order & Send OTP -->
        <div v-if="!otpSent" style="display:flex; flex-direction:column; gap:16px; padding:20px; background:#f0fdf4; border:1px solid #86efac; border-radius:12px">
          <div style="display:flex; align-items:center; gap:10px; color:#166534">
            <span style="font-size:24px">🔐</span>
            <div>
              <strong style="font-size:16px">OTP Verification Required</strong>
              <div style="font-size:13px; color:var(--ink-muted)">An OTP will be sent to <strong>naveentitare52@gmail.com</strong> and <strong>ptitare@gmail.com</strong> with order summary.</div>
            </div>
          </div>
          <div style="background:white; border-radius:8px; padding:16px; border:1px solid #86efac">
            <h4 style="margin:0 0 12px; font-size:14px; color:#166534">Order Summary for OTP Email</h4>
            <div style="font-size:13px; color:var(--ink-muted)">
              <div><strong>{{ cart.length }} brand{{ cart.length !== 1 ? 's' : '' }}</strong>, <strong>{{ store.cartItemCount }} codes</strong> worth <strong>₹{{ fmt(store.cartTotal) }}</strong></div>
              <div>Customer: <strong>{{ customer?.company_name }}</strong> (Balance after: <span :style="balanceAfter<0?'color:#b91c1c':'color:var(--teal-deep)'">₹{{ fmt(balanceAfter) }}</span>)</div>
              <div>SPOC: <strong>{{ spoc?.name }}</strong> ({{ spoc?.email }})</div>
            </div>
          </div>
          <div style="display:flex; justify-content:flex-end; gap:12px">
            <button class="avq-btn-ghost" @click="router.push('/send-vouchers')">Cancel</button>
            <button class="avq-btn-primary" style="padding:12px 28px" :disabled="sending" @click="initiateOrder">
              {{ sending ? 'Initiating… sending OTP' : '🔐 Initiate Order & Send OTP' }}
            </button>
          </div>
        </div>

        <!-- Step 2: OTP Verification -->
        <div v-else-if="otpSent && !otpVerified" style="display:flex; flex-direction:column; gap:16px; padding:20px; background:#fff3e0; border:1px solid #ffb74d; border-radius:12px">
          <div style="display:flex; align-items:center; gap:10px; color:#e65100">
            <span style="font-size:24px">📧</span>
            <div>
              <strong style="font-size:16px">OTP Sent Successfully</strong>
              <div style="font-size:13px; color:var(--ink-muted)">OTP sent to <strong>naveentitare52@gmail.com</strong> and <strong>ptitare@gmail.com</strong>. Valid for 10 minutes.</div>
            </div>
          </div>
          
          <div style="background:white; border-radius:8px; padding:16px; border:1px solid #ffb74d">
            <label style="display:block; font-size:12px; font-weight:600; color:var(--ink-muted); margin-bottom:8px">Enter 6-Digit OTP</label>
            <input v-model="otp" type="text" maxlength="6" class="avq-input" style="letter-spacing:4px; text-align:center; font-size:24px; font-family:monospace" placeholder="123456" @keyup.enter="verifyOtp" />
            <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center">
              <button class="avq-btn-ghost" style="padding:8px 16px" @click="resendOtp" :disabled="resending">{{ resending ? 'Resending…' : 'Resend OTP' }}</button>
              <span style="font-size:12px; color:var(--ink-muted)">{{ otp.length }}/6 digits</span>
            </div>
          </div>
          
          <div style="display:flex; justify-content:flex-end; gap:12px">
            <button class="avq-btn-ghost" @click="cancelOtp">Cancel Order</button>
            <button class="avq-btn-primary" style="padding:12px 28px" :disabled="verifying || otp.length !== 6" @click="verifyOtp">
              {{ verifying ? 'Verifying…' : '✅ Verify OTP & Send Vouchers' }}
            </button>
          </div>
        </div>

        <!-- Step 3: Final Success (after OTP verified) -->
        <div v-else-if="otpVerified" style="display:flex; flex-direction:column; gap:16px; padding:20px; background:#f0fdf4; border:1px solid #86efac; border-radius:12px">
          <div style="display:flex; align-items:center; gap:10px; color:#166534">
            <span style="font-size:24px">⏳</span>
            <div>
              <strong style="font-size:16px">OTP Verified! Sending Vouchers…</strong>
              <div style="font-size:13px; color:var(--ink-muted)">Building encrypted Excel and sending to SPOC email</div>
            </div>
          </div>
        </div>

        <p v-if="error" style="color:#b91c1c; background:#fef2f2; padding:12px; border-radius:8px">{{ error }}</p>
      </div>

      <!-- Final Success Overlay -->
      <div v-if="success" style="position:fixed; inset:0; background:rgba(8,80,65,0.6); backdrop-filter:blur(4px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:24px">
        <div style="background:#fff; border-radius:20px; padding:40px; max-width:480px; width:100%; text-align:center">
          <div style="font-size:48px">✅</div>
          <h2 style="font-size:24px; margin:12px 0">Vouchers Sent!</h2>
          <p style="font-size:14px; color:var(--ink-muted)">
            <strong>{{ savedCodeCount }} codes</strong> across <strong>{{ savedBrandCount }} brand{{ savedBrandCount !== 1 ? 's' : '' }}</strong>
            worth ₹{{ fmt(savedTotal) }} sent to <strong>{{ spoc?.email }}</strong>
          </p>
          <p style="font-size:11px; font-family:monospace; background:var(--surface-2); padding:6px 10px; border-radius:6px; margin-top:8px">
            Order: {{ orderNumber }} • encrypted, no file on disk
          </p>
          <!-- Verification info -->
          <div v-if="codesHash" style="margin-top:12px; padding:10px; background:#f0fdf4; border:1px solid #86efac; border-radius:8px; font-size:12px; color:#166534">
            <strong>Verification Hash:</strong> {{ codesHash }}
            <br><span style="font-size:11px">Use this hash to verify codes in Excel match database</span>
          </div>
          <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px">
            <button class="avq-btn-primary" @click="goToCatalog">Send more</button>
            <button class="avq-btn-ghost" @click="router.push('/dashboard')">Dashboard</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>

  <AppDialogModal
    :open="showCancelModal"
    title="Cancel order"
    message="Cancel this order? Balance will be restored."
    confirm-text="Cancel order"
    cancel-text="Keep order"
    variant="danger"
    :loading="cancelSubmitting"
    @cancel="showCancelModal = false"
    @confirm="confirmCancelOtp"
  >
    <p v-if="cancelError" class="form-error" style="margin-top:16px">{{ cancelError }}</p>
  </AppDialogModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';

const store = useSendVoucherStore();
const router = useRouter();

const customer = ref(null);
const spoc = ref(null);
const sending = ref(false);
const success = ref(false);
const error = ref('');
const orderNumber = ref('');
const sentTotal = ref(0);

// FIX: Save cart data BEFORE placeOrder clears it
const savedBrandCount = ref(0);
const savedCodeCount = ref(0);
const savedTotal = ref(0);
const codesHash = ref('');

// OTP Flow State
const otpSent = ref(false);
const otpVerified = ref(false);
const otp = ref('');
const verifying = ref(false);
const resending = ref(false);
const showCancelModal = ref(false);
const cancelSubmitting = ref(false);
const cancelError = ref('');

const cart = computed(() => store.cart);
const balanceAfter = computed(() => (customer.value?.balance || 0) - store.cartTotal);

onMounted(() => {
  const c = sessionStorage.getItem('avq_sendv_customer');
  const s = sessionStorage.getItem('avq_sendv_spoc');
  if (!c || !s || !store.cart.length) {
    router.push('/send-vouchers');
    return;
  }
  customer.value = JSON.parse(c);
  spoc.value = JSON.parse(s);
  
  // Check for pending OTP order
  const pendingOrder = sessionStorage.getItem('avq_pending_otp_order');
  if (pendingOrder) {
    const pending = JSON.parse(pendingOrder);
    if (pending.customer_id === customer.value.id) {
      // Show banner for pending OTP
      otpSent.value = true;
      orderNumber.value = pending.order_number;
    }
  }
});

// Step 1: Initiate Order & Send OTP
async function initiateOrder() {
  sending.value = true;
  error.value = '';
  
  // Capture cart data BEFORE placeOrder clears the cart
  savedBrandCount.value = store.cart.length;
  savedCodeCount.value = store.cartItemCount;
  savedTotal.value = store.cartTotal;
  
  try {
    const items = store.cart.map(i => ({
      product_id: i.product_id,
      denomination: i.denomination,
      quantity: i.quantity,
    }));

    const r = await store.initiateOrder(customer.value.id, spoc.value.id, items);
    orderNumber.value = r.order.order_number;
    otpSent.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to initiate order';
  } finally {
    sending.value = false;
  }
}

// Step 2: Verify OTP
async function verifyOtp() {
  if (otp.value.length !== 6) return;
  verifying.value = true;
  error.value = '';
  
  try {
    otpVerified.value = true;
    // Wait a bit for UI
    await new Promise(r => setTimeout(r, 500));
    
    const r = await store.verifyOrderOtp(orderNumber.value, otp.value);
    
    // Get verification hash from backend response
    if (r.order.codes_hash) {
      codesHash.value = r.order.codes_hash;
    }
    
    success.value = true;
    sessionStorage.removeItem('avq_sendv_customer');
    sessionStorage.removeItem('avq_sendv_spoc');
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to verify OTP';
    otpVerified.value = false;
  } finally {
    verifying.value = false;
  }
}

// Resend OTP
async function resendOtp() {
  resending.value = true;
  error.value = '';
  try {
    await store.resendOrderOtp(orderNumber.value);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to resend OTP';
  } finally {
    resending.value = false;
  }
}

// Cancel OTP flow - need to restore balance
async function cancelOtp() {
  cancelError.value = '';
  showCancelModal.value = true;
}

async function confirmCancelOtp() {
  cancelSubmitting.value = true;
  cancelError.value = '';
  error.value = '';
  try {
    await store.cancelOrder(orderNumber.value);
    showCancelModal.value = false;
    router.push('/send-vouchers');
  } catch (e) {
    cancelError.value = e.response?.data?.message || 'Failed to cancel order';
  } finally {
    cancelSubmitting.value = false;
  }
}

function goToCatalog() {
  success.value = false;
  router.push('/send-vouchers');
}

function fmt(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }
</script>
