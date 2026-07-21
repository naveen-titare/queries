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
        <div v-for="customer in filteredCustomers" :key="customer.id" @click="selectCustomer(customer)" class="cust-row" style="padding:12px;margin-top:8px;border:1px solid var(--border-2);border-radius:8px;cursor:pointer">
          <strong>{{ customer.company_name }}</strong><small style="display:block;color:var(--ink-muted)">{{ customer.location }}</small>
        </div>
        <p v-if="customerError" class="form-error">{{ customerError }}</p>
      </div>
      <div v-else class="cust-table-wrap">
        <div style="display:flex; align-items:center; gap:12px; padding:16px; border-bottom:1px solid var(--border-2); flex-wrap:wrap">
          <input v-model="search" class="avq-input" placeholder="Search brand..." @input="onSearch" style="min-width:260px" />
          <select v-model="usageFilter" class="avq-input" @change="load">
            <option value="">All usage types</option>
            <option value="online">Online</option>
            <option value="offline">Offline</option>
            <option value="both">Both</option>
          </select>
          <span style="margin-left:auto; font-size:13px; color:var(--ink-muted)" v-if="store.pagination">
            {{ store.pagination.total }} products • Cart: ₹{{ fmt(store.cartTotal) }}
          </span>
        </div>

        <div v-if="store.loading" class="cust-empty">Loading catalog…</div>

        <table v-else class="cust-table">
          <thead>
            <tr>
              <th>BRAND</th>
              <th>DENOMINATIONS AVAILABLE (QTY)</th>
              <th>TOTAL VALUE</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!store.catalog.length">
              <td colspan="4" style="text-align:center; padding:40px; color:var(--ink-muted)">
                No vouchers found. Try a different search.<br>
                <small>Run: php artisan db:seed --class=SendVoucherDummySeeder</small>
              </td>
            </tr>
            <tr v-for="product in store.catalog" :key="product.id" class="cust-row">
              <td>
                <div style="display:flex; align-items:center; gap:10px">
                  <div style="width:36px; height:36px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700">{{ (product.brand||product.name).charAt(0) }}</div>
                  <div>
                    <div class="cust-name">{{ product.brand || product.name }}</div>
                    <div style="font-size:12px; color:var(--ink-muted)">{{ product.name }} • {{ product.country_code||'IN' }} • {{ product.usage_type }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div style="display:flex; flex-wrap:wrap; gap:6px">
                  <span
                    v-for="(stock, denom) in product.stock"
                    :key="denom"
                    class="cust-badge"
                    :class="stock.out_of_stock ? 'badge-inactive' : stock.low_stock ? 'badge-on_hold' : 'badge-active'"
                    style="cursor:pointer"
                    @click="!stock.out_of_stock && quickAdd(product, denom)"
                  >
                    {{ product.currency_code }} {{ fmt(denom) }} ({{ stock.available }})
                    <span v-if="stock.low_stock">⚠</span>
                  </span>
                </div>
              </td>
              <td class="cust-balance">₹{{ fmt(Object.keys(product.stock).reduce((s,k)=> s + (product.stock[k].available * parseFloat(k)),0)) }}</td>
              <td>
                <button class="avq-btn-sm" @click="openProduct(product)">View</button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="store.pagination && store.pagination.last_page>1" class="cust-pagination">
          <button class="avq-btn-sm" :disabled="store.pagination.current_page===1" @click="changePage(store.pagination.current_page-1)">← Prev</button>
          <span>Page {{ store.pagination.current_page }} of {{ store.pagination.last_page }}</span>
          <button class="avq-btn-sm" :disabled="store.pagination.current_page===store.pagination.last_page" @click="changePage(store.pagination.current_page+1)">Next →</button>
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

      <div v-if="toast" style="position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:var(--teal-deep); color:#fff; padding:12px 20px; border-radius:10px; z-index:9999; font-weight:600">{{ toast }}</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import apiClient from '../../../shared/apiClient';

const store = useSendVoucherStore();
const router = useRouter();
const search = ref('');
const usageFilter = ref('');
const page = ref(1);
const selectedProduct = ref(null);
const selectedQty = reactive({});
const toast = ref('');
const cancellingPending = ref(false);
const customers = ref([]); const selectedCustomer = ref(null); const campaign = ref(null); const customerSearch = ref(''); const customerError = ref('');
const filteredCustomers = computed(() => customers.value.filter(c => c.company_name.toLowerCase().includes(customerSearch.value.toLowerCase())));
let timer=null;

onMounted(async()=> { const {data}=await apiClient.get('/customers',{params:{status:'active'}}); customers.value=data.data||data; const saved=sessionStorage.getItem('avq_sendv_customer'); const savedCampaign=sessionStorage.getItem('avq_sendv_campaign'); if(saved && savedCampaign) { selectedCustomer.value=JSON.parse(saved); campaign.value=JSON.parse(savedCampaign); load(); } });
function load(){ if(selectedCustomer.value) store.fetchCatalog({search:search.value, usage_type:usageFilter.value, page:page.value, customer_id:selectedCustomer.value.id}) }
async function selectCustomer(customer) { customerError.value=''; try { const [{data:fullCustomer},{data:assigned}] = await Promise.all([apiClient.get(`/customers/${customer.id}`),apiClient.get(`/customers/${customer.id}/voucher-campaign`)]); if(!assigned) { customerError.value='This customer is not assigned to an active campaign.'; return; } selectedCustomer.value=fullCustomer; campaign.value=assigned; sessionStorage.setItem('avq_sendv_customer',JSON.stringify(fullCustomer)); sessionStorage.setItem('avq_sendv_campaign',JSON.stringify(assigned)); store.clearCart(); load(); } catch(e) { customerError.value=e.response?.data?.message || 'Could not load customer campaign.'; } }
function changeCustomer() { selectedCustomer.value=null; campaign.value=null; store.clearCart(); store.catalog=[]; sessionStorage.removeItem('avq_sendv_customer'); sessionStorage.removeItem('avq_sendv_campaign'); customerSearch.value=''; customerError.value=''; }
function onSearch(){ clearTimeout(timer); timer=setTimeout(()=>{page.value=1; load();},300) }
function changePage(p){ page.value=p; load(); }
function openProduct(p){ selectedProduct.value=p; Object.keys(selectedQty).forEach(k=>delete selectedQty[k]); }
function changeQty(d,delta){ selectedQty[d]=Math.max(0,(selectedQty[d]||0)+delta); }
function addSelected(d){ const q=selectedQty[d]||0; if(!q) return; store.addToCart(selectedProduct.value,parseFloat(d),q); toast.value=`Added ${q} × ${selectedProduct.value.currency_code} ${fmt(d)}`; setTimeout(()=>toast.value='',2500); selectedQty[d]=0; }
function quickAdd(p,d){ store.addToCart(p,parseFloat(d),1); toast.value=`Added 1 × ${p.currency_code} ${fmt(d)}`; setTimeout(()=>toast.value='',2500); }
function fmt(n){ return Number(n).toLocaleString('en-IN',{maximumFractionDigits:0}); }

// Resume a pending order on checkout, which opens directly in OTP-entry mode.
function resumeOtp() {
  router.push('/send-vouchers/send');
}

// Cancel the pending OTP order from the banner — refunds balance
async function cancelPendingOrder() {
  if (!store.pendingOrder) return;
  if (!confirm(`Cancel order ${store.pendingOrder.orderNumber}? The deducted balance will be refunded.`)) return;
  cancellingPending.value = true;
  try {
    await store.cancelOrder(store.pendingOrder.orderNumber);
    toast.value = '✅ Order cancelled — balance refunded.';
    setTimeout(() => toast.value = '', 3000);
  } catch (e) {
    toast.value = '❌ ' + (e.response?.data?.message || 'Failed to cancel order');
    setTimeout(() => toast.value = '', 4000);
  } finally {
    cancellingPending.value = false;
  }
}
</script>
