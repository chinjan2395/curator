<template>
  <div class="space-y-4">
    <AppPageHeader
      title="Credentials"
      subtitle="Connected social accounts (including configured providers without accounts yet)."
      icon="lock"
    />

    <div
      v-if="auth.brokenCredentials?.length"
      class="rounded-xl border border-rose-200 bg-rose-50/80 px-4 py-3 flex items-start gap-3"
    >
      <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
      </svg>
      <div>
        <p class="text-sm-pro font-semibold text-rose-700">
          {{ auth.brokenCredentials.length }} account{{ auth.brokenCredentials.length !== 1 ? 's' : '' }} need reconnection
        </p>
        <p class="text-xs-pro text-rose-600 mt-0.5">
          Background sync failed for:
          <span v-for="(c, i) in auth.brokenCredentials" :key="c.id">
            <strong>{{ c.account_label || c.account_id || c.provider }}</strong><span v-if="i < auth.brokenCredentials.length - 1">, </span>
          </span>.
          Click "+ Add" on the affected provider to reconnect.
        </p>
      </div>
    </div>

    <div v-if="creds.loading" class="surface-card-soft px-4 py-3">
      <AppLoader size="sm" label="Loading..." />
    </div>
    <div v-else-if="creds.error" class="text-sm-pro text-red-600">{{ creds.error }}</div>
    <AppCard v-else-if="!providerCards.length" class="p-6 text-center text-sm-pro text-slate-500">
      No providers are ready yet. Configure shared defaults or your override in
      <router-link to="/oauth-apps" class="text-blue-600 font-medium hover:underline">OAuth apps</router-link>.
    </AppCard>
    <div v-else class="connected-accounts-shell compact-accounts-shell space-y-2.5 sm:p-3 rounded-xl">
      <div class="flex flex-wrap items-center justify-between gap-2 px-1">
        <p class="text-xs-pro font-medium text-slate-700">Current connected accounts</p>
        <div class="flex items-center gap-2">
          <p class="text-2xs text-slate-500">{{ creds.list.length }} account{{ creds.list.length > 1 ? 's' : '' }}</p>
          <AppButton
            size="sm"
            variant="secondary"
            :disabled="syncingAll"
            @click="syncAllAccounts"
            title="Sync all accounts"
          >
            <AppIcon name="sync" class="w-3.5 h-3.5 shrink-0" :class="{ 'animate-spin': syncingAll }" />
            Sync all
          </AppButton>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <div
          v-for="card in providerCards"
          :key="card.type"
          class="connected-provider-card overflow-hidden"
          :class="{ 'connected-provider-card--broken': card.hasBroken }"
        >
          <div class="px-3 py-2 border-b flex items-center justify-between gap-2"
            :class="card.hasBroken ? 'border-rose-100' : 'border-slate-100/95'"
          >
            <div class="flex items-center gap-2 min-w-0">
              <SocialPlatformLabel :type="card.type" variant="badge" size="sm" class="flex-shrink-0" />
              <div class="min-w-0">
                <p class="text-2xs text-slate-500 truncate">{{ providerUi[card.type]?.tagline || 'Connected accounts' }}</p>
              </div>
              <span class="connected-provider-count" :class="{ 'connected-provider-count--broken': card.hasBroken }">
                {{ card.credentials.length }} account{{ card.credentials.length > 1 ? 's' : '' }}
              </span>
            </div>
            <AppButton
              size="sm"
              variant="secondary"
              :disabled="creds.connecting"
              @click="startConnect(card.type)"
              title="Add another account"
            >
              <AppIcon name="add" class="w-3.5 h-3.5 shrink-0" />
              Add
            </AppButton>
          </div>
          <!-- Empty state -->
          <div v-if="!card.credentials.length" class="p-2.5">
            <div class="connected-account-empty">
              <p class="text-xs-pro text-slate-500 font-medium">No account connected yet</p>
              <p class="text-2xs text-slate-400 mt-0.5">Click <strong>Add</strong> above to connect your {{ getPlatformLabel(card.type) }} account.</p>
            </div>
          </div>
          <!-- Credential rows -->
          <div v-else class="p-2 space-y-2">
            <div
              v-for="c in card.credentials"
              :key="c.id"
              class="connected-account-row"
              :class="{ 'connected-account-row--broken': c.status && c.status !== 'active' }"
            >
              <!-- Name + expiry row -->
              <div v-if="renamingId !== c.id" class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-medium text-slate-800 truncate text-sm-pro">{{ c.account_label || c.account_id || '—' }}</span>
                    <span
                      class="credential-status-badge"
                      :class="c.status === 'active' ? 'credential-status-badge--active' : 'credential-status-badge--broken'"
                    >
                      <span class="credential-status-dot"></span>
                      {{ c.status === 'active' ? 'Active' : 'Disconnected' }}
                    </span>
                  </div>
                  <p class="text-2xs text-slate-400 mt-0.5">
                    {{ c.last_synced_at ? formatSynced(c.last_synced_at) : 'Never synced' }}
                    <span v-if="c.follower_count"> · {{ c.follower_count }} followers</span>
                    <span v-if="c.token_health && c.token_health !== 'valid'"> · {{ c.token_health }}</span>
                  </p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-2xs font-medium text-slate-600">{{ c.expires_at ? formatExpiry(c.expires_at) : '—' }}</p>
                  <AppButton
                    size="sm"
                    variant="ghost"
                    class="text-2xs text-slate-400 hover:text-slate-600 underline mt-0.5"
                    @click="startRename(c)"
                  >
                    <AppIcon name="edit" class="w-3 h-3 shrink-0" />
                    rename
                  </AppButton>
                </div>
              </div>
              <!-- Rename input row -->
              <div v-else class="flex items-center gap-1.5">
                <AppInput
                  v-model="renameValue"
                  type="text"
                  input-class="!py-1 !text-sm-pro flex-1"
                  placeholder="Account label"
                  @keyup.enter="saveRename(c.id)"
                  @keyup.escape="cancelRename"
                />
                <AppButton size="sm" @click="saveRename(c.id)">
                  <AppIcon name="save" class="w-3.5 h-3.5 shrink-0" />
                  Save
                </AppButton>
                <AppButton variant="secondary" size="sm" @click="cancelRename">
                  <AppIcon name="close" class="w-3.5 h-3.5 shrink-0" />
                </AppButton>
              </div>
              <!-- Actions row -->
              <div v-if="renamingId !== c.id" class="flex items-center gap-1.5 mt-2 pt-2 border-t border-slate-100">
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="compact-sync-btn"
                  :disabled="syncingIds.has(c.id)"
                  @click="syncCredential(c)"
                  title="Sync feeds for this account"
                >
                  <AppIcon name="sync" class="w-3.5 h-3.5 shrink-0" :class="{ 'animate-spin': syncingIds.has(c.id) }" />
                  Sync
                </AppButton>
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="compact-disconnect-btn"
                  @click="disconnect(c)"
                  title="Disconnect account"
                >
                  <AppIcon name="delete" class="w-3.5 h-3.5 shrink-0" />
                  Disconnect
                </AppButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useCredentialsStore } from '../stores/credentials';
import { useOAuthAppsStore } from '../stores/oauthApps';
import { useToastStore } from '../stores/toast';
import { useAuthStore } from '../stores/auth';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { getPlatformLabel } from '../constants/socialPlatforms';
import { AppPageHeader } from '../components/layout/index.js';
import { AppButton, AppCard, AppIcon, AppInput, AppLoader } from '../components/ui';

defineOptions({ name: 'CredentialsView' });

const route = useRoute();
const creds = useCredentialsStore();
const { confirm } = inject('confirm');
const oauthApps = useOAuthAppsStore();
const toast = useToastStore();
const auth = useAuthStore();
const provider = ref('youtube');

const socialProviders = [
  { type: 'youtube', label: 'YouTube', tagline: 'Channel connect', color: '#ef4444', softBg: 'rgba(239,68,68,0.12)' },
  { type: 'google', label: 'Google', tagline: 'Google account', color: '#2563eb', softBg: 'rgba(37,99,235,0.12)' },
  { type: 'facebook', label: 'Facebook', tagline: 'Pages access', color: '#1877f2', softBg: 'rgba(24,119,242,0.14)' },
  { type: 'instagram', label: 'Instagram', tagline: 'Meta business', color: '#db2777', softBg: 'rgba(219,39,119,0.14)' },
  { type: 'twitter', label: 'Twitter / X', tagline: 'API tokens', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'tiktok', label: 'TikTok', tagline: 'Creator access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'threads', label: 'Threads', tagline: 'Meta Threads access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'linkedin', label: 'LinkedIn', tagline: 'Member posts', color: '#0a66c2', softBg: 'rgba(10,102,194,0.12)' },
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
  linkedin: 'LinkedIn',
  other: 'Other',
};
const implementedProviders = ['youtube', 'google', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads', 'linkedin'];
const oauthProviderByProvider = {
  youtube: 'google',
  google: 'google',
  facebook: 'facebook',
  instagram: 'facebook',
  twitter: 'twitter',
  tiktok: 'tiktok',
  threads: 'threads',
  linkedin: 'linkedin',
};
const connectableProviders = computed(() => socialProviders.filter((item) => isProviderConnectable(item.type)));
const providerCards = computed(() => connectableProviders.value.map((item) => {
  const credentials = creds.byProvider?.[item.type] || [];
  return {
    type: item.type,
    credentials,
    hasBroken: credentials.some((c) => c.status && c.status !== 'active'),
  };
}));

function isProviderConnectable(providerType) {
  if (!implementedProviders.includes(providerType)) return false;
  if (creds.byProvider?.[providerType]?.length) return true;
  const oauthProvider = oauthProviderByProvider[providerType];
  if (!oauthProvider) return false;
  return Boolean(oauthApps.effectiveConfigFor(oauthProvider));
}

onMounted(async () => {
  await creds.fetchAll();
  await oauthApps.fetchAll();
  if (!isProviderConnectable(provider.value) && connectableProviders.value.length) {
    provider.value = connectableProviders.value[0].type;
  }
  const connected = route.query.connected;
  const error = route.query.error;
  const message = route.query.message;
  if (connected && implementedProviders.includes(connected)) {
    const label = getPlatformLabel(connected);
    toast.success(`${label} connected successfully`);
    await creds.fetchAll();
    if (window.history.replaceState) {
      window.history.replaceState({}, '', '/credentials');
    }
  } else if (error) {
    toast.error(message ? decodeURIComponent(message) : 'Connection failed');
    if (window.history.replaceState) {
      window.history.replaceState({}, '', '/credentials');
    }
  }
});

async function startConnect(providerType) {
  if (!isProviderConnectable(providerType)) return;
  try {
    await creds.connect(providerType);
  } catch {
    // toast already shown in store
  }
}

const syncingIds = ref(new Set());
const syncingAll = ref(false);

async function syncAllAccounts() {
  syncingAll.value = true;
  try {
    await creds.syncAll();
    await auth.fetchSyncSummary();
    toast.success('All accounts synced');
  } catch {
    // individual errors handled in store
  } finally {
    syncingAll.value = false;
  }
}

async function syncCredential(c) {
  syncingIds.value = new Set([...syncingIds.value, c.id]);
  try {
    const result = await creds.syncCredential(c.id);
    const label = c.account_label || c.account_id || c.provider;
    if (result.status === 'disconnected') {
      toast.error(`Sync failed for "${label}" — token expired. Try reconnecting.`);
    } else {
      toast.success(`Synced "${label}" (${result.synced} feed${result.synced !== 1 ? 's' : ''})`);
      await auth.fetchSyncSummary();
    }
  } catch {
    // toast shown in store
  } finally {
    const next = new Set(syncingIds.value);
    next.delete(c.id);
    syncingIds.value = next;
  }
}

const renamingId = ref(null);
const renameValue = ref('');

function startRename(c) {
  renamingId.value = c.id;
  renameValue.value = c.account_label || '';
}

function cancelRename() {
  renamingId.value = null;
  renameValue.value = '';
}

async function saveRename(id) {
  if (!renameValue.value.trim()) return;
  await creds.renameCredential(id, renameValue.value.trim());
  cancelRename();
}

async function disconnect(c) {
  const label = c.account_label || c.account_id || c.provider;
  if (await confirm({ title: 'Disconnect account?', message: `Disconnect "${label}"?`, confirmLabel: 'Disconnect' })) {
    await creds.disconnect(c.id);
  }
}

function formatExpiry(v) {
  try {
    const diff = new Date(v) - Date.now();
    const abs = Math.abs(diff);
    const days = Math.floor(abs / 86400000);
    const hours = Math.floor(abs / 3600000);
    const mins = Math.floor(abs / 60000);
    const past = diff < 0;
    if (days > 0) return past ? `Expired ${days}d ago` : `Expires in ${days}d`;
    if (hours > 0) return past ? `Expired ${hours}h ago` : `Expires in ${hours}h`;
    return past ? `Expired ${mins}m ago` : `Expires in ${mins}m`;
  } catch {
    return String(v);
  }
}

function formatSynced(v) {
  try {
    const diff = Date.now() - new Date(v);
    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    if (mins < 1) return 'Synced just now';
    if (mins < 60) return `Synced ${mins}m ago`;
    if (hours < 24) return `Synced ${hours}h ago`;
    return `Synced ${days}d ago`;
  } catch {
    return 'Last synced unknown';
  }
}
</script>

<style scoped>
.credentials-provider-panel {
  background:
    radial-gradient(700px 200px at -8% -60%, rgba(30, 58, 138, 0.05), transparent 62%),
    radial-gradient(560px 200px at 120% -58%, rgba(30, 58, 138, 0.04), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}

.credentials-provider-card {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 0.65rem 0.75rem;
  transition: all 0.18s ease;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.credentials-provider-card:hover {
  border-color: rgba(30, 58, 138, 0.2);
  background: rgba(239, 246, 255, 0.5);
}

.credentials-provider-card--active {
  border-color: rgba(30, 58, 138, 0.35);
  background: rgba(239, 246, 255, 0.9);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.oauth-empty-state {
  border: 1px dashed #d1d9e6;
  border-radius: 0.875rem;
  background: #f8fafc;
  padding: 0.7rem 0.8rem;
}

.credentials-provider-icon {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.credentials-step {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid #e6ebf2;
  background: #fff;
  border-radius: 999px;
  padding: 0.28rem 0.62rem;
  color: #64748b;
  font-size: 0.68rem;
}

.credentials-step__dot {
  width: 1.05rem;
  height: 1.05rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #f1f5f9;
  color: #475569;
  font-size: 0.62rem;
  font-weight: 600;
}

.credentials-step--active {
  border-color: rgba(30, 58, 138, 0.3);
  color: #1e3a8a;
  background: rgba(239, 246, 255, 0.9);
}

.credentials-step--active .credentials-step__dot {
  background: #1e3a8a;
  color: white;
}

.credentials-step-divider {
  width: 1rem;
  height: 1px;
  background: #e2e8f0;
}

.connected-accounts-shell {
  background: transparent;
}

.connected-provider-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.04);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.connected-provider-card--broken {
  border-color: rgba(251, 113, 133, 0.5);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 0 0 3px rgba(254, 205, 211, 0.4);
}

.connected-provider-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.connected-provider-count {
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  color: #64748b;
  border-radius: 999px;
  padding: 0.15rem 0.45rem;
  font-size: 0.68rem;
}

.connected-provider-count--broken {
  border-color: rgba(252, 165, 165, 0.6);
  background: rgba(255, 241, 242, 0.9);
  color: #be123c;
}

.connected-account-row {
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid #f1f5f9;
  border-radius: 0.75rem;
  background: #f8fafc;
  padding: 0.5rem 0.55rem;
  transition: border-color 0.15s ease;
}

.connected-account-row--broken {
  border-color: rgba(252, 165, 165, 0.45);
  background: rgba(255, 248, 248, 0.9);
}

.connected-account-empty {
  border: 1px dashed #d1d9e6;
  border-radius: 0.75rem;
  background: #f8fafc;
  padding: 0.75rem 0.85rem;
}

.credential-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border-radius: 999px;
  padding: 0.1rem 0.45rem;
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  flex-shrink: 0;
}

.credential-status-badge--active {
  background: rgba(220, 252, 231, 0.9);
  color: #15803d;
  border: 1px solid rgba(134, 239, 172, 0.5);
}

.credential-status-badge--broken {
  background: rgba(255, 228, 230, 0.9);
  color: #be123c;
  border: 1px solid rgba(252, 165, 165, 0.5);
}

.credential-status-dot {
  width: 0.38rem;
  height: 0.38rem;
  border-radius: 999px;
  background: currentColor;
  flex-shrink: 0;
}

.compact-accounts-shell .connected-provider-card {
  border-radius: 0.875rem;
}

.compact-sync-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: 1px solid rgba(203, 213, 225, 0.9);
  background: rgba(248, 250, 252, 0.8);
  color: #475569;
  border-radius: 0.5rem;
  padding: 0.22rem 0.38rem;
  font-size: 0.68rem;
  line-height: 1;
  transition: all 0.16s ease;
}

.compact-sync-btn:hover:not(:disabled) {
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(238, 242, 255, 0.9);
  color: #4338ca;
}

.compact-sync-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.compact-disconnect-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: 1px solid rgba(254, 205, 211, 0.9);
  background: rgba(255, 241, 242, 0.8);
  color: rgb(190 18 60);
  border-radius: 0.5rem;
  padding: 0.22rem 0.38rem;
  font-size: 0.68rem;
  line-height: 1;
  transition: all 0.16s ease;
}

.compact-disconnect-btn:hover {
  border-color: rgba(251, 113, 133, 0.55);
  background: rgba(255, 228, 230, 0.92);
  color: rgb(159 18 57);
}
</style>
