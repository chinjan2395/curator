<template>
  <div class="space-y-4">
    <div>
      <h1 class="page-title flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path d="M10 2.5a4.5 4.5 0 0 0-4.5 4.5v1H5A2 2 0 0 0 3 10v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-.5V7A4.5 4.5 0 0 0 10 2.5Zm3 5.5V7a3 3 0 1 0-6 0v1h6Z" />
        </svg>
        Credentials
      </h1>
      <p class="page-kicker">Connected social accounts (including configured providers without accounts yet).</p>
    </div>

    <div v-if="creds.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="creds.error" class="text-sm-pro text-red-600">{{ creds.error }}</div>
    <div v-else-if="!providerCards.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      No providers are ready yet. Configure shared defaults or your override in
      <router-link to="/oauth-apps" class="text-indigo-600 font-medium hover:underline">OAuth apps</router-link>.
    </div>
    <div v-else class="connected-accounts-shell compact-accounts-shell space-y-2.5 p-2.5 sm:p-3 rounded-xl">
      <div class="flex flex-wrap items-center justify-between gap-2 px-1">
        <p class="text-xs-pro font-medium text-slate-700">Current connected accounts</p>
        <p class="text-2xs text-slate-500">{{ creds.list.length }} total account{{ creds.list.length > 1 ? 's' : '' }}</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <div
          v-for="card in providerCards"
          :key="card.type"
          class="connected-provider-card overflow-hidden"
        >
          <div class="px-3 py-2 border-b border-slate-100/95 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <div class="connected-provider-icon" :style="{ background: providerUi[card.type]?.softBg || 'rgba(148,163,184,0.14)', color: providerUi[card.type]?.color || '#475569' }">
                <SocialIcon :type="card.type" />
              </div>
              <div class="min-w-0">
                <p class="text-xs-pro font-semibold text-slate-800 truncate">{{ providerLabels[card.type] || card.type }}</p>
                <p class="text-2xs text-slate-500 truncate">{{ providerUi[card.type]?.tagline || 'Connected accounts' }}</p>
              </div>
              <span class="connected-provider-count">{{ card.credentials.length }} account{{ card.credentials.length > 1 ? 's' : '' }}</span>
            </div>
            <button
              type="button"
              class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro"
              :disabled="creds.connecting"
              @click="startConnect(card.type)"
              title="Add another account"
            >
              + Add
            </button>
          </div>
          <div v-if="!card.credentials.length" class="p-2">
            <div class="connected-account-empty text-2xs text-slate-500">
              Configured, but no account connected yet.
            </div>
          </div>
          <div v-else class="p-2 space-y-2">
            <div v-for="c in card.credentials" :key="c.id" class="connected-account-row">
              <div class="min-w-0">
                  <div v-if="renamingId !== c.id" class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 truncate">{{ c.account_label || c.account_id || '—' }}</span>
                    <button
                      type="button"
                      class="text-2xs text-slate-400 hover:text-slate-600 underline"
                      @click="startRename(c)"
                    >edit</button>
                  </div>
                  <div v-else class="flex items-center gap-1.5">
                    <input
                      v-model="renameValue"
                      type="text"
                      class="input-pro !py-1 !text-sm-pro flex-1"
                      placeholder="Account label"
                      @keyup.enter="saveRename(c.id)"
                      @keyup.escape="cancelRename"
                    />
                    <button type="button" class="btn-primary !w-auto !py-1 !px-2 text-xs-pro" @click="saveRename(c.id)">Save</button>
                    <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro" @click="cancelRename">✕</button>
                  </div>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                  <p class="compact-expiry text-2xs text-slate-500">
                    <span class="font-medium text-slate-600">{{ c.expires_at ? formatDate(c.expires_at) : '—' }}</span>
                  </p>
                  <button
                    type="button"
                    class="action-link !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80"
                    @click="disconnect(c)"
                  >
                    Disconnect
                  </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useCredentialsStore } from '../stores/credentials';
import { useOAuthAppsStore } from '../stores/oauthApps';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';

const route = useRoute();
const creds = useCredentialsStore();
const oauthApps = useOAuthAppsStore();
const toast = useToastStore();
const provider = ref('youtube');

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
const implementedProviders = ['youtube', 'google', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads'];
const oauthProviderByProvider = {
  youtube: 'google',
  google: 'google',
  facebook: 'facebook',
  instagram: 'facebook',
  twitter: 'twitter',
  tiktok: 'tiktok',
  threads: 'threads',
};
const connectableProviders = computed(() => socialProviders.filter((item) => isProviderConnectable(item.type)));
const providerCards = computed(() => connectableProviders.value.map((item) => ({
  type: item.type,
  credentials: creds.byProvider?.[item.type] || [],
})));

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
    const label = providerLabels[connected] || connected;
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
  if (window.confirm(`Disconnect "${label}"?`)) {
    await creds.disconnect(c.id);
  }
}

function formatDate(v) {
  try {
    return new Date(v).toLocaleString();
  } catch {
    return String(v);
  }
}
</script>

<style scoped>
.credentials-provider-panel {
  background:
    radial-gradient(700px 200px at -8% -60%, rgba(56, 189, 248, 0.09), transparent 62%),
    radial-gradient(560px 200px at 120% -58%, rgba(99, 102, 241, 0.11), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}

.credentials-provider-card {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 0.75rem;
  background: rgba(255, 255, 255, 0.92);
  padding: 0.65rem 0.75rem;
  transition: all 0.18s ease;
}

.credentials-provider-card:hover {
  border-color: rgba(165, 180, 252, 0.72);
  background: rgba(238, 242, 255, 0.62);
}

.credentials-provider-card--active {
  border-color: rgba(99, 102, 241, 0.55);
  background: rgba(238, 242, 255, 0.92);
  box-shadow: 0 10px 24px -18px rgba(79, 70, 229, 0.85);
}

.oauth-empty-state {
  border: 1px dashed rgba(203, 213, 225, 0.9);
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.72);
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
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(255, 255, 255, 0.72);
  border-radius: 999px;
  padding: 0.28rem 0.62rem;
  color: rgb(100 116 139);
  font-size: 0.68rem;
}

.credentials-step__dot {
  width: 1.05rem;
  height: 1.05rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(226, 232, 240, 0.88);
  color: rgb(71 85 105);
  font-size: 0.62rem;
  font-weight: 600;
}

.credentials-step--active {
  border-color: rgba(129, 140, 248, 0.55);
  color: rgb(67 56 202);
  background: rgba(238, 242, 255, 0.84);
}

.credentials-step--active .credentials-step__dot {
  background: rgba(99, 102, 241, 0.95);
  color: white;
}

.credentials-step-divider {
  width: 1rem;
  height: 1px;
  background: rgba(203, 213, 225, 0.9);
}

.connected-accounts-shell {
  background:
    radial-gradient(640px 180px at -6% -50%, rgba(56, 189, 248, 0.08), transparent 62%),
    radial-gradient(540px 180px at 115% -50%, rgba(99, 102, 241, 0.09), transparent 62%),
    linear-gradient(180deg, rgba(248, 250, 252, 0.7), rgba(248, 250, 252, 0.45));
}

.connected-provider-card {
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 0.9rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 12px 28px -28px rgba(15, 23, 42, 0.6);
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
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(248, 250, 252, 0.9);
  color: rgb(100 116 139);
  border-radius: 999px;
  padding: 0.15rem 0.45rem;
  font-size: 0.68rem;
}

.connected-account-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.55rem;
  border: 1px solid rgba(226, 232, 240, 0.85);
  border-radius: 0.7rem;
  background: rgba(248, 250, 252, 0.68);
  padding: 0.45rem 0.55rem;
}

.connected-account-empty {
  border: 1px dashed rgba(226, 232, 240, 0.95);
  border-radius: 0.65rem;
  background: rgba(248, 250, 252, 0.75);
  padding: 0.6rem 0.65rem;
}

.compact-accounts-shell .connected-provider-card {
  border-radius: 0.75rem;
}

.compact-expiry {
  white-space: nowrap;
  padding-right: 0.15rem;
}
</style>

