<template>
  <AppLayout>
    <main class="page">
      <header class="page-header">
        <div>
          <h1>Voucher Campaigns</h1>
          <p>Configure global margins once, then campaign product adjustments and customer assignment.</p>
        </div>
        <button class="avq-btn-primary" @click="create">+ New campaign</button>
      </header>

      <div class="layout">
        <aside class="card sidebar">
        <button type="button" class="campaign" :class="{ active: tab === 'margins' }" @click="openMargins">
            <strong>Global product margins</strong>
            <small>Shared Avirqo pricing</small>
          </button>

          <button
            v-for="campaign in campaigns"
            :key="campaign.id"
            type="button"
            class="campaign"
            :class="{ active: selected?.id === campaign.id }"
            @click="open(campaign)"
          >
            <strong>{{ campaign.name }}</strong>
            <small>{{ campaign.customers_count }} customers · {{ campaign.is_active ? 'Active' : 'Disabled' }}</small>
          </button>
        </aside>

        <section class="card" v-if="tab === 'margins'">
          <div class="sticky">
            <div>
              <h2>Global margin products</h2>
              <p>Margin is applied before campaign or invoice adjustments.</p>
            </div>
            <button class="avq-btn-primary" @click="saveMargins" :disabled="savingMargins">
              {{ pendingApproval ? 'Re-save & resend OTP' : savingMargins ? 'Saving…' : 'Save global margins' }}
            </button>
          </div>

          <div v-if="message && messagePlacement === 'banner'" class="global-margin-banner">
            <strong>✅ {{ message }}</strong>
          </div>

          <p v-if="!pendingApproval" class="section-note">Use decimal values if needed. Negative margins are allowed.</p>

          <div class="rules-toolbar">
            <input
              v-model="search"
              class="avq-input"
              placeholder="Search products…"
            />
            <select v-model="marginFilter" class="avq-input">
              <option value="all">All products</option>
              <option value="margined">Products with margin</option>
              <option value="negative">Products with service charge</option>
              <option value="zero">Zero Margin Products</option>
              <option value="blacklisted">Blacklisted products</option>
            </select>
          </div>

          <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Brand</th>
                <th>Product</th>
                <th>Margin (%)</th>
                <th>
                  <label class="header-check">
                    <input
                      type="checkbox"
                      :checked="filteredMarginProducts.length > 0 && filteredMarginProducts.every((product) => product.is_blacklisted)"
                      @change="toggleAllMarginBlacklist($event.target.checked)"
                    />
                    Blacklist
                  </label>
                </th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in filteredMarginProducts" :key="product.id" :class="{ 'row-dirty': marginProductChanged(product) }">
                <td>
                  <strong>{{ product.brand || '—' }}</strong>
                </td>
                <td>
                  <strong>{{ product.name }}</strong>
                </td>
                <td>
                  <input
                    v-model.number="product.global_margin_percentage"
                    class="avq-input number"
                    type="number"
                    min="-100"
                    max="100"
                    step=".01"
                    :disabled="pendingApproval"
                    @focus="$event.target.select()"
                  />
                </td>
                <td>
                  <input v-model="product.is_blacklisted" type="checkbox" />
                </td>
                <td>
                  <span class="margin-pill" :class="marginPillClass(product)">
                    {{ marginStatusLabel(product) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </section>

        <section class="card" v-else-if="selected">
          <div class="sticky">
            <div>
              <h2>{{ selected.name }}</h2>
              <div class="campaign-actions">
                <button type="button" class="avq-btn-sm" @click="rename">Rename</button>
                <button type="button" class="avq-btn-sm" @click="toggleStatus">{{ selected.is_active ? 'Disable' : 'Enable' }}</button>
              </div>
            </div>
            <button
              type="button"
              v-if="tab === 'rules'"
              class="avq-btn-primary"
              @click="saveRules"
              :disabled="savingRules || !campaignRulesDirty"
            >
              {{ savingRules ? 'Saving…' : campaignRulesDirty ? 'Save Product Discount' : 'No changes to save' }}
            </button>
            <button type="button" v-else-if="tab === 'customers'" class="avq-btn-primary" @click="saveCustomers">Update customer</button>
          </div>

          <div class="tabs">
            <button type="button" @click="tab = 'rules'" :class="{ on: tab === 'rules' }">Product rules</button>
            <button type="button" @click="tab = 'customers'" :class="{ on: tab === 'customers' }">Customers ({{ assigned.size }})</button>
          </div>

          <template v-if="tab === 'rules'">
            <div class="rules-toolbar">
              <input v-model="search" class="avq-input" placeholder="Search products…" />
            <select v-model="productFilter" class="avq-input">
              <option value="all">All products</option>
              <option value="blacklisted">Blacklisted products</option>
              <option value="discounted">Discounted Products</option>
              <option value="service-charge">Products with service charge</option>
              <option value="no-discount">No Discount</option>
            </select>
          </div>
            <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Brand</th>
                  <th>Product</th>
                  <th>Global margin</th>
                  <th>Campaign adjustment (%)</th>
                  <th>Blacklist</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in filteredProducts" :key="product.id" :class="{ 'row-dirty': campaignProductChanged(product) }">
                  <td>
                    <strong>{{ product.brand || '—' }}</strong>
                  </td>
                  <td>
                    <strong>{{ product.name }}</strong>
                  </td>
                  <td>{{ formatMargin(product.global_margin_percentage) }}%</td>
                  <td>
                    <input
                      v-model.number="product.discount_percentage"
                      class="avq-input number"
                      type="number"
                      min="-100"
                      max="100"
                      step=".01"
                      @focus="$event.target.select()"
                    />
                  </td>
                  <td><input v-model="product.is_blacklisted" type="checkbox" /></td>
                </tr>
              </tbody>
            </table>
            </div>
          </template>

          <template v-else>
            <div class="customer-toolbar">
              <input v-model="customerSearch" class="avq-input" placeholder="Search customers…" />
              <div class="customer-summary">{{ assigned.size }} selected</div>
            </div>

            <div class="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th style="width:90px">Assign</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="customer in filteredCustomers" :key="customer.id">
                    <td>
                      <input
                        type="checkbox"
                        :checked="assigned.has(customer.id)"
                        @change="toggle(customer.id, $event.target.checked)"
                      />
                    </td>
                    <td>
                      <strong>{{ customer.company_name }}</strong>
                    </td>
                    <td>{{ customer.location || '—' }}</td>
                    <td>
                      <span class="customer-pill" :class="assigned.has(customer.id) ? 'customer-pill-on' : 'customer-pill-off'">
                        {{ assigned.has(customer.id) ? 'Mapped' : 'Not mapped' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </section>

        <section v-else class="card empty">Create or select a campaign.</section>
      </div>

      <AppDialogModal
        :open="showCampaignModal"
        :title="campaignMode === 'create' ? 'Create campaign' : 'Rename campaign'"
        :message="campaignMode === 'create' ? 'Give the new campaign a name.' : 'Update the campaign name.'"
        :show-input="true"
        input-label="Campaign name"
        input-placeholder="Campaign name"
        :input-value="campaignName"
        confirm-text="Save"
        cancel-text="Cancel"
        :loading="campaignLoading"
        :confirm-disabled="!campaignName.trim()"
        @cancel="showCampaignModal = false"
        @confirm="saveCampaignName"
        @update:inputValue="campaignName = $event"
      >
        <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border-2);">
          <label class="campaign-otp-toggle">
            <input v-model="campaignOtpRequired" type="checkbox" />
            <span>
              <strong>Required OTP confirmation</strong>
              <small>When enabled, campaign changes must be approved with OTP.</small>
            </span>
          </label>
        </div>
        <p v-if="campaignError" class="error" style="margin-top:16px">{{ campaignError }}</p>
      </AppDialogModal>

      <AppOtpModal
        :open="!!campaignPendingApproval"
        title="OTP Sent Successfully"
        :message="campaignOtpMessage"
        v-model:otp="campaignOtpCode"
        :error="campaignOtpError"
        :loading="campaignVerifyingOtp"
        loading-text="Verifying…"
        confirm-text="Verify OTP & Save"
        :confirm-disabled="campaignOtpCode.length !== 6"
        :show-cancel="false"
        :resend-loading="campaignResendingOtp"
        resend-text="Resend OTP"
        resend-loading-text="Resending…"
        @confirm="verifyCampaignOtp"
        @resend="resendCampaignOtp"
      />

      <AppOtpModal
        :open="!!pendingApproval"
        title="OTP Sent Successfully"
        message="Enter the OTP sent to naveentitare52@gmail.com and ptitare@gmail.com to confirm. OTP valid for 10 minutes."
        v-model:otp="otp"
        :error="error"
        :loading="verifyingOtp"
        loading-text="Verifying…"
        confirm-text="Verify OTP & Save"
        :confirm-disabled="otp.length !== 6"
        :show-cancel="false"
        :resend-loading="resendingOtp"
        resend-text="Resend OTP"
        resend-loading-text="Resending…"
        @confirm="verifyMarginsOtp"
        @resend="resendMarginsOtp"
      />

      <AppToast :open="!!message && messagePlacement === 'toast'" :message="message" @close="clearMessage" />
      <p v-if="error" class="error">{{ error }}</p>
    </main>
  </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';
import AppOtpModal from '../../shared/components/AppOtpModal.vue';
import AppToast from '../../shared/components/AppToast.vue';
import api from '../api/campaignApi';

const campaigns = ref([]);
const selected = ref(null);
const products = ref([]);
const margins = ref([]);
const customers = ref([]);
const assigned = ref(new Set());
const tab = ref('rules');
const search = ref('');
const productFilter = ref('all');
const marginFilter = ref('all');
const customerSearch = ref('');
const message = ref('');
const messagePlacement = ref('toast');
const error = ref('');
const savingMargins = ref(false);
const savingRules = ref(false);
const verifyingOtp = ref(false);
const resendingOtp = ref(false);
const pendingApproval = ref(null);
const otp = ref('');
const campaignPendingApproval = ref(null);
const campaignOtpCode = ref('');
const campaignOtpError = ref('');
const campaignVerifyingOtp = ref(false);
const campaignResendingOtp = ref(false);
const showCampaignModal = ref(false);
const campaignMode = ref('create');
const campaignName = ref('');
const campaignOtpRequired = ref(false);
const campaignError = ref('');
const campaignLoading = ref(false);
const campaignRulesSnapshot = ref('');
const campaignRulesBaseline = ref({});
const marginBaseline = ref({});
let messageTimer = null;

const filtered = computed(() =>
  products.value.filter((product) =>
    !product.global_blacklisted &&
    `${product.name} ${product.brand || ''}`.toLowerCase().includes(search.value.toLowerCase())
  ),
);

const filteredProducts = computed(() => {
  return filtered.value.filter((product) => {
    const discount = Number(product.discount_percentage ?? 0);
    if (productFilter.value === 'blacklisted') return !!product.is_blacklisted;
    if (productFilter.value === 'discounted') return discount > 0;
    if (productFilter.value === 'service-charge') return discount < 0;
    if (productFilter.value === 'no-discount') return discount === 0;
    return true;
  });
});

const filteredMargins = computed(() =>
  margins.value.filter((product) => `${product.name} ${product.brand || ''}`.toLowerCase().includes(search.value.toLowerCase())),
);

const filteredMarginProducts = computed(() => {
  return filteredMargins.value.filter((product) => {
    const margin = Number(product.global_margin_percentage || 0);
    if (marginFilter.value === 'margined') return margin > 0;
    if (marginFilter.value === 'negative') return margin < 0;
    if (marginFilter.value === 'zero') return margin === 0;
    if (marginFilter.value === 'blacklisted') return !!product.is_blacklisted;
    return true;
  });
});

const filteredCustomers = computed(() =>
  customers.value.filter((customer) => customer.company_name.toLowerCase().includes(customerSearch.value.toLowerCase())),
);

const campaignOtpMessage = computed(() => {
  const label = campaignPendingApproval.value?.contextLabel || 'campaign changes';
  return `Enter the OTP sent to naveentitare52@gmail.com and ptitare@gmail.com to confirm ${label}. OTP valid for 10 minutes.`;
});

const campaignRulesDirty = computed(() => {
  if (!selected.value || tab.value !== 'rules') return false;
  return serializeCampaignRules(products.value) !== campaignRulesSnapshot.value;
});

async function refresh() {
  const { data } = await api.list();
  campaigns.value = data;
}

async function open(campaign) {
  selected.value = campaign;
  tab.value = 'rules';
  clearMessage();
  error.value = '';
  const [productRes, customerRes, allCustomersRes] = await Promise.all([
    api.products(campaign.id),
    api.customers(campaign.id),
    api.allCustomers(),
  ]);
  products.value = productRes.data.data;
  assigned.value = new Set(customerRes.data.map((row) => row.id));
  customers.value = allCustomersRes.data.data || allCustomersRes.data;
  campaignRulesSnapshot.value = serializeCampaignRules(products.value);
  campaignRulesBaseline.value = Object.fromEntries(
    products.value.map((product) => [
      product.id,
      {
        discount_percentage: Number(product.discount_percentage || 0),
        is_blacklisted: !!product.is_blacklisted,
      },
    ]),
  );
}

async function openMargins() {
  selected.value = null;
  tab.value = 'margins';
  clearMessage();
  error.value = '';
  const { data } = await api.globalMargins();
  margins.value = data.map((product) => ({
    ...product,
    global_margin_percentage: Number(product.global_margin_percentage ?? 0),
  }));
  marginBaseline.value = Object.fromEntries(
    margins.value.map((product) => [
      product.id,
      {
        global_margin_percentage: Number(product.global_margin_percentage || 0),
        is_blacklisted: !!product.is_blacklisted,
      },
    ]),
  );
}

async function create() {
  campaignMode.value = 'create';
  campaignName.value = '';
  campaignOtpRequired.value = false;
  campaignError.value = '';
  showCampaignModal.value = true;
}

async function rename() {
  campaignMode.value = 'rename';
  campaignName.value = selected.value.name;
  campaignOtpRequired.value = !!selected.value.required_otp_confirmation;
  campaignError.value = '';
  showCampaignModal.value = true;
}

async function saveCampaignName() {
  if (!campaignName.value.trim()) {
    campaignError.value = 'Campaign name is required.';
    return;
  }

  campaignLoading.value = true;
  campaignError.value = '';
  try {
    if (campaignMode.value === 'create') {
      const { data } = await api.create({
        name: campaignName.value.trim(),
        required_otp_confirmation: !!campaignOtpRequired.value,
      });
      showCampaignModal.value = false;
      await refresh();
      await open(data);
    } else {
      const payload = {
        name: campaignName.value.trim(),
        required_otp_confirmation: !!campaignOtpRequired.value,
      };
      const { data } = await api.update(selected.value.id, payload);
      if (data.requires_otp) {
        showCampaignModal.value = false;
        queueCampaignApproval(data, 'update', 'campaign settings');
        return;
      }
      selected.value = data;
      showCampaignModal.value = false;
      await refresh();
    }
  } catch (e) {
    campaignError.value = e.response?.data?.message || 'Failed to save campaign name.';
  } finally {
    campaignLoading.value = false;
  }
}

async function toggleStatus() {
  try {
    const { data } = await api.update(selected.value.id, { is_active: !selected.value.is_active });
    if (data.requires_otp) {
      queueCampaignApproval(data, 'update', 'campaign status');
      return;
    }
    selected.value = data;
    await refresh();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to update campaign status.';
  }
}

async function saveRules() {
  if (!selected.value?.id) return;
  if (!campaignRulesDirty.value) return;

  try {
    savingRules.value = true;
    error.value = '';
    const { data } = await api.saveProducts(
      selected.value.id,
      products.value.map((product) => ({
        product_id: product.id,
        discount_percentage: Number(product.discount_percentage || 0),
        is_blacklisted: !!product.is_blacklisted,
      })),
    );
    if (data.requires_otp) {
      queueCampaignApproval(data, 'save_products', 'campaign product rules');
      return;
    }
    showMessage('Product rules saved.');
    await open(selected.value);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save product rules.';
  } finally {
    savingRules.value = false;
  }
}

async function saveMargins() {
  try {
    savingMargins.value = true;
    message.value = '';
    messagePlacement.value = 'banner';
    error.value = '';
    const { data } = await api.saveGlobalMargins(
      margins.value.map((product) => ({
        id: product.id,
        global_margin_percentage: Number(product.global_margin_percentage || 0),
        is_blacklisted: !!product.is_blacklisted,
      })),
    );

    if (data.requires_otp) {
      pendingApproval.value = {
        requestId: data.request_id,
        expiresAt: data.expires_at,
        recipients: data.recipients || [],
      };
      otp.value = '';
      showMessage(data.message, 'banner');
      return;
    }

    showMessage(data.message || 'Global margins saved.', 'banner');
    await openMargins();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save global margins.';
  } finally {
    savingMargins.value = false;
  }
}

function toggleAllMarginBlacklist(checked) {
  filteredMarginProducts.value.forEach((product) => {
    product.is_blacklisted = checked;
  });
}

async function verifyMarginsOtp() {
  if (!pendingApproval.value) return;

  try {
    verifyingOtp.value = true;
    error.value = '';
    messagePlacement.value = 'banner';
    const { data } = await api.verifyGlobalMarginsOtp(pendingApproval.value.requestId, otp.value);
    pendingApproval.value = null;
    otp.value = '';
    await openMargins();
    showMessage(data.message || 'Global margins saved.', 'banner');
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to verify OTP.';
  } finally {
    verifyingOtp.value = false;
  }
}

async function resendMarginsOtp() {
  if (!pendingApproval.value || resendingOtp.value) return;

  try {
    resendingOtp.value = true;
    error.value = '';
    const { data } = await api.resendGlobalMarginsOtp(pendingApproval.value.requestId);
    pendingApproval.value = {
      ...pendingApproval.value,
      expiresAt: data.expires_at || pendingApproval.value.expiresAt,
    };
    otp.value = '';
    showMessage(data.message || 'OTP resent successfully.', 'toast');
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to resend OTP.';
  } finally {
    resendingOtp.value = false;
  }
}

function toggle(id, on) {
  const next = new Set(assigned.value);
  if (on) {
    next.add(id);
  } else {
    next.delete(id);
  }
  assigned.value = next;
}

async function saveCustomers() {
  try {
    const { data } = await api.saveCustomers(selected.value.id, [...assigned.value]);
    if (data.requires_otp) {
      queueCampaignApproval(data, 'save_customers', 'campaign customer mapping');
      return;
    }
    const currentTab = tab.value;
    await refresh();
    await open(selected.value);
    tab.value = currentTab;
    showMessage('Customer updated.');
    error.value = '';
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to update customers.';
  }
}

function queueCampaignApproval(data, action, contextLabel) {
  campaignPendingApproval.value = {
    campaignId: selected.value?.id || null,
    requestId: data.request_id,
    expiresAt: data.expires_at,
    recipients: data.recipients || [],
    action,
    contextLabel,
    restoreTab: tab.value,
  };
  campaignOtpCode.value = '';
  campaignOtpError.value = '';
  showMessage(data.message || 'OTP sent successfully.', 'toast');
}

async function verifyCampaignOtp() {
  if (!campaignPendingApproval.value) return;
  const campaignId = campaignPendingApproval.value.campaignId || selected.value?.id;
  if (!campaignId) return;

  try {
    campaignVerifyingOtp.value = true;
    campaignOtpError.value = '';
    const restoreTab = campaignPendingApproval.value.restoreTab;
    const { data } = await api.verifyCampaignOtp(campaignId, campaignPendingApproval.value.requestId, campaignOtpCode.value);
    campaignPendingApproval.value = null;
    campaignOtpCode.value = '';
    await refresh();
    await open(data.campaign || selected.value);
    tab.value = restoreTab;
    showMessage(data.message || 'Campaign changes saved successfully.');
  } catch (e) {
    campaignOtpError.value = e.response?.data?.message || 'Failed to verify OTP.';
  } finally {
    campaignVerifyingOtp.value = false;
  }
}

async function resendCampaignOtp() {
  const campaignId = campaignPendingApproval.value?.campaignId || selected.value?.id;
  if (!campaignPendingApproval.value || campaignResendingOtp.value || !campaignId) return;

  try {
    campaignResendingOtp.value = true;
    campaignOtpError.value = '';
    const { data } = await api.resendCampaignOtp(campaignId, campaignPendingApproval.value.requestId);
    campaignPendingApproval.value = {
      ...campaignPendingApproval.value,
      expiresAt: data.expires_at || campaignPendingApproval.value.expiresAt,
    };
    campaignOtpCode.value = '';
    showMessage(data.message || 'OTP resent successfully.', 'toast');
  } catch (e) {
    campaignOtpError.value = e.response?.data?.message || 'Failed to resend OTP.';
  } finally {
    campaignResendingOtp.value = false;
  }
}

function clearMessage() {
  if (messageTimer) {
    clearTimeout(messageTimer);
    messageTimer = null;
  }
  message.value = '';
  messagePlacement.value = 'toast';
}

function campaignProductChanged(product) {
  const baseline = campaignRulesBaseline.value?.[product.id];
  if (!baseline) return false;
  return (
    Number(product.discount_percentage || 0) !== Number(baseline.discount_percentage || 0) ||
    !!product.is_blacklisted !== !!baseline.is_blacklisted
  );
}

function marginProductChanged(product) {
  const baseline = marginBaseline.value?.[product.id];
  if (!baseline) return false;
  return (
    Number(product.global_margin_percentage || 0) !== Number(baseline.global_margin_percentage || 0) ||
    !!product.is_blacklisted !== !!baseline.is_blacklisted
  );
}

function serializeCampaignRules(list) {
  return JSON.stringify(
    list.map((product) => ({
      id: product.id,
      discount_percentage: Number(product.discount_percentage || 0),
      is_blacklisted: !!product.is_blacklisted,
    })),
  );
}

function showMessage(text, placement = 'toast') {
  clearMessage();
  message.value = text;
  messagePlacement.value = placement;
  messageTimer = setTimeout(() => {
    message.value = '';
    messageTimer = null;
    messagePlacement.value = 'toast';
  }, 3000);
}

function marginStatusLabel(product) {
  const margin = Number(product.global_margin_percentage || 0);
  if (margin < 0) return 'Service charge applicable';
  if (margin > 0) return 'Margin available';
  return 'No margin';
}

function marginPillClass(product) {
  const margin = Number(product.global_margin_percentage || 0);
  if (margin < 0) return 'margin-pill-negative';
  if (margin > 0) return 'margin-pill-positive';
  return 'margin-pill-neutral';
}

function formatMargin(value) {
  const num = Number(value ?? 0);
  if (Number.isNaN(num)) return '0';
  if (Number.isInteger(num)) return String(num);
  return String(num).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
}

onBeforeUnmount(() => {
  if (messageTimer) clearTimeout(messageTimer);
});

onMounted(refresh);
</script>

<style scoped>
.page {
  padding: 28px;
  max-width: 1300px;
  width: 100%;
  box-sizing: border-box;
}

.page-header,
.sticky {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.page-header {
  margin-bottom: 22px;
}

h1 {
  font: 600 30px var(--fd);
  margin: 0 0 5px;
}

h2 {
  margin: 0 0 8px;
}

.page-header p,
small,
.card p,
.section-note {
  color: var(--ink-muted);
  font-size: 13px;
}

.layout {
  display: grid;
  grid-template-columns: 250px 1fr;
  gap: 18px;
}

.card {
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 12px;
  padding: 18px;
}

.sidebar {
  min-height: 220px;
}

.campaign {
  display: block;
  width: 100%;
  text-align: left;
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 8px;
  font: inherit;
  cursor: pointer;
}

.campaign.active {
  border-color: var(--teal-mid);
  background: var(--teal-pale);
}

.campaign small,
.campaign strong,
.customer small {
  display: block;
}

.campaign-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.tabs {
  display: flex;
  gap: 8px;
  margin: 16px 0;
}

.rules-toolbar {
  display: flex;
  gap: 10px;
  margin: 16px 0 12px;
  flex-wrap: wrap;
}

.rules-toolbar .avq-input {
  flex: 1 1 260px;
}

.customer-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.customer-toolbar .avq-input {
  flex: 1 1 260px;
}

.customer-summary {
  margin-left: auto;
  font-size: 13px;
  font-weight: 600;
  color: var(--teal-deep);
  background: var(--teal-pale);
  border-radius: 999px;
  padding: 8px 12px;
}

.tabs button {
  border: 0;
  background: #eee;
  padding: 8px 11px;
  border-radius: 6px;
}

.tabs .on {
  background: var(--teal-pale);
  color: var(--teal-deep);
}

.sticky {
  position: sticky;
  top: 64px;
  background: #fff;
  padding: 4px 0 12px;
  z-index: 2;
  border-bottom: 1px solid var(--border-2);
}

table {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0 18px;
}

.table-scroll {
  max-height: 60vh;
  overflow: auto;
  border: 1px solid var(--border-2);
  border-radius: 10px;
}

.table-scroll table {
  margin: 0;
}

.table-scroll thead th {
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 1;
}

th,
td {
  text-align: left;
  padding: 9px;
  border-bottom: 1px solid var(--border-2);
}

th {
  font-size: 12px;
  color: var(--ink-muted);
}

.number {
  width: 110px;
  padding: 7px;
}

.customer {
  display: flex;
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid var(--border-2);
}

.customer-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 700;
}

.customer-pill-on {
  color: var(--teal-deep);
  background: var(--teal-pale);
}

.customer-pill-off {
  color: var(--ink-muted);
  background: #f3f4f6;
}

.row-dirty {
  background: rgba(16, 185, 129, 0.08);
}

.row-dirty td:first-child {
  border-left: 3px solid var(--teal-mid);
}

.campaign-otp-toggle {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
}

.campaign-otp-toggle input {
  margin-top: 3px;
}

.campaign-otp-toggle span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.campaign-otp-toggle small {
  font-size: 12px;
  color: var(--ink-muted);
  line-height: 1.4;
}

.message {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--teal-deep);
  color: #fff;
  padding: 12px 20px;
  border-radius: 10px;
  z-index: 9999;
  font-weight: 600;
  box-shadow: 0 8px 24px rgba(8, 80, 65, 0.25);
}

.error {
  color: #b42318;
  margin-top: 8px;
}

.global-margin-banner {
  margin: 14px 0 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--teal-pale);
  border: 1px solid var(--teal-light);
  color: var(--teal-deep);
  font-size: 14px;
}

.header-check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.empty {
  color: var(--ink-muted);
}

.otp-panel {
  margin: 16px 0 18px;
  padding: 16px;
  border-radius: 12px;
  background: #fffaf1;
  border: 1px solid #f3d08c;
}

.otp-banner {
  color: #8a4b00;
  margin-bottom: 16px;
}

.otp-row {
  display: flex;
  gap: 16px;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
}

.otp-row label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-muted);
  margin-bottom: 6px;
}

.otp-input {
  width: 170px;
}

.otp-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

@media (max-width: 800px) {
  .layout {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
  }

  .sticky {
    flex-direction: column;
  }
}
</style>
