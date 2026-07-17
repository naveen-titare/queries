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
        <select v-model="statusFilter" class="avq-input" @change="load">
          <option value="">All statuses</option>
          <option value="delivered">Delivered</option>
          <option value="pending_verification">Pending Verification</option>
          <option value="failed">Failed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input v-model="dateFrom" class="avq-input" type="date" @change="load" />
        <input v-model="dateTo" class="avq-input" type="date" @change="load" />
      </div>

      <!-- Summary chips -->
      <div class="oh-summary">
        <div class="oh-chip oh-chip-delivered">
          ✅ Delivered: <strong>{{ counts.delivered }}</strong>
        </div>
        <div class="oh-chip oh-chip-pending">
          ⏳ Pending: <strong>{{ counts.pending_verification }}</strong>
        </div>
        <div class="oh-chip oh-chip-failed">
          ❌ Failed: <strong>{{ counts.failed }}</strong>
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
                  <button
                    v-if="order.status !== 'cancelled'"
                    class="avq-btn-sm oh-resend-btn"
                    :disabled="resending === order.id"
                    @click="handleResend(order.id)"
                  >
                    {{ resending === order.id ? '…' : '✉ Resend' }}
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
                  @click="handleResend(store.currentOrder.id)"
                >
                  {{ resending === store.currentOrder.id ? 'Sending…' : '✉ Resend Email' }}
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
                    <th class="num">Subtotal</th>
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
                    <td class="num">₹{{ fmt(item.total_value) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="oh-total-label">Order Total</td>
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
const resendSuccess = ref('');
const resendError = ref('');
const toast = ref('');
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

function changePage(p) { page.value = p; load(); }

async function openDetail(id) {
  showDetail.value = true;
  resendSuccess.value = '';
  resendError.value = '';
  await store.fetchOrder(id);
}

async function handleResend(id) {
  resending.value = id;
  resendSuccess.value = '';
  resendError.value = '';
  try {
    await store.resendEmail(id);
    resendSuccess.value = 'Voucher email resent successfully.';
    showToast('✅ Email resent successfully');
  } catch (e) {
    resendError.value = e.response?.data?.message || 'Failed to resend email.';
  } finally {
    resending.value = null;
  }
}

function showToast(msg) {
  toast.value = msg;
  setTimeout(() => toast.value = '', 3000);
}

const counts = computed(() => {
  const all = store.orders;
  return {
    delivered: all.filter(o => o.status === 'delivered').length,
    pending_verification: all.filter(o => o.status === 'pending_verification').length,
    failed: all.filter(o => o.status === 'failed').length,
    cancelled: all.filter(o => o.status === 'cancelled').length,
  };
});

function statusLabel(s) {
  return {
    delivered: 'Delivered',
    pending_verification: 'Pending Verification',
    failed: 'Failed',
    cancelled: 'Cancelled',
    processing: 'Processing',
    pending: 'Pending',
    sent: 'Sent',
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
.oh-chip-delivered { background: #E8F7F2; color: #085041; }
.oh-chip-pending { background: #FEF3E2; color: #B45309; }
.oh-chip-failed { background: #FEF2F2; color: #B91C1C; }
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
.badge-delivered { background: #E8F7F2; color: #085041; }
.badge-pending_verification { background: #FEF3E2; color: #B45309; }
.badge-failed { background: #FEF2F2; color: #B91C1C; }
.badge-cancelled { background: #F3F4F6; color: #6B7280; }
.badge-processing { background: #EEF2FF; color: #4F46E5; }
.badge-pending { background: #FEF3E2; color: #B45309; }
.badge-sent { background: #E8F7F2; color: #085041; }

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
