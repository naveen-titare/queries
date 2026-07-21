<template>
  <AppLayout>
    <div class="avq-customers">

      <div class="cust-header">
        <div>
          <h2>Checkout</h2>
          <p>Review the campaign-priced cart and recipient before confirmation</p>
        </div>
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers')">← Back to catalog</button>
      </div>

      <div style="display:grid; grid-template-columns:minmax(0, 1fr) 380px; gap:20px; align-items:start">
        <div class="cust-table-wrap" style="padding:20px">
          <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Cart Summary ({{ store.cartItemCount }} items)</h3>
          <div v-if="!store.cart.length" class="cust-empty">Your cart is empty. <RouterLink to="/send-vouchers">Go to catalog</RouterLink></div>
          <div v-for="item in store.cart" :key="item.key" class="cart-row">
            <div style="width:36px; height:36px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700">{{ item.brand.charAt(0) }}</div>
            <div style="flex:1">
              <div style="font-size:13px; font-weight:600">{{ item.product_name }}</div>
              <div style="font-size:12px; color:var(--ink-muted)">{{ item.currency_code }} {{ fmt(item.denomination) }} × {{ item.quantity }}</div>
              <div v-if="pricingMode === 'product' && item.discount_percentage" style="font-size:12px;color:var(--teal-deep)">{{ item.discount_percentage }}% campaign discount</div>
              <div v-if="item.available<=10" style="font-size:10px; color:#B45309">⚠ Only {{ item.available }} left</div>
            </div>
            <div class="qty-wrap">
              <button
                type="button"
                class="qty-btn"
                :disabled="item.quantity <= 1"
                @click="decrementQty(item)"
                aria-label="Decrease quantity"
              >
                −
              </button>
              <input
                :value="item.quantity"
                class="qty-input"
                type="number"
                min="1"
                :max="item.available"
                @change="updateQty(item, $event.target.value)"
                @focus="$event.target.select()"
              />
              <button
                type="button"
                class="qty-btn"
                :disabled="item.quantity >= item.available"
                @click="incrementQty(item)"
                aria-label="Increase quantity"
              >
                +
              </button>
            </div>
            <div class="cust-balance">₹{{ fmt(item.denomination * item.quantity * (pricingMode === 'product' ? 1 - item.discount_percentage / 100 : 1)) }}</div>
            <button class="avq-btn-sm" @click="store.removeFromCart(item.key)">✕</button>
          </div>
          <div style="margin:12px 0;padding:12px;background:var(--surface-2);border-radius:8px;opacity:.82;pointer-events:none"><strong style="font-size:13px">Discount type from PI</strong><label style="margin-left:14px;font-size:13px"><input v-model="pricingMode" value="product" type="radio" disabled/> Campaign Discount</label><label style="margin-left:14px;font-size:13px"><input v-model="pricingMode" value="invoice" type="radio" disabled/> Invoice Discount</label><div v-if="pricingMode==='invoice'" style="margin-top:10px"><label style="font-size:12px;color:var(--ink-muted)">Discount / Service Charge (%)</label><input v-model.number="invoiceDiscountPercentage" class="avq-input" type="number" min="-100" max="100" step=".01" style="display:block;margin-top:4px;width:160px" disabled/><small>This value is frozen from the selected PI.</small></div></div>
          <div v-if="pricingMode==='product' && store.cartDiscountTotal" style="display:flex; justify-content:space-between; padding:8px 0; color:productAdjustment >= 0 ? 'var(--teal-deep)' : '#b45309'"><span>{{ productAdjustment >= 0 ? 'Campaign discount' : 'Campaign service charge' }}</span><span>{{ productAdjustment >= 0 ? '−' : '+' }} ₹{{ fmt(Math.abs(productAdjustment)) }}</span></div>
          <div v-if="pricingMode==='invoice' && invoiceAdjustment" style="display:flex; justify-content:space-between; padding:8px 0" :style="invoiceAdjustment >= 0 ? 'color:var(--teal-deep)' : 'color:#b45309'"><span>{{ invoiceAdjustment >= 0 ? 'Discount' : 'Service Charge' }}</span><span>{{ invoiceAdjustment >= 0 ? '−' : '+' }} ₹{{ fmt(Math.abs(invoiceAdjustment)) }}</span></div>
          <div style="display:flex; justify-content:space-between; padding:12px 0; border-top:2px solid var(--border-2); margin-top:8px; font-weight:700">
            <span>Total to deduct</span>
            <span class="cust-balance" style="font-size:18px">₹{{ fmt(orderTotal) }}</span>
          </div>

          <div v-if="selectedCustomer" style="background:var(--surface-2); border-radius:10px; padding:12px; margin:12px 0">
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0"><span>Balance before</span><span>₹{{ fmt(selectedCustomer.balance) }}</span></div>
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0"><span>Order total</span><span>₹{{ fmt(orderTotal) }}</span></div>
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0; font-weight:700" :style="balanceShortfall > 0 ? 'color:#b91c1c' : 'color:var(--teal-deep)'">
              <span>Balance after</span>
              <span>₹{{ fmt(selectedCustomer.balance - orderTotal) }}</span>
            </div>
          </div>

          <div style="background:var(--surface-2); border-radius:10px; padding:12px; margin:12px 0">
            <label style="font-size:12px;color:var(--ink-muted);font-weight:700">Selected Proforma Invoice</label>
            <div v-if="selectedProforma" style="display:flex; justify-content:space-between; gap:12px; align-items:center; margin-top:6px; flex-wrap:wrap">
              <strong>{{ selectedProforma.pi_number }}</strong>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span class="cust-badge badge-active">Available ₹{{ fmt(selectedProforma.available_amount) }}</span>
                <button class="avq-btn-sm" type="button" @click="openPdfPreview">Preview PI</button>
              </div>
            </div>
            <small v-if="!selectedProforma" style="color:#b91c1c">No PI selected. Please go back and select a PI.</small>
            <small v-else-if="piShortfall > 0" style="color:#b91c1c">Selected PI available balance is short by ₹{{ fmt(piShortfall) }}.</small>
            <small v-else>Voucher delivery is checked against this selected paid PI balance.</small>
          </div>

          <!-- Balance validation warning -->
          <div v-if="balanceShortfall > 0" style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; margin:12px 0; color:#b91c1c">
            <strong>⚠ Insufficient Balance</strong><br>
            Order total (₹{{ fmt(orderTotal) }}) exceeds customer balance (₹{{ fmt(selectedCustomer.balance) }}).<br>
            Shortfall: ₹{{ fmt(balanceShortfall) }}<br>
            <small>Remove items from cart or add balance to customer before proceeding.</small>
          </div>

          <p v-if="error" class="form-error" style="color:#b91c1c; background:#fef2f2; padding:10px; border-radius:8px; font-size:13px">{{ error }}</p>

          <button v-if="!otpSent" class="avq-btn-primary" style="width:100%; margin-top:12px; padding:12px" :disabled="!canProceed || loading" @click="initiateOrder">
            {{ loading ? 'Initiating… sending OTP' : '🔐 Initiate Order & Send OTP' }}
          </button>
          <p style="font-size:11px; color:var(--ink-muted); text-align:center; margin-top:8px">You can cancel the order before OTP verification. Codes are reserved only after initiation.</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px">
          <div v-if="selectedCustomer" class="cust-table-wrap" style="padding:20px">
            <h3 style="font-size:14px; font-weight:700; margin-bottom:4px">SPOC (Recipient)</h3>
            <p style="font-size:12px; color:var(--ink-muted); margin-bottom:12px">Vouchers will be sent to this email as Excel attachment.</p>

            <!-- Only show ACTIVE PRIMARY SPOC -->
            <div v-if="primaryActiveSpoc" style="display:flex; flex-direction:column; gap:8px">
              <div
                @click="selectSpoc(primaryActiveSpoc)"
                style="padding:12px; border:1.5px solid var(--teal-deep); border-radius:10px; cursor:pointer; background:var(--teal-pale)"
              >
                <div style="font-weight:600; font-size:14px">
                  {{ primaryActiveSpoc.name }}
                  <span class="cust-badge badge-active" style="font-size:10px">Primary</span>
                </div>
                <div style="font-size:12px; color:var(--ink-muted)">{{ primaryActiveSpoc.email }}</div>
                <div style="font-size:12px; color:var(--ink-muted)">{{ primaryActiveSpoc.phone || '' }}</div>
                <div style="font-size:11px; color:var(--teal-deep); margin-top:4px; font-weight:600">✓ Active Primary SPOC - Ready to receive vouchers</div>
              </div>
            </div>

            <div v-else-if="selectedCustomer.spocs && selectedCustomer.spocs.length > 0" class="cust-empty" style="padding:16px; background:#fef3e2; border:1px solid #fde68a; border-radius:8px">
              ⚠ No Active Primary SPOC found for this customer.<br>
              <small>Please set an Active Primary SPOC in the Customers module before placing an order.</small>
            </div>

            <div v-else class="cust-empty">No SPOCs found for this customer.</div>
          </div>

          <div v-if="otpSent && !success" style="padding:20px; background:#fff3e0; border:1px solid #ffb74d; border-radius:12px">
            <div style="display:flex; align-items:center; gap:10px; color:#e65100"><span style="font-size:24px">📧</span><div><strong style="font-size:16px">OTP Sent Successfully</strong><div style="font-size:13px; color:var(--ink-muted)">Enter the OTP sent to <strong>naveentitare52@gmail.com</strong> and <strong>ptitare@gmail.com</strong> to confirm. OTP valid for 10 minutes.</div></div></div>
            <div style="background:white; border-radius:8px; padding:16px; border:1px solid #ffb74d; margin-top:16px">
              <label style="display:block; font-size:12px; font-weight:600; color:var(--ink-muted); margin-bottom:8px">Enter 6-Digit OTP</label>
              <input v-model="otp" type="text" maxlength="6" class="avq-input" style="width:100%; max-width:100%; box-sizing:border-box; letter-spacing:4px; text-align:center; font-size:24px; font-family:monospace" placeholder="123456" @keyup.enter="verifyOtp" />
              <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center"><button class="avq-btn-ghost" @click="resendOtp" :disabled="resending">{{resending?'Resending…':'Resend OTP'}}</button><span style="font-size:12px;color:var(--ink-muted)">{{otp.length}}/6 digits</span></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px"><button class="avq-btn-ghost" @click="cancelOtp">Cancel Order</button><button class="avq-btn-primary" :disabled="verifying || otp.length!==6" @click="verifyOtp">{{verifying?'Verifying…':'✅ Verify OTP & Send Vouchers'}}</button></div>
          </div>

          <div v-if="success" style="padding:20px; background:#f0fdf4; border:1px solid #86efac; border-radius:12px; text-align:center">
            <div style="font-size:32px">✅</div>
            <strong style="font-size:18px">Vouchers Sent!</strong>
            <p style="color:var(--ink-muted);font-size:13px">The voucher email has been sent to {{ selectedSpoc?.email }}.</p>
            <button class="avq-btn-primary" @click="router.push('/send-vouchers')">Send more</button>
          </div>
        </div>
      </div>
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
      <div v-if="pdfPreview.open" class="avq-modal-overlay" @click.self="closePdfPreview">
        <div class="avq-modal avq-modal-lg pdf-preview-modal">
          <div class="pdf-preview-title">
            <h3>{{ pdfPreview.title }}</h3>
            <button class="avq-btn-ghost" type="button" @click="closePdfPreview">Close</button>
          </div>
          <p v-if="pdfPreview.loading" class="preview-state">Loading PDF preview…</p>
          <p v-else-if="pdfPreview.error" class="preview-state error">{{ pdfPreview.error }}</p>
          <iframe v-else-if="pdfPreview.url" class="pdf-preview-frame" :src="pdfPreview.url" title="PI PDF preview" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';
import billingApi from '../../billing/api/billingApi';

const store = useSendVoucherStore();
const router = useRouter();
const selectedCustomer = ref(null);
const selectedSpoc = ref(null);
const loading = ref(false);
const error = ref('');
const otpSent = ref(false); const otp = ref(''); const verifying = ref(false); const resending = ref(false); const success = ref(false); const orderNumber = ref('');
const pricingMode = ref('product');
const invoiceDiscountPercentage = ref(0);
const showCancelModal = ref(false);
const cancelSubmitting = ref(false);
const cancelError = ref('');
const selectedProformaId = ref('');
const pdfPreview = ref({ open: false, title: '', url: '', loading: false, error: '' });

const productAdjustment = computed(() => store.cartDiscountTotal);
const invoiceAdjustment = computed(() => pricingMode.value === 'invoice' ? store.cartBaseTotal * Number(invoiceDiscountPercentage.value || 0) / 100 : 0);
const orderTotal = computed(() => pricingMode.value === 'invoice' ? store.cartBaseTotal - invoiceAdjustment.value : store.cartTotal);
const balanceShortfall = computed(() => selectedCustomer.value ? orderTotal.value - selectedCustomer.value.balance : 0);
const selectedProforma = computed(() => store.selectedPi && Number(store.selectedPi.id) === Number(selectedProformaId.value) ? store.selectedPi : null);
const piShortfall = computed(() => selectedProforma.value ? Math.max(0, orderTotal.value - Number(selectedProforma.value.available_amount || 0)) : orderTotal.value);

// Only allow proceed if: customer selected, primary active SPOC selected, cart not empty, balance sufficient
const canProceed = computed(() =>
	  selectedCustomer.value &&
	  primaryActiveSpoc.value &&
	  store.cartItemCount > 0 &&
	  selectedProformaId.value &&
	  piShortfall.value <= 0 &&
	  balanceShortfall.value <= 0
	);

// Get the active primary SPOC
const primaryActiveSpoc = computed(() => {
  if (!selectedCustomer.value?.spocs) return null;
  return selectedCustomer.value.spocs.find(s => s.is_primary && s.status === 'active') || null;
});

onMounted(() => {
  const saved = sessionStorage.getItem('avq_sendv_customer');
  if (!saved) { router.push('/send-vouchers'); return; }
	  selectedCustomer.value = JSON.parse(saved);
	  selectedSpoc.value = selectedCustomer.value.spocs?.find(s => s.is_primary && s.status === 'active') || null;
  if (!store.selectedPi?.id || !store.cart.length) {
    router.push('/send-vouchers');
    return;
  }
  selectedProformaId.value = store.selectedPi.id;
  applyPiPricing(store.selectedPi);
  if (store.pendingOrder) {
    orderNumber.value = store.pendingOrder.orderNumber;
    otpSent.value = true;
  }
});

onUnmounted(() => {
  revokePdfUrl(pdfPreview.value.url);
});

async function initiateOrder(){
  loading.value = true;
  error.value = '';
  try{
	    await store.validateCart(selectedCustomer.value.id, pricingMode.value, selectedProformaId.value);
	    const items = store.cart.filter(i => Number(i.quantity || 0) > 0).map(i => ({product_id:i.product_id, denomination:i.denomination, quantity:i.quantity}));
	    const response = await store.initiateOrder(selectedCustomer.value.id, selectedSpoc.value.id, items, pricingMode.value, invoiceDiscountPercentage.value, selectedProformaId.value);
    orderNumber.value = response.order.order_number;
    otpSent.value = true;
  } catch(e){
    error.value = apiErrorMessage(e, 'Failed to initiate order');
  } finally{
    loading.value = false;
  }
}

function apiErrorMessage(error, fallback) {
  const response = error?.response?.data;
  const details = response?.errors;
  const messages = Array.isArray(details)
    ? details
    : details && typeof details === 'object'
      ? Object.values(details).flat()
      : [];
  return messages.filter(Boolean).join(' ') || response?.message || fallback;
}

function applyPiPricing(pi) {
  pricingMode.value = pi?.discount_type === 'invoice' ? 'invoice' : 'product';
  invoiceDiscountPercentage.value = Number(pi?.invoice_discount_percentage || 0);
}

async function verifyOtp(){
  verifying.value=true; error.value='';
  try { await store.verifyOrderOtp(orderNumber.value, otp.value); success.value=true; sessionStorage.removeItem('avq_sendv_customer'); sessionStorage.removeItem('avq_sendv_spoc'); }
  catch(e) { error.value=e.response?.data?.message || 'Failed to verify OTP'; }
  finally { verifying.value=false; }
}
async function resendOtp(){ try { resending.value=true; await store.resendOrderOtp(orderNumber.value); } catch(e) { error.value=e.response?.data?.message || 'Failed to resend OTP'; } finally { resending.value=false; } }
async function cancelOtp(){ cancelError.value=''; showCancelModal.value=true; }
async function confirmCancelOtp(){ cancelSubmitting.value=true; cancelError.value=''; try { await store.cancelOrder(orderNumber.value); otpSent.value=false; otp.value=''; showCancelModal.value=false; } catch(e) { cancelError.value=e.response?.data?.message || 'Failed to cancel order'; } finally { cancelSubmitting.value=false; } }

async function openPdfPreview() {
  if (!selectedProforma.value?.id) return;
  closePdfPreview();
  pdfPreview.value = {
    open: true,
    title: selectedProforma.value.pi_number || 'Proforma Invoice',
    url: '',
    loading: true,
    error: '',
  };
  try {
    const { data } = await billingApi.documentBlob('proforma_invoice', selectedProforma.value.id);
    pdfPreview.value.url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
  } catch (e) {
    pdfPreview.value.error = e.response?.data?.message || 'Could not load PI PDF preview.';
  } finally {
    pdfPreview.value.loading = false;
  }
}

function closePdfPreview() {
  revokePdfUrl(pdfPreview.value.url);
  pdfPreview.value = { open: false, title: '', url: '', loading: false, error: '' };
}

function revokePdfUrl(url) {
  if (url) URL.revokeObjectURL(url);
}

function incrementQty(item) {
  updateQty(item, Number(item.quantity || 0) + 1);
}

function decrementQty(item) {
  updateQty(item, Number(item.quantity || 0) - 1);
}

function updateQty(item, value) {
  const next = Number.parseInt(value, 10);
  if (Number.isNaN(next)) return;
  store.updateCartQty(item.key, next);
}

function fmt(n){ return Number(n||0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }
</script>

<style scoped>
.qty-btn {
  width: 30px;
  height: 30px;
  border: 1px solid var(--border-2);
  background: #fff;
  border-radius: 8px;
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
  line-height: 1;
}

.qty-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: var(--surface-2);
  border: 1px solid var(--border-2);
  border-radius: 10px;
  padding: 4px 6px;
  width: 136px;
  flex: 0 0 136px;
  justify-self: center;
}

.cart-row {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) 136px 96px 36px;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border-2);
}

.qty-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.qty-input {
  width: 52px;
  height: 30px;
  border: 1px solid var(--border-2);
  border-radius: 8px;
  text-align: center;
  font: 600 13px var(--fb);
  color: var(--ink);
  background: #fff;
  box-sizing: border-box;
}

.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.qty-input[type="number"] {
  -moz-appearance: textfield;
}

.pdf-preview-modal {
  max-width: 1180px;
  padding: 24px;
}

.pdf-preview-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.pdf-preview-title h3 {
  margin: 0;
}

.pdf-preview-frame {
  width: 100%;
  height: min(72vh, 760px);
  border: 1px solid var(--border-2);
  border-radius: 14px;
  background: #fff;
}

.preview-state {
  padding: 28px;
  text-align: center;
  color: var(--ink-muted);
  font-weight: 700;
}

.preview-state.error {
  color: #b91c1c;
}
</style>
