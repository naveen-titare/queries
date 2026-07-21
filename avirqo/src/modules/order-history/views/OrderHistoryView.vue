<template>
  <AppLayout>
    <div class="oh-page">
      <!-- Header -->
      <div class="oh-header">
        <div>
          <h1>Order History</h1>
          <p>All client voucher orders across all customers</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="oh-filters">
        <input
          v-model="search"
          class="avq-input oh-search"
          placeholder="Search order no., customer, SPOC…"
          @input="onSearch"
        />
        <select v-model="statusFilter" class="avq-input" @change="onFilterChange">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="pending_otp">Awaiting OTP</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="partially_failed">Partially Failed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input v-model="dateFrom" class="avq-input" type="date" @change="onFilterChange" />
        <input v-model="dateTo" class="avq-input" type="date" @change="onFilterChange" />
      </div>

      <!-- Summary chips -->
      <div class="oh-summary">
        <div class="oh-chip oh-chip-sent">
          ✅ Sent: <strong>{{ counts.sent }}</strong>
        </div>
        <div class="oh-chip oh-chip-pending_otp">
          ⏳ Awaiting OTP: <strong>{{ counts.pending_otp }}</strong>
        </div>
        <div class="oh-chip oh-chip-processing">
          ⚙ Processing: <strong>{{ counts.processing }}</strong>
        </div>
        <div class="oh-chip oh-chip-failed">
          ❌ Failed: <strong>{{ counts.failed }}</strong>
        </div>
        <div class="oh-chip oh-chip-partially_failed">
          ⚠ Partially Failed: <strong>{{ counts.partially_failed }}</strong>
        </div>
        <div class="oh-chip oh-chip-cancelled">
          🚫 Cancelled: <strong>{{ counts.cancelled }}</strong>
        </div>
      </div>

      <!-- Table -->
      <div class="oh-table-wrap">
        <div v-if="store.loading" class="oh-empty">Loading orders…</div>
        <div v-else-if="store.error" class="oh-empty oh-error">{{ store.error }}</div>
        <table v-else class="oh-table">
          <thead>
            <tr>
              <th>Order No.</th>
              <th>Customer</th>
              <th>SPOC</th>
              <th>Items</th>
              <th class="num">Total</th>
              <th>Status</th>
              <th>Sent By</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!store.orders.length">
              <td colspan="9" class="oh-empty">No orders found.</td>
            </tr>
            <tr
              v-for="order in store.orders"
              :key="order.id"
              class="oh-row"
              @click="openDetail(order.id)"
            >
              <td class="oh-order-num">{{ order.order_number }}</td>
              <td>{{ order.customer?.company_name || '—' }}</td>
              <td>
                <div>{{ order.spoc?.name || '—' }}</div>
                <div class="oh-spoc-email">{{ order.email_sent_to || '—' }}</div>
              </td>
              <td>{{ order.items?.length || '—' }} item(s)</td>
              <td class="num oh-total">₹{{ fmt(order.total_amount) }}</td>
              <td @click.stop>
                <span class="oh-badge" :class="`badge-${order.status}`">
                  {{ statusLabel(order.status) }}
                </span>
              </td>
              <td>{{ order.sent_by?.name || '—' }}</td>
              <td>{{ fmtDate(order.sent_at || order.created_at) }}</td>
              <td @click.stop>
                <div class="oh-actions">
                  <button class="avq-btn-sm" @click="openDetail(order.id)">View</button>
                  
                  <!-- For Awaiting OTP orders -->
                  <template v-if="order.status === 'pending_otp'">
                    <button 
                      class="avq-btn-sm oh-otp-btn"
                      @click="openOtpModal(order)"
                    >
                      Enter OTP
                    </button>
                    <button 
                      class="avq-btn-sm oh-cancel-btn"
                      :disabled="cancelling === order.id"
                      @click="cancelPendingOrder(order)"
                    >
                      {{ cancelling === order.id ? 'Cancelling…' : 'Cancel' }}
                    </button>
                    <button
                      class="avq-btn-sm oh-resend-btn"
                      :disabled="resending === order.id"
                      @click="handleResend(order)"
                    >
                      {{ resending === order.id ? '…' : 'Resend OTP' }}
                    </button>
                  </template>
                  
                  <!-- For other orders -->
                  <button
                    v-else-if="order.status !== 'cancelled'"
                    class="avq-btn-sm oh-resend-btn"
                    :disabled="resending === order.id"
                    @click="handleResend(order)"
                  >
                    {{ resending === order.id ? '…' : resendLabel(order.status) }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="store.pagination && store.pagination.last_page > 1" class="oh-pagination">
        <button :disabled="store.pagination.current_page === 1" @click="changePage(store.pagination.current_page - 1)">← Prev</button>
        <span>Page {{ store.pagination.current_page }} of {{ store.pagination.last_page }} ({{ store.pagination.total }} orders)</span>
        <button :disabled="store.pagination.current_page === store.pagination.last_page" @click="changePage(store.pagination.current_page + 1)">Next →</button>
      </div>

      <!-- Order Detail Drawer -->
      <div v-if="showDetail" class="avq-modal-overlay" @click.self="showDetail = false">
        <div class="avq-drawer oh-drawer">
          <div v-if="store.detailLoading" class="oh-empty">Loading order details…</div>
          <template v-else-if="store.currentOrder">
            <!-- Drawer header -->
            <div class="oh-drawer-header">
              <div>
                <div class="oh-drawer-order-num">{{ store.currentOrder.order_number }}</div>
                <span class="oh-badge" :class="`badge-${store.currentOrder.status}`">
                  {{ statusLabel(store.currentOrder.status) }}
                </span>
              </div>
              <div class="oh-drawer-actions">
                <button
                  v-if="store.currentOrder.status !== 'cancelled'"
                  class="avq-btn-primary oh-resend-btn"
                  :disabled="resending === store.currentOrder.id"
                  @click="handleResend(store.currentOrder)"
                >
                  {{ resending === store.currentOrder.id ? 'Sending…' : resendLabel(store.currentOrder.status) }}
                </button>
                <button class="avq-btn-ghost" @click="showDetail = false">✕ Close</button>
              </div>
            </div>

            <p v-if="resendSuccess" class="oh-success-msg">✅ {{ resendSuccess }}</p>
            <p v-if="resendError" class="oh-error-msg">❌ {{ resendError }}</p>

            <!-- Product Summary -->
            <div class="oh-drawer-section">
              <h4>Product Summary</h4>
              <table class="oh-detail-table">
                <thead>
                  <tr>
                    <th>Brand</th>
                    <th>Denomination</th>
                    <th>Qty</th>
                    <th class="num">Gross Total</th>
                    <th class="num">Discount</th>
                    <th class="num">Net Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in store.currentOrder.items" :key="item.id">
                    <td>
                      <div class="oh-product-name">{{ item.product?.brand || item.product?.name }}</div>
                      <div class="oh-product-sub">{{ item.product?.name }}</div>
                    </td>
                    <td>{{ item.currency_code }} {{ fmt(item.denomination) }}</td>
                    <td>{{ item.quantity }}</td>
                    <td class="num">₹{{ fmt(item.gross_total) }}</td>
                    <td class="num" style="color:#16a34a">
                      <template v-if="item.discount_percentage > 0">
                        −{{ item.discount_percentage }}% (−₹{{ fmt(item.discount_amount) }})
                      </template>
                      <template v-else>—</template>
                    </td>
                    <td class="num oh-total">₹{{ fmt(item.total_value) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="oh-total-label">Order Total</td>
                    <td class="num">₹{{ fmt(itemGrossTotal) }}</td>
                    <td class="num" style="color:#16a34a">−₹{{ fmt(itemDiscountTotal) }}</td>
                    <td class="num oh-total">₹{{ fmt(store.currentOrder.total_amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- SPOC Summary -->
            <div class="oh-drawer-section">
              <h4>Recipient (SPOC)</h4>
              <div class="oh-info-grid">
                <div class="oh-info-item">
                  <span class="oh-info-label">Company</span>
                  <span class="oh-info-value">{{ store.currentOrder.customer?.company_name }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">SPOC Name</span>
                  <span class="oh-info-value">{{ store.currentOrder.spoc?.name }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Email Sent To</span>
                  <span class="oh-info-value">{{ store.currentOrder.email_sent_to }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Phone</span>
                  <span class="oh-info-value">{{ store.currentOrder.spoc?.phone || '—' }}</span>
                </div>
              </div>
            </div>

            <!-- Balance Deduction Summary -->
            <div class="oh-drawer-section">
              <h4>Balance Deduction</h4>
              <div class="oh-balance-summary">
                <div class="oh-balance-row">
                  <span>Balance Before</span>
                  <span>₹{{ fmt(store.currentOrder.customer_balance_before) }}</span>
                </div>
                <div class="oh-balance-row oh-balance-deduct">
                  <span>Deducted</span>
                  <span>− ₹{{ fmt(store.currentOrder.total_amount) }}</span>
                </div>
                <div class="oh-balance-row oh-balance-after" :class="store.currentOrder.customer_balance_after < 0 ? 'negative' : ''">
                  <span>Balance After</span>
                  <span>₹{{ fmt(store.currentOrder.customer_balance_after) }}</span>
                </div>
              </div>
            </div>

            <!-- Order Meta -->
            <div class="oh-drawer-section">
              <h4>Order Details</h4>
              <div class="oh-info-grid">
                <div class="oh-info-item">
                  <span class="oh-info-label">Order Number</span>
                  <span class="oh-info-value oh-mono">{{ store.currentOrder.order_number }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Sent By</span>
                  <span class="oh-info-value">{{ store.currentOrder.sent_by?.name }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Sent At</span>
                  <span class="oh-info-value">{{ fmtDate(store.currentOrder.sent_at) || '—' }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Email Attempts</span>
                  <span class="oh-info-value">{{ store.currentOrder.email_attempts || 1 }}</span>
                </div>
                <div class="oh-info-item" v-if="store.currentOrder.failure_reason">
                  <span class="oh-info-label">Failure Reason</span>
                  <span class="oh-info-value oh-error-text">{{ store.currentOrder.failure_reason }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Total Codes</span>
                  <span class="oh-info-value">{{ store.currentOrder.total_codes_count || '—' }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast" class="oh-toast">{{ toast }}</div>

      <!-- OTP Verification Modal -->
      <div v-if="showOtpModal" class="avq-modal-overlay" @click.self="showOtpModal = false">
        <div class="avq-modal avq-modal-sm">
          <h3>Verify OTP - {{ otpOrder?.order_number }}</h3>
          
          <div style="margin: 20px 0;">
            <p style="color: var(--ink-muted); font-size: 14px; margin-bottom: 16px;">
              Enter the 6-digit OTP sent to <strong>naveentitare52@gmail.com</strong> and <strong>ptitare@gmail.com</strong> to complete this order. Valid for 10 minutes.
            </p>
            
            <input 
              v-model="otpCode" 
              type="text" 
              maxlength="6" 
              class="avq-input" 
              style="letter-spacing: 8px; text-align: center; font-size: 24px; font-family: monospace;"
              placeholder="123456"
              @keyup.enter="verifyOtpFromHistory"
            />
            
            <p v-if="otpError" style="color: #b91c1c; margin-top: 8px; font-size: 13px;">
              {{ otpError }}
            </p>
          </div>
          
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showOtpModal = false">Cancel</button>
            <button 
              type="button" 
              class="avq-btn-primary" 
              :disabled="verifyingOtp || otpCode.length !== 6"
              @click="verifyOtpFromHistory"
            >
              {{ verifyingOtp ? 'Verifying...' : 'Verify OTP' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useOrderHistoryStore } from '../store/orderHistoryStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const store = useOrderHistoryStore();

const search = ref('');
const statusFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);
const showDetail = ref(false);
const resending = ref(null);
const cancelling = ref(null);
const resendSuccess = ref('');
const resendError = ref('');
const toast = ref('');

// OTP Modal
const showOtpModal = ref(false);
const otpOrder = ref(null);
const otpCode = ref('');
const verifyingOtp = ref(false);
const otpError = ref('');
let searchTimer = null;

onMounted(() => load());

function load() {
  store.fetchOrders({
    search: search.value,
    status: statusFilter.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    page: page.value,
  });
}

function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
}

function onFilterChange() {
  // Reset to page 1 whenever status/date filters change, otherwise a narrower
  // filter can land on a now-out-of-range page and appear to return nothing.
  page.value = 1;
  load();
}

function changePage(p) { page.value = p; load(); }

async function openDetail(id) {
  showDetail.value = true;
  resendSuccess.value = '';
  resendError.value = '';
  await store.fetchOrder(id);
}

async function handleResend(order) {
  const id = order.id;
  resending.value = id;
  resendSuccess.value = '';
  resendError.value = '';
  const isOtpPending = order.status === 'pending_otp';
  try {
    if (isOtpPending) {
      await store.resendOtp(id);
      resendSuccess.value = 'OTP resent successfully.';
      showToast('✅ OTP resent successfully');
    } else {
      await store.resendEmail(id);
      resendSuccess.value = 'Voucher email resent successfully.';
      showToast('✅ Email resent successfully');
    }
  } catch (e) {
    const fallback = isOtpPending ? 'Failed to resend OTP.' : 'Failed to resend email.';
    resendError.value = e.response?.data?.message || fallback;
  } finally {
    resending.value = null;
  }
}

function openOtpModal(order) {
  otpOrder.value = order;
  otpCode.value = '';
  otpError.value = '';
  showOtpModal.value = true;
}

async function verifyOtpFromHistory() {
  if (!otpCode.value || otpCode.value.length !== 6) {
    otpError.value = 'Please enter a valid 6-digit OTP';
    return;
  }
  
  verifyingOtp.value = true;
  otpError.value = '';
  
  try {
    await store.verifyOtp(otpOrder.value.id, otpCode.value);
    showToast('✅ OTP verified! Order completed.');
    showOtpModal.value = false;
    load(); // Refresh list
  } catch (e) {
    otpError.value = e.response?.data?.message || 'OTP verification failed';
  } finally {
    verifyingOtp.value = false;
  }
}

async function cancelPendingOrder(order) {
  if (!confirm(`Cancel order ${order.order_number}? Balance will be refunded.`)) return;
  
  cancelling.value = order.id;
  try {
    await store.cancelOrder(order.id);
    showToast('✅ Order cancelled and balance refunded');
    load();
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to cancel order');
  } finally {
    cancelling.value = null;
  }
}

function resendLabel(status) {
  return status === 'pending_otp' ? '✉ Resend OTP' : '✉ Resend Email';
}

function showToast(msg) {
  toast.value = msg;
  setTimeout(() => toast.value = '', 3000);
}

const counts = computed(() => {
  const all = store.orders;
  return {
    sent: all.filter(o => o.status === 'sent').length,
    pending_otp: all.filter(o => o.status === 'pending_otp').length,
    processing: all.filter(o => o.status === 'processing').length,
    failed: all.filter(o => o.status === 'failed').length,
    partially_failed: all.filter(o => o.status === 'partially_failed').length,
    cancelled: all.filter(o => o.status === 'cancelled').length,
  };
});

const itemGrossTotal = computed(() => {
  if (!store.currentOrder?.items) return 0;
  return store.currentOrder.items.reduce((sum, item) => sum + parseFloat(item.gross_total || 0), 0);
});

const itemDiscountTotal = computed(() => {
  if (!store.currentOrder?.items) return 0;
  return store.currentOrder.items.reduce((sum, item) => sum + parseFloat(item.discount_amount || 0), 0);
});

function statusLabel(s) {
  return {
    pending: 'Pending',
    processing: 'Processing',
    pending_otp: 'Awaiting OTP',
    sent: 'Sent',
    failed: 'Failed',
    partially_failed: 'Partially Failed',
    cancelled: 'Cancelled',
  }[s] || s;
}

function fmt(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<style>
.oh-page { padding: 28px; font-family: var(--fb); color: var(--ink); }
.oh-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.oh-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 0 0 4px; }
.oh-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }

.oh-filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.oh-search { min-width: 260px; flex: 1; }

.oh-summary { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.oh-chip { font-size: 13px; padding: 6px 14px; border-radius: 100px; font-weight: 500; }
.oh-chip-sent { background: #E8F7F2; color: #085041; }
.oh-chip-pending_otp { background: #FEF3E2; color: #B45309; }
.oh-chip-processing { background: #EEF2FF; color: #4F46E5; }
.oh-chip-failed { background: #FEF2F2; color: #B91C1C; }
.oh-chip-partially_failed { background: #FFF7ED; color: #C2410C; }
.oh-chip-cancelled { background: #F3F4F6; color: #6B7280; }

.oh-table-wrap { background: #fff; border: 1px solid var(--border-2); border-radius: 14px; overflow: hidden; }
.oh-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oh-table thead tr { background: var(--surface-2); }
.oh-table th { padding: 12px 14px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); font-weight: 600; }
.oh-table td { padding: 13px 14px; border-top: 1px solid var(--border-2); vertical-align: middle; }
.oh-table .num { text-align: right; font-family: var(--fd); }
.oh-row { cursor: pointer; transition: background 0.1s; }
.oh-row:hover td { background: var(--teal-pale); }
.oh-order-num { font-family: monospace; font-size: 13px; font-weight: 600; color: var(--teal-deep); }
.oh-total { font-weight: 700; color: var(--teal-deep); }
.oh-spoc-email { font-size: 11px; color: var(--ink-muted); }
.oh-empty { text-align: center; padding: 32px; color: var(--ink-muted); font-size: 14px; }
.oh-error { color: #b91c1c; }
.oh-actions { display: flex; gap: 8px; }
.oh-resend-btn { background: var(--teal-pale) !important; color: var(--teal-deep) !important; border-color: var(--border-2) !important; }
.oh-resend-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.oh-badge { display: inline-block; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.badge-sent { background: #E8F7F2; color: #085041; }
.badge-pending_otp { background: #FEF3E2; color: #B45309; }
.badge-failed { background: #FEF2F2; color: #B91C1C; }
.badge-partially_failed { background: #FFF7ED; color: #C2410C; }
.badge-cancelled { background: #F3F4F6; color: #6B7280; }
.badge-processing { background: #EEF2FF; color: #4F46E5; }
.badge-pending { background: #FEF3E2; color: #B45309; }

.oh-pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 20px; font-size: 14px; color: var(--ink-muted); }
.oh-pagination button { background: var(--surface-2); border: 1px solid var(--border-2); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-family: var(--fb); }
.oh-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Drawer */
.oh-drawer { max-width: 700px; }
.oh-drawer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.oh-drawer-order-num { font-family: monospace; font-size: 18px; font-weight: 700; color: var(--teal-deep); margin-bottom: 8px; }
.oh-drawer-actions { display: flex; gap: 10px; align-items: center; }
.oh-drawer-section { border-top: 1px solid var(--border-2); padding-top: 18px; margin-bottom: 20px; }
.oh-drawer-section h4 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); margin-bottom: 14px; }

.oh-detail-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oh-detail-table th { text-align: left; padding: 8px 10px; background: var(--surface-2); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink-muted); }
.oh-detail-table td { padding: 10px; border-bottom: 1px solid var(--border-2); }
.oh-detail-table .num { text-align: right; font-family: var(--fd); }
.oh-detail-table tfoot td { font-weight: 700; padding-top: 12px; }
.oh-total-label { text-align: right; color: var(--ink-soft); }
.oh-product-name { font-weight: 600; }
.oh-product-sub { font-size: 12px; color: var(--ink-muted); }

.oh-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.oh-info-item { display: flex; flex-direction: column; gap: 4px; }
.oh-info-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); }
.oh-info-value { font-size: 14px; font-weight: 500; color: var(--ink); }
.oh-mono { font-family: monospace; }
.oh-error-text { color: #b91c1c; }

.oh-balance-summary { background: var(--surface-2); border-radius: 10px; padding: 16px; }
.oh-balance-row { display: flex; justify-content: space-between; font-size: 14px; padding: 6px 0; border-bottom: 1px solid var(--border-2); }
.oh-balance-row:last-child { border-bottom: none; }
.oh-balance-deduct span:last-child { color: #b91c1c; font-weight: 600; }
.oh-balance-after span:last-child { font-weight: 700; color: var(--teal-deep); }
.oh-balance-after.negative span:last-child { color: #b91c1c; }

.oh-success-msg { background: #E8F7F2; color: #085041; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 12px; }
.oh-error-msg { background: #FEF2F2; color: #B91C1C; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 12px; }
.oh-toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--teal-deep); color: #fff; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; z-index: 9999; font-family: var(--fb); box-shadow: 0 8px 24px rgba(8,80,65,0.25); }
</style>
