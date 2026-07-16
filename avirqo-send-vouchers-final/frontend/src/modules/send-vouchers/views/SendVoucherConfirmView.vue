<template>
  <AppLayout>
    <div class="avq-customers" style="max-width:860px">
      <div class="cust-header">
        <div>
          <h2>Confirm & Send</h2>
          <p>Review order before sending - balance will be auto-deducted</p>
        </div>
        <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">← Back</button>
      </div>

      <div style="display:flex; flex-direction:column; gap:20px">
        <div class="cust-table-wrap" style="padding:20px">
          <h3 style="font-size:14px; font-weight:700; margin-bottom:12px">Order Summary - One email per SPOC, all vouchers as Excel attachment</h3>
          <table class="cust-table">
            <thead><tr><th>PRODUCT</th><th>DENOMINATION</th><th>QTY</th><th style="text-align:right">TOTAL</th></tr></thead>
            <tbody>
              <tr v-for="item in cart" :key="item.key">
                <td><div style="display:flex; gap:8px; align-items:center"><div style="width:28px; height:28px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px">{{ item.brand.charAt(0) }}</div><span>{{ item.product_name }} <small style="color:var(--ink-muted)">{{ item.brand }}</small></span></div></td>
                <td>{{ item.currency_code }} {{ fmt(item.denomination) }}</td>
                <td>{{ item.quantity }}</td>
                <td style="text-align:right" class="cust-balance">₹{{ fmt(item.denomination*item.quantity) }}</td>
              </tr>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right; font-weight:700">Total ({{ cart.length }} brands, {{ store.cartItemCount }} codes)</td><td style="text-align:right; font-weight:700; font-size:18px" class="cust-balance">₹{{ fmt(store.cartTotal) }}</td></tr></tfoot>
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

        <div style="display:flex; justify-content:flex-end; gap:12px">
          <button class="avq-btn-ghost" @click="router.push('/send-vouchers/send')">Cancel</button>
          <button class="avq-btn-primary" style="padding:12px 28px" :disabled="sending" @click="send">{{ sending ? 'Sending… building Excel in memory' : '✉ Confirm & Send (deduct balance)' }}</button>
        </div>
        <p v-if="error" style="color:#b91c1c; background:#fef2f2; padding:12px; border-radius:8px">{{ error }}</p>
      </div>

      <div v-if="success" style="position:fixed; inset:0; background:rgba(8,80,65,0.6); backdrop-filter:blur(4px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:24px">
        <div style="background:#fff; border-radius:20px; padding:40px; max-width:480px; width:100%; text-align:center">
          <div style="font-size:48px">✅</div>
          <h2 style="font-size:24px; margin:12px 0">Vouchers Sent!</h2>
          <p style="font-size:14px; color:var(--ink-muted)"><strong>{{ store.cartItemCount }} codes</strong> across {{ cart.length }} brands worth ₹{{ fmt(sentTotal) }} sent to <strong>{{ spoc?.email }}</strong></p>
          <p style="font-size:11px; font-family:monospace; background:var(--surface-2); padding:6px 10px; border-radius:6px; margin-top:8px">Order: {{ orderNumber }} • encrypted, no file on disk</p>
          <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px">
            <button class="avq-btn-primary" @click="router.push('/send-vouchers')">Send more</button>
            <button class="avq-btn-ghost" @click="router.push('/dashboard')">Dashboard</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const store = useSendVoucherStore();
const router = useRouter();
const customer=ref(null); const spoc=ref(null); const sending=ref(false); const success=ref(false); const error=ref(''); const orderNumber=ref(''); const sentTotal=ref(0);
const cart=computed(()=> store.cart);
const balanceAfter=computed(()=> (customer.value?.balance||0) - store.cartTotal);

onMounted(()=>{
  const c=sessionStorage.getItem('avq_sendv_customer'); const s=sessionStorage.getItem('avq_sendv_spoc');
  if(!c||!s||!store.cart.length){ router.push('/send-vouchers'); return; }
  customer.value=JSON.parse(c); spoc.value=JSON.parse(s);
});

async function send(){
  sending.value=true; error.value=''; sentTotal.value=store.cartTotal;
  try{ const r=await store.placeOrder(customer.value.id, spoc.value.id); orderNumber.value=r.order.order_number; success.value=true; sessionStorage.removeItem('avq_sendv_customer'); sessionStorage.removeItem('avq_sendv_spoc'); }
  catch(e){ error.value=e.response?.data?.message || 'Failed to send'; }
  finally{ sending.value=false; }
}
function fmt(n){ return Number(n||0).toLocaleString('en-IN',{maximumFractionDigits:0}); }
</script>
