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
        <div class="oh-table-scroll">
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
                  <div>{{ order.spoc_name || order.spoc?.name || '—' }}</div>
                  <div class="oh-spoc-email">{{ order.spoc_email || order.spoc?.email || '—' }}</div>
                </td>
                <td>{{ order.items?.length || '—' }} item(s)</td>
                <td class="num oh-total">₹{{ fmt(orderDisplayTotal(order)) }}</td>
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
                  v-if="store.currentOrder.status === 'pending_otp'"
                  class="avq-btn-ghost"
                  @click="openSwitchSpocModal(store.currentOrder)"
                >
                  Switch SPOC
                </button>
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
                    <td class="num" :style="item.discount_percentage < 0 ? 'color:#b45309' : 'color:#16a34a'">
                      <template v-if="store.currentOrder.pricing_mode === 'product' && item.discount_percentage > 0">
                        −{{ item.discount_percentage }}% (−₹{{ fmt(item.discount_amount) }})
                      </template>
                      <template v-else-if="store.currentOrder.pricing_mode === 'product' && item.discount_percentage < 0">
                        +{{ Math.abs(item.discount_percentage) }}% (+₹{{ fmt(Math.abs(item.discount_amount)) }})
                      </template>
                      <template v-else>—</template>
                    </td>
                    <td class="num oh-total">₹{{ fmt(itemLineTotal(item)) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="oh-total-label">Products subtotal</td>
                    <td class="num">₹{{ fmt(itemGrossTotal) }}</td>
                    <td class="num" :style="itemDiscountTotal < 0 ? 'color:#b45309' : 'color:#16a34a'">{{ itemDiscountTotal >= 0 ? '−' : '+' }}₹{{ fmt(Math.abs(itemDiscountTotal)) }}</td>
                    <td class="num oh-total">₹{{ fmt(itemNetSubtotal) }}</td>
                  </tr>
                  <tr v-if="store.currentOrder.pricing_mode === 'invoice' && Number(store.currentOrder.invoice_discount_percentage)">
                    <td colspan="4" class="oh-total-label">{{ Number(store.currentOrder.invoice_discount_percentage) > 0 ? 'Discount' : 'Service Charge' }} ({{ store.currentOrder.invoice_discount_percentage }}%)</td>
                    <td class="num" :style="Number(store.currentOrder.invoice_discount_percentage) < 0 ? 'color:#b45309' : 'color:#16a34a'">{{ Number(store.currentOrder.invoice_discount_percentage) > 0 ? '−' : '+' }}₹{{ fmt(Math.abs(store.currentOrder.invoice_discount_amount)) }}</td>
                    <td class="num oh-total">₹{{ fmt(currentOrderDisplayTotal) }}</td>
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
                  <span class="oh-info-value">{{ store.currentOrder.spoc_name || store.currentOrder.spoc?.name }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">SPOC Email</span>
                  <span class="oh-info-value">{{ store.currentOrder.spoc_email || store.currentOrder.spoc?.email }}</span>
                </div>
                <div class="oh-info-item">
                  <span class="oh-info-label">Phone</span>
                  <span class="oh-info-value">{{ store.currentOrder.spoc_phone || '—' }}</span>
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
                  <span>− ₹{{ fmt(currentOrderDisplayTotal) }}</span>
                </div>
                <div class="oh-balance-row oh-balance-after" :class="currentOrderDisplayBalanceAfter < 0 ? 'negative' : ''">
                  <span>Balance After</span>
                  <span>₹{{ fmt(currentOrderDisplayBalanceAfter) }}</span>
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

      <AppToast
        :open="!!toast"
        :message="toast"
        @close="toast = ''"
      />

      <!-- Switch SPOC Modal -->
      <div v-if="showSwitchModal" class="avq-modal-overlay" @click.self="showSwitchModal = false">
        <div class="avq-modal">
          <h3>Switch SPOC - {{ store.currentOrder?.order_number }}</h3>
          <p style="color: var(--ink-muted); font-size: 14px; margin-bottom: 16px;">
            Choose an active primary SPOC from the same customer. This will send an approval OTP to the admin emails.
          </p>

          <div v-if="availableSpocs.length" class="switch-spoc-list">
            <button
              v-for="spoc in availableSpocs"
              :key="spoc.id"
              type="button"
              class="switch-spoc-item"
              :class="{ selected: switchSelectedSpocId === spoc.id }"
              @click="switchSelectedSpocId = spoc.id"
            >
              <strong>{{ spoc.name }}</strong>
              <span>{{ spoc.email }}</span>
              <small v-if="spoc.is_primary">Primary</small>
            </button>
          </div>
          <div v-else class="oh-empty" style="padding: 18px 0;">No active primary SPOC available.</div>

          <p v-if="switchError" style="color: #b91c1c; margin-top: 8px; font-size: 13px;">{{ switchError }}</p>

          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showSwitchModal = false">Cancel</button>
            <button
              type="button"
              class="avq-btn-primary"
              :disabled="switchSubmitting || !switchSelectedSpocId"
              @click="requestSpocSwitch"
            >
              {{ switchSubmitting ? 'Sending…' : 'Send approval OTP' }}
            </button>
          </div>
        </div>
      </div>

      <AppOtpModal
        :open="showOtpModal"
        :title="`Verify OTP - ${otpOrder?.order_number || ''}`"
        message="Enter the 6-digit OTP sent to naveentitare52@gmail.com and ptitare@gmail.com to complete this order. Valid for 10 minutes."
        v-model:otp="otpCode"
        :error="otpError"
        :loading="verifyingOtp"
        loading-text="Verifying..."
        confirm-text="Verify OTP"
        :confirm-disabled="otpCode.length !== 6"
        :show-resend="false"
        @cancel="showOtpModal = false"
        @confirm="verifyOtpFromHistory"
      />

      <AppOtpModal
        :open="showSwitchOtpModal"
        title="Approve SPOC Switch"
        message="Enter the OTP sent to naveentitare52@gmail.com and ptitare@gmail.com to confirm the SPOC change. OTP valid for 10 minutes."
        v-model:otp="switchOtpCode"
        :error="switchOtpError"
        :loading="switchVerifying"
        loading-text="Verifying..."
        confirm-text="Verify OTP"
        :confirm-disabled="switchOtpCode.length !== 6"
        :show-resend="false"
        @cancel="showSwitchOtpModal = false"
        @confirm="verifySpocSwitchFromHistory"
      />

      <AppDialogModal
        :open="showCancelModal"
        title="Cancel order"
        :message="cancelTargetOrder ? `Cancel order ${cancelTargetOrder.order_number}? Balance will be refunded.` : ''"
        confirm-text="Cancel order"
        cancel-text="Keep order"
        variant="danger"
        :loading="cancelSubmitting"
        :confirm-disabled="!cancelTargetOrder"
        @cancel="showCancelModal = false"
        @confirm="confirmCancelPendingOrder"
      >
        <p v-if="cancelError" class="oh-error-msg" style="margin-top:16px">{{ cancelError }}</p>
      </AppDialogModal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useOrderHistoryStore } from '../store/orderHistoryStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';
import AppOtpModal from '../../shared/components/AppOtpModal.vue';
import AppToast from '../../shared/components/AppToast.vue';

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
const showSwitchModal = ref(false);
const showSwitchOtpModal = ref(false);
const switchSelectedSpocId = ref(null);
const switchSubmitting = ref(false);
const switchVerifying = ref(false);
const switchError = ref('');
const switchOtpCode = ref('');
const switchOtpError = ref('');
const switchRequestId = ref('');
const showCancelModal = ref(false);
const cancelTargetOrder = ref(null);
const cancelSubmitting = ref(false);
const cancelError = ref('');
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
  switchSelectedSpocId.value = store.currentOrder?.spoc_id || null;
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

const availableSpocs = computed(() => {
  return (store.currentOrder?.customer?.spocs || []).filter((spoc) => spoc.status === 'active' && spoc.is_primary);
});

function openSwitchSpocModal(order) {
  switchError.value = '';
  switchSelectedSpocId.value = order?.spoc_id || availableSpocs.value[0]?.id || null;
  showSwitchModal.value = true;
}

async function requestSpocSwitch() {
  if (!switchSelectedSpocId.value) {
    switchError.value = 'Please select a SPOC.';
    return;
  }

  switchSubmitting.value = true;
  switchError.value = '';

  try {
    const data = await store.initiateSpocSwitch(store.currentOrder.order_number, switchSelectedSpocId.value);
    switchRequestId.value = data.request_id;
    switchOtpCode.value = '';
    showSwitchModal.value = false;
    showSwitchOtpModal.value = true;
  } catch (e) {
    switchError.value = e.response?.data?.message || 'Failed to send approval OTP';
  } finally {
    switchSubmitting.value = false;
  }
}

async function verifySpocSwitchFromHistory() {
  if (!switchOtpCode.value || switchOtpCode.value.length !== 6) {
    switchOtpError.value = 'Please enter a valid 6-digit OTP';
    return;
  }

  switchVerifying.value = true;
  switchOtpError.value = '';

  try {
    await store.verifySpocSwitch(store.currentOrder.order_number, switchRequestId.value, switchOtpCode.value);
    showSwitchOtpModal.value = false;
    showToast('✅ SPOC updated successfully');
    load();
    if (store.currentOrder?.id) {
      await store.fetchOrder(store.currentOrder.id);
    }
  } catch (e) {
    switchOtpError.value = e.response?.data?.message || 'SPOC switch approval failed';
  } finally {
    switchVerifying.value = false;
  }
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
  cancelTargetOrder.value = order;
  cancelError.value = '';
  showCancelModal.value = true;
}

async function confirmCancelPendingOrder() {
  if (!cancelTargetOrder.value) return;

  cancelling.value = cancelTargetOrder.value.id;
  cancelSubmitting.value = true;
  cancelError.value = '';
  try {
    await store.cancelOrder(cancelTargetOrder.value.id);
    showCancelModal.value = false;
    showToast('✅ Order cancelled and balance refunded');
    load();
  } catch (e) {
    cancelError.value = e.response?.data?.message || 'Failed to cancel order';
  } finally {
    cancelling.value = null;
    cancelSubmitting.value = false;
  }
}

function resendLabel(status) {
  if (status === 'pending_otp') return '✉ Resend OTP';
  return '✉ Resend Secret Key';
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
  if (store.currentOrder.pricing_mode !== 'product') return 0;
  return store.currentOrder.items.reduce((sum, item) => sum + parseFloat(item.discount_amount || 0), 0);
});

const itemNetSubtotal = computed(() => {
  if (!store.currentOrder?.items) return 0;
  if (store.currentOrder.pricing_mode === 'invoice') return itemGrossTotal.value;
  return itemGrossTotal.value - itemDiscountTotal.value;
});

const currentOrderDisplayTotal = computed(() => orderDisplayTotal(store.currentOrder));
const currentOrderDisplayBalanceAfter = computed(() => {
  if (!store.currentOrder) return 0;
  return parseFloat(store.currentOrder.customer_balance_before || 0) - currentOrderDisplayTotal.value;
});

function orderDisplayTotal(order) {
  if (!order?.items?.length) return parseFloat(order?.total_amount || 0);
  const gross = order.items.reduce((sum, item) => sum + parseFloat(item.gross_total || 0), 0);
  if (order.pricing_mode === 'invoice') {
    return gross - parseFloat(order.invoice_discount_amount || 0);
  }
  const discount = order.items.reduce((sum, item) => sum + parseFloat(item.discount_amount || 0), 0);
  return gross - discount;
}

function itemLineTotal(item) {
  if (store.currentOrder?.pricing_mode === 'invoice') {
    return parseFloat(item.gross_total || 0);
  }
  return parseFloat(item.gross_total || 0) - parseFloat(item.discount_amount || 0);
}

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
.oh-table-scroll {
  max-height: min(68vh, 760px);
  overflow: auto;
}
.oh-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oh-table thead tr { background: var(--surface-2); }
.oh-table th {
  padding: 12px 14px;
  text-align: left;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--ink-muted);
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--surface-2);
}
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

.switch-spoc-list {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin: 16px 0;
  max-height: 320px;
  overflow: auto;
}

.switch-spoc-item {
  text-align: left;
  border: 1.5px solid var(--border-2);
  border-radius: 12px;
  padding: 12px 14px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 4px;
  cursor: pointer;
}

.switch-spoc-item.selected {
  border-color: var(--teal-deep);
  background: var(--teal-pale);
}

.switch-spoc-item strong {
  font-size: 14px;
}

.switch-spoc-item span,
.switch-spoc-item small {
  font-size: 12px;
  color: var(--ink-muted);
}

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
