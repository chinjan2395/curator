<template>
  <div class="space-y-4 max-w-3xl">
    <nav class="page-breadcrumb">
      <router-link to="/admin/users" class="hover:text-indigo-600 transition-colors">Admin / Users</router-link>
      <span class="mx-1 text-slate-300">/</span>
      <span class="truncate">{{ users.currentUser?.name || 'User' }}</span>
    </nav>

    <!-- Loading -->
    <div v-if="users.loading && !users.currentUser" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>

    <!-- Error -->
    <div v-else-if="users.error" class="text-sm-pro text-red-600">{{ users.error }}</div>

    <template v-else-if="users.currentUser">
      <!-- Page header -->
      <div class="flex items-center gap-4 mb-2">
        <div class="h-14 w-14 rounded-full bg-indigo-100 text-indigo-700 text-xl font-semibold flex items-center justify-center shrink-0">
          {{ initials(users.currentUser.name) }}
        </div>
        <div class="min-w-0">
          <h1 class="page-title !mb-0">{{ users.currentUser.name }}</h1>
          <div class="flex items-center gap-2 mt-1 flex-wrap">
            <span class="text-sm-pro text-slate-500">{{ users.currentUser.email }}</span>
            <span
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium"
              :class="users.currentUser.deactivated_at ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="users.currentUser.deactivated_at ? 'bg-red-400' : 'bg-emerald-400'" />
              {{ users.currentUser.deactivated_at ? 'Deactivated' : 'Active' }}
            </span>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-medium"
              :class="users.currentUser.role === 'admin' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-50 text-slate-600 border border-slate-200'"
            >
              {{ users.currentUser.role === 'admin' ? 'Admin' : 'User' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Profile card -->
      <div class="surface-card p-5 space-y-4">
        <h2 class="text-sm-pro font-semibold text-slate-700 border-b border-slate-100 pb-2">Profile</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label-pro">Name</label>
            <input v-model="form.name" type="text" class="input-pro" placeholder="Full name" />
          </div>
          <div>
            <label class="label-pro">Email</label>
            <input v-model="form.email" type="email" class="input-pro" placeholder="email@example.com" />
          </div>
          <div>
            <label class="label-pro">Role</label>
            <select v-model="form.role" class="input-pro">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div>
            <label class="label-pro">Member since</label>
            <div class="input-pro !bg-slate-50 text-slate-500 cursor-default">{{ formatDate(users.currentUser.created_at) }}</div>
          </div>
        </div>
        <div class="flex items-center gap-3 pt-1">
          <button type="button" class="btn-primary !w-auto !py-1.5 !px-4 text-sm-pro" @click="saveProfile" :disabled="saving">
            <span v-if="saving" class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin mr-1.5" />
            Save changes
          </button>
          <button type="button" class="action-link action-link--premium text-sm-pro" @click="resetForm">Reset</button>
        </div>
      </div>

      <!-- Status card -->
      <div class="surface-card p-5 space-y-3">
        <h2 class="text-sm-pro font-semibold text-slate-700 border-b border-slate-100 pb-2">Account status</h2>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm-pro font-medium text-slate-800">
              {{ users.currentUser.deactivated_at ? 'Account deactivated' : 'Account active' }}
            </div>
            <div v-if="users.currentUser.deactivated_at" class="text-2xs text-slate-500 mt-0.5">
              Deactivated on {{ formatDate(users.currentUser.deactivated_at) }}
            </div>
            <div v-else class="text-2xs text-slate-500 mt-0.5">
              This user can sign in and use the application.
            </div>
          </div>
          <button
            v-if="!users.currentUser.deactivated_at"
            type="button"
            class="action-link action-link--premium !text-amber-700 hover:!bg-amber-50/75 hover:!border-amber-200/80 inline-flex items-center gap-1.5 text-sm-pro"
            @click="doDeactivate"
            :disabled="actionLoading"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg>
            Deactivate account
          </button>
          <button
            v-else
            type="button"
            class="action-link action-link--premium !text-emerald-700 hover:!bg-emerald-50/75 hover:!border-emerald-200/80 inline-flex items-center gap-1.5 text-sm-pro"
            @click="doActivate"
            :disabled="actionLoading"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
            Activate account
          </button>
        </div>
      </div>

      <!-- Social accounts -->
      <div class="surface-card p-5 space-y-3">
        <h2 class="text-sm-pro font-semibold text-slate-700 border-b border-slate-100 pb-2">
          Connected social accounts
          <span class="ml-2 text-2xs font-normal text-slate-400">({{ users.currentUser.social_credentials?.length ?? 0 }})</span>
        </h2>
        <div v-if="!users.currentUser.social_credentials?.length" class="text-sm-pro text-slate-400">
          No social accounts connected.
        </div>
        <div v-else class="flex flex-wrap items-center gap-2">
          <div
            v-for="cred in users.currentUser.social_credentials"
            :key="cred.id"
            class="connected-provider-icon cursor-help"
            role="img"
            :aria-label="credentialTooltip(cred)"
            :title="credentialTooltip(cred)"
            :style="{
              background: providerUi[credentialIconType(cred.provider)]?.softBg || 'rgba(148,163,184,0.14)',
              color: providerUi[credentialIconType(cred.provider)]?.color || '#475569',
            }"
          >
            <SocialIcon :type="credentialIconType(cred.provider)" />
          </div>
        </div>
      </div>

      <!-- Workspaces -->
      <div class="surface-card p-5 space-y-3">
        <h2 class="text-sm-pro font-semibold text-slate-700 border-b border-slate-100 pb-2">
          Workspaces
          <span class="ml-2 text-2xs font-normal text-slate-400">({{ users.currentUser.workspaces?.length ?? 0 }})</span>
        </h2>
        <div v-if="!users.currentUser.workspaces?.length" class="text-sm-pro text-slate-400">
          No workspaces created.
        </div>
        <ul v-else class="space-y-2">
          <li
            v-for="ws in users.currentUser.workspaces"
            :key="ws.id"
            class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5"
          >
            <svg class="w-4 h-4 text-slate-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z"/></svg>
            <span class="text-sm-pro font-medium text-slate-700">{{ ws.name }}</span>
          </li>
        </ul>
      </div>

      <!-- Danger zone -->
      <div class="surface-card p-5 space-y-3 border-red-200/60">
        <h2 class="text-sm-pro font-semibold text-red-700 border-b border-red-100 pb-2">Danger zone</h2>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="text-sm-pro font-medium text-slate-800">Send password reset email</div>
            <div class="text-2xs text-slate-500">Sends a reset link to the user's email address.</div>
          </div>
          <button
            type="button"
            class="action-link action-link--premium inline-flex items-center gap-1.5 text-sm-pro shrink-0"
            @click="doResetPassword"
            :disabled="actionLoading"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
            Send reset link
          </button>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-red-100 pt-3">
          <div>
            <div class="text-sm-pro font-medium text-red-800">Delete this account</div>
            <div class="text-2xs text-slate-500">Permanently removes the user and all their data. Cannot be undone.</div>
          </div>
          <button
            type="button"
            class="action-link action-link--premium inline-flex items-center gap-1.5 text-sm-pro !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80 shrink-0"
            @click="doDelete"
            :disabled="actionLoading"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
            Delete account
          </button>
        </div>
      </div>
    </template>

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
import { ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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

const route = useRoute();
const router = useRouter();
const users = useUsersStore();

const saving = ref(false);
const actionLoading = ref(false);
const toast = ref(null);
const form = ref({ name: '', email: '', role: 'user' });

onMounted(() => load());

watch(() => users.currentUser, (user) => {
  if (user) resetForm();
}, { immediate: true });

async function load() {
  await users.fetchOne(route.params.id);
}

function resetForm() {
  if (!users.currentUser) return;
  form.value = {
    name: users.currentUser.name,
    email: users.currentUser.email,
    role: users.currentUser.role,
  };
}

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function showToast(message, type = 'success') {
  toast.value = { message, type };
  setTimeout(() => { toast.value = null; }, 3500);
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

function credentialTooltip(cred) {
  const d = formatDate(cred.created_at);
  return d !== '—' ? `${credentialTitle(cred)} · ${d}` : credentialTitle(cred);
}

async function saveProfile() {
  saving.value = true;
  const result = await users.updateUser(route.params.id, form.value);
  saving.value = false;
  if (result.success) {
    showToast('Profile updated successfully.');
  } else {
    showToast(result.message, 'error');
  }
}

async function doResetPassword() {
  actionLoading.value = true;
  const result = await users.resetPassword(route.params.id);
  actionLoading.value = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doDeactivate() {
  if (!window.confirm(`Deactivate this account? The user will be logged out and cannot sign in.`)) return;
  actionLoading.value = true;
  const result = await users.deactivate(route.params.id);
  actionLoading.value = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doActivate() {
  actionLoading.value = true;
  const result = await users.activate(route.params.id);
  actionLoading.value = false;
  showToast(result.message, result.success ? 'success' : 'error');
}

async function doDelete() {
  if (!window.confirm(`Permanently delete this account? This cannot be undone.`)) return;
  actionLoading.value = true;
  const result = await users.deleteUser(route.params.id);
  actionLoading.value = false;
  if (result.success) {
    router.push('/admin/users');
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

.connected-provider-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
</style>
