<template>
  <div class="space-y-4">
    <div class="space-y-3 mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-one-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2.5a4.5 4.5 0 0 0-4.5 4.5v1H5A2 2 0 0 0 3 10v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-.5V7A4.5 4.5 0 0 0 10 2.5Zm3 5.5V7a3 3 0 1 0-6 0v1h6Z" />
          </svg>
          Credentials
        </h1>
        <p class="page-kicker">Connect providers and manage OAuth settings.</p>
      </div>
      <div class="surface-card credentials-provider-panel p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs-pro font-semibold text-one-text">Select social media</p>
          <p class="text-2xs text-one-sub">Matches Add/Update Feed selection flow</p>
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
              <div class="text-sm-pro font-medium text-one-text truncate">{{ item.label }}</div>
              <div class="text-2xs text-one-sub truncate">{{ item.tagline }}</div>
            </div>
          </button>
        </div>
        <div class="mt-3 flex items-center justify-between gap-3">
          <p class="text-2xs text-one-sub">Selected: <span class="font-semibold text-one-text">{{ providerLabels[provider] || provider }}</span></p>
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
          <div class="text-sm-pro font-semibold text-one-text flex items-center gap-1.5">
            <svg class="w-4 h-4 text-one-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
            </svg>
            OAuth app settings
          </div>
          <div class="mt-0.5 text-2xs text-one-sub">Configure client credentials for <span class="font-semibold text-one-text">{{ providerLabels[provider] || provider }}</span> (stored encrypted).</div>
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
              <span class="font-semibold text-one-text">{{ selectedCallbackPath }}</span>
            </p>
          </div>
        </div>
        <div class="oauth-hints">
          <p class="text-2xs text-one-sub">Common callback paths:</p>
          <p class="text-2xs text-one-sub">
            Google / YouTube: <span class="font-medium">/api/social/callback/google</span> ·
            Facebook / Instagram: <span class="font-medium">/api/social/callback/facebook</span>
          </p>
          <p class="text-2xs text-one-sub">
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
          <div v-if="oauthApps.error" class="text-2xs text-rose-600">{{ oauthApps.error }}</div>
        </div>
      </div>
    </div>

    <div v-if="creds.loading" class="surface-card flex items-center gap-2 text-sm-pro text-one-sub px-5 py-4">
      <span class="inline-block w-4 h-4 border-2 border-one-divider border-t-one-primary rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="creds.error" class="text-sm-pro text-rose-600">{{ creds.error }}</div>
    <div v-else-if="!creds.list.length" class="surface-card p-6 text-center text-sm-pro text-one-sub">
      No credentials connected yet.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="(providerCreds, prov) in creds.byProvider"
        :key="prov"
        class="surface-card overflow-hidden"
      >
        <div class="px-4 py-3 border-b border-one-divider flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <div class="type-dot" :class="`type-dot--${prov}`">
              <SocialIcon :type="prov" />
            </div>
            <span class="text-sm-pro font-semibold text-one-text">{{ providerLabels[prov] || prov }}</span>
            <span class="text-2xs text-one-muted">({{ providerCreds.length }} account{{ providerCreds.length > 1 ? 's' : '' }})</span>
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
        <table class="w-full text-left">
          <tbody class="divide-y divide-one-divider">
            <tr v-for="c in providerCreds" :key="c.id" class="table-tr">
              <td class="table-td">
                <div v-if="renamingId !== c.id" class="flex items-center gap-2">
                  <span class="font-semibold text-one-text">{{ c.account_label || c.account_id || '—' }}</span>
                  <button
                    type="button"
                    class="text-2xs text-one-muted hover:text-one-sub underline"
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
              </td>
              <td class="table-td text-2xs text-one-sub">{{ c.expires_at ? formatDate(c.expires_at) : '—' }}</td>
              <td class="table-td w-28">
                <button
                  type="button"
                  class="action-link action-link--destructive"
                  @click="disconnect(c)"
                >
                  Disconnect
                </button>
              </td>
            </tr>
          </tbody>
        </table>
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
.credentials-provider-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 14px;
  background: #F4F4F6;
  padding: 0.65rem 0.75rem;
  transition: background-color 0.15s ease;
  text-align: left;
}

.credentials-provider-card:hover {
  background: #EBF1FB;
}

.credentials-provider-card--active {
  background: #EBF1FB;
  outline: 2px solid #1259C3;
  outline-offset: -2px;
}

.credentials-provider-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.credentials-step {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: #F4F4F6;
  border-radius: 999px;
  padding: 0.3rem 0.65rem;
  color: #6E6E73;
  font-size: 0.75rem;
}

.credentials-step__dot {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #E5E5EA;
  color: #1C1C1E;
  font-size: 0.62rem;
  font-weight: 700;
}

.credentials-step--active {
  background: #EBF1FB;
  color: #1259C3;
}

.credentials-step--active .credentials-step__dot {
  background: #1259C3;
  color: white;
}

.credentials-step-divider {
  width: 1rem;
  height: 1px;
  background: #E5E5EA;
}

.oauth-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: #F4F4F6;
  color: #6E6E73;
  font-size: 0.72rem;
  padding: 0.3rem 0.6rem;
  border-radius: 999px;
}

.oauth-status-pill__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: #AEAEB2;
}

.oauth-status-pill--ok {
  background: #E6F9F0;
  color: #1C8C52;
}

.oauth-status-pill--ok .oauth-status-pill__dot {
  background: #1C8C52;
}

.oauth-empty {
  border-radius: 14px;
  background: #F4F4F6;
  padding: 0.8rem 1rem;
  color: #6E6E73;
  font-size: 0.75rem;
}

.oauth-field-card {
  border-radius: 14px;
  background: #F4F4F6;
  padding: 0.85rem;
}

.oauth-hints {
  border-radius: 14px;
  background: #F4F4F6;
  padding: 0.7rem 0.85rem;
  display: grid;
  gap: 0.25rem;
}

.type-dot {
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #F4F4F6;
  color: #6E6E73;
}

.type-dot :deep(svg) {
  width: 0.9rem;
  height: 0.9rem;
}

.type-dot--youtube { background: #FEE2E2; color: #DC2626; }
.type-dot--facebook { background: #DBEAFE; color: #2563EB; }
.type-dot--instagram { background: #FCE7F3; color: #BE185D; }
.type-dot--tiktok { background: #F4F4F6; color: #1C1C1E; }
.type-dot--threads { background: #F4F4F6; color: #1C1C1E; }
.type-dot--rss { background: #FFEDD5; color: #EA580C; }
.type-dot--twitter { background: #F4F4F6; color: #1C1C1E; }
</style>

