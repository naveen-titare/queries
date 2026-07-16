<template>
  <div class="overlay" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <div>
          <h2>Fetch vouchers from Xoxoday</h2>
          <p class="sub">Select a brand, denomination and quantity to import.</p>
        </div>
        <button class="x" @click="$emit('close')">✕</button>
      </header>

      <!-- Balance strip -->
      <div class="balance-strip">
        <span>Xoxoday wallet balance</span>
        <strong v-if="store.balance">
          {{ store.balance.currency }} {{ formatNum(store.balance.value) }}
        </strong>
        <em v-else-if="store.loadingBalance">loading…</em>
        <em v-else>—</em>
      </div>

      <!-- Fetch catalog button -->
      <div v-if="!store.catalog.length" class="fetch-cta">
        <button class="btn-primary" :disabled="store.loadingCatalog" @click="loadCatalog">
          {{ store.loadingCatalog ? 'Fetching…' : 'Fetch vouchers from Xoxoday' }}
        </button>
        <p v-if="store.error" class="error">{{ store.error }}</p>
      </div>

      <!-- Catalog + selection -->
      <div v-else class="picker">
        <input v-model="search" class="search" placeholder="Search brand…" />

        <div class="grid">
          <label
            v-for="p in filteredCatalog"
            :key="p.product_id"
            class="brand-card"
            :class="{ active: form.product_id === p.product_id }"
          >
            <input type="radio" :value="p.product_id" v-model="form.product_id" @change="selectBrand(p)" />
            <img v-if="p.image_url" :src="p.image_url" alt="" />
            <div class="brand-info">
              <span class="brand-name">{{ p.brand_name }}</span>
              <span class="brand-meta">{{ p.currency_code }} · {{ p.denominations.length }} denominations</span>
            </div>
          </label>
        </div>

        <div v-if="selected" class="selection">
          <div class="field">
            <label>Denomination</label>
            <select v-model.number="form.denomination">
              <option v-for="d in selected.denominations" :key="d" :value="d">
                {{ selected.currency_code }} {{ formatNum(d) }}
              </option>
            </select>
          </div>
          <div class="field">
            <label>Quantity</label>
            <input type="number" min="1" :max="selected.order_limit || 1000" v-model.number="form.quantity" />
          </div>
          <div class="field total">
            <label>Total value</label>
            <strong>{{ selected.currency_code }} {{ formatNum(totalValue) }}</strong>
          </div>
        </div>

        <div v-if="selected" class="actions">
          <p v-if="insufficient" class="warn">
            ⚠ Insufficient balance for this import.
          </p>
          <p v-if="resultMsg" :class="resultOk ? 'success' : 'error'">{{ resultMsg }}</p>
          <button
            class="btn-primary"
            :disabled="store.importing || insufficient || !form.denomination || !form.quantity"
            @click="doImport"
          >
            {{ store.importing ? 'Importing…' : 'Fetch selected voucher' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useVoucherStore } from '../store/voucherStore'

const emit = defineEmits(['close', 'imported'])
const store = useVoucherStore()

const search = ref('')
const selected = ref(null)
const resultMsg = ref('')
const resultOk = ref(false)
const form = ref({ product_id: null, denomination: null, quantity: 1 })

onMounted(() => { store.fetchBalance() })

const filteredCatalog = computed(() => {
  const q = search.value.toLowerCase()
  return store.catalog.filter((p) => p.brand_name.toLowerCase().includes(q))
})

const totalValue = computed(() =>
  (Number(form.value.denomination) || 0) * (Number(form.value.quantity) || 0)
)

const insufficient = computed(() =>
  store.balance ? totalValue.value > Number(store.balance.value) : false
)

function formatNum(n) {
  return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

async function loadCatalog() {
  await store.fetchCatalog()
}

function selectBrand(p) {
  selected.value = p
  form.value.denomination = p.denominations[0] ?? null
  form.value.quantity = 1
  resultMsg.value = ''
}

async function doImport() {
  resultMsg.value = ''
  const res = await store.importVoucher({
    product_id: selected.value.product_id,
    brand_name: selected.value.brand_name,
    denomination: form.value.denomination,
    quantity: form.value.quantity,
    currency_code: selected.value.currency_code,
    image_url: selected.value.image_url,
  })
  resultOk.value = res.ok
  resultMsg.value = res.message
  if (res.ok) emit('imported')
}
</script>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,42,34,.45); display: flex; align-items: flex-start; justify-content: center; padding: 4vh 1rem; z-index: 50; }
.modal { background: #fff; width: 100%; max-width: 760px; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,.2); overflow: hidden; font-family: system-ui, sans-serif; }
.modal-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.25rem 1.5rem; border-bottom: 1px solid #eef2f0; }
.modal-head h2 { margin: 0; font-size: 1.15rem; color: #0f2a22; }
.sub { margin: .25rem 0 0; color: #6b7280; font-size: .85rem; }
.x { border: 0; background: none; font-size: 1rem; cursor: pointer; color: #6b7280; }
.balance-strip { display: flex; justify-content: space-between; align-items: center; background: #f0f7f4; margin: 1rem 1.5rem; padding: .75rem 1rem; border-radius: 10px; font-size: .9rem; color: #0f2a22; }
.balance-strip strong { font-size: 1.05rem; }
.fetch-cta { padding: 2rem 1.5rem; text-align: center; }
.picker { padding: 0 1.5rem 1.5rem; }
.search { width: 100%; padding: .6rem .8rem; border: 1px solid #d8e0dd; border-radius: 8px; margin-bottom: 1rem; box-sizing: border-box; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .6rem; max-height: 260px; overflow-y: auto; padding: 2px; }
.brand-card { display: flex; align-items: center; gap: .6rem; border: 1px solid #e5e9e7; border-radius: 10px; padding: .6rem; cursor: pointer; transition: .15s; }
.brand-card:hover { border-color: #0f5132; }
.brand-card.active { border-color: #0f5132; background: #f0f7f4; }
.brand-card input { accent-color: #0f5132; }
.brand-card img { width: 34px; height: 34px; object-fit: contain; border-radius: 6px; }
.brand-info { display: flex; flex-direction: column; }
.brand-name { font-weight: 600; font-size: .9rem; color: #0f2a22; }
.brand-meta { font-size: .72rem; color: #6b7280; }
.selection { display: flex; gap: 1rem; margin-top: 1.25rem; flex-wrap: wrap; align-items: flex-end; }
.field { display: flex; flex-direction: column; gap: .3rem; }
.field label { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .03em; }
.field select, .field input { padding: .55rem .7rem; border: 1px solid #d8e0dd; border-radius: 8px; }
.field.total strong { font-size: 1.1rem; color: #0f5132; }
.actions { margin-top: 1.25rem; display: flex; flex-direction: column; gap: .5rem; }
.btn-primary { background: #0f5132; color: #fff; border: 0; border-radius: 9px; padding: .7rem 1.1rem; font-weight: 600; cursor: pointer; align-self: flex-start; }
.btn-primary:disabled { opacity: .5; cursor: default; }
.error { color: #dc2626; font-size: .85rem; }
.success { color: #0f5132; font-size: .85rem; }
.warn { color: #d97706; font-size: .85rem; }
</style>