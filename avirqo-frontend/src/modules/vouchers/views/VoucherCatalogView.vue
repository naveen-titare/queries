<template>
  <AppLayout>
    <div class="vcat-page">
      <div class="vcat-header">
        <div>
          <h1>Send Vouchers</h1>
          <p>Select vouchers from the catalog and send to a customer</p>
        </div>
        <button v-if="store.cartItemCount > 0" class="vcat-cart-btn" @click="router.push('/vouchers/send')">
          🛒 Cart ({{ store.cartItemCount }}) — ₹{{ fmt(store.cartTotal) }}
        </button>
      </div>

      <!-- Filters -->
      <div class="vcat-filters">
        <input v-model="search" class="avq-input" placeholder="Search brand or product…" @input="onSearch" />
        <select v-model="usageFilter" class="avq-input" @change="load">
          <option value="">All usage types</option>
          <option value="online">Online</option>
          <option value="offline">Offline</option>
          <option value="both">Online & Offline</option>
        </select>
        <span class="vcat-count" v-if="store.pagination">
          {{ store.pagination.total }} products
        </span>
      </div>

      <!-- Loading -->
      <div v-if="store.loading" class="vcat-loading">Loading catalog…</div>

      <!-- Grid -->
      <div v-else class="vcat-grid">
        <div v-if="!store.catalog.length" class="vcat-empty">
          No vouchers found. Try a different search.
        </div>
        <div v-for="product in store.catalog" :key="product.id" class="vcat-card" @click="openProduct(product)">
          <div class="vcat-card-img">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" />
            <div v-else class="vcat-card-img-placeholder">🎁</div>
          </div>
          <div class="vcat-card-body">
            <div class="vcat-card-brand">{{ product.brand || product.name }}</div>
            <div class="vcat-card-name">{{ product.name }}</div>
            <div class="vcat-card-usage">
              <span class="vcat-chip">{{ product.usage_type || 'universal' }}</span>
              <span class="vcat-chip">{{ product.country_code || 'IN' }}</span>
            </div>
            <div class="vcat-denoms">
              <span
                v-for="(stock, denom) in product.stock"
                :key="denom"
                class="vcat-denom"
                :class="{ 'low-stock': stock.low_stock, 'out-of-stock': stock.out_of_stock }"
                @click.stop="!stock.out_of_stock && quickAdd(product, denom)"
              >
                {{ product.currency_code }} {{ fmt(denom) }}
                <span class="vcat-denom-qty">
                  {{ stock.out_of_stock ? 'Out' : stock.low_stock ? `⚠ ${stock.available} left` : `${stock.available}` }}
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="store.pagination && store.pagination.last_page > 1" class="vcat-pagination">
        <button :disabled="store.pagination.current_page === 1" @click="changePage(store.pagination.current_page - 1)">← Prev</button>
        <span>Page {{ store.pagination.current_page }} of {{ store.pagination.last_page }}</span>
        <button :disabled="store.pagination.current_page === store.pagination.last_page" @click="changePage(store.pagination.current_page + 1)">Next →</button>
      </div>

      <!-- Product Detail Modal -->
      <div v-if="selectedProduct" class="avq-modal-overlay" @click.self="selectedProduct = null">
        <div class="avq-modal vcat-detail-modal">
          <div class="vcat-detail-head">
            <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="vcat-detail-img" />
            <div>
              <div class="vcat-detail-brand">{{ selectedProduct.brand }}</div>
              <h3>{{ selectedProduct.name }}</h3>
              <div class="vcat-detail-chips">
                <span class="vcat-chip">{{ selectedProduct.usage_type }}</span>
                <span class="vcat-chip">{{ selectedProduct.delivery_type }}</span>
                <span class="vcat-chip">{{ selectedProduct.country_name }}</span>
              </div>
            </div>
          </div>

          <div class="vcat-detail-section">
            <h4>Select Denomination & Quantity</h4>
            <div v-for="(stock, denom) in selectedProduct.stock" :key="denom" class="vcat-denom-row">
              <span class="vcat-denom-label">{{ selectedProduct.currency_code }} {{ fmt(denom) }}</span>
              <span :class="['vcat-stock-label', stock.out_of_stock ? 'out' : stock.low_stock ? 'low' : 'ok']">
                {{ stock.out_of_stock ? 'Out of stock' : stock.low_stock ? `⚠ Only ${stock.available} left` : `${stock.available} available` }}
              </span>
              <div v-if="!stock.out_of_stock" class="vcat-qty-ctrl">
                <button @click="changeQty(denom, -1)">−</button>
                <span>{{ selectedQty[denom] || 0 }}</span>
                <button @click="changeQty(denom, 1)" :disabled="(selectedQty[denom] || 0) >= stock.available">+</button>
                <button class="vcat-add-btn" @click="addSelected(denom)" :disabled="!selectedQty[denom]">Add to cart</button>
              </div>
            </div>
          </div>

          <div v-if="selectedProduct.terms_and_conditions" class="vcat-detail-section">
            <h4>Terms & Conditions</h4>
            <p>{{ selectedProduct.terms_and_conditions }}</p>
          </div>
          <div v-if="selectedProduct.redemption_instructions" class="vcat-detail-section">
            <h4>How to Redeem</h4>
            <p>{{ selectedProduct.redemption_instructions }}</p>
          </div>
          <div v-if="selectedProduct.expiry_and_validity" class="vcat-detail-section">
            <h4>Expiry & Validity</h4>
            <p>{{ selectedProduct.expiry_and_validity }}</p>
          </div>

          <div class="modal-footer">
            <button class="avq-btn-ghost" @click="selectedProduct = null">Close</button>
          </div>
        </div>
      </div>

      <!-- Cart added toast -->
      <div v-if="toast" class="vcat-toast">{{ toast }}</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useVoucherStore } from '../store/voucherStore';
import AppLayout from '../../shared/components/AppLayout.vue';

const store = useVoucherStore();
const router = useRouter();
const search = ref('');
const usageFilter = ref('');
const page = ref(1);
const selectedProduct = ref(null);
const selectedQty = reactive({});
const toast = ref('');
let searchTimer = null;

onMounted(() => load());

function load() {
  store.fetchCatalog({ search: search.value, usage_type: usageFilter.value, page: page.value });
}

function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
}

function changePage(p) { page.value = p; load(); }

function openProduct(product) {
  selectedProduct.value = product;
  Object.keys(selectedQty).forEach(k => delete selectedQty[k]);
}

function changeQty(denom, delta) {
  selectedQty[denom] = Math.max(0, (selectedQty[denom] || 0) + delta);
}

function addSelected(denom) {
  const qty = selectedQty[denom] || 0;
  if (!qty) return;
  store.addToCart(selectedProduct.value, parseFloat(denom), qty);
  showToast(`Added ${qty} × ${selectedProduct.value.currency_code} ${fmt(denom)} to cart`);
  selectedQty[denom] = 0;
}

function quickAdd(product, denom) {
  store.addToCart(product, parseFloat(denom), 1);
  showToast(`Added 1 × ${product.currency_code} ${fmt(denom)} to cart`);
}

function showToast(msg) {
  toast.value = msg;
  setTimeout(() => toast.value = '', 2500);
}

function fmt(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
</script>

<style>
.vcat-page { padding: 28px; font-family: var(--fb); }
.vcat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.vcat-header h1 { font-family: var(--fd); font-size: 24px; font-weight: 600; margin: 0 0 4px; }
.vcat-header p { color: var(--ink-muted); font-size: 14px; margin: 0; }
.vcat-cart-btn { background: var(--teal-deep); color: #fff; border: none; border-radius: 10px; padding: 12px 20px; font-weight: 700; font-size: 14px; cursor: pointer; font-family: var(--fb); transition: background 0.2s; }
.vcat-cart-btn:hover { background: var(--teal-mid); }
.vcat-filters { display: flex; gap: 12px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; }
.vcat-count { font-size: 13px; color: var(--ink-muted); }
.vcat-loading { text-align: center; padding: 48px; color: var(--ink-muted); }
.vcat-empty { text-align: center; padding: 48px; color: var(--ink-muted); font-size: 15px; }

.vcat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }

.vcat-card {
  background: #fff; border: 1px solid var(--border-2); border-radius: 14px;
  overflow: hidden; cursor: pointer; transition: box-shadow 0.2s, transform 0.15s;
}
.vcat-card:hover { box-shadow: 0 8px 24px rgba(8,80,65,0.12); transform: translateY(-2px); }
.vcat-card-img { height: 120px; display: flex; align-items: center; justify-content: center; background: var(--surface-2); padding: 12px; }
.vcat-card-img img { max-height: 96px; max-width: 100%; object-fit: contain; }
.vcat-card-img-placeholder { font-size: 40px; }
.vcat-card-body { padding: 14px; }
.vcat-card-brand { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); margin-bottom: 4px; }
.vcat-card-name { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 8px; line-height: 1.3; }
.vcat-card-usage { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.vcat-chip { font-size: 10px; font-weight: 600; background: var(--teal-pale); color: var(--teal-deep); padding: 3px 8px; border-radius: 100px; text-transform: capitalize; }
.vcat-denoms { display: flex; flex-wrap: wrap; gap: 6px; }
.vcat-denom {
  font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 7px;
  background: var(--surface-2); border: 1px solid var(--border-2); cursor: pointer;
  transition: background 0.15s; color: var(--ink-soft);
}
.vcat-denom:hover:not(.out-of-stock) { background: var(--teal-pale); color: var(--teal-deep); }
.vcat-denom.low-stock { border-color: #f3e2c7; background: #fef6ec; color: #b45309; }
.vcat-denom.out-of-stock { opacity: 0.4; cursor: not-allowed; }
.vcat-denom-qty { font-size: 10px; opacity: 0.7; margin-left: 4px; }

.vcat-pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 28px; font-size: 14px; color: var(--ink-muted); }
.vcat-pagination button { background: var(--surface-2); border: 1px solid var(--border-2); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-family: var(--fb); font-size: 13px; }
.vcat-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Detail modal */
.vcat-detail-modal { max-width: 640px; }
.vcat-detail-head { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 20px; }
.vcat-detail-img { width: 80px; height: 80px; object-fit: contain; border-radius: 10px; border: 1px solid var(--border-2); background: var(--surface-2); padding: 8px; }
.vcat-detail-brand { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--teal-mid); margin-bottom: 4px; }
.vcat-detail-head h3 { font-family: var(--fd); font-size: 20px; margin: 0 0 8px; }
.vcat-detail-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.vcat-detail-section { border-top: 1px solid var(--border-2); padding-top: 16px; margin-bottom: 16px; }
.vcat-detail-section h4 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); margin: 0 0 12px; }
.vcat-detail-section p { font-size: 13px; color: var(--ink-soft); line-height: 1.6; margin: 0; }
.vcat-denom-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-2); flex-wrap: wrap; }
.vcat-denom-label { font-weight: 700; font-size: 15px; min-width: 100px; }
.vcat-stock-label { font-size: 12px; min-width: 120px; }
.vcat-stock-label.ok { color: var(--teal-mid); }
.vcat-stock-label.low { color: #b45309; }
.vcat-stock-label.out { color: #b91c1c; }
.vcat-qty-ctrl { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.vcat-qty-ctrl button { width: 28px; height: 28px; border: 1px solid var(--border-2); background: var(--surface-2); border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; }
.vcat-qty-ctrl button:disabled { opacity: 0.4; cursor: not-allowed; }
.vcat-qty-ctrl span { min-width: 24px; text-align: center; font-weight: 700; }
.vcat-add-btn { background: var(--teal-deep) !important; color: #fff !important; border-color: var(--teal-deep) !important; padding: 0 12px !important; width: auto !important; font-size: 12px !important; font-family: var(--fb); font-weight: 600; }
.vcat-add-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.vcat-toast {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: var(--teal-deep); color: #fff; padding: 12px 20px; border-radius: 10px;
  font-size: 13px; font-weight: 600; z-index: 9999; font-family: var(--fb);
  box-shadow: 0 8px 24px rgba(8,80,65,0.25);
}
</style>
