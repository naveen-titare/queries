<template>
  <AppLayout>
    <div class="avq-customers">
      <div class="cust-header">
        <div>
          <h2>Select Recipient</h2>
          <p>Choose the customer and SPOC to receive the vouchers</p>
        </div>
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers')">← Back to catalog</button>
      </div>

      <div style="display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start">
        <div style="display:flex; flex-direction:column; gap:16px">
          <div class="cust-table-wrap" style="padding:20px">
            <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Customer</h3>
            <input v-model="customerSearch" class="avq-input" placeholder="Search company or GST…" @input="searchCustomers" style="width:100%" />
            <div v-if="customers.length" style="display:flex; flex-direction:column; gap:8px; margin-top:12px; max-height:300px; overflow:auto">
              <div v-for="c in customers" :key="c.id" @click="selectCustomer(c)" style="padding:12px; border:1.5px solid var(--border-2); border-radius:10px; cursor:pointer" :style="selectedCustomer?.id===c.id ? 'border-color:var(--teal-deep); background:var(--teal-pale)' : ''">
                <div class="cust-name">{{ c.company_name }}</div>
                <div style="font-size:12px; color:var(--ink-muted)">{{ c.location }} • GST: {{ c.gst_number||'—' }}</div>
                <div class="cust-balance" style="font-size:12px; margin-top:4px">Balance: ₹{{ fmt(c.balance) }} <span :class="c.status==='active'?'cust-badge badge-active':'cust-badge badge-on_hold'">{{ c.status }}</span></div>
              </div>
            </div>
            <div v-else-if="customerSearch" class="cust-empty">No customers found.</div>
            <div v-else style="font-size:12px; color:var(--ink-muted); padding:8px 0">Type 2+ letters to search (same as Customers module)</div>
          </div>

          <div v-if="selectedCustomer" class="cust-table-wrap" style="padding:20px">
            <h3 style="font-size:14px; font-weight:700; margin-bottom:4px">SPOC (Recipient)</h3>
            <p style="font-size:12px; color:var(--ink-muted); margin-bottom:12px">Encrypted vouchers will be sent to this email</p>
            <div v-if="!selectedCustomer.spocs?.length" class="cust-empty">No SPOCs found for this customer.</div>
            <div v-else style="display:flex; flex-direction:column; gap:8px">
              <div v-for="spoc in selectedCustomer.spocs" :key="spoc.id" @click="spoc.email && selectSpoc(spoc)" :style="selectedSpoc?.id===spoc.id ? 'border-color:var(--teal-deep); background:var(--teal-pale)' : ''" style="padding:12px; border:1.5px solid var(--border-2); border-radius:10px; cursor:pointer">
                <div style="font-weight:600; font-size:14px">{{ spoc.name }} <span v-if="spoc.is_primary" class="cust-badge badge-active" style="font-size:10px">Primary</span></div>
                <div style="font-size:12px; color:var(--ink-muted)">{{ spoc.email || '⚠ No email - cannot receive' }}</div>
                <div style="font-size:12px; color:var(--ink-muted)">{{ spoc.phone || '' }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="cust-table-wrap" style="padding:20px">
          <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Cart Summary ({{ store.cartItemCount }} items)</h3>
          <div v-if="!store.cart.length" class="cust-empty">Your cart is empty. <RouterLink to="/send-vouchers">Go to catalog</RouterLink></div>
          <div v-for="item in store.cart" :key="item.key" style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border-2)">
            <div style="width:36px; height:36px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700">{{ item.brand.charAt(0) }}</div>
            <div style="flex:1">
              <div style="font-size:13px; font-weight:600">{{ item.product_name }}</div>
              <div style="font-size:12px; color:var(--ink-muted)">{{ item.currency_code }} {{ fmt(item.denomination) }} × {{ item.quantity }}</div>
              <div v-if="item.available<=10" style="font-size:10px; color:#B45309">⚠ Only {{ item.available }} left</div>
            </div>
            <div class="cust-balance">₹{{ fmt(item.denomination * item.quantity) }}</div>
            <button class="avq-btn-sm" @click="store.removeFromCart(item.key)">✕</button>
          </div>
          <div style="display:flex; justify-content:space-between; padding:12px 0; border-top:2px solid var(--border-2); margin-top:8px; font-weight:700">
            <span>Total to deduct</span>
            <span class="cust-balance" style="font-size:18px">₹{{ fmt(store.cartTotal) }}</span>
          </div>

          <div v-if="selectedCustomer" style="background:var(--surface-2); border-radius:10px; padding:12px; margin:12px 0">
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0"><span>Balance before</span><span>₹{{ fmt(selectedCustomer.balance) }}</span></div>
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0"><span>Order total</span><span>₹{{ fmt(store.cartTotal) }}</span></div>
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:4px 0; font-weight:700" :style="balanceShortfall>0 ? 'color:#b91c1c' : 'color:var(--teal-deep)'"><span>Balance after</span><span>₹{{ fmt(selectedCustomer.balance - store.cartTotal) }}</span></div>
          </div>

          <p v-if="error" class="form-error" style="color:#b91c1c; background:#fef2f2; padding:10px; border-radius:8px; font-size:13px">{{ error }}</p>

          <button class="avq-btn-primary" style="width:100%; margin-top:12px; padding:12px" :disabled="!canProceed || loading" @click="proceed">
            {{ loading ? 'Validating…' : 'Review & Confirm →' }}
          </button>
          <p style="font-size:11px; color:var(--ink-muted); text-align:center; margin-top:8px">Codes are encrypted and reserved only after confirmation</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';
import axios from 'axios';

const store = useSendVoucherStore();
const router = useRouter();
const customerSearch=ref(''); const customers=ref([]); const selectedCustomer=ref(null); const selectedSpoc=ref(null); const loading=ref(false); const error=ref(''); let timer=null;

const balanceShortfall=computed(()=> selectedCustomer.value ? store.cartTotal - selectedCustomer.value.balance : 0);
const canProceed=computed(()=> selectedCustomer.value && selectedSpoc.value && store.cart.length>0);

function searchCustomers(){
  clearTimeout(timer);
  timer=setTimeout(async()=>{
    if(!customerSearch.value || customerSearch.value.length<2) return;
    const token=localStorage.getItem('avirqo_access_token');
    const base=import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
    try{ const {data}=await axios.get(`${base}/customers`,{params:{search:customerSearch.value}, headers:{Authorization:`Bearer ${token}`}}); customers.value=data.data||data; }catch(e){ console.error(e); }
  },300);
}
async function selectCustomer(c){
  const token=localStorage.getItem('avirqo_access_token');
  const base=import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
  const {data}=await axios.get(`${base}/customers/${c.id}`,{headers:{Authorization:`Bearer ${token}`}});
  selectedCustomer.value=data; selectedSpoc.value=data.spocs?.length===1 ? data.spocs[0] : null;
}
function selectSpoc(s){ selectedSpoc.value=s; }
async function proceed(){
  loading.value=true; error.value='';
  try{ await store.validateCart(); sessionStorage.setItem('avq_sendv_customer', JSON.stringify(selectedCustomer.value)); sessionStorage.setItem('avq_sendv_spoc', JSON.stringify(selectedSpoc.value)); router.push('/send-vouchers/confirm'); }
  catch(e){ error.value=e.response?.data?.message || 'Validation failed - check stock'; }
  finally{ loading.value=false; }
}
function fmt(n){ return Number(n||0).toLocaleString('en-IN',{maximumFractionDigits:0}); }
</script>
