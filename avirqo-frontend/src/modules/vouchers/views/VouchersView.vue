<template>
  <AppLayout>
  <div class="page">
    <!-- Header row -->
    <div class="page-head">
      <div>
        <h1>Vouchers</h1>
        <p class="sub">Gift-card inventory imported from Xoxoday</p>
      </div>
      <div class="head-actions">
        <div class="balance-pill" v-if="store.balance">
          Xoxoday balance:
          <strong>{{ store.balance.currency }} {{ formatNum(store.balance.value) }}</strong>
        </div>
        <button class="btn-primary" @click="openModal">+ Fetch from Xoxoday</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="{ active: tab==='inventory' }" @click="tab='inventory'">Inventory</button>
      <button :class="{ active: tab==='history' }" @click="switchToHistory">Import history</button>
    </div>

    <!-- INVENTORY TAB -->
    <div v-if="tab==='inventory'" class="card">
      <div class="card-top">
        <input v-model="search" class="search" placeholder="Search brand…" @input="onSearch" />
        <div class="grand">Total inventory value: <strong>₹{{ formatNum(store.grandTotal) }}</strong></div>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>Brand</th>
            <th>Denominations available (qty)</th>
            <th class="num">Total value</th>
            <th class="num">Shared with customers</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.loadingInventory"><td colspan="4" class="empty">Loading…</td></tr>
          <tr v-else-if="!store.inventory.length"><td colspan="4" class="empty">No vouchers imported yet. Click “Fetch from Xoxoday”.</td></tr>
          <tr v-for="b in store.inventory" :key="b.brand_name">
            <td class="brand">
              <img v-if="b.image_url" :src="b.image_url" alt="" />
              <span>{{ b.brand_name }}</span>
            </td>
            <td>
              <span v-for="d in b.denominations" :key="d.denomination" class="denom-chip">
                {{ d.currency_code }} {{ formatNum(d.denomination) }}
                <b>× {{ d.quantity_available }}</b>
              </span>
            </td>
            <td class="num">₹{{ formatNum(b.total_value) }}</td>
            <td class="num">
              <template v-for="d in b.denominations" :key="'s'+d.denomination">
                <span v-if="d.quantity_shared" class="denom-chip shared">
                  {{ formatNum(d.denomination) }} <b>× {{ d.quantity_shared }}</b>
                </span>
              </template>
              <span v-if="!b.total_shared" class="muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- HISTORY TAB -->
    <div v-else class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Brand</th>
            <th class="num">Denomination</th>
            <th class="num">Qty</th>
            <th class="num">Total</th>
            <th>Status</th>
            <th>Message</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.history.length"><td colspan="7" class="empty">No import history yet.</td></tr>
          <tr v-for="h in store.history" :key="h.id">
            <td>{{ formatDate(h.created_at) }}</td>
            <td>{{ h.brand_name }}</td>
            <td class="num">{{ h.currency_code }} {{ formatNum(h.denomination) }}</td>
            <td class="num">{{ h.quantity }}</td>
            <td class="num">{{ h.currency_code }} {{ formatNum(h.total_value) }}</td>
            <td>
              <span class="badge" :class="h.status">{{ h.status }}</span>
            </td>
            <td class="msg">{{ h.message }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <FetchVoucherModal v-if="showModal" @close="showModal=false" @imported="onImported" />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useVoucherStore } from '../store/voucherStore'
import FetchVoucherModal from './FetchVoucherModal.vue'
import AppLayout from '../../shared/components/AppLayout.vue'

const store = useVoucherStore()
const tab = ref('inventory')
const showModal = ref(false)
const search = ref('')
let searchTimer = null

onMounted(() => {
  store.fetchInventory()
  store.fetchBalance()
})

function openModal() { showModal.value = true }
function onImported() { /* modal stays open to show result; inventory already refreshed in store */ }

function switchToHistory() {
  tab.value = 'history'
  store.fetchHistory()
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => store.fetchInventory(search.value), 300)
}

function formatNum(n) {
  return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })
}
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page { font-family: var(--fb); color: var(--ink); padding: 28px; max-width: 1200px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.page-head h1 { font-family: var(--fd); font-size: 24px; margin: 0; font-weight: 600; color: var(--ink); }
.sub { color: var(--ink-muted); font-size: 14px; margin: 2px 0 0; }
.head-actions { display: flex; align-items: center; gap: 12px; }
.balance-pill { background: var(--surface-2); border: 1px solid var(--border-2); padding: 8px 14px; border-radius: 8px; font-size: 13px; color: var(--ink-soft); }
.balance-pill strong { color: var(--teal-deep); font-family: var(--fd); }
.btn-primary { background: var(--teal-deep); color: #fff; border: none; border-radius: 8px; padding: 10px 18px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: var(--fb); }
.tabs { display: flex; gap: 4px; margin-bottom: 16px; }
.tabs button { border: none; background: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; color: var(--ink-muted); font-weight: 600; font-size: 14px; font-family: var(--fb); }
.tabs button.active { background: var(--teal-pale); color: var(--teal-deep); }
.card { background: #fff; border: 1px solid var(--border-2); border-radius: 12px; overflow: hidden; }
.card-top { display: flex; justify-content: space-between; align-items: center; padding: 16px; gap: 16px; flex-wrap: wrap; }
.search { padding: 10px 14px; border: 1.5px solid var(--border-2); border-radius: 8px; font-size: 14px; min-width: 240px; outline: none; font-family: var(--fb); background: #fff; color: var(--ink); }
.grand { font-size: 14px; color: var(--ink-muted); }
.grand strong { color: var(--teal-deep); font-family: var(--fd); }
.table { width: 100%; border-collapse: collapse; font-size: 14px; }
.table thead th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); font-weight: 600; padding: 12px 16px; background: var(--surface-2); }
.table td { padding: 14px 16px; border-top: 1px solid var(--border-2); vertical-align: top; }
.table .num { text-align: right; font-family: var(--fd); }
.brand { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--ink); }
.brand img { width: 26px; height: 26px; object-fit: contain; border-radius: 5px; }
.denom-chip { display: inline-block; background: var(--teal-pale); border: 1px solid var(--border-2); border-radius: 7px; padding: 2px 8px; margin: 2px; font-size: 12px; }
.denom-chip.shared { background: #fef6ec; border-color: #f3e2c7; }
.denom-chip b { color: var(--teal-deep); }
.badge { display: inline-block; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
.badge.success { background: var(--teal-pale); color: var(--teal-deep); }
.badge.error { background: #fef2f2; color: #b91c1c; }
.msg { color: var(--ink-muted); max-width: 320px; font-size: 13px; }
.empty { text-align: center; color: var(--ink-muted); padding: 24px; font-size: 14px; }
.muted { color: var(--ink-muted); }
</style>