<template>
  <div class="avq-customers">
    <!-- Header -->
    <div class="cust-header">
      <div>
        <h2>Customers</h2>
        <p>B2B clients purchasing vouchers from Avirqo</p>
      </div>
      <button class="avq-btn-primary" @click="openCreate">+ Add Customer</button>
    </div>

    <!-- Filters -->
    <div class="cust-filters">
      <input v-model="search" class="avq-input" placeholder="Search company or GST…" @input="loadList" />
      <select v-model="statusFilter" class="avq-input" @change="loadList">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="on_hold">On Hold</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Table -->
    <div class="cust-table-wrap">
      <div v-if="store.loading" class="cust-empty">Loading…</div>
      <div v-else-if="store.error" class="cust-empty err">{{ store.error }}</div>
      <table v-else class="cust-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Location</th>
            <th>Primary SPOC</th>
            <th>Balance</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.list.length === 0">
            <td colspan="6" style="text-align:center;padding:32px;color:var(--ink-muted)">No customers yet.</td>
          </tr>
          <tr v-for="c in store.list" :key="c.id" @click="openDetail(c.id)" class="cust-row">
            <td class="cust-name">{{ c.company_name }}</td>
            <td>{{ c.location }}</td>
            <td>{{ primarySpoc(c) }}</td>
            <td class="cust-balance">₹{{ Number(c.balance).toLocaleString('en-IN') }}</td>
            <td><span class="cust-badge" :class="`badge-${c.status}`">{{ statusLabel(c.status) }}</span></td>
            <td @click.stop>
              <div class="cust-actions">
                <button class="avq-btn-sm" @click="openEdit(c.id)">Edit</button>
                <select class="avq-input-sm" :value="c.status" @change="changeStatus(c, $event.target.value)">
                  <option value="active">Active</option>
                  <option value="on_hold">On Hold</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="store.pagination" class="cust-pagination">
      <button :disabled="store.pagination.current_page === 1" @click="changePage(store.pagination.current_page - 1)">← Prev</button>
      <span>Page {{ store.pagination.current_page }} of {{ store.pagination.last_page }}</span>
      <button :disabled="store.pagination.current_page === store.pagination.last_page" @click="changePage(store.pagination.current_page + 1)">Next →</button>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="avq-modal-overlay" @click.self="showForm = false">
      <div class="avq-modal">
        <h3>{{ editId ? 'Edit Customer' : 'Add Customer' }}</h3>
        <form @submit.prevent="submitForm">
          <div class="form-grid">
            <div class="form-field">
              <label>Company Name *</label>
              <input v-model="form.company_name" class="avq-input" required />
            </div>
            <div class="form-field">
              <label>Location *</label>
              <input v-model="form.location" class="avq-input" required />
            </div>
            <div class="form-field">
              <label>GST Number</label>
              <input v-model="form.gst_number" class="avq-input" />
            </div>
            <div class="form-field">
              <label>Registration Number</label>
              <input v-model="form.registration_number" class="avq-input" />
            </div>
          </div>

          <div class="spoc-section">
            <div class="spoc-head">
              <h4>SPOCs (Contacts)</h4>
              <button type="button" class="avq-btn-sm" @click="addSpoc">+ Add SPOC</button>
            </div>
            <div v-for="(spoc, i) in form.spocs" :key="i" class="spoc-row">
              <input v-model="spoc.name" class="avq-input" placeholder="Name *" required />
              <input v-model="spoc.email" class="avq-input" type="email" placeholder="Email *" required />
              <input v-model="spoc.phone" class="avq-input" placeholder="Phone" />
              <button v-if="form.spocs.length > 1" type="button" class="avq-btn-sm btn-danger" @click="removeSpoc(i)">✕</button>
            </div>
          </div>

          <p v-if="formError" class="form-error">{{ formError }}</p>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showForm = false">Cancel</button>
            <button type="submit" class="avq-btn-primary" :disabled="formLoading">
              {{ formLoading ? 'Saving…' : (editId ? 'Save changes' : 'Create customer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Detail Drawer -->
    <div v-if="showDetail" class="avq-modal-overlay" @click.self="showDetail = false">
      <div class="avq-drawer">
        <div v-if="store.loading" style="padding:32px">Loading…</div>
        <template v-else-if="store.current">
          <div class="drawer-header">
            <div>
              <h3>{{ store.current.company_name }}</h3>
              <span class="cust-badge" :class="`badge-${store.current.status}`">{{ statusLabel(store.current.status) }}</span>
            </div>
            <button class="avq-btn-ghost" @click="showDetail = false">✕ Close</button>
          </div>

          <!-- Balance -->
          <div class="drawer-section">
            <h4>Balance</h4>
            <div class="balance-display">₹{{ Number(store.current.balance).toLocaleString('en-IN') }}</div>
            <div class="balance-actions">
              <button class="avq-btn-primary" @click="openBalance('credit')">+ Add Balance</button>
              <button class="avq-btn-ghost" @click="openBalance('debit')">− Deduct Balance</button>
            </div>
          </div>

          <!-- SPOCs -->
          <div class="drawer-section">
            <h4>SPOCs</h4>
            <div v-for="spoc in store.current.spocs" :key="spoc.id" class="spoc-card">
              <strong>{{ spoc.name }}</strong>
              <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
              <div class="spoc-detail">{{ spoc.email }}</div>
              <div v-if="spoc.phone" class="spoc-detail">{{ spoc.phone }}</div>
            </div>
          </div>

          <!-- Documents -->
          <div class="drawer-section">
            <div class="section-head">
              <h4>Documents</h4>
              <label class="avq-btn-sm" style="cursor:pointer">
                Upload
                <input type="file" style="display:none" @change="handleUpload" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
              </label>
            </div>
            <div v-if="!store.current.documents?.length" class="cust-empty">No documents uploaded.</div>
            <div v-for="doc in store.current.documents" :key="doc.id" class="doc-row">
              <span class="doc-name">{{ doc.original_name }}</span>
              <span class="doc-size">{{ formatSize(doc.size) }}</span>
              <a :href="downloadUrl(doc)" target="_blank" class="avq-btn-sm">↓ Download</a>
              <button class="avq-btn-sm btn-danger" @click="confirmDeleteDoc(doc)">✕</button>
            </div>
          </div>

          <!-- Balance Logs -->
          <div class="drawer-section">
            <h4>Balance History</h4>
            <div v-if="!store.current.balance_logs?.length" class="cust-empty">No transactions yet.</div>
            <table v-else class="log-table">
              <thead><tr><th>Type</th><th>Amount</th><th>Balance After</th><th>Note</th><th>By</th><th>Date</th></tr></thead>
              <tbody>
                <tr v-for="log in store.current.balance_logs" :key="log.id">
                  <td><span :class="log.type === 'credit' ? 'log-credit' : 'log-debit'">{{ log.type }}</span></td>
                  <td>₹{{ Number(log.amount).toLocaleString('en-IN') }}</td>
                  <td>₹{{ Number(log.balance_after).toLocaleString('en-IN') }}</td>
                  <td>{{ log.note || '—' }}</td>
                  <td>{{ log.done_by?.name || '—' }}</td>
                  <td>{{ new Date(log.created_at).toLocaleDateString('en-IN') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Voucher History -->
          <div class="drawer-section">
            <h4>Voucher History</h4>
            <div v-if="!store.current.voucher_history?.length" class="cust-empty">No vouchers issued yet.</div>
            <table v-else class="log-table">
              <thead><tr><th>Voucher</th><th>Denomination</th><th>Qty</th><th>Total Deducted</th><th>Sent By</th><th>Date</th></tr></thead>
              <tbody>
                <tr v-for="v in store.current.voucher_history" :key="v.id">
                  <td>{{ v.voucher_name }}</td>
                  <td>₹{{ Number(v.denomination).toLocaleString('en-IN') }}</td>
                  <td>{{ v.quantity }}</td>
                  <td>₹{{ Number(v.total_deducted).toLocaleString('en-IN') }}</td>
                  <td>{{ v.sent_by?.name || '—' }}</td>
                  <td>{{ new Date(v.sent_at).toLocaleDateString('en-IN') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <!-- Balance Modal -->
    <div v-if="showBalance" class="avq-modal-overlay" @click.self="showBalance = false">
      <div class="avq-modal avq-modal-sm">
        <h3>{{ balanceType === 'credit' ? 'Add Balance' : 'Deduct Balance' }}</h3>
        <div class="form-field">
          <label>Amount (₹) *</label>
          <input v-model="balanceAmount" class="avq-input" type="number" min="0.01" step="0.01" required />
        </div>
        <div class="form-field">
          <label>Note</label>
          <input v-model="balanceNote" class="avq-input" placeholder="Optional reason" />
        </div>
        <p v-if="balanceError" class="form-error">{{ balanceError }}</p>
        <div class="modal-footer">
          <button class="avq-btn-ghost" @click="showBalance = false">Cancel</button>
          <button class="avq-btn-primary" :disabled="balanceLoading" @click="submitBalance">
            {{ balanceLoading ? 'Saving…' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useCustomerStore } from '../store/customerStore';
import customerApi from '../api/customerApi';

const store = useCustomerStore();
const search = ref('');
const statusFilter = ref('');
const page = ref(1);

const showForm = ref(false);
const editId = ref(null);
const formLoading = ref(false);
const formError = ref('');
const form = reactive({ company_name: '', location: '', gst_number: '', registration_number: '', spocs: [{ name: '', email: '', phone: '' }] });

const showDetail = ref(false);
const showBalance = ref(false);
const balanceType = ref('credit');
const balanceAmount = ref('');
const balanceNote = ref('');
const balanceLoading = ref(false);
const balanceError = ref('');

onMounted(() => loadList());

function loadList() { store.fetchList({ search: search.value, status: statusFilter.value, page: page.value }); }
function changePage(p) { page.value = p; loadList(); }

function primarySpoc(c) { return c.spocs?.find((s) => s.is_primary)?.name || c.spocs?.[0]?.name || '—'; }
function statusLabel(s) { return { active: 'Active', on_hold: 'On Hold', inactive: 'Inactive' }[s] || s; }

function openCreate() {
  editId.value = null;
  Object.assign(form, { company_name: '', location: '', gst_number: '', registration_number: '', spocs: [{ name: '', email: '', phone: '' }] });
  formError.value = '';
  showForm.value = true;
}

async function openEdit(id) {
  await store.fetchOne(id);
  editId.value = id;
  const c = store.current;
  Object.assign(form, {
    company_name: c.company_name, location: c.location,
    gst_number: c.gst_number || '', registration_number: c.registration_number || '',
    spocs: c.spocs?.length ? c.spocs.map((s) => ({ name: s.name, email: s.email, phone: s.phone || '' })) : [{ name: '', email: '', phone: '' }],
  });
  formError.value = '';
  showForm.value = true;
}

function addSpoc() { form.spocs.push({ name: '', email: '', phone: '' }); }
function removeSpoc(i) { form.spocs.splice(i, 1); }

async function submitForm() {
  formLoading.value = true;
  formError.value = '';
  try {
    if (editId.value) {
      await store.update(editId.value, { ...form });
    } else {
      await store.create({ ...form });
    }
    showForm.value = false;
    loadList();
  } catch (e) {
    const errs = e.response?.data?.errors;
    formError.value = errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message || 'Save failed.');
  } finally {
    formLoading.value = false;
  }
}

async function openDetail(id) {
  showDetail.value = true;
  await store.fetchOne(id);
}

async function changeStatus(customer, status) {
  await store.setStatus(customer.id, status);
}

function openBalance(type) {
  balanceType.value = type;
  balanceAmount.value = '';
  balanceNote.value = '';
  balanceError.value = '';
  showBalance.value = true;
}

async function submitBalance() {
  balanceLoading.value = true;
  balanceError.value = '';
  try {
    await store.adjustBalance(store.current.id, {
      type: balanceType.value,
      amount: parseFloat(balanceAmount.value),
      note: balanceNote.value,
    });
    showBalance.value = false;
  } catch (e) {
    balanceError.value = e.response?.data?.message || 'Failed.';
  } finally {
    balanceLoading.value = false;
  }
}

async function handleUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  await store.uploadDocument(store.current.id, file);
  e.target.value = '';
}

async function confirmDeleteDoc(doc) {
  if (!confirm(`Delete "${doc.original_name}"?`)) return;
  await store.deleteDocument(store.current.id, doc.id);
}

function downloadUrl(doc) { return customerApi.downloadUrl(store.current.id, doc.id); }
function formatSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1048576).toFixed(1)} MB`;
}
</script>

<style>
.avq-customers { padding: 28px; max-width: 1200px; }
.cust-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.cust-header h2 { font-family: var(--fd); font-size: 24px; font-weight: 600; }
.cust-header p { color: var(--ink-muted); font-size: 14px; }
.cust-filters { display: flex; gap: 12px; margin-bottom: 20px; }
.avq-input { padding: 10px 14px; border: 1.5px solid var(--border-2); border-radius: 8px; font-size: 14px; outline: none; font-family: var(--fb); background: #fff; color: var(--ink); }
.avq-input:focus { border-color: var(--teal-mid); }
.avq-input-sm { padding: 6px 10px; border: 1px solid var(--border-2); border-radius: 6px; font-size: 12px; font-family: var(--fb); background: #fff; }
.avq-btn-primary { background: var(--teal-deep); color: #fff; border: none; border-radius: 8px; padding: 10px 18px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: var(--fb); }
.avq-btn-primary:hover { background: var(--teal-mid); }
.avq-btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
.avq-btn-ghost { background: transparent; color: var(--ink-soft); border: 1.5px solid var(--border-2); border-radius: 8px; padding: 10px 18px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: var(--fb); }
.avq-btn-ghost:hover { background: var(--surface-2); }
.avq-btn-sm { background: var(--surface-2); color: var(--ink-soft); border: 1px solid var(--border-2); border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: var(--fb); }
.avq-btn-sm:hover { background: var(--teal-pale); }
.btn-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.cust-table-wrap { background: #fff; border: 1px solid var(--border-2); border-radius: 14px; overflow: hidden; }
.cust-table { width: 100%; border-collapse: collapse; }
.cust-table thead tr { background: var(--surface-2); }
.cust-table th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); font-weight: 600; }
.cust-table td { padding: 14px 16px; border-top: 1px solid var(--border-2); font-size: 14px; }
.cust-row { cursor: pointer; transition: background 0.1s; }
.cust-row:hover td { background: var(--teal-pale); }
.cust-name { font-weight: 600; color: var(--ink); }
.cust-balance { font-family: var(--fd); font-weight: 600; }
.cust-actions { display: flex; gap: 8px; align-items: center; }
.cust-badge { display: inline-block; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; }
.badge-active { background: #E8F7F2; color: var(--teal-deep); }
.badge-on_hold { background: #FEF3E2; color: #B45309; }
.badge-inactive { background: #F3F4F6; color: #6B7280; }
.cust-pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 20px; font-size: 14px; color: var(--ink-muted); }
.cust-empty { padding: 24px; text-align: center; color: var(--ink-muted); font-size: 14px; }
.err { color: #b91c1c; }

.avq-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 100; display: flex; align-items: center; justify-content: center; }
.avq-modal { background: #fff; border-radius: 16px; padding: 32px; width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; }
.avq-modal-sm { max-width: 380px; }
.avq-modal h3 { font-family: var(--fd); font-size: 20px; margin-bottom: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-field label { font-size: 12px; font-weight: 600; color: var(--ink-soft); }
.form-error { color: #b91c1c; background: #fef2f2; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin: 12px 0 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
.spoc-section { margin-bottom: 16px; }
.spoc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.spoc-head h4 { font-size: 14px; font-weight: 700; }
.spoc-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 8px; margin-bottom: 8px; align-items: center; }

.avq-drawer { background: #fff; width: 100%; max-width: 680px; height: 100vh; overflow-y: auto; margin-left: auto; padding: 32px; }
.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.drawer-header h3 { font-family: var(--fd); font-size: 22px; margin-bottom: 8px; }
.drawer-section { margin-bottom: 28px; border-top: 1px solid var(--border-2); padding-top: 20px; }
.drawer-section h4 { font-size: 14px; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink-muted); }
.section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-head h4 { margin-bottom: 0; }
.balance-display { font-family: var(--fd); font-size: 32px; font-weight: 600; color: var(--teal-deep); margin-bottom: 12px; }
.balance-actions { display: flex; gap: 12px; }
.spoc-card { border: 1px solid var(--border-2); border-radius: 8px; padding: 12px; margin-bottom: 8px; }
.spoc-primary { background: var(--teal-pale); color: var(--teal-deep); font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 100px; margin-left: 8px; }
.spoc-detail { font-size: 13px; color: var(--ink-muted); margin-top: 4px; }
.doc-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-2); }
.doc-name { flex: 1; font-size: 13px; }
.doc-size { font-size: 12px; color: var(--ink-muted); }
.log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.log-table th { text-align: left; padding: 8px 10px; background: var(--surface-2); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink-muted); }
.log-table td { padding: 10px; border-bottom: 1px solid var(--border-2); }
.log-credit { color: var(--teal-deep); font-weight: 700; text-transform: uppercase; font-size: 11px; }
.log-debit { color: #b91c1c; font-weight: 700; text-transform: uppercase; font-size: 11px; }
</style>
