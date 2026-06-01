<template>
  <div class="space-y-4 max-w-3xl">
    <AppPageHeader
      :title="users.currentUser?.name || 'User detail'"
      subtitle="View and manage this user's profile, status, and connected accounts."
      icon="users"
      :breadcrumb="['Admin', 'Users', users.currentUser?.name || 'Detail']"
    />

    <!-- Loading -->
    <AppLoader v-if="users.loading && !users.currentUser" label="Loading…" />

    <!-- Error -->
    <div v-else-if="users.error" class="text-sm-pro text-red-600">{{ users.error }}</div>

    <template v-else-if="users.currentUser">
      <!-- User identity strip -->
      <div class="surface-card p-4 flex items-center gap-4">
        <div class="h-12 w-12 rounded-full bg-blue-100 text-blue-700 text-lg font-semibold flex items-center justify-center shrink-0">
          {{ initials(users.currentUser.name) }}
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-semibold text-slate-800">{{ users.currentUser.name }}</div>
          <div class="text-sm text-slate-500 truncate">{{ users.currentUser.email }}</div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium"
            :class="users.currentUser.deactivated_at ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="users.currentUser.deactivated_at ? 'bg-red-400' : 'bg-emerald-400'" />
            {{ users.currentUser.deactivated_at ? 'Deactivated' : 'Active' }}
          </span>
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-medium"
            :class="users.currentUser.role === 'admin' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-50 text-slate-600 border border-slate-200'"
          >
            {{ users.currentUser.role === 'admin' ? 'Admin' : 'User' }}
          </span>
        </div>
      </div>

      <!-- Profile card -->
      <AppCard class="p-5 space-y-4">
        <h2 class="text-sm-pro font-semibold text-slate-700 border-b border-slate-100 pb-2">Profile</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label-pro">Name</label>
            <AppInput v-model="form.name" type="text" placeholder="Full name" />
          </div>
          <div>
            <label class="label-pro">Email</label>
            <AppInput v-model="form.email" type="email" placeholder="email@example.com" />
          </div>
          <div>
            <label class="label-pro">Role</label>
            <AppSelect v-model="form.role" :show-placeholder="false">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </AppSelect>
          </div>
          <div>
            <label class="label-pro">Member since</label>
            <div class="input-pro !bg-slate-50 text-slate-500 cursor-default">{{ formatDate(users.currentUser.created_at) }}</div>
          </div>
        </div>
        <div class="flex items-center gap-3 pt-1">
          <AppButton size="sm" :loading="saving" @click="saveProfile" :disabled="saving">Save changes</AppButton>
          <AppButton variant="ghost" size="sm" @click="resetForm">Reset</AppButton>
        </div>
      </AppCard>

      <!-- Status card -->
      <AppCard class="p-5 space-y-3">
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
          <AppButton
            v-if="!users.currentUser.deactivated_at"
            variant="ghost"
            tone="warning"
            size="sm"
            @click="doDeactivate"
            :disabled="actionLoading"
          >
            <AppIcon name="close" class="w-4 h-4" />
            Deactivate account
          </AppButton>
          <AppButton
            v-else
            variant="ghost"
            tone="success"
            size="sm"
            @click="doActivate"
            :disabled="actionLoading"
          >
            <AppIcon name="check" class="w-4 h-4" />
            Activate account
          </AppButton>
        </div>
      </AppCard>

      <!-- Social accounts -->
      <AppCard class="p-5 space-y-3">
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
      </AppCard>

      <!-- Workspaces -->
      <AppCard class="p-5 space-y-3">
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
            <AppIcon name="workspaces" class="w-4 h-4 text-slate-400 shrink-0" />
            <span class="text-sm-pro font-medium text-slate-700">{{ ws.name }}</span>
          </li>
        </ul>
      </AppCard>

      <!-- Danger zone -->
      <AppCard class="p-5 space-y-3 border-red-200/60">
        <h2 class="text-sm-pro font-semibold text-red-700 border-b border-red-100 pb-2">Danger zone</h2>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="text-sm-pro font-medium text-slate-800">Send password reset email</div>
            <div class="text-2xs text-slate-500">Sends a reset link to the user's email address.</div>
          </div>
          <AppButton
            variant="ghost"
            size="sm"
            class="shrink-0"
            @click="doResetPassword"
            :disabled="actionLoading"
          >
            <AppIcon name="credentials" class="w-4 h-4" />
            Send reset link
          </AppButton>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-red-100 pt-3">
          <div>
            <div class="text-sm-pro font-medium text-red-800">Delete this account</div>
            <div class="text-2xs text-slate-500">Permanently removes the user and all their data. Cannot be undone.</div>
          </div>
          <AppButton
            variant="ghost"
            tone="destructive"
            size="sm"
            class="shrink-0"
            @click="doDelete"
            :disabled="actionLoading"
          >
            <AppIcon name="delete" class="w-4 h-4" />
            Delete account
          </AppButton>
        </div>
      </AppCard>
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
import { ref, watch, onMounted, inject } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUsersStore } from '../../stores/users';
import SocialIcon from '../../components/SocialIcon.vue';
import { AppButton, AppCard, AppIcon, AppInput, AppLoader, AppSelect } from '../../components/ui';
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

const route = useRoute();
const router = useRouter();
const users = useUsersStore();
const { confirm } = inject('confirm');

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
  if (!await confirm({ title: 'Deactivate user?', message: 'Deactivate this account? The user will be logged out and cannot sign in.', confirmLabel: 'Deactivate' })) return;
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
  if (!await confirm({ title: 'Delete user?', message: 'Permanently delete this account? This cannot be undone.', confirmLabel: 'Delete' })) return;
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
.connected-provider-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
</style>
