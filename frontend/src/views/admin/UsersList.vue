<template>
  <div class="space-y-4">
    <nav class="page-breadcrumb">
      <span>Admin</span>
      <span class="mx-1 text-slate-300">/</span>
      <span>Users</span>
    </nav>

    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z" />
          </svg>
          Users
        </h1>
        <p class="page-kicker">Manage registered users, roles, and account status.</p>
      </div>
      <div class="text-sm-pro text-slate-500" v-if="users.pagination">
        {{ users.pagination.total }} user{{ users.pagination.total !== 1 ? 's' : '' }}
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
        </svg>
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by name or email…"
          class="input-pro pl-9"
          @input="onFilterChange"
        />
      </div>
      <select v-model="filters.role" class="input-pro !w-auto" @change="onFilterChange">
        <option value="">All roles</option>
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </select>
      <select v-model="filters.status" class="input-pro !w-auto" @change="onFilterChange">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="deactivated">Deactivated</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="users.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading users…
    </div>

    <!-- Error -->
    <div v-else-if="users.error" class="text-sm-pro text-red-600">{{ users.error }}</div>

    <!-- Empty -->
    <div v-else-if="!users.list.length" class="surface-card p-8 text-center text-sm-pro text-slate-500">
      No users found.
    </div>

    <!-- Table -->
    <div v-else class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">User</th>
            <th class="table-th">Role</th>
            <th class="table-th min-w-[11rem] align-middle">Connected</th>
            <th class="table-th">Status</th>
            <th class="table-th">Joined</th>
            <th class="table-th w-72">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="user in users.list" :key="user.id" class="table-tr group">
            <!-- User info -->
            <td class="table-td">
              <router-link :to="`/admin/users/${user.id}`" class="flex items-center gap-3 hover:text-indigo-700 transition-colors">
                <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-700 text-sm-pro font-semibold flex items-center justify-center shrink-0">
                  {{ initials(user.name) }}
                </div>
                <div class="min-w-0">
                  <div class="font-medium text-slate-800 truncate">{{ user.name }}</div>
                  <div class="text-2xs text-slate-500 truncate">{{ user.email }}</div>
                </div>
              </router-link>
            </td>

            <!-- Role -->
            <td class="table-td">
              <select
                :value="user.role"
                class="text-2xs border border-slate-200 rounded-md px-2 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-indigo-400 focus:outline-none cursor-pointer"
                @change="changeRole(user, $event.target.value)"
                :disabled="actionLoading[user.id]"
              >
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </td>

            <!-- Connected social accounts — circular icons only (same chrome as Credentials.vue) -->
            <td class="table-td align-middle">
              <div v-if="user.social_credentials?.length" class="flex flex-wrap items-center justify-start gap-1.5 py-0.5">
                <div
                  v-for="cred in user.social_credentials"
                  :key="cred.id"
                  class="connected-provider-icon cursor-help"
                  role="img"
                  :aria-label="credentialTitle(cred)"
                  :title="credentialTitle(cred)"
                  :style="{
                    background: providerUi[credentialIconType(cred.provider)]?.softBg || 'rgba(148,163,184,0.14)',
                    color: providerUi[credentialIconType(cred.provider)]?.color || '#475569',
                  }"
                >
                  <SocialIcon :type="credentialIconType(cred.provider)" />
                </div>
              </div>
              <span v-else class="text-2xs text-slate-400">None connected</span>
            </td>

            <!-- Status -->
            <td class="table-td">
              <span
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium"
                :class="user.deactivated_at ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="user.deactivated_at ? 'bg-red-400' : 'bg-emerald-400'" />
                {{ user.deactivated_at ? 'Deactivated' : 'Active' }}
              </span>
            </td>

            <!-- Joined date -->
            <td class="table-td text-sm-pro text-slate-500">
              {{ formatDate(user.created_at) }}
            </td>

            <!-- Actions -->
            <td class="table-td">
              <div class="flex items-center gap-1.5 flex-wrap">
                <router-link
                  :to="`/admin/users/${user.id}`"
                  class="action-link action-link--premium inline-flex items-center gap-1"
                  title="View details"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                  View
                </router-link>
                <button
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1"
                  title="Send password reset email"
                  @click="doResetPassword(user)"
                  :disabled="actionLoading[user.id]"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                  Reset pwd
                </button>
                <button
                  v-if="!user.deactivated_at"
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1 !text-amber-700 hover:!bg-amber-50/75 hover:!border-amber-200/80"
                  title="Deactivate user"
                  @click="doDeactivate(user)"
                  :disabled="actionLoading[user.id]"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg>
                  Deactivate
                </button>
                <button
                  v-else
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1 !text-emerald-700 hover:!bg-emerald-50/75 hover:!border-emerald-200/80"
                  title="Activate user"
                  @click="doActivate(user)"
                  :disabled="actionLoading[user.id]"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                  Activate
                </button>
                <button
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1 !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80"
                  title="Delete user"
                  @click="doDelete(user)"
                  :disabled="actionLoading[user.id]"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="users.pagination && users.pagination.last_page > 1" class="flex items-center justify-between pt-1">
      <div class="text-2xs text-slate-500">
        Showing {{ users.pagination.from }}–{{ users.pagination.to }} of {{ users.pagination.total }}
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
          Prev
        </button>
        <span class="text-2xs text-slate-600 px-2">Page {{ currentPage }} of {{ users.pagination.last_page }}</span>
        <button
          type="button"
          class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage === users.pagination.last_page"
          @click="goToPage(currentPage + 1)"
        >
          Next
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </button>
      </div>
    </div>

    <!-- Toast -->
    <div
      v-if="toast"
      class="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-xl border shadow-panel px-4 py-3 text-sm-pro font-medium transition-all"
      :class="toast.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useUsersStore } from '../../stores/users';
import SocialIcon from '../../components/SocialIcon.vue';

/** Same palette / labels as Credentials.vue */
const socialProviders = [
  { type: 'youtube', label: 'YouTube', tagline: 'Channel connect', color: '#ef4444', softBg: 'rgba(239,68,68,0.12)' },
  { type: 'google', label: 'Google', tagline: 'Google account', color: '#2563eb', softBg: 'rgba(37,99,235,0.12)' },
  { type: 'facebook', label: 'Facebook', tagline: 'Pages access', color: '#1877f2', softBg: 'rgba(24,119,242,0.14)' },
  { type: 'instagram', label: 'Instagram', tagline: 'Meta business', color: '#db2777', softBg: 'rgba(219,39,119,0.14)' },
  { type: 'twitter', label: 'Twitter / X', tagline: 'API tokens', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'tiktok', label: 'TikTok', tagline: 'Creator access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'threads', label: 'Threads', tagline: 'Meta Threads access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'other', label: 'Other', tagline: 'Custom provider', color: '#475569', softBg: 'rgba(71,85,105,0.12)' },
];
const providerUi = Object.fromEntries(socialProviders.map((item) => [item.type, item]));
const providerLabels = {
  youtube: 'YouTube',
  google: 'Google',
  facebook: 'Facebook',
  instagram: 'Instagram',
  twitter: 'Twitter / X',
  tiktok: 'TikTok',
  threads: 'Threads',
  other: 'Other',
};

const users = useUsersStore();
const filters = ref({ search: '', role: '', status: '' });
const currentPage = ref(1);
const actionLoading = ref({});
const toast = ref(null);
let searchTimeout = null;

onMounted(() => load());

function load() {
  const params = { page: currentPage.value };
  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.role) params.role = filters.value.role;
  if (filters.value.status) params.status = filters.value.status;
  users.fetchAll(params);
}

function onFilterChange() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    load();
  }, 300);
}

function goToPage(page) {
  currentPage.value = page;
  load();
}

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function credentialIconType(provider) {
  return String(provider || 'other').toLowerCase();
}

function credentialDisplayLabel(provider) {
  const t = credentialIconType(provider);
  return providerLabels[t] || t.replace(/_/g, ' ');
}

function credentialTitle(cred) {
  const label = cred.account_label?.trim();
  const prov = credentialDisplayLabel(cred.provider);
  return label ? `${prov}: ${label}` : `${prov} account`;
}

function showToast(message, type = 'success') {
  toast.value = { message, type };
  setTimeout(() => { toast.value = null; }, 3500);
}

async function changeRole(user, role) {
  actionLoading.value[user.id] = true;
  const result = await users.updateUser(user.id, { role });
  actionLoading.value[user.id] = false;
  if (result.success) {
    showToast(`Role updated to "${role}" for ${user.name}.`);
  } else {
    showToast(result.message, 'error');
    load();
  }
}

async function doResetPassword(user) {
  actionLoading.value[user.id] = true;
  const result = await users.resetPassword(user.id);
  actionLoading.value[user.id] = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doDeactivate(user) {
  if (!window.confirm(`Deactivate "${user.name}"? They will be logged out and cannot sign in.`)) return;
  actionLoading.value[user.id] = true;
  const result = await users.deactivate(user.id);
  actionLoading.value[user.id] = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doActivate(user) {
  actionLoading.value[user.id] = true;
  const result = await users.activate(user.id);
  actionLoading.value[user.id] = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doDelete(user) {
  if (!window.confirm(`Permanently delete "${user.name}"? This cannot be undone.`)) return;
  actionLoading.value[user.id] = true;
  const result = await users.deleteUser(user.id);
  actionLoading.value[user.id] = false;
  if (result.success) {
    showToast(`User "${user.name}" deleted.`);
  } else {
    showToast(result.message, 'error');
  }
}
</script>

<style scoped>
.action-link--premium {
  border-color: rgba(203, 213, 225, 0.95);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}

/* Matches Credentials.vue `.connected-provider-icon` */
.connected-provider-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
</style>
