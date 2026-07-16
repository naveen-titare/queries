<template>
  <AppLayout>
    <div class="avq-customers">
      <div class="cust-header">
        <div>
          <h2>Send Vouchers</h2>
          <p>Select vouchers from the catalog and send to a customer</p>
        </div>
        <button v-if="store.cartItemCount>0" class="avq-btn-primary" @click="router.push('/send-vouchers/send')">
          📤 Cart ({{ store.cartItemCount }}) - ₹{{ fmt(store.cartTotal) }}
        </button>
      </div>

      <div class="cust-table-wrap">
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
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useSendVoucherStore } from '../store/sendVoucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const store = useSendVoucherStore();
const router = useRouter();
const search = ref('');
const usageFilter = ref('');
const page = ref(1);
const selectedProduct = ref(null);
const selectedQty = reactive({});
const toast = ref('');
let timer=null;

onMounted(()=> load());
function load(){ store.fetchCatalog({search:search.value, usage_type:usageFilter.value, page:page.value}) }
function onSearch(){ clearTimeout(timer); timer=setTimeout(()=>{page.value=1; load();},300) }
function changePage(p){ page.value=p; load(); }
function openProduct(p){ selectedProduct.value=p; Object.keys(selectedQty).forEach(k=>delete selectedQty[k]); }
function changeQty(d,delta){ selectedQty[d]=Math.max(0,(selectedQty[d]||0)+delta); }
function addSelected(d){ const q=selectedQty[d]||0; if(!q) return; store.addToCart(selectedProduct.value,parseFloat(d),q); toast.value=`Added ${q} × ${selectedProduct.value.currency_code} ${fmt(d)}`; setTimeout(()=>toast.value='',2500); selectedQty[d]=0; }
function quickAdd(p,d){ store.addToCart(p,parseFloat(d),1); toast.value=`Added 1 × ${p.currency_code} ${fmt(d)}`; setTimeout(()=>toast.value='',2500); }
function fmt(n){ return Number(n).toLocaleString('en-IN',{maximumFractionDigits:0}); }
</script>
