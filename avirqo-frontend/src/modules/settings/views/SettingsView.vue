<template>
  <AppLayout>
    <div class="settings-page">
      <div class="settings-header">
        <div>
          <h1>Settings</h1>
          <p>Manage system users, module access, and your own profile.</p>
        </div>
      </div>

      <div class="settings-grid">
        <section v-if="canManageUsers" class="settings-card settings-users">
          <div class="settings-card-head">
            <div>
              <h2>System Users</h2>
              <p>Controls users and their access after login.</p>
            </div>
            <button class="avq-btn-primary" @click="openCreateUser">+ Add User</button>
          </div>

          <div class="settings-toolbar">
            <input v-model="search" class="avq-input settings-search" placeholder="Search users…" @input="loadUsers" />
            <span class="settings-meta">{{ users.length }} user{{ users.length === 1 ? '' : 's' }}</span>
          </div>

          <div class="settings-table-wrap">
            <table class="settings-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Employee ID</th>
                  <th>Date Added</th>
                  <th>Last Modified</th>
                  <th>Status</th>
                  <th>Admin</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="9" class="settings-empty">Loading users…</td>
                </tr>
                <tr v-else-if="!users.length">
                  <td colspan="9" class="settings-empty">No users found.</td>
                </tr>
                <tr v-for="user in users" :key="user.id">
                  <td>
                    <div class="settings-user-name">{{ user.name }}</div>
                    <div class="settings-user-photo" v-if="user.profile_photo_url">
                      <img :src="user.profile_photo_url" alt="" />
                    </div>
                  </td>
                  <td>{{ user.email }}</td>
                  <td>{{ user.phone || '—' }}</td>
                  <td>{{ user.employee_id || '—' }}</td>
                  <td>{{ fmtDate(user.date_added) }}</td>
                  <td>{{ fmtDate(user.last_modified) }}</td>
                  <td>
                    <span class="settings-badge" :class="`status-${user.status}`">
                      {{ statusLabel(user.status) }}
                    </span>
                  </td>
                  <td>
                    <span class="settings-badge" :class="user.is_admin ? 'status-admin' : 'status-user'">
                      {{ user.is_admin ? 'Yes' : 'No' }}
                    </span>
                  </td>
                  <td>
                    <div class="settings-actions">
                      <button class="avq-btn-sm" @click="openEditUser(user)">Edit</button>
                      <button class="avq-btn-sm" @click="openModuleAccess(user)">Module Access</button>
                      <button class="avq-btn-sm" @click="askResetPassword(user)">Reset Password</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section v-else class="settings-card settings-users settings-locked">
          <div class="settings-card-head">
            <div>
              <h2>System Users</h2>
              <p>You don’t have access to manage users.</p>
            </div>
          </div>
          <div class="settings-empty" style="min-height: 220px; display:flex; align-items:center; justify-content:center;">
            This area is restricted to users with Manager Module access.
          </div>
        </section>

        <aside class="settings-card settings-profile">
          <div class="settings-card-head">
            <div>
              <h2>Profile Settings</h2>
              <p>Update your own account details.</p>
            </div>
          </div>

          <div v-if="profileLoading" class="settings-empty" style="min-height: 220px;">Loading profile…</div>
          <template v-else>
            <div class="profile-card">
              <div class="profile-photo">
                <img v-if="profilePreview" :src="profilePreview" alt="Profile photo" />
                <div v-else>{{ profileInitials }}</div>
              </div>
              <div>
                <div class="profile-name">{{ profileForm.name || 'Your profile' }}</div>
                <div class="profile-sub">{{ profileForm.email }}</div>
              </div>
            </div>

            <div class="profile-form">
              <label>Full name</label>
              <input v-model="profileForm.name" class="avq-input" type="text" />

              <label>Mobile number</label>
              <input v-model="profileForm.phone" class="avq-input" type="text" />

              <label>Profile photo</label>
              <input class="avq-input" type="file" accept="image/*" @change="onProfilePhotoChange" />

              <button class="avq-btn-primary" :disabled="savingProfile" @click="saveProfile">
                {{ savingProfile ? 'Saving…' : 'Update Profile' }}
              </button>
            </div>

            <div class="profile-divider"></div>

            <div class="profile-form">
              <h3>Change Password</h3>
              <label>Current password</label>
              <input v-model="passwordForm.current_password" class="avq-input" type="password" />

              <label>New password</label>
              <input v-model="passwordForm.password" class="avq-input" type="password" />

              <label>Confirm new password</label>
              <input v-model="passwordForm.password_confirmation" class="avq-input" type="password" />

              <button class="avq-btn-primary" :disabled="savingPassword" @click="changePassword">
                {{ savingPassword ? 'Updating…' : 'Change Password' }}
              </button>
            </div>
          </template>
        </aside>
      </div>

      <AppToast :open="!!toast" :message="toast" @close="toast = ''" />

      <div v-if="showUserModal" class="avq-modal-overlay" @click.self="showUserModal = false">
        <div class="avq-modal avq-modal-lg">
          <h3>{{ editingUserId ? 'Edit User' : 'Add User' }}</h3>
          <div class="settings-form-grid">
            <div>
              <label>Name</label>
              <input v-model="userForm.name" class="avq-input" type="text" />
            </div>
            <div>
              <label>Email</label>
              <input v-model="userForm.email" class="avq-input" type="email" />
            </div>
            <div>
              <label>Phone number</label>
              <input v-model="userForm.phone" class="avq-input" type="text" />
            </div>
            <div>
              <label>Employee ID</label>
              <input v-model="userForm.employee_id" class="avq-input" type="text" />
            </div>
            <div>
              <label>Status</label>
              <select v-model="userForm.status" class="avq-input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="temporary_disabled">Temporary disabled</option>
              </select>
            </div>
            <div>
              <label>Is Admin</label>
              <select v-model="userForm.is_admin" class="avq-input">
                <option :value="false">False</option>
                <option :value="true">True</option>
              </select>
            </div>
          </div>
          <p class="settings-note">
            A password email will be sent when a new user is created.
          </p>
          <p v-if="userError" class="settings-error">{{ userError }}</p>
          <div class="modal-footer">
            <button class="avq-btn-ghost" @click="showUserModal = false">Cancel</button>
            <button class="avq-btn-primary" :disabled="savingUser" @click="saveUser">
              {{ savingUser ? 'Saving…' : 'Save User' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="showAccessModal" class="avq-modal-overlay" @click.self="showAccessModal = false">
        <div class="avq-modal avq-modal-lg">
          <h3>Module Access — {{ accessUser?.name }}</h3>
          <p class="settings-note">
            Dashboard and Settings are available by default. Admin users receive access to all modules with all permissions.
          </p>
          <div v-if="accessUser?.is_admin" class="settings-note" style="margin-bottom: 16px;">
            This user is an Admin and already has full access.
          </div>
          <div class="module-access-list">
            <div v-for="module in moduleAccess" :key="module.key" class="module-access-card" :class="{ disabled: accessUser?.is_admin }">
              <div class="module-access-head">
                <div>
                  <strong>{{ module.label }}</strong>
                  <div class="module-access-key">{{ module.key }}</div>
                </div>
              </div>
              <div class="module-permissions">
                <label v-for="perm in module.permissionLabels" :key="perm.key">
                  <input
                    v-model="module.permissions[perm.key]"
                    type="checkbox"
                    :disabled="accessUser?.is_admin"
                  />
                  {{ perm.label }}
                </label>
              </div>
            </div>
          </div>
          <p v-if="accessError" class="settings-error">{{ accessError }}</p>
          <div class="modal-footer">
            <button class="avq-btn-ghost" @click="showAccessModal = false">Cancel</button>
            <button class="avq-btn-primary" :disabled="savingAccess || accessUser?.is_admin" @click="saveModuleAccess">
              {{ savingAccess ? 'Saving…' : 'Save Access' }}
            </button>
          </div>
        </div>
      </div>

      <AppDialogModal
        :open="showResetDialog"
        title="Reset password"
        :message="resetTarget ? `Reset password for ${resetTarget.name}? A new password will be emailed.` : ''"
        confirm-text="Reset password"
        cancel-text="Cancel"
        :loading="resettingPassword"
        variant="danger"
        @cancel="showResetDialog = false"
        @confirm="confirmResetPassword"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppToast from '../../shared/components/AppToast.vue';
import AppDialogModal from '../../shared/components/AppDialogModal.vue';
import settingsApi from '../api/settingsApi';
import { useAuthStore } from '../../auth/store/authStore';

const auth = useAuthStore();

const users = ref([]);
const loading = ref(false);
const profileLoading = ref(false);
const savingUser = ref(false);
const savingAccess = ref(false);
const savingProfile = ref(false);
const savingPassword = ref(false);
const resettingPassword = ref(false);
const toast = ref('');
const search = ref('');

const showUserModal = ref(false);
const editingUserId = ref(null);
const userError = ref('');
const userForm = reactive({
  name: '',
  email: '',
  phone: '',
  employee_id: '',
  status: 'active',
  is_admin: false,
});

const showAccessModal = ref(false);
const accessError = ref('');
const accessUser = ref(null);
const accessForm = ref([]);

const showResetDialog = ref(false);
const resetTarget = ref(null);

const profile = ref(null);
const profilePreview = ref('');
const profilePhotoFile = ref(null);
const profileForm = reactive({
  name: '',
  email: '',
  phone: '',
});
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const moduleAccess = computed(() => accessForm.value);
const profileInitials = computed(() => {
  const name = profileForm.name || auth.user?.name || 'U';
  return name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
});

const canManageUsers = computed(() => {
  if (auth.user?.is_admin) return true;
  return (auth.user?.module_access || []).some((module) => module?.key === 'manager_module_access' && module?.permissions?.view);
});

onMounted(async () => {
  await Promise.all([loadUsers(), loadProfile()]);
});

async function loadUsers() {
  loading.value = true;
  try {
    const { data } = await settingsApi.users({ search: search.value });
    users.value = data.data || [];
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to load users');
  } finally {
    loading.value = false;
  }
}

async function loadProfile() {
  profileLoading.value = true;
  try {
    const { data } = await settingsApi.profile();
    profile.value = data.user;
    profileForm.name = data.user.name || '';
    profileForm.email = data.user.email || '';
    profileForm.phone = data.user.phone || '';
    profilePreview.value = data.user.profile_photo_url || '';
    if (auth.user?.id === data.user.id) {
      auth.user = { ...auth.user, ...data.user };
      localStorage.setItem('avirqo_user', JSON.stringify(auth.user));
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to load profile');
  } finally {
    profileLoading.value = false;
  }
}

function openCreateUser() {
  editingUserId.value = null;
  userError.value = '';
  Object.assign(userForm, {
    name: '',
    email: '',
    phone: '',
    employee_id: '',
    status: 'active',
    is_admin: false,
  });
  showUserModal.value = true;
}

function openEditUser(user) {
  editingUserId.value = user.id;
  userError.value = '';
  Object.assign(userForm, {
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    employee_id: user.employee_id || '',
    status: user.status || 'active',
    is_admin: !!user.is_admin,
  });
  showUserModal.value = true;
}

async function saveUser() {
  savingUser.value = true;
  userError.value = '';
  try {
    const payload = {
      name: userForm.name,
      email: userForm.email,
      phone: userForm.phone || null,
      employee_id: userForm.employee_id || null,
      status: userForm.status,
      is_admin: !!userForm.is_admin,
    };
    if (editingUserId.value) {
      await settingsApi.updateUser(editingUserId.value, payload);
      showToast('User updated successfully');
    } else {
      await settingsApi.createUser(payload);
      showToast('User created and password emailed');
    }
    showUserModal.value = false;
    await loadUsers();
  } catch (e) {
    userError.value = e.response?.data?.message || 'Failed to save user';
  } finally {
    savingUser.value = false;
  }
}

function openModuleAccess(user) {
  accessUser.value = user;
  accessError.value = '';
  accessForm.value = buildModuleAccess(user);
  showAccessModal.value = true;
}

function buildModuleAccess(user) {
  const defaults = [
    { key: 'dashboard', label: 'Dashboard', permissions: [{ key: 'view', label: 'View' }] },
    { key: 'settings', label: 'Settings', permissions: [{ key: 'view', label: 'View' }, { key: 'change_password', label: 'Change password' }] },
    { key: 'customers', label: 'Customer', permissions: [{ key: 'view', label: 'View' }, { key: 'edit', label: 'Edit' }, { key: 'status', label: 'Inactive/Active' }] },
    { key: 'campaigns', label: 'Campaigns', permissions: [{ key: 'view', label: 'View' }, { key: 'edit', label: 'Edit' }, { key: 'delete', label: 'Delete' }] },
    { key: 'send_vouchers', label: 'Send Voucher', permissions: [{ key: 'view', label: 'View' }] },
    { key: 'order_history', label: 'Order history', permissions: [{ key: 'view', label: 'View' }] },
    { key: 'manager_module_access', label: 'Manager Module access', permissions: [{ key: 'view', label: 'View' }, { key: 'edit', label: 'Edit' }, { key: 'delete', label: 'Delete' }] },
  ];

  const current = new Map((user.module_access || []).map((m) => [m.key, m]));

  return defaults.map((module) => {
    const saved = current.get(module.key);
    const permissions = {};
    module.permissions.forEach((perm) => {
      permissions[perm.key] = user.is_admin ? true : !!saved?.permissions?.[perm.key];
    });
    return {
      key: module.key,
      label: module.label,
      permissions: Object.fromEntries(module.permissions.map((perm) => [perm.key, permissions[perm.key]])),
      permissionLabels: module.permissions,
    };
  });
}

async function saveModuleAccess() {
  if (!accessUser.value) return;
  savingAccess.value = true;
  accessError.value = '';
  try {
    await settingsApi.updateUser(accessUser.value.id, {
      module_access: accessForm.value.map((module) => ({
        key: module.key,
        label: module.label,
        permissions: module.permissions,
      })),
    });
    showToast('Module access updated');
    showAccessModal.value = false;
    await loadUsers();
  } catch (e) {
    accessError.value = e.response?.data?.message || 'Failed to update access';
  } finally {
    savingAccess.value = false;
  }
}

function askResetPassword(user) {
  resetTarget.value = user;
  showResetDialog.value = true;
}

async function confirmResetPassword() {
  if (!resetTarget.value) return;
  resettingPassword.value = true;
  try {
    await settingsApi.resetPassword(resetTarget.value.id);
    showToast('Password reset email sent');
    showResetDialog.value = false;
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to reset password');
  } finally {
    resettingPassword.value = false;
  }
}

function onProfilePhotoChange(event) {
  const file = event.target.files?.[0];
  profilePhotoFile.value = file || null;
  profilePreview.value = file ? URL.createObjectURL(file) : (profile.value?.profile_photo_url || '');
}

async function saveProfile() {
  savingProfile.value = true;
  try {
    const formData = new FormData();
    formData.append('name', profileForm.name || '');
    formData.append('phone', profileForm.phone || '');
    if (profilePhotoFile.value) formData.append('profile_photo', profilePhotoFile.value);
    const { data } = await settingsApi.updateProfile(formData);
    profile.value = data.user;
    profilePreview.value = data.user.profile_photo_url || '';
    profilePhotoFile.value = null;
    showToast('Profile updated');
    if (auth.user?.id === data.user.id) {
      auth.user = { ...auth.user, ...data.user };
      localStorage.setItem('avirqo_user', JSON.stringify(auth.user));
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to update profile');
  } finally {
    savingProfile.value = false;
  }
}

async function changePassword() {
  savingPassword.value = true;
  try {
    await settingsApi.changePassword({ ...passwordForm });
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    showToast('Password changed successfully');
  } catch (e) {
    showToast(e.response?.data?.message || 'Failed to change password');
  } finally {
    savingPassword.value = false;
  }
}

function statusLabel(status) {
  return {
    active: 'Active',
    inactive: 'Inactive',
    temporary_disabled: 'Temporary disabled',
  }[status] || status;
}

function fmtDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

function showToast(message) {
  toast.value = message;
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => {
    toast.value = '';
  }, 3000);
}
</script>

<style scoped>
.settings-page { padding: 28px; }
.settings-header h1 { font-family: var(--fd); font-size: 26px; margin: 0 0 4px; }
.settings-header p { color: var(--ink-muted); margin: 0; }
.settings-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; margin-top: 22px; align-items: start; }
.settings-card { background: #fff; border: 1px solid var(--border-2); border-radius: 18px; box-shadow: 0 10px 30px rgba(8, 14, 16, 0.04); }
.settings-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 20px 20px 0; }
.settings-card-head h2 { font-family: var(--fd); font-size: 22px; margin: 0 0 4px; }
.settings-card-head p { margin: 0; color: var(--ink-muted); font-size: 13px; }
.settings-toolbar { display: flex; align-items: center; gap: 12px; padding: 16px 20px; }
.settings-search { min-width: 260px; flex: 1; }
.settings-meta { font-size: 13px; color: var(--ink-muted); }
.settings-table-wrap { overflow: auto; }
.settings-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
.settings-table th { position: sticky; top: 0; background: var(--surface-2); text-align: left; padding: 12px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-muted); border-bottom: 1px solid var(--border-2); z-index: 1; }
.settings-table td { padding: 14px; border-top: 1px solid var(--border-2); vertical-align: top; }
.settings-empty { padding: 28px 14px; text-align: center; color: var(--ink-muted); }
.settings-user-name { font-weight: 700; }
.settings-user-photo img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; margin-top: 8px; }
.settings-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.settings-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.status-active { background: #E8F7F2; color: #085041; }
.status-inactive { background: #FEF2F2; color: #B91C1C; }
.status-temporary_disabled { background: #FFF7ED; color: #C2410C; }
.status-admin { background: #EEF2FF; color: #4F46E5; }
.status-user { background: #F3F4F6; color: #6B7280; }
.settings-profile { padding-bottom: 20px; }
.profile-card { display: flex; align-items: center; gap: 14px; padding: 20px; border-top: 1px solid var(--border-2); }
.profile-photo { width: 56px; height: 56px; border-radius: 16px; background: var(--teal-pale); color: var(--teal-deep); display: flex; align-items: center; justify-content: center; font-weight: 800; overflow: hidden; }
.profile-photo img { width: 100%; height: 100%; object-fit: cover; }
.profile-name { font-weight: 700; font-size: 16px; }
.profile-sub { font-size: 13px; color: var(--ink-muted); }
.profile-form { display: flex; flex-direction: column; gap: 8px; padding: 0 20px 20px; }
.profile-form label { font-size: 12px; font-weight: 700; color: var(--ink-muted); }
.profile-form h3 { font-family: var(--fd); margin: 0 0 4px; font-size: 18px; }
.profile-divider { height: 1px; background: var(--border-2); margin: 0 20px 20px; }
.settings-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.settings-form-grid label { display: block; font-size: 12px; font-weight: 700; color: var(--ink-muted); margin-bottom: 6px; }
.settings-note { font-size: 12px; color: var(--ink-muted); margin: 14px 0 0; }
.settings-error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 10px; margin-top: 14px; }
.module-access-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
.module-access-card { border: 1px solid var(--border-2); border-radius: 14px; padding: 14px; background: #fff; }
.module-access-card.disabled { opacity: 0.7; }
.module-access-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
.module-access-key { font-size: 11px; color: var(--ink-muted); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
.module-permissions { display: flex; flex-direction: column; gap: 8px; }
.module-permissions label { display: flex; gap: 8px; align-items: center; font-size: 13px; color: var(--ink-soft); }

@media (max-width: 1200px) {
  .settings-grid { grid-template-columns: 1fr; }
  .settings-card { overflow: hidden; }
}
</style>
