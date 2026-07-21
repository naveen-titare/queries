<template>
  <AppLayout>
    <main class="cp-page">
      <header class="cp-header">
        <div>
          <h1>Customer Products</h1>
          <p>Choose products from the Send Vouchers catalogue, set their discounts, and blacklist products per customer.</p>
        </div>
        <button class="avq-btn-primary" :disabled="!selectedCustomer || saving" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </header>

      <div class="cp-layout">
        <section class="cp-customers avq-card">
          <label for="customer-search">Customer</label>
          <input id="customer-search" v-model="customerSearch" class="avq-input" placeholder="Search customer…" @input="loadCustomers" />
          <p v-if="customersLoading" class="cp-muted">Loading customers…</p>
          <button v-for="customer in customers" :key="customer.id" class="cp-customer"
            :class="{ selected: selectedCustomer?.id === customer.id }" @click="selectCustomer(customer)">
            <strong>{{ customer.company_name }}</strong>
            <small>{{ customer.location }}</small>
          </button>
          <p v-if="!customersLoading && !customers.length" class="cp-muted">No customers found.</p>
        </section>

        <section class="cp-products avq-card">
          <template v-if="selectedCustomer">
            <div class="cp-products-head">
              <div>
                <h2>{{ selectedCustomer.company_name }}</h2>
                <span>{{ selectedCount }} selected · {{ blacklistedCount }} blacklisted</span>
              </div>
              <input v-model="productSearch" class="avq-input" placeholder="Search Send Voucher products…" />
            </div>

            <p v-if="productsLoading" class="cp-empty">Loading Send Voucher catalogue…</p>
            <p v-else-if="!filteredProducts.length" class="cp-empty">No catalogue products match your search.</p>
            <div v-else class="cp-table-wrap">
              <table class="cp-table">
                <thead><tr><th>Use</th><th>Product</th><th>Discount (%)</th><th>Blacklist</th></tr></thead>
                <tbody>
                  <tr v-for="product in filteredProducts" :key="product.id" :class="{ muted: !product.selected }">
                    <td><input v-model="product.selected" type="checkbox" :aria-label="`Use ${product.name}`" /></td>
                    <td><strong>{{ product.brand || product.name }}</strong><small>{{ product.name }}</small></td>
                    <td><input v-model.number="product.discount_percentage" class="avq-input cp-discount" type="number" min="0" max="100" step="0.01" :disabled="!product.selected" /></td>
                    <td><input v-model="product.is_blacklisted" type="checkbox" :disabled="!product.selected" :aria-label="`Blacklist ${product.name}`" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          <div v-else class="cp-empty">Select a customer to configure their product catalogue.</div>
          <p v-if="error" class="cp-error">{{ error }}</p>
        </section>
      </div>
    </main>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../../shared/components/AppLayout.vue';
import customerProductApi from '../api/customerProductApi';

const customers = ref([]);
const products = ref([]);
const selectedCustomer = ref(null);
const customerSearch = ref('');
const productSearch = ref('');
const customersLoading = ref(false);
const productsLoading = ref(false);
const saving = ref(false);
const error = ref('');
let customerSearchTimer;

const filteredProducts = computed(() => {
  const term = productSearch.value.trim().toLowerCase();
  return term ? products.value.filter((p) => `${p.name} ${p.brand || ''}`.toLowerCase().includes(term)) : products.value;
});
const selectedCount = computed(() => products.value.filter((p) => p.selected).length);
const blacklistedCount = computed(() => products.value.filter((p) => p.selected && p.is_blacklisted).length);

async function fetchCustomers() {
  customersLoading.value = true;
  try {
    const { data } = await customerProductApi.customers({ search: customerSearch.value, status: 'active' });
    customers.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load customers.';
  } finally { customersLoading.value = false; }
}
function loadCustomers() {
  clearTimeout(customerSearchTimer);
  customerSearchTimer = setTimeout(fetchCustomers, 250);
}
async function selectCustomer(customer) {
  selectedCustomer.value = customer;
  products.value = [];
  error.value = '';
  productsLoading.value = true;
  try {
    const { data } = await customerProductApi.products(customer.id);
    products.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load the Send Voucher catalogue.';
  } finally { productsLoading.value = false; }
}
async function save() {
  if (!selectedCustomer.value) return;
  saving.value = true;
  error.value = '';
  try {
    await customerProductApi.save(selectedCustomer.value.id, products.value.filter((p) => p.selected).map((p) => ({
      product_id: p.id,
      discount_percentage: Number(p.discount_percentage || 0),
      is_blacklisted: p.is_blacklisted,
    })));
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save customer products.';
  } finally { saving.value = false; }
}
onMounted(fetchCustomers);
</script>

<style scoped>
.cp-page { padding: 28px; max-width: 1400px; width: 100%; box-sizing: border-box; }
.cp-header, .cp-products-head { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
.cp-header { margin-bottom:24px; }.cp-header h1 { font:600 30px var(--fd); margin:0 0 6px; }.cp-header p { color:var(--ink-muted); margin:0; max-width:680px; font-size:14px; }
.cp-layout { display:grid; grid-template-columns:280px minmax(0, 1fr); gap:20px; }.avq-card { background:#fff; border:1px solid var(--border-2); border-radius:12px; padding:18px; }
.cp-customers { display:flex; flex-direction:column; gap:9px; }.cp-customers label { font-size:13px; font-weight:700; }.cp-customer { text-align:left; border:1px solid var(--border-2); background:#fff; border-radius:8px; padding:10px; cursor:pointer; font:inherit; }.cp-customer:hover,.cp-customer.selected { border-color:var(--teal-mid); background:var(--teal-pale); }.cp-customer strong,.cp-customer small,.cp-table small { display:block; }.cp-customer small,.cp-table small,.cp-muted,.cp-products-head span { color:var(--ink-muted); font-size:12px; margin-top:3px; }
.cp-products { min-height:480px; }.cp-products-head { margin-bottom:18px; }.cp-products-head h2 { margin:0 0 4px; font-size:18px; }.cp-products-head .avq-input { width:260px; }.cp-table-wrap { overflow:auto; }.cp-table { width:100%; border-collapse:collapse; font-size:14px; }.cp-table th { color:var(--ink-muted); font-size:12px; text-align:left; border-bottom:1px solid var(--border-2); padding:10px; }.cp-table td { padding:11px 10px; border-bottom:1px solid var(--border-2); }.cp-table tr.muted { opacity:.52; }.cp-discount { width:100px; padding:7px 9px; }.cp-empty { color:var(--ink-muted); padding:42px 10px; text-align:center; }.cp-error { color:#b42318; margin:14px 0 0; font-size:13px; }
@media (max-width:800px) { .cp-page { padding:18px; }.cp-layout { grid-template-columns:1fr; }.cp-header { flex-direction:column; }.cp-products-head { flex-direction:column; }.cp-products-head .avq-input { width:100%; box-sizing:border-box; } }
</style>
