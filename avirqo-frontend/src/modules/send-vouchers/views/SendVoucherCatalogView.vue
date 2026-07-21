<template>
  <AppLayout>
    <div class="avq-customers">

      <!-- ⚠ Pending OTP Banner — shown when user navigated away from confirm screen -->
      <div v-if="store.pendingOrder" style="background:#fff3e0; border:1.5px solid #ffb74d; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap">
        <div style="display:flex; align-items:center; gap:12px">
          <span style="font-size:24px">⏳</span>
          <div>
            <strong style="font-size:14px; color:#e65100">OTP Verification Pending</strong>
            <div style="font-size:13px; color:var(--ink-muted); margin-top:2px">
              Order <strong style="font-family:monospace">{{ store.pendingOrder.orderNumber }}</strong>
              worth <strong>₹{{ Number(store.pendingOrder.total || 0).toLocaleString('en-IN') }}</strong>
              is awaiting OTP verification. Vouchers have <strong>not</strong> been sent yet.
            </div>
          </div>
        </div>
        <div style="display:flex; gap:10px; flex-shrink:0">
          <button class="avq-btn-primary" style="background:#e65100; padding:8px 16px; font-size:13px" @click="resumeOtp">
            ✅ Verify OTP Now
          </button>
          <button class="avq-btn-sm btn-danger" style="padding:8px 14px; font-size:13px" :disabled="cancellingPending" @click="cancelPendingOrder">
            {{ cancellingPending ? 'Cancelling…' : '✕ Cancel Order' }}
          </button>
        </div>
      </div>

      <div class="cust-header">
        <div>
          <h2>Send Vouchers</h2>
          <p v-if="selectedCustomer">
            {{ selectedCustomer.company_name }} · {{ campaign?.name }} campaign
            <button class="avq-btn-sm" style="margin-left:12px;padding:4px 10px;font-size:11px" @click="changeCustomer">
              🔄 Change Customer
            </button>
          </p>
          <p v-else>Select a customer to load their campaign catalogue</p>
        </div>
        <button v-if="store.cartItemCount>0 && selectedCustomer" class="avq-btn-primary" @click="router.push('/send-vouchers/send')">
          📤 Cart ({{ store.cartItemCount }}) - ₹{{ fmt(store.cartTotal) }}
        </button>
      </div>

      <div v-if="!selectedCustomer" class="cust-table-wrap" style="padding:20px; max-width:680px">
        <h3 style="margin-top:0">1. Select customer</h3>
        <p style="color:var(--ink-muted);font-size:13px">Only customers assigned to an active campaign can send vouchers.</p>
        <input v-model="customerSearch" class="avq-input" placeholder="Search customer…" style="width:100%;box-sizing:border-box" />
        <p v-if="customerLoading" class="cust-empty" style="padding:16px">Loading customers…</p>
        <p v-else-if="customerError" class="form-error">{{ customerError }}</p>
        <p v-else-if="!filteredCustomers.length" class="cust-empty" style="padding:16px">
          No active campaign customers found.
        </p>
        <div v-for="customer in filteredCustomers" :key="customer.id" @click="selectCustomer(customer)" class="cust-row" style="padding:12px;margin-top:8px;border:1px solid var(--border-2);border-radius:8px;cursor:pointer">
          <strong>{{ customer.company_name }}</strong><small style="display:block;color:var(--ink-muted)">{{ customer.location }}</small>
        </div>
      </div>
      <div v-else class="cust-table-wrap">
        <div style="display:flex; align-items:center; gap:12px; padding:16px; border-bottom:1px solid var(--border-2); flex-wrap:wrap">
          <div style="min-width:320px; flex:1">
            <label style="font-size:12px;color:var(--ink-muted);font-weight:700">2. Select PI for delivery</label>
            <select v-model="selectedProformaId" class="avq-input" style="margin-top:6px; width:100%; box-sizing:border-box" @change="selectProforma">
              <option value="">Select delivery pending PI</option>
              <option v-for="pi in paidProformas" :key="pi.id" :value="pi.id">
                {{ pi.pi_number }} — Available ₹{{ fmt(pi.available_amount) }}
              </option>
            </select>
            <small v-if="!paidProformas.length" style="display:block;color:#b45309;margin-top:6px">No delivery pending paid PI found for this customer.</small>
            <small v-else style="display:block;color:var(--ink-muted);margin-top:6px">Cart is generated from the selected PI only. Extra products cannot be added.</small>
          </div>
          <select v-if="selectedProforma" v-model="selectedProformaId" class="avq-input" style="max-width:260px" @change="selectProforma">
            <option v-for="pi in paidProformas" :key="`quick-${pi.id}`" :value="pi.id">{{ pi.pi_number }}</option>
          </select>
          <span style="margin-left:auto; font-size:13px; color:var(--ink-muted)" v-if="selectedProforma">
            {{ store.cartItemCount }} item(s) • Cart: ₹{{ fmt(store.cartTotal) }}
          </span>
        </div>

        <div class="send-voucher-table-scroll">
          <table class="cust-table">
            <thead>
              <tr>
                <th>BRAND</th>
                <th>DENOMINATION (PI PENDING / STOCK)</th>
                <th>TOTAL VALUE</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!selectedProforma">
                <td colspan="4" style="text-align:center; padding:40px; color:var(--ink-muted)">
                  Select a PI to generate the voucher cart.
                </td>
              </tr>
              <tr v-else-if="!store.cart.length">
                <td colspan="4" style="text-align:center; padding:40px; color:var(--ink-muted)">
                  This PI has no pending items with available voucher stock.
                </td>
              </tr>
              <tr v-for="item in store.cart" :key="item.key" class="cust-row">
                <td>
                  <div style="display:flex; align-items:center; gap:10px">
                    <div style="width:36px; height:36px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700">{{ (item.brand||item.product_name).charAt(0) }}</div>
                    <div>
                      <div class="cust-name">{{ item.brand || item.product_name }}</div>
                      <div style="font-size:12px; color:var(--ink-muted)">{{ item.product_name }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="display:flex; flex-wrap:wrap; gap:6px">
                    <span class="cust-badge" :class="item.available <= 0 ? 'badge-inactive' : item.available < item.pi_pending_quantity ? 'badge-on_hold' : 'badge-active'">
                      {{ item.currency_code }} {{ fmt(item.denomination) }} · PI {{ item.pi_pending_quantity }} · Stock {{ item.available }}
                      <span v-if="item.available < item.pi_pending_quantity">⚠</span>
                    </span>
                  </div>
                </td>
                <td class="cust-balance">₹{{ fmt(item.denomination * item.quantity * (selectedProforma.discount_type === 'invoice' ? 1 : 1 - item.discount_percentage / 100)) }}</td>
                <td>
                  <button class="avq-btn-sm" @click="router.push('/send-vouchers/send')" :disabled="!store.cart.length">Proceed</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="selectedProduct" class="avq-modal-overlay" @click.self="selectedProduct=null">
        <div class="avq-modal">
          <h3>{{ selectedProduct.brand }} - {{ selectedProduct.name }}</h3>
          <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px">{{ selectedProduct.terms_and_conditions || 'Select denomination & quantity' }}</p>
          <div v-for="(stock, denom) in selectedProduct.stock" :key="denom" style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border-2)">
            <span style="font-weight:700; min-width:100px">{{ selectedProduct.currency_code }} {{ fmt(denom) }}</span>
            <span style="font-size:12px" :class="stock.out_of_stock?'err': stock.low_stock?'cust-balance':''">{{ stock.out_of_stock?'Out of stock': stock.low_stock?`Only ${stock.available} left`:`${stock.available} available` }}</span>
            <div style="margin-left:auto; display:flex; gap:8px; align-items:center" v-if="!stock.out_of_stock">
              <button class="avq-btn-sm" @click="changeQty(denom,-1)">−</button>
              <span style="min-width:24px; text-align:center; font-weight:700">{{ selectedQty[denom]||0 }}</span>
              <button class="avq-btn-sm" @click="changeQty(denom,1)" :disabled="(selectedQty[denom]||0)>=stock.available">+</button>
              <button class="avq-btn-primary" style="padding:6px 12px; font-size:12px" @click="addSelected(denom)" :disabled="!selectedQty[denom]">Add</button>
            </div>
          </div>
          <div class="modal-footer">
            <button class="avq-btn-ghost" @click="selectedProduct=null">Close</button>
          </div>
        </div>
      </div>

      <AppToast
        :open="!!toast"
        :message="toast"
        :variant="toastVariant"
        @close="toast = ''"
      />

      <AppDialogModal
        :open="showCancelPendingModal"
        title="Cancel order"
        :message="store.pendingOrder ? `Cancel order ${store.pendingOrder.orderNumber}? The deducted balance will be refunded.` : ''"
        confirm-text="Cancel order"
        cancel-text="Keep order"
        variant="danger"
        :loading="cancellingPending"
        @cancel="showCancelPendingModal = false"
        @confirm="confirmCancelPendingOrder"
      >
        <p v-if="cancelPendingError" class="form-error" style="margin-top:16px">{{ cancelPendingError }}</p>
      </AppDialogModal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';
import AppToast from '../../shared/components/AppToast.vue';
import apiClient from '../../../shared/apiClient';
import billingApi from '../../billing/api/billingApi';

const store = useSendVoucherStore();
const router = useRouter();
const search = ref('');
const usageFilter = ref('');
const page = ref(1);
const selectedProduct = ref(null);
const selectedQty = reactive({});
const toast = ref('');
const toastVariant = ref('success');
const cancellingPending = ref(false);
const showCancelPendingModal = ref(false);
const cancelPendingError = ref('');
const customers = ref([]); const selectedCustomer = ref(null); const campaign = ref(null); const customerSearch = ref(''); const customerError = ref(''); const customerLoading = ref(false);
const paidProformas = ref([]);
const selectedProformaId = ref('');
const filteredCustomers = computed(() => customers.value.filter(c => c.company_name.toLowerCase().includes(customerSearch.value.toLowerCase())));
const selectedProforma = computed(() => paidProformas.value.find(pi => Number(pi.id) === Number(selectedProformaId.value)) || null);
let timer=null;

onMounted(async()=> {
  await loadCustomers();
  const saved=sessionStorage.getItem('avq_sendv_customer');
  const savedCampaign=sessionStorage.getItem('avq_sendv_campaign');
  if(saved && savedCampaign) {
    selectedCustomer.value=JSON.parse(saved);
    campaign.value=JSON.parse(savedCampaign);
    await loadPaidProformas();
    const savedPi=sessionStorage.getItem('avq_sendv_pi');
    if(savedPi) selectedProformaId.value=JSON.parse(savedPi).id || '';
  }
});
function load(){ if(selectedCustomer.value) loadPaidProformas() }
async function loadCustomers() {
  customerLoading.value = true;
  customerError.value = '';
  try {
    const {data}=await apiClient.get('/send-vouchers/customers');
    customers.value=data.data||data;
  } catch(e) {
    customers.value = [];
    customerError.value=e.response?.data?.message || 'Could not load customers for Send Vouchers.';
  } finally {
    customerLoading.value = false;
  }
}
async function selectCustomer(customer) { customerError.value=''; try { const [{data:fullCustomer},{data:assigned}] = await Promise.all([apiClient.get(`/send-vouchers/customers/${customer.id}`),apiClient.get(`/send-vouchers/customers/${customer.id}/voucher-campaign`)]); if(!assigned) { customerError.value='This customer is not assigned to an active campaign.'; return; } selectedCustomer.value=fullCustomer; campaign.value=assigned; sessionStorage.setItem('avq_sendv_customer',JSON.stringify(fullCustomer)); sessionStorage.setItem('avq_sendv_campaign',JSON.stringify(assigned)); store.clearCart(); store.clearSelectedPi(); await loadPaidProformas(); } catch(e) { customerError.value=e.response?.data?.message || 'Could not load customer campaign.'; } }
function changeCustomer() { selectedCustomer.value=null; campaign.value=null; paidProformas.value=[]; selectedProformaId.value=''; store.clearCart(); store.clearSelectedPi(); store.catalog=[]; sessionStorage.removeItem('avq_sendv_customer'); sessionStorage.removeItem('avq_sendv_campaign'); customerSearch.value=''; customerError.value=''; }
async function loadPaidProformas() { if(!selectedCustomer.value?.id) return; const { data } = await billingApi.paidProformasForCustomer(selectedCustomer.value.id); paidProformas.value = data.data || []; if(!paidProformas.value.some(pi => Number(pi.id) === Number(selectedProformaId.value))) selectedProformaId.value = ''; }
function selectProforma() { const pi = selectedProforma.value; if(!pi) { store.clearCart(); store.clearSelectedPi(); return; } store.setPiCart(pi); }
function onSearch(){ clearTimeout(timer); timer=setTimeout(()=>{page.value=1; load();},300) }
function changePage(p){ page.value=p; load(); }
function openProduct(p){ selectedProduct.value=p; Object.keys(selectedQty).forEach(k=>delete selectedQty[k]); }
function changeQty(d,delta){ selectedQty[d]=Math.max(0,(selectedQty[d]||0)+delta); }
function showToast(msg, variant = 'success') {
  toast.value = msg;
  toastVariant.value = variant;
  setTimeout(() => { toast.value = ''; }, 2500);
}
function addSelected(d){ const q=selectedQty[d]||0; if(!q) return; store.addToCart(selectedProduct.value,parseFloat(d),q); showToast(`Added ${q} × ${selectedProduct.value.currency_code} ${fmt(d)}`); selectedQty[d]=0; }
function quickAdd(p,d){ store.addToCart(p,parseFloat(d),1); showToast(`Added 1 × ${p.currency_code} ${fmt(d)}`); }
function fmt(n){ return Number(n).toLocaleString('en-IN',{maximumFractionDigits:0}); }

// Resume a pending order on checkout, which opens directly in OTP-entry mode.
async function resumeOtp() {
  // First check if order is still pending_otp
  const status = await store.checkPendingOrderStatus();
  if (!status || status !== 'pending_otp') {
    store.clearPendingOrder();
    showToast('⚠️ Order is already updated, check Order History for details.', 'warning');
    return;
  }
  router.push('/send-vouchers/send');
}

// Cancel the pending OTP order from the banner — refunds balance
async function cancelPendingOrder() {
  if (!store.pendingOrder) return;
  
  // First check if order is still pending_otp
  const status = await store.checkPendingOrderStatus();
  if (!status || status !== 'pending_otp') {
    store.clearPendingOrder();
    showToast('⚠️ Order is already updated, check Order History for details.', 'warning');
    return;
  }
  
  cancelPendingError.value = '';
  showCancelPendingModal.value = true;
}

async function confirmCancelPendingOrder() {
  if (!store.pendingOrder) return;
  cancellingPending.value = true;
  cancelPendingError.value = '';
  try {
    await store.cancelOrder(store.pendingOrder.orderNumber);
    showCancelPendingModal.value = false;
    showToast('✅ Order cancelled — balance refunded.');
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to cancel order', 'danger');
  } finally {
    cancellingPending.value = false;
  }
}
</script>

<style scoped>
.send-voucher-table-scroll {
  max-height: min(68vh, 760px);
  overflow: auto;
}

.send-voucher-table-scroll .cust-table {
  width: 100%;
}

.send-voucher-table-scroll .cust-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #fff;
}
</style>
