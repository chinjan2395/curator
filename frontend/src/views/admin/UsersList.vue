<template>
  <div class="space-y-4">
    <AppPageHeader
      title="Users"
      subtitle="Manage registered users, roles, and account status."
      icon="users"
      :breadcrumb="['Admin', 'Users']"
    >
      <template #actions v-if="users.pagination">
        <span class="text-sm-pro text-slate-500">
          {{ users.pagination.total }} user{{ users.pagination.total !== 1 ? 's' : '' }}
        </span>
      </template>
    </AppPageHeader>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <AppInput
          v-model="filters.search"
          type="text"
          placeholder="Search by name or email…"
          input-class="pl-9"
          @input="onFilterChange"
        />
      </div>
      <AppSelect v-model="filters.role" wrapper-class="!w-auto shrink-0" select-class="!w-auto" :show-placeholder="false" @update:modelValue="onFilterChange">
        <option value="">All roles</option>
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </AppSelect>
      <AppSelect v-model="filters.status" wrapper-class="!w-auto shrink-0" select-class="!w-auto" :show-placeholder="false" @update:modelValue="onFilterChange">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="deactivated">Deactivated</option>
      </AppSelect>
    </div>

    <!-- Loading -->
    <AppLoader v-if="users.loading" label="Loading users…" />

    <!-- Error -->
    <div v-else-if="users.error" class="text-sm-pro text-red-600">{{ users.error }}</div>

    <!-- Empty -->
    <AppCard v-else-if="!users.list.length" class="p-8 text-center text-sm-pro text-slate-500">
      No users found.
    </AppCard>

    <!-- Table -->
    <div v-else>
      <AppTable :columns="tableColumns" :rows="users.list" row-key="id">
        <template #cell-user="{ row: user }">
          <router-link :to="`/admin/users/${user.id}`" class="flex items-center gap-3 hover:text-blue-700 transition-colors">
            <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-700 text-sm-pro font-semibold flex items-center justify-center shrink-0">
              {{ initials(user.name) }}
            </div>
            <div class="min-w-0">
              <div class="font-medium text-slate-800 truncate">{{ user.name }}</div>
              <div class="text-2xs text-slate-500 truncate">{{ user.email }}</div>
            </div>
          </router-link>
        </template>
        <template #cell-role="{ row: user }">
          <AppSelect
            :model-value="user.role"
            select-class="text-2xs border border-slate-200 rounded-md px-2 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-blue-400 focus:outline-none cursor-pointer"
            :show-placeholder="false"
            @update:modelValue="(role) => changeRole(user, role)"
            :disabled="actionLoading[user.id]"
          >
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </AppSelect>
        </template>
        <template #cell-connected="{ row: user }">
          <div v-if="user.social_credentials?.length" class="flex flex-wrap items-center justify-start gap-1.5 py-0.5">
            <div
              v-for="cred in user.social_credentials"
              :key="cred.id"
              class="connected-provider-icon cursor-help"
              role="img"
              :aria-label="credentialTitle(cred)"
              :title="credentialTitle(cred)"
              :style="{ background: providerUi[credentialIconType(cred.provider)]?.softBg || 'rgba(148,163,184,0.14)', color: providerUi[credentialIconType(cred.provider)]?.color || '#475569' }"
            >
              <SocialIcon :type="credentialIconType(cred.provider)" />
            </div>
          </div>
          <span v-else class="text-2xs text-slate-400">None connected</span>
        </template>
        <template #cell-status="{ row: user }">
          <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium" :class="user.deactivated_at ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
            <span class="w-1.5 h-1.5 rounded-full" :class="user.deactivated_at ? 'bg-red-400' : 'bg-emerald-400'" />
            {{ user.deactivated_at ? 'Deactivated' : 'Active' }}
          </span>
        </template>
        <template #cell-joined="{ row: user }">
          <span class="text-sm-pro text-slate-500">{{ formatDate(user.created_at) }}</span>
        </template>
        <template #cell-actions="{ row: user }">
          <AppDropdown align="right">
            <template #trigger>
              <AppButton
                variant="ghost"
                size="sm"
                class="!px-2.5"
                :disabled="actionLoading[user.id]"
                :title="`Open actions for ${user.name}`"
                :aria-label="`Open actions for ${user.name}`"
              >
                <AppIcon name="more" class="w-4 h-4" />
              </AppButton>
            </template>

            <template #default="{ close }">
              <div class="mx-0.5 flex min-w-[11rem] flex-col gap-0.5">
                <router-link
                  :to="`/admin/users/${user.id}`"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                  @click="close()"
                >
                  <AppIcon name="view" class="w-4 h-4 shrink-0" />
                  View details
                </router-link>
                <button
                  type="button"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading[user.id]"
                  @click="close(); doResetPassword(user)"
                >
                  <AppIcon name="sync" class="w-4 h-4 shrink-0" />
                  Reset password
                </button>
                <button
                  v-if="!user.deactivated_at"
                  type="button"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-amber-700 transition hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading[user.id]"
                  @click="close(); doDeactivate(user)"
                >
                  <AppIcon name="activity" class="w-4 h-4 shrink-0" />
                  Deactivate
                </button>
                <button
                  v-else
                  type="button"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading[user.id]"
                  @click="close(); doActivate(user)"
                >
                  <AppIcon name="check" class="w-4 h-4 shrink-0" />
                  Activate
                </button>
                <button
                  type="button"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading[user.id]"
                  @click="close(); doDelete(user)"
                >
                  <AppIcon name="delete" class="w-4 h-4 shrink-0" />
                  Delete user
                </button>
              </div>
            </template>
          </AppDropdown>
        </template>
      </AppTable>
    </div>

    <!-- Pagination -->
    <div v-if="users.pagination && users.pagination.last_page > 1" class="flex items-center justify-between pt-1">
      <div class="text-2xs text-slate-500">
        Showing {{ users.pagination.from }}–{{ users.pagination.to }} of {{ users.pagination.total }}
      </div>
      <div class="flex items-center gap-1">
        <AppButton variant="ghost" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
          <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
          Prev
        </AppButton>
        <span class="text-2xs text-slate-600 px-2">Page {{ currentPage }} of {{ users.pagination.last_page }}</span>
        <AppButton variant="ghost" size="sm" :disabled="currentPage === users.pagination.last_page" @click="goToPage(currentPage + 1)">
          Next
          <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
        </AppButton>
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
import { AppButton, AppCard, AppDropdown, AppIcon, AppInput, AppLoader, AppSelect, AppTable } from '../../components/ui';
import { AppPageHeader } from '../../components/layout/index.js';

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
const tableColumns = [
  { key: 'user', label: 'User' },
  { key: 'role', label: 'Role' },
  { key: 'connected', label: 'Connected' },
  { key: 'status', label: 'Status' },
  { key: 'joined', label: 'Joined' },
  { key: 'actions', label: 'Actions', class: 'w-20 text-right' },
];
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
