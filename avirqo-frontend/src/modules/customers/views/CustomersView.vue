<template>
  <AppLayout>
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

    <!-- Company Details Modal (Form 1) -->
    <div v-if="showCompanyForm" class="avq-modal-overlay" @click.self="showCompanyForm = false">
      <div class="avq-modal avq-modal-sm">
        <h3>{{ editId ? 'Edit Company Details' : 'Add New Customer' }}</h3>
        <form @submit.prevent="submitCompanyForm">
          <div class="form-grid">
            <div class="form-field">
              <label>Company Name *</label>
              <input v-model="companyForm.company_name" class="avq-input" required />
            </div>
            <div class="form-field">
              <label>Location *</label>
              <input v-model="companyForm.location" class="avq-input" required />
            </div>
            <div class="form-field">
              <label>GST Number</label>
              <input v-model="companyForm.gst_number" class="avq-input" />
            </div>
            <div class="form-field">
              <label>Registration Number</label>
              <input v-model="companyForm.registration_number" class="avq-input" />
            </div>
            <div class="form-field">
              <label>Voucher Campaign</label>
              <select v-model="companyForm.voucher_campaign_id" class="avq-input">
                <option value="">-- Select Campaign --</option>
                <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                  {{ campaign.name }}
                </option>
              </select>
            </div>
          </div>

          <p v-if="formError" class="form-error">{{ formError }}</p>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showCompanyForm = false">Cancel</button>
            <button type="submit" class="avq-btn-primary" :disabled="formLoading">
              {{ formLoading ? 'Saving…' : (editId ? 'Save Details' : 'Create Customer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- SPOC Management Modal (Form 2) -->
    <div v-if="showSpocForm" class="avq-modal-overlay" @click.self="showSpocForm = false">
      <div class="avq-modal">
        <h3>Manage SPOCs - {{ store.current?.company_name }}</h3>
        
        <div class="spoc-section">
          <!-- EXISTING SPOCs -->
          <div v-if="existingSpocs.length" class="spoc-subsection">
            <h5 class="spoc-subtitle">Existing SPOCs</h5>
            <div v-for="(spoc, idx) in existingSpocs" :key="spoc.id" class="spoc-row existing-spoc">
              <input v-model="spoc.name" class="avq-input" placeholder="Name *" required />
              <input v-model="spoc.email" class="avq-input" type="email" placeholder="Email *" required />
              <input v-model="spoc.phone" class="avq-input" placeholder="Phone" />
              
              <select v-model="spoc.status" class="avq-input" style="width:140px">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
              
              <div class="spoc-primary-cell" style="display:flex; align-items:center; gap:8px; min-width:120px">
                <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
                <button 
                  v-else 
                  type="button" 
                  class="avq-btn-sm" 
                  style="padding:4px 10px; font-size:11px"
                  @click="makePrimary(spoc)"
                  :disabled="spoc.status !== 'active'"
                >Set Primary</button>
              </div>
              
              <div class="spoc-actions" style="display:flex; gap:6px; align-items:center">
                <button 
                  type="button" 
                  class="avq-btn-sm btn-danger" 
                  @click="removeExistingSpoc(spoc)"
                  :disabled="spoc.is_primary"
                  :title="spoc.is_primary ? 'Primary SPOC cannot be removed. Set another as primary first.' : ''"
                >✕</button>
              </div>
            </div>
          </div>

          <!-- NEW SPOCs -->
          <div class="spoc-subsection" v-if="newSpocs.length">
            <h5 class="spoc-subtitle">New SPOCs</h5>
            <div v-for="(spoc, i) in newSpocs" :key="i" class="spoc-row new-spoc">
              <input v-model="spoc.name" class="avq-input" placeholder="Name *" required />
              <input v-model="spoc.email" class="avq-input" type="email" placeholder="Email *" required />
              <input v-model="spoc.phone" class="avq-input" placeholder="Phone" />
              
              <!-- Primary for new SPOCs -->
              <div class="spoc-primary-cell" style="display:flex; align-items:center; gap:8px; min-width:120px">
                <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
                <button 
                  v-else 
                  type="button" 
                  class="avq-btn-sm" 
                  style="padding:4px 10px; font-size:11px"
                  @click="makePrimary(spoc)"
                >Set Primary</button>
              </div>
              
              <button type="button" class="avq-btn-sm btn-danger" @click="removeNewSpoc(i)">✕</button>
            </div>
          </div>

          <div class="spoc-add-row" style="margin-top:8px">
            <button type="button" class="avq-btn-sm" @click="addSpoc" style="width:100%; justify-content:center">
              + Add New SPOC
            </button>
          </div>
        </div>

        <p v-if="formError" class="form-error">{{ formError }}</p>
        <div class="modal-footer">
          <button type="button" class="avq-btn-ghost" @click="showSpocForm = false">Close</button>
          <button type="button" class="avq-btn-primary" :disabled="formLoading" @click="saveSpocs">
            {{ formLoading ? 'Saving…' : 'Save SPOCs' }}
          </button>
        </div>
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

          <!-- Company Details -->
          <div class="drawer-section">
            <h4>Company Details</h4>
            <div class="detail-grid">
              <div class="detail-item"><span class="detail-label">Company Name</span><span class="detail-value">{{ store.current.company_name }}</span></div>
              <div class="detail-item"><span class="detail-label">Location</span><span class="detail-value">{{ store.current.location }}</span></div>
              <div class="detail-item"><span class="detail-label">GST Number</span><span class="detail-value">{{ store.current.gst_number || '—' }}</span></div>
              <div class="detail-item"><span class="detail-label">Registration No.</span><span class="detail-value">{{ store.current.registration_number || '—' }}</span></div>
              <div class="detail-item"><span class="detail-label">Customer Since</span><span class="detail-value">{{ new Date(store.current.created_at).toLocaleDateString('en-IN') }}</span></div>
              <div class="detail-item">
                <span class="detail-label">Voucher Campaign</span>
                <span class="detail-value">
                  <template v-if="store.current.voucher_campaigns?.length">
                    <span class="campaign-badge">{{ store.current.voucher_campaigns[0].name }}</span>
                  </template>
                  <template v-else>—</template>
                </span>
              </div>
            </div>
            <div style="margin-top:12px; display:flex; gap:8px;">
              <button class="avq-btn-sm" @click="showDetail=false; openEdit(store.current.id)">✏️ Edit Company</button>
              <button class="avq-btn-sm" @click="showDetail=false; editId = store.current.id; spocForm.spocs = store.current.spocs || []; showSpocForm = true;">👥 Manage SPOCs</button>
            </div>
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

          <!-- SPOCs (Only Active) -->
          <div class="drawer-section">
            <h4>SPOCs (Active Only)</h4>
            <div v-if="activeSpocs.length === 0" class="cust-empty">No active SPOCs.</div>
            <div v-for="spoc in activeSpocs" :key="spoc.id" class="spoc-card">
              <strong>{{ spoc.name }}</strong>
              <span v-if="spoc.is_primary" class="spoc-primary">Primary</span>
              <div class="spoc-detail">{{ spoc.email }}</div>
              <div v-if="spoc.phone" class="spoc-detail">{{ spoc.phone }}</div>
              <span class="spoc-status-badge" :class="`badge-${spoc.status}`">{{ spoc.status }}</span>
            </div>
          </div>

          <!-- Documents -->
          <div class="drawer-section">
            <div class="section-head">
              <h4>Documents</h4>
              <label class="avq-btn-sm" style="cursor:pointer">
                📎 Attach file
                <input type="file" style="display:none" @change="handleFileSelect" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
              </label>
            </div>

            <!-- Pending file confirmation -->
            <div v-if="pendingFile" class="doc-pending">
              <span class="doc-name">{{ pendingFile.name }}</span>
              <span class="doc-size">{{ formatSize(pendingFile.size) }}</span>
              <button class="avq-btn-primary avq-btn-sm-p" :disabled="uploadLoading" @click="confirmUpload">
                {{ uploadLoading ? 'Uploading…' : '↑ Upload' }}
              </button>
              <button class="avq-btn-sm btn-danger" @click="cancelUpload">✕ Cancel</button>
            </div>

            <div v-if="!store.current.documents?.length && !pendingFile" class="cust-empty">No documents uploaded.</div>
            <div v-for="doc in store.current.documents" :key="doc.id" class="doc-row">
              <span class="doc-name">{{ doc.original_name }}</span>
              <span class="doc-size">{{ formatSize(doc.size) }}</span>
              <button class="avq-btn-sm" @click="downloadDoc(doc)">↓ Download</button>
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

    <AppDialogModal
      :open="alertDialogOpen"
      :title="alertDialogTitle"
      :message="alertDialogMessage"
      confirm-text="OK"
      :show-cancel="false"
      @cancel="alertDialogOpen = false"
      @confirm="alertDialogOpen = false"
    />

    <AppDialogModal
      :open="deleteDocDialogOpen"
      title="Delete document"
      :message="deleteDocTarget ? `Delete ${deleteDocTarget.original_name}? This cannot be undone.` : ''"
      confirm-text="Delete"
      cancel-text="Keep file"
      variant="danger"
      :loading="deleteDocLoading"
      @cancel="deleteDocDialogOpen = false"
      @confirm="confirmDeleteDocNow"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useCustomerStore } from '../store/customerStore';
import customerApi from '../api/customerApi';
import campaignApi from '../../campaigns/api/campaignApi';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';

const store = useCustomerStore();
const search = ref('');
const statusFilter = ref('');
const page = ref(1);

const showCompanyForm = ref(false);
const showSpocForm = ref(false);
const editId = ref(null);
const formLoading = ref(false);
const formError = ref('');

// Available campaigns for dropdown
const campaigns = ref([]);

// Company Details Form (Form 1)
const companyForm = reactive({ 
  company_name: '', 
  location: '', 
  gst_number: '', 
  registration_number: '',
  voucher_campaign_id: '' 
});

// SPOC Form (Form 2)
const spocForm = reactive({ 
  spocs: [] 
});

// Computed: Separate existing SPOCs (with ID) from new SPOCs (without ID)
// Only show ACTIVE SPOCs in the edit form
const existingSpocs = computed(() => 
  spocForm.spocs.filter(s => s.id && s.status !== 'inactive')
);
const newSpocs = computed(() => spocForm.spocs.filter(s => !s.id));

const showDetail = ref(false);
const showBalance = ref(false);
const balanceType = ref('credit');
const balanceAmount = ref('');
const balanceNote = ref('');
const balanceLoading = ref(false);
const balanceError = ref('');
const alertDialogOpen = ref(false);
const alertDialogTitle = ref('Notice');
const alertDialogMessage = ref('');
const deleteDocDialogOpen = ref(false);
const deleteDocTarget = ref(null);
const deleteDocLoading = ref(false);

// Detail view: Only active SPOCs
const activeSpocs = computed(() => 
  store.current?.spocs?.filter(s => s.status === 'active') || []
);

onMounted(() => { loadList(); loadCampaigns(); });

function loadList() { store.fetchList({ search: search.value, status: statusFilter.value, page: page.value }); }

async function loadCampaigns() {
  try {
    const { data } = await campaignApi.list();
    campaigns.value = data.data || data;
  } catch (e) {
    console.error('Failed to load campaigns:', e);
  }
}
function changePage(p) { page.value = p; loadList(); }

function primarySpoc(c) { return c.spocs?.find((s) => s.is_primary)?.name || c.spocs?.[0]?.name || '—'; }
function statusLabel(s) { return { active: 'Active', on_hold: 'On Hold', inactive: 'Inactive' }[s] || s; }

function openCreate() {
  editId.value = null;
  
  // Ensure campaigns are loaded before opening form
  if (campaigns.value.length === 0) {
    loadCampaigns();
  }
  
  Object.assign(companyForm, { 
    company_name: '', 
    location: '', 
    gst_number: '', 
    registration_number: '',
    voucher_campaign_id: '' 
  });
  formError.value = '';
  showCompanyForm.value = true;
}

async function openEdit(id) {
  await store.fetchOne(id);
  editId.value = id;
  const c = store.current;
  
  // Ensure campaigns are loaded before setting form values
  if (campaigns.value.length === 0) {
    await loadCampaigns();
  }
  
  // Load company details
  Object.assign(companyForm, {
    company_name: c.company_name, 
    location: c.location,
    gst_number: c.gst_number || '', 
    registration_number: c.registration_number || '',
    voucher_campaign_id: c.voucher_campaigns?.[0]?.id || '',
  });
  
  // Load SPOCs into separate form
  spocForm.spocs = c.spocs?.length ? c.spocs.map((s) => ({ 
    id: s.id,
    name: s.name, 
    email: s.email, 
    phone: s.phone || '',
    status: s.status || 'active',
    is_primary: s.is_primary || false
  })) : [];
  
  formError.value = '';
  showCompanyForm.value = true;
}

function addSpoc() { 
  spocForm.spocs.push({ name: '', email: '', phone: '' }); 
}

function removeNewSpoc(i) {
  const idx = spocForm.spocs.findIndex(s => !s.id);
  if (idx >= 0) spocForm.spocs.splice(idx, 1);
}

function removeExistingSpoc(spoc) {
  if (spoc.is_primary) {
    formError.value = 'Primary SPOC cannot be removed. Set another as primary first.';
    return;
  }
  spocForm.spocs = spocForm.spocs.filter(s => s.id !== spoc.id);
}

function makePrimary(spoc) {
  if (spoc.status !== 'active') {
    formError.value = 'Only active SPOCs can be set as primary.';
    return;
  }
  // Remove primary from all others
  spocForm.spocs.forEach(s => { s.is_primary = false; });
  // Set this as primary
  spoc.is_primary = true;
}

// Helper: Ensure exactly one primary when saving
function ensureSinglePrimary() {
  const activeSpocs = spocForm.spocs.filter(s => s.status === 'active' || !s.status);
  const primaryCount = activeSpocs.filter(s => s.is_primary).length;
  
  if (primaryCount === 0 && activeSpocs.length > 0) {
    // Make first active one primary
    activeSpocs[0].is_primary = true;
  } else if (primaryCount > 1) {
    // Keep only the last one as primary
    let foundPrimary = false;
    spocForm.spocs.forEach(s => {
      if (s.is_primary && s.status !== 'inactive') {
        if (foundPrimary) s.is_primary = false;
        else foundPrimary = true;
      }
    });
  }
}

// Submit Company Details (Form 1)
async function submitCompanyForm() {
  formLoading.value = true;
  formError.value = '';

  try {
    const payload = {
      company_name: companyForm.company_name,
      location: companyForm.location,
      gst_number: companyForm.gst_number || null,
      registration_number: companyForm.registration_number || null,
      voucher_campaign_id: companyForm.voucher_campaign_id || null,
    };

    if (editId.value) {
      await store.update(editId.value, payload);
      showCompanyForm.value = false;
      // Open SPOC form after saving company details
      showSpocForm.value = true;
    } else {
      const newCustomer = await store.create(payload);
      editId.value = newCustomer.id;
      showCompanyForm.value = false;
      // Open SPOC form for new customer
      spocForm.spocs = [];
      showSpocForm.value = true;
    }
    loadList();
  } catch (e) {
    const message = e.response?.data?.message || 'Save failed.';
    formError.value = message;
    openAlertDialog(message, 'Save failed');
  } finally {
    formLoading.value = false;
  }
}

// Save SPOCs (Form 2)
async function saveSpocs() {
  formLoading.value = true;
  formError.value = '';
  
  // Validation: At least one active SPOC required
  const activeCount = spocForm.spocs.filter(s => (s.status || 'active') === 'active').length;
  if (activeCount === 0) {
    formError.value = 'At least one active SPOC is required.';
    formLoading.value = false;
    return;
  }
  
  // Ensure single primary
  ensureSinglePrimary();

  // Auto-assign primary if none exists (for new customers or customers with 0 SPOCs)
  const activeSpocs = spocForm.spocs.filter(s => (s.status || 'active') === 'active');
  const hasPrimary = activeSpocs.some(s => s.is_primary);

  if (!hasPrimary && activeSpocs.length > 0) {
    // Make the first active SPOC as primary by default
    activeSpocs[0].is_primary = true;
  }

  // Final check after auto-assign
  const finalHasPrimary = spocForm.spocs.some(s => s.is_primary && (s.status || 'active') === 'active');
  if (!finalHasPrimary && activeSpocs.length > 0) {
    formError.value = 'An active primary SPOC is required.';
    formLoading.value = false;
    return;
  }

  try {
    const payload = {
      spocs: spocForm.spocs.map(s => ({
        id: s.id || undefined,
        name: s.name,
        email: s.email,
        phone: s.phone || null,
        status: s.status || 'active',
        is_primary: s.is_primary || false
      }))
    };

    await store.update(editId.value, payload);
    showSpocForm.value = false;
    loadList();
  } catch (e) {
    const message = e.response?.data?.message || 'Save failed.';
    formError.value = message;
    openAlertDialog(message, 'Save failed');
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

function openAlertDialog(message, title = 'Notice') {
  alertDialogTitle.value = title;
  alertDialogMessage.value = message;
  alertDialogOpen.value = true;
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

const pendingFile = ref(null);
const uploadLoading = ref(false);

function handleFileSelect(e) {
  const file = e.target.files[0];
  if (!file) return;
  pendingFile.value = file;
  e.target.value = '';
}

function cancelUpload() { pendingFile.value = null; }

async function confirmUpload() {
  if (!pendingFile.value) return;
  uploadLoading.value = true;
  try {
    await store.uploadDocument(store.current.id, pendingFile.value);
    pendingFile.value = null;
  } catch (e) {
    openAlertDialog(e.response?.data?.message || 'Upload failed.', 'Upload failed');
  } finally {
    uploadLoading.value = false;
  }
}

async function confirmDeleteDoc(doc) {
  deleteDocTarget.value = doc;
  deleteDocDialogOpen.value = true;
}

async function confirmDeleteDocNow() {
  if (!deleteDocTarget.value) return;
  deleteDocLoading.value = true;
  try {
    await store.deleteDocument(store.current.id, deleteDocTarget.value.id);
    deleteDocDialogOpen.value = false;
  } catch (e) {
    openAlertDialog(e.response?.data?.message || 'Failed to delete the file.', 'Delete failed');
  } finally {
    deleteDocLoading.value = false;
  }
}

async function downloadDoc(doc) {
  try {
    const token = localStorage.getItem('avirqo_access_token');
    const url = customerApi.downloadUrl(store.current.id, doc.id);
    const response = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
    if (!response.ok) throw new Error('Download failed');
    const blob = await response.blob();
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = doc.original_name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  } catch (e) {
    openAlertDialog('Could not download the file. Please try again.', 'Download failed');
  }
}

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
.avq-btn-sm:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.btn-danger:hover { background: #fee2e2; }

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

.avq-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 16px; }
.avq-modal { background: #fff; border-radius: 16px; padding: 36px; width: 100%; max-width: 900px; max-height: 90vh; overflow-y: auto; overflow-x: hidden; box-sizing: border-box; }
.avq-modal-sm { max-width: 420px; }
.avq-modal h3 { font-family: var(--fd); font-size: 22px; margin-bottom: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.form-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.form-field label { font-size: 12px; font-weight: 600; color: var(--ink-soft); }
.form-field .avq-input { width: 100%; box-sizing: border-box; }
.form-error { color: #b91c1c; background: #fef2f2; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin: 12px 0 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
.spoc-section { margin-bottom: 16px; }
.spoc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.spoc-head h4 { font-size: 14px; font-weight: 700; }
.spoc-row { display: grid; grid-template-columns: 1fr 1fr 1fr 140px 120px auto; gap: 8px; margin-bottom: 8px; align-items: center; min-width: 0; }
.spoc-row .avq-input { width: 100%; box-sizing: border-box; min-width: 0; }
.spoc-row select.avq-input { width: 100%; }
.spoc-subsection { margin-bottom: 16px; }
.spoc-subtitle { font-size: 13px; font-weight: 600; color: var(--ink-soft); margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border-2); }
.existing-spoc { background: var(--surface-2); }
.new-spoc { background: #f0fdf4; border-left: 3px solid var(--teal-mid); }
.spoc-primary-cell { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.spoc-actions { display: flex; gap: 6px; align-items: center; }
.spoc-add-row { padding-top: 8px; border-top: 1px dashed var(--border-2); }
.doc-upload-note { font-size: 12px; color: var(--ink-muted); background: var(--surface-2); border-radius: 8px; padding: 10px 14px; margin-top: 8px; border: 1px dashed var(--border-2); }

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
.spoc-status-badge { display: inline-block; margin-top: 6px; padding: 2px 8px; border-radius: 100px; font-size: 10px; font-weight: 700; }
.doc-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-2); }
.doc-pending { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--teal-pale); border: 1px solid var(--border-2); border-radius: 8px; margin-bottom: 10px; }
.avq-btn-sm-p { padding: 6px 12px; font-size: 12px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-family: var(--fb); background: var(--teal-deep); color: #fff; }
.avq-btn-sm-p:disabled { opacity: 0.55; cursor: not-allowed; }
.doc-name { flex: 1; font-size: 13px; }
.doc-size { font-size: 12px; color: var(--ink-muted); }
.log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.log-table th { text-align: left; padding: 8px 10px; background: var(--surface-2); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink-muted); }
.log-table td { padding: 10px; border-bottom: 1px solid var(--border-2); }
.log-credit { color: var(--teal-deep); font-weight: 700; text-transform: uppercase; font-size: 11px; }
.log-debit { color: #b91c1c; font-weight: 700; text-transform: uppercase; font-size: 11px; }
</style>

<style>
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.detail-item { display: flex; flex-direction: column; gap: 4px; }
.detail-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted, #6B6A67); }
.detail-value { font-size: 14px; color: var(--ink, #0D0D0C); font-weight: 500; }
.campaign-badge { background: var(--teal-pale); color: var(--teal-deep); padding: 3px 10px; border-radius: 100px; font-size: 12px; font-weight: 600; }
</style>
