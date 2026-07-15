<template>
  <AppLayout>
    <div class="sv-send-page">
      <div class="sv-send-header">
        <button class="avq-btn-ghost sv-send-back" @click="router.push('/send-vouchers')">← Back to catalog</button>
        <h1>Select Recipient</h1>
        <p>Choose the customer and SPOC to receive the vouchers — one email per SPOC</p>
      </div>

      <div class="sv-send-layout">
        <!-- Left: Customer + SPOC selection -->
        <div class="sv-send-left">
          <div class="avq-card sv-send-card">
            <h3>Customer</h3>
            <input v-model="customerSearch" class="avq-input" placeholder="Search customer… (company, email)" @input="searchCustomers" />
            <div v-if="customers.length" class="sv-send-cust-list">
              <div
                v-for="c in customers"
                :key="c.id"
                class="sv-send-cust-item"
                :class="{ selected: selectedCustomer?.id === c.id }"
                @click="selectCustomer(c)"
              >
                <div class="sv-send-cust-name">{{ c.company_name }}</div>
                <div class="sv-send-cust-meta">{{ c.location }}</div>
                <div class="sv-send-cust-balance">Balance: ₹{{ fmt(c.balance) }}</div>
              </div>
            </div>
            <p v-else-if="customerSearch && !loading" class="sv-send-empty">No customers found.</p>
            <p v-else class="sv-send-hint">Type 2+ letters to search.</p>
          </div>

          <div v-if="selectedCustomer" class="avq-card sv-send-card">
            <h3>SPOC (Recipient)</h3>
            <p style="font-size:12px;color:var(--ink-muted);margin:-8px 0 12px;">Encrypted vouchers will be sent to this email with Excel attachment</p>
            <div v-if="selectedCustomer.spocs?.length === 0" class="sv-send-empty">No SPOCs found for this customer.</div>
            <div v-else class="sv-send-spoc-list">
              <div
                v-for="spoc in selectedCustomer.spocs"
                :key="spoc.id"
                class="sv-send-spoc-item"
                :class="{ selected: selectedSpoc?.id === spoc.id, 'no-email': !spoc.email }"
                @click="spoc.email && selectSpoc(spoc)"
              >
                <div class="sv-send-spoc-name">
                  {{ spoc.name }}
                  <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
                </div>
                <div class="sv-send-spoc-email">
                  {{ spoc.email || '⚠ No email — cannot receive vouchers' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Cart summary -->
        <div class="sv-send-right">
          <div class="avq-card sv-send-card">
            <h3>Cart Summary ({{ store.cartItemCount }} items)</h3>
            <div v-if="!store.cart.length" class="sv-send-empty">Your cart is empty. <RouterLink to="/send-vouchers">Go to catalog</RouterLink></div>
            <div v-for="item in store.cart" :key="item.key" class="sv-send-cart-item">
              <img v-if="item.image_url" :src="item.image_url" class="sv-send-cart-img" />
              <div class="sv-send-cart-info">
                <div class="sv-send-cart-name">{{ item.product_name }}</div>
                <div class="sv-send-cart-denom">{{ item.currency_code }} {{ fmt(item.denomination) }} × {{ item.quantity }}</div>
                <div v-if="item.available <= 10" style="font-size:10px;color:#b45309;">⚠ Only {{ item.available }} left in stock</div>
              </div>
              <div class="sv-send-cart-total">₹{{ fmt(item.denomination * item.quantity) }}</div>
              <button class="sv-send-remove" @click="store.removeFromCart(item.key)">✕</button>
            </div>
            <div class="sv-send-cart-footer">
              <span>Total to deduct from balance</span>
              <strong>₹{{ fmt(store.cartTotal) }}</strong>
            </div>

            <div v-if="selectedCustomer" class="sv-send-balance-check">
              <div class="sv-send-balance-row">
                <span>Customer balance (before)</span>
                <span>₹{{ fmt(selectedCustomer.balance) }}</span>
              </div>
              <div class="sv-send-balance-row">
                <span>Order total</span>
                <span>₹{{ fmt(store.cartTotal) }}</span>
              </div>
              <div class="sv-send-balance-row" :class="balanceShortfall > 0 ? 'text-red' : 'text-green'">
                <span>Balance after (auto deducted)</span>
                <span>₹{{ fmt(selectedCustomer.balance - store.cartTotal) }}</span>
              </div>
            </div>

            <p v-if="error" class="form-error">{{ error }}</p>

            <button
              class="avq-btn-primary sv-send-confirm-btn"
              :disabled="!canProceed || loading"
              @click="proceed"
            >
              {{ loading ? 'Validating stock…' : 'Review & Confirm →' }}
            </button>
            <p style="font-size:11px;color:var(--ink-muted);margin-top:8px;text-align:center;">Codes are encrypted and reserved only after confirmation</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../../shared/components/AppLayout.vue';
import axios from 'axios';
// NOTE: We REFERENCE customer module but DO NOT MODIFY it.
// Customer module exists at frontend/src/modules/customers/
// Its API is: api/customerApi.js with list() and get() methods
// Its routes are: /customers, /customers/{id} - same as we use below
// If you want to reuse customerApi directly, you can uncomment:
// import customerApi from '../../customers/api/customerApi.js';
// and use customerApi.list({search}) instead of axios below.
// Current implementation uses direct axios to keep send-vouchers independent
// but 100% compatible with customer module's backend API.

const store = useSendVoucherStore();
const router = useRouter();

const customerSearch = ref('');
const customers = ref([]);
const selectedCustomer = ref(null);
const selectedSpoc = ref(null);
const loading = ref(false);
const error = ref('');
let searchTimer = null;

const balanceShortfall = computed(() =>
  selectedCustomer.value ? store.cartTotal - selectedCustomer.value.balance : 0
);

const canProceed = computed(() =>
  selectedCustomer.value && selectedSpoc.value && store.cart.length > 0
);

function searchCustomers() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    if (!customerSearch.value || customerSearch.value.length < 2) return;
    const token = localStorage.getItem('avirqo_access_token');
    const base = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
    try {
      const { data } = await axios.get(`${base}/customers`, {
        params: { search: customerSearch.value },
        headers: { Authorization: `Bearer ${token}` },
      });
      customers.value = data.data || data;
    } catch (e) {
      console.error(e);
    }
  }, 300);
}

async function selectCustomer(customer) {
  const token = localStorage.getItem('avirqo_access_token');
  const base = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
  const { data } = await axios.get(`${base}/customers/${customer.id}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  selectedCustomer.value = data;
  selectedSpoc.value = data.spocs?.length === 1 ? data.spocs[0] : null;
}

function selectSpoc(spoc) { selectedSpoc.value = spoc; }

async function proceed() {
  loading.value = true;
  error.value = '';
  try {
    await store.validateCart();
    // Pass selection to confirm page via sessionStorage - renamed keys to avoid conflict
    sessionStorage.setItem('avq_sendv_customer', JSON.stringify(selectedCustomer.value));
    sessionStorage.setItem('avq_sendv_spoc', JSON.stringify(selectedSpoc.value));
    router.push('/send-vouchers/confirm');
  } catch (e) {
    error.value = e.response?.data?.message || (e.response?.data?.errors ? JSON.stringify(e.response.data.errors) : 'Validation failed - check stock.');
  } finally {
    loading.value = false;
  }
}

function fmt(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
</script>

<style>
.sv-send-page { padding: 28px; font-family: var(--fb); }
.sv-send-header { margin-bottom: 24px; }
.sv-send-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 8px 0 4px; }
.sv-send-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }
.sv-send-back { margin-bottom: 12px; }
.sv-send-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .sv-send-layout { grid-template-columns: 1fr; } }
.sv-send-left, .sv-send-right { display: flex; flex-direction: column; gap: 16px; }
.sv-send-card { padding: 20px; }
.sv-send-card h3 { font-family: var(--fd); font-size: 16px; font-weight: 600; margin: 0 0 14px; }
.sv-send-empty { color: var(--ink-muted); font-size: 14px; padding: 12px 0; }
.sv-send-hint { color: var(--ink-muted); font-size: 12px; padding: 8px 0; opacity: 0.7; }
.sv-send-cust-list, .sv-send-spoc-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; max-height: 280px; overflow-y: auto; }
.sv-send-cust-item, .sv-send-spoc-item { padding: 12px; border: 1.5px solid var(--border-2); border-radius: 10px; cursor: pointer; transition: all 0.15s; }
.sv-send-cust-item:hover, .sv-send-spoc-item:hover:not(.no-email) { border-color: var(--teal-mid); background: var(--teal-pale); }
.sv-send-cust-item.selected, .sv-send-spoc-item.selected { border-color: var(--teal-deep); background: var(--teal-pale); }
.sv-send-spoc-item.no-email { opacity: 0.5; cursor: not-allowed; }
.sv-send-cust-name, .sv-send-spoc-name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
.sv-send-cust-meta, .sv-send-spoc-email { font-size: 12px; color: var(--ink-muted); }
.sv-send-cust-balance { font-size: 12px; color: var(--teal-deep); font-weight: 600; margin-top: 4px; }
.sv-send-cart-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-2); }
.sv-send-cart-img { width: 36px; height: 36px; object-fit: contain; border-radius: 6px; border: 1px solid var(--border-2); }
.sv-send-cart-info { flex: 1; }
.sv-send-cart-name { font-size: 13px; font-weight: 600; }
.sv-send-cart-denom { font-size: 12px; color: var(--ink-muted); }
.sv-send-cart-total { font-family: var(--fd); font-weight: 600; font-size: 14px; }
.sv-send-remove { background: none; border: none; color: var(--ink-muted); cursor: pointer; font-size: 14px; padding: 4px; }
.sv-send-remove:hover { color: #b91c1c; }
.sv-send-cart-footer { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; font-size: 15px; border-top: 2px solid var(--border-2); margin-top: 8px; }
.sv-send-cart-footer strong { font-family: var(--fd); font-size: 18px; color: var(--teal-deep); }
.sv-send-balance-check { background: var(--surface-2); border-radius: 10px; padding: 12px; margin: 12px 0; }
.sv-send-balance-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
.sv-send-balance-row.text-red span:last-child { color: #b91c1c; font-weight: 700; }
.sv-send-balance-row.text-green span:last-child { color: var(--teal-deep); font-weight: 700; }
.sv-send-confirm-btn { width: 100%; margin-top: 16px; padding: 14px; font-size: 15px; }
.sv-send-confirm-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.spoc-primary { font-size: 9px; background: var(--teal-deep); color: white; padding: 2px 6px; border-radius: 4px; margin-left: 6px; }
</style>
