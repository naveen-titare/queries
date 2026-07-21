<template>
  <AppLayout>
    <div class="avq-customers">
      <div class="cust-header">
        <div>
          <h2>Voucher Inventory</h2>
          <p>Available brand vouchers by denomination and quantity.</p>
        </div>
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
          <span style="margin-left:auto; font-size:13px; color:var(--ink-muted)" v-if="pagination">
            {{ pagination.total }} products
          </span>
        </div>

        <div class="send-voucher-table-scroll">
          <div v-if="loading" class="cust-empty">Loading inventory…</div>

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
              <tr v-if="!catalog.length">
                <td colspan="4" style="text-align:center; padding:40px; color:var(--ink-muted)">
                  No available vouchers found.
                </td>
              </tr>
              <tr v-for="product in catalog" :key="product.id" class="cust-row">
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
                    >
                      {{ product.currency_code }} {{ fmt(denom) }} ({{ stock.available }})
                      <span v-if="stock.low_stock">⚠</span>
                    </span>
                  </div>
                </td>
                <td class="cust-balance">₹{{ fmt(Object.keys(product.stock || {}).reduce((s,k)=> s + (product.stock[k].available * parseFloat(k)),0)) }}</td>
                <td>
                  <button class="avq-btn-sm" @click="selectedProduct = product">View</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination && pagination.last_page>1" class="cust-pagination">
          <button class="avq-btn-sm" :disabled="pagination.current_page===1" @click="changePage(pagination.current_page-1)">← Prev</button>
          <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="avq-btn-sm" :disabled="pagination.current_page===pagination.last_page" @click="changePage(pagination.current_page+1)">Next →</button>
        </div>
      </div>

      <div v-if="selectedProduct" class="avq-modal-overlay" @click.self="selectedProduct=null">
        <div class="avq-modal">
          <h3>{{ selectedProduct.brand }} - {{ selectedProduct.name }}</h3>
          <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px">{{ selectedProduct.terms_and_conditions || 'Available denominations and stock' }}</p>
          <div v-for="(stock, denom) in selectedProduct.stock" :key="denom" style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border-2)">
            <span style="font-weight:700; min-width:100px">{{ selectedProduct.currency_code }} {{ fmt(denom) }}</span>
            <span style="font-size:12px" :class="stock.out_of_stock?'err': stock.low_stock?'cust-balance':''">{{ stock.out_of_stock?'Out of stock': stock.low_stock?`Only ${stock.available} left`:`${stock.available} available` }}</span>
          </div>
          <div class="modal-footer">
            <button class="avq-btn-ghost" @click="selectedProduct=null">Close</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../../shared/components/AppLayout.vue';
import sendVoucherApi from '../../send-vouchers/api/sendVoucherApi';

const catalog = ref([]);
const pagination = ref(null);
const loading = ref(false);
const search = ref('');
const usageFilter = ref('');
const page = ref(1);
const selectedProduct = ref(null);
let timer = null;

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const { data } = await sendVoucherApi.getCatalog({ search: search.value, usage_type: usageFilter.value, page: page.value });
    catalog.value = data.data || [];
    pagination.value = { total: data.total, per_page: data.per_page, current_page: data.current_page, last_page: data.last_page };
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  clearTimeout(timer);
  timer = setTimeout(() => { page.value = 1; load(); }, 300);
}

function changePage(nextPage) {
  page.value = nextPage;
  load();
}

function fmt(n){ return Number(n).toLocaleString('en-IN',{maximumFractionDigits:0}); }
</script>

<style scoped>
.send-voucher-table-scroll {
  max-height: min(68vh, 760px);
  overflow: auto;
}
</style>
