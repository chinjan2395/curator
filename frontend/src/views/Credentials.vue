<template>
  <div class="space-y-4">
    <div class="space-y-3 mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2.5a4.5 4.5 0 0 0-4.5 4.5v1H5A2 2 0 0 0 3 10v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-.5V7A4.5 4.5 0 0 0 10 2.5Zm3 5.5V7a3 3 0 1 0-6 0v1h6Z" />
          </svg>
          Credentials
        </h1>
        <p class="page-kicker">Connect providers and manage OAuth settings.</p>
      </div>
      <div class="surface-card credentials-provider-panel p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs-pro font-medium text-slate-700">Select social media</p>
          <p class="text-2xs text-slate-500">Matches Add/Update Feed selection flow</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
          <button
            v-for="item in socialProviders"
            :key="item.type"
            type="button"
            class="credentials-provider-card"
            :class="provider === item.type ? 'credentials-provider-card--active' : ''"
            @click="provider = item.type"
          >
            <div class="credentials-provider-icon" :style="{ background: item.softBg, color: item.color }">
              <SocialIcon :type="item.type" class="w-4 h-4" />
            </div>
            <div class="text-left min-w-0">
              <div class="text-sm-pro font-medium text-slate-800 truncate">{{ item.label }}</div>
              <div class="text-2xs text-slate-500 truncate">{{ item.tagline }}</div>
            </div>
          </button>
        </div>
        <div class="mt-3 flex items-center justify-between gap-3">
          <p class="text-2xs text-slate-500">Selected: <span class="font-medium text-slate-700">{{ providerLabels[provider] || provider }}</span></p>
          <button type="button" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro" @click="startConnect" :disabled="creds.connecting">
            {{ creds.connecting ? 'Redirecting…' : connectButtonLabel }}
          </button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2.5">
          <div class="credentials-step" :class="provider ? 'credentials-step--active' : ''">
            <span class="credentials-step__dot">1</span>
            Choose platform
          </div>
          <div class="credentials-step-divider" />
          <div class="credentials-step" :class="supportsOAuthApp ? 'credentials-step--active' : ''">
            <span class="credentials-step__dot">2</span>
            Configure OAuth
          </div>
          <div class="credentials-step-divider" />
          <div class="credentials-step" :class="oauthApps.configFor(oauthProviderKey) ? 'credentials-step--active' : ''">
            <span class="credentials-step__dot">3</span>
            Save settings
          </div>
        </div>
      </div>
    </div>

    <div class="surface-card oauth-settings-shell p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <div class="text-sm-pro font-semibold text-slate-800 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
            </svg>
            OAuth app settings
          </div>
          <div class="mt-0.5 text-2xs text-slate-500">Configure client credentials for <span class="font-medium text-slate-700">{{ providerLabels[provider] || provider }}</span> (stored encrypted).</div>
        </div>
        <div class="oauth-status-pill" :class="oauthApps.configFor(oauthProviderKey) ? 'oauth-status-pill--ok' : ''">
          <span class="oauth-status-pill__dot" />
          {{ oauthApps.configFor(oauthProviderKey) ? 'Configured' : 'Not configured' }}
        </div>
      </div>

      <div v-if="!supportsOAuthApp" class="oauth-empty text-2xs text-slate-500">
        OAuth app settings are not required for this provider. You can still connect if this integration supports direct auth.
      </div>
      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="oauth-field-card sm:col-span-1">
            <label class="label-pro">Client ID</label>
            <input v-model="oauthForm.client_id" type="text" class="input-pro" placeholder="OAuth Client ID" />
          </div>
          <div class="oauth-field-card sm:col-span-1">
            <label class="label-pro">Client secret</label>
            <input v-model="oauthForm.client_secret" type="password" class="input-pro" placeholder="OAuth Client Secret" />
            <p class="mt-1 text-2xs text-slate-500">Saved encrypted. Leave blank to keep the existing secret.</p>
          </div>
          <div class="oauth-field-card sm:col-span-2">
            <label class="label-pro">Redirect URI</label>
            <input
              v-model="oauthForm.redirect_uri"
              type="url"
              class="input-pro"
              placeholder="https://your-backend-domain.com/api/social/callback/youtube"
            />
            <p class="mt-1 text-2xs text-slate-500">
              Use your backend callback URL. Suggested for this selection:
              <span class="font-medium text-slate-700">{{ selectedCallbackPath }}</span>
            </p>
          </div>
        </div>
        <div class="oauth-hints">
          <p class="text-2xs text-slate-500">Common callback paths:</p>
          <p class="text-2xs text-slate-500">
            Google / YouTube: <span class="font-medium">/api/social/callback/google</span> ·
            Facebook / Instagram: <span class="font-medium">/api/social/callback/facebook</span>
          </p>
          <p class="text-2xs text-slate-500">
            Twitter / X: <span class="font-medium">/api/social/callback/twitter</span> ·
            TikTok: <span class="font-medium">/api/social/callback/tiktok</span> ·
            Threads: <span class="font-medium">/api/social/callback/threads</span>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro"
            :disabled="oauthApps.saving || !oauthForm.client_id"
            @click="saveOauth"
          >
            {{ oauthApps.saving ? 'Saving…' : 'Save OAuth settings' }}
          </button>
          <button
            v-if="oauthApps.configFor(oauthProviderKey)"
            type="button"
            class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro"
            @click="removeOauth"
          >
            Remove
          </button>
          <div v-if="oauthApps.error" class="text-2xs text-red-600">{{ oauthApps.error }}</div>
        </div>
      </div>
    </div>

    <div v-if="creds.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="creds.error" class="text-sm-pro text-red-600">{{ creds.error }}</div>
    <div v-else-if="!creds.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      No credentials connected yet.
    </div>
    <div v-else class="connected-accounts-shell space-y-3 p-3 sm:p-4 rounded-xl">
      <div class="flex flex-wrap items-center justify-between gap-2 px-1">
        <p class="text-xs-pro font-medium text-slate-700">Current connected accounts</p>
        <p class="text-2xs text-slate-500">{{ creds.list.length }} total account{{ creds.list.length > 1 ? 's' : '' }}</p>
      </div>
      <div
        v-for="(providerCreds, prov) in creds.byProvider"
        :key="prov"
        class="connected-provider-card overflow-hidden"
      >
        <div class="px-4 py-3 border-b border-slate-100/95 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <div class="connected-provider-icon" :style="{ background: providerUi[prov]?.softBg || 'rgba(148,163,184,0.14)', color: providerUi[prov]?.color || '#475569' }">
              <SocialIcon :type="prov" />
            </div>
            <div>
              <p class="text-sm-pro font-semibold text-slate-800">{{ providerLabels[prov] || prov }}</p>
              <p class="text-2xs text-slate-500">{{ providerUi[prov]?.tagline || 'Connected accounts' }}</p>
            </div>
            <span class="connected-provider-count">{{ providerCreds.length }} account{{ providerCreds.length > 1 ? 's' : '' }}</span>
          </div>
          <button
            type="button"
            class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro"
            :disabled="creds.connecting"
            @click="provider = prov; startConnect()"
            title="Add another account"
          >
            + Add
          </button>
        </div>
        <div class="p-2 space-y-2">
          <div v-for="c in providerCreds" :key="c.id" class="connected-account-row">
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
              <p class="text-2xs text-slate-500 mt-1">
                Expires: <span class="font-medium text-slate-600">{{ c.expires_at ? formatDate(c.expires_at) : '—' }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2">
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
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useCredentialsStore } from '../stores/credentials';
import { useToastStore } from '../stores/toast';
import { useOAuthAppsStore } from '../stores/oauthApps';
import SocialIcon from '../components/SocialIcon.vue';

const route = useRoute();
const creds = useCredentialsStore();
const toast = useToastStore();
const oauthApps = useOAuthAppsStore();
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

const oauthProviderKey = computed(() => oauthProviderByProvider[provider.value] || provider.value);
const supportsOAuthApp = computed(() => Boolean(oauthProviderByProvider[provider.value]));
const selectedCallbackPath = computed(() => `/api/social/callback/${oauthProviderKey.value}`);
const oauthForm = reactive({
  client_id: '',
  client_secret: '',
  redirect_uri: '',
});

function hydrateOauthForm() {
  if (!supportsOAuthApp.value) return;
  const existing = oauthApps.configFor(oauthProviderKey.value);
  oauthForm.client_id = existing?.client_id || '';
  oauthForm.client_secret = '';
  oauthForm.redirect_uri = existing?.redirect_uri || '';
}
const connectButtonLabel = computed(() => {
  const label = providerLabels[provider.value] || provider.value;
  return implementedProviders.includes(provider.value)
    ? `Connect ${label}`
    : 'Connect (coming soon)';
});

onMounted(async () => {
  await creds.fetchAll();
  await oauthApps.fetchAll();
  hydrateOauthForm();
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

watch(provider, () => {
  hydrateOauthForm();
});

async function startConnect() {
  try {
    await creds.connect(provider.value);
  } catch {
    // toast already shown in store
  }
}

async function saveOauth() {
  if (!supportsOAuthApp.value) return;
  const existing = oauthApps.configFor(oauthProviderKey.value);
  await oauthApps.save({
    provider: oauthProviderKey.value,
    client_id: oauthForm.client_id,
    client_secret: oauthForm.client_secret || (existing ? '__KEEP__' : ''),
    redirect_uri: oauthForm.redirect_uri || null,
  });
  hydrateOauthForm();
}

async function removeOauth() {
  if (!supportsOAuthApp.value) return;
  if (window.confirm('Remove OAuth app settings?')) {
    await oauthApps.remove(oauthProviderKey.value);
    hydrateOauthForm();
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

.oauth-settings-shell {
  background:
    radial-gradient(620px 180px at 105% -45%, rgba(79, 70, 229, 0.08), transparent 60%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.94));
}

.oauth-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(248, 250, 252, 0.9);
  color: rgb(100 116 139);
  font-size: 0.68rem;
  padding: 0.28rem 0.55rem;
  border-radius: 999px;
}

.oauth-status-pill__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: rgb(148 163 184);
}

.oauth-status-pill--ok {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(236, 253, 245, 0.92);
  color: rgb(6 95 70);
}

.oauth-status-pill--ok .oauth-status-pill__dot {
  background: rgb(5 150 105);
}

.oauth-empty {
  border: 1px dashed rgba(203, 213, 225, 0.9);
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.72);
  padding: 0.8rem 0.9rem;
}

.oauth-field-card {
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 0.75rem;
  background: rgba(255, 255, 255, 0.92);
  padding: 0.75rem;
}

.oauth-hints {
  border: 1px dashed rgba(203, 213, 225, 0.9);
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.82);
  padding: 0.65rem 0.75rem;
  display: grid;
  gap: 0.2rem;
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
  width: 2rem;
  height: 2rem;
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
  padding: 0.18rem 0.5rem;
  font-size: 0.68rem;
}

.connected-account-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border: 1px solid rgba(226, 232, 240, 0.85);
  border-radius: 0.7rem;
  background: rgba(248, 250, 252, 0.68);
  padding: 0.6rem 0.7rem;
}
</style>

