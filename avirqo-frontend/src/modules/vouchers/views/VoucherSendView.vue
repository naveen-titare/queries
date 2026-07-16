<template>
  <AppLayout>
    <div class="vsend-page">
      <div class="vsend-header">
        <button class="avq-btn-ghost vsend-back" @click="router.push('/vouchers')">← Back to catalog</button>
        <h1>Select Recipient</h1>
        <p>Choose the customer and SPOC to receive the vouchers</p>
      </div>

      <div class="vsend-layout">
        <!-- Left: Customer + SPOC selection -->
        <div class="vsend-left">
          <div class="avq-card vsend-card">
            <h3>Customer</h3>
            <input v-model="customerSearch" class="avq-input" placeholder="Search customer…" @input="searchCustomers" />
            <div v-if="customers.length" class="vsend-cust-list">
              <div
                v-for="c in customers"
                :key="c.id"
                class="vsend-cust-item"
                :class="{ selected: selectedCustomer?.id === c.id }"
                @click="selectCustomer(c)"
              >
                <div class="vsend-cust-name">{{ c.company_name }}</div>
                <div class="vsend-cust-meta">{{ c.location }}</div>
                <div class="vsend-cust-balance">Balance: ₹{{ fmt(c.balance) }}</div>
              </div>
            </div>
            <p v-else-if="customerSearch && !loading" class="vsend-empty">No customers found.</p>
          </div>

          <div v-if="selectedCustomer" class="avq-card vsend-card">
            <h3>SPOC (Recipient)</h3>
            <div v-if="selectedCustomer.spocs?.length === 0" class="vsend-empty">No SPOCs found for this customer.</div>
            <div v-else class="vsend-spoc-list">
              <div
                v-for="spoc in selectedCustomer.spocs"
                :key="spoc.id"
                class="vsend-spoc-item"
                :class="{ selected: selectedSpoc?.id === spoc.id, 'no-email': !spoc.email }"
                @click="spoc.email && selectSpoc(spoc)"
              >
                <div class="vsend-spoc-name">
                  {{ spoc.name }}
                  <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
                </div>
                <div class="vsend-spoc-email">
                  {{ spoc.email || '⚠ No email — cannot receive vouchers' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Cart summary -->
        <div class="vsend-right">
          <div class="avq-card vsend-card">
            <h3>Cart Summary</h3>
            <div v-if="!store.cart.length" class="vsend-empty">Your cart is empty.</div>
            <div v-for="item in store.cart" :key="item.key" class="vsend-cart-item">
              <img v-if="item.image_url" :src="item.image_url" class="vsend-cart-img" />
              <div class="vsend-cart-info">
                <div class="vsend-cart-name">{{ item.product_name }}</div>
                <div class="vsend-cart-denom">{{ item.currency_code }} {{ fmt(item.denomination) }} × {{ item.quantity }}</div>
              </div>
              <div class="vsend-cart-total">₹{{ fmt(item.denomination * item.quantity) }}</div>
              <button class="vsend-remove" @click="store.removeFromCart(item.key)">✕</button>
            </div>
            <div class="vsend-cart-footer">
              <span>Total</span>
              <strong>₹{{ fmt(store.cartTotal) }}</strong>
            </div>

            <div v-if="selectedCustomer" class="vsend-balance-check">
              <div class="vsend-balance-row">
                <span>Customer balance</span>
                <span>₹{{ fmt(selectedCustomer.balance) }}</span>
              </div>
              <div class="vsend-balance-row">
                <span>Order total</span>
                <span>₹{{ fmt(store.cartTotal) }}</span>
              </div>
              <div class="vsend-balance-row" :class="balanceShortfall > 0 ? 'text-red' : 'text-green'">
                <span>Balance after</span>
                <span>₹{{ fmt(selectedCustomer.balance - store.cartTotal) }}</span>
              </div>
            </div>

            <p v-if="error" class="form-error">{{ error }}</p>

            <button
              class="avq-btn-primary vsend-confirm-btn"
              :disabled="!canProceed || loading"
              @click="proceed"
            >
              {{ loading ? 'Validating…' : 'Review & Confirm →' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useVoucherStore } from '../store/voucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import axios from 'axios';

const store = useVoucherStore();
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
    if (!customerSearch.value) return;
    const token = localStorage.getItem('avirqo_access_token');
    const base = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
    const { data } = await axios.get(`${base}/customers`, {
      params: { search: customerSearch.value },
      headers: { Authorization: `Bearer ${token}` },
    });
    customers.value = data.data;
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
    // Pass selection to confirm page via sessionStorage
    sessionStorage.setItem('vsend_customer', JSON.stringify(selectedCustomer.value));
    sessionStorage.setItem('vsend_spoc', JSON.stringify(selectedSpoc.value));
    router.push('/vouchers/confirm');
  } catch (e) {
    error.value = e.response?.data?.message || 'Validation failed.';
  } finally {
    loading.value = false;
  }
}

function fmt(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
</script>

<style>
.vsend-page { padding: 28px; font-family: var(--fb); }
.vsend-header { margin-bottom: 24px; }
.vsend-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 8px 0 4px; }
.vsend-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }
.vsend-back { margin-bottom: 12px; }
.vsend-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .vsend-layout { grid-template-columns: 1fr; } }
.vsend-left, .vsend-right { display: flex; flex-direction: column; gap: 16px; }
.vsend-card { padding: 20px; }
.vsend-card h3 { font-family: var(--fd); font-size: 16px; font-weight: 600; margin: 0 0 14px; }
.vsend-empty { color: var(--ink-muted); font-size: 14px; padding: 12px 0; }
.vsend-cust-list, .vsend-spoc-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; max-height: 280px; overflow-y: auto; }
.vsend-cust-item, .vsend-spoc-item { padding: 12px; border: 1.5px solid var(--border-2); border-radius: 10px; cursor: pointer; transition: all 0.15s; }
.vsend-cust-item:hover, .vsend-spoc-item:hover:not(.no-email) { border-color: var(--teal-mid); background: var(--teal-pale); }
.vsend-cust-item.selected, .vsend-spoc-item.selected { border-color: var(--teal-deep); background: var(--teal-pale); }
.vsend-spoc-item.no-email { opacity: 0.5; cursor: not-allowed; }
.vsend-cust-name, .vsend-spoc-name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
.vsend-cust-meta, .vsend-spoc-email { font-size: 12px; color: var(--ink-muted); }
.vsend-cust-balance { font-size: 12px; color: var(--teal-deep); font-weight: 600; margin-top: 4px; }
.vsend-cart-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-2); }
.vsend-cart-img { width: 36px; height: 36px; object-fit: contain; border-radius: 6px; border: 1px solid var(--border-2); }
.vsend-cart-info { flex: 1; }
.vsend-cart-name { font-size: 13px; font-weight: 600; }
.vsend-cart-denom { font-size: 12px; color: var(--ink-muted); }
.vsend-cart-total { font-family: var(--fd); font-weight: 600; font-size: 14px; }
.vsend-remove { background: none; border: none; color: var(--ink-muted); cursor: pointer; font-size: 14px; padding: 4px; }
.vsend-remove:hover { color: #b91c1c; }
.vsend-cart-footer { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; font-size: 15px; }
.vsend-cart-footer strong { font-family: var(--fd); font-size: 18px; color: var(--teal-deep); }
.vsend-balance-check { background: var(--surface-2); border-radius: 10px; padding: 12px; margin: 12px 0; }
.vsend-balance-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
.vsend-balance-row.text-red span:last-child { color: #b91c1c; font-weight: 700; }
.vsend-balance-row.text-green span:last-child { color: var(--teal-deep); font-weight: 700; }
.vsend-confirm-btn { width: 100%; margin-top: 16px; padding: 14px; font-size: 15px; }
.vsend-confirm-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
