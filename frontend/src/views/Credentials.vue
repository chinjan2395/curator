<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2.5a4.5 4.5 0 0 0-4.5 4.5v1H5A2 2 0 0 0 3 10v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-.5V7A4.5 4.5 0 0 0 10 2.5Zm3 5.5V7a3 3 0 1 0-6 0v1h6Z" />
          </svg>
          Credentials
        </h1>
        <p class="page-kicker">Connect providers and manage OAuth settings.</p>
      </div>
      <div class="surface-card-soft flex items-center gap-2 px-2 py-2">
        <select v-model="provider" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto">
          <option value="youtube">YouTube</option>
          <option value="google">Google</option>
          <option value="facebook">Facebook</option>
          <option value="instagram">Instagram</option>
          <option value="twitter">Twitter / X</option>
          <option value="tiktok">TikTok</option>
          <option value="other">Other</option>
        </select>
        <button type="button" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro" @click="startConnect" :disabled="creds.connecting">
          {{ creds.connecting ? 'Redirecting…' : connectButtonLabel }}
        </button>
      </div>
    </div>

    <div class="surface-card p-4">
      <div class="flex items-center justify-between mb-3">
        <div>
          <div class="text-sm-pro font-medium text-slate-800 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
            </svg>
            OAuth app settings
          </div>
          <div class="text-2xs text-slate-500">
            Configure the OAuth client used to connect providers (stored encrypted).
          </div>
        </div>
      </div>

      <div v-if="!supportsOAuthApp" class="text-2xs text-slate-500">
        OAuth app settings are not required for this provider.
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-1">
          <label class="label-pro">Client ID</label>
          <input v-model="oauthForm.client_id" type="text" class="input-pro" placeholder="OAuth Client ID" />
        </div>
        <div class="sm:col-span-1">
          <label class="label-pro">Client secret</label>
          <input v-model="oauthForm.client_secret" type="password" class="input-pro" placeholder="OAuth Client Secret" />
          <p class="mt-1 text-2xs text-slate-500">
            Saved encrypted. If you leave this blank, we’ll keep the existing secret.
          </p>
        </div>
        <div class="sm:col-span-2">
          <label class="label-pro">Redirect URI</label>
          <input
            v-model="oauthForm.redirect_uri"
            type="url"
            class="input-pro"
            placeholder="https://your-backend-domain.com/api/social/callback/youtube"
          />
          <p class="mt-1 text-2xs text-slate-500">
            Use your backend callback URL. For YouTube: <span class="font-medium">/api/social/callback/youtube</span>. For Google: <span class="font-medium">/api/social/callback/google</span>.
          </p>
          <p class="mt-1 text-2xs text-slate-500">
            For Facebook + Instagram use: <span class="font-medium">/api/social/callback/facebook</span>. For Twitter / X use: <span class="font-medium">/api/social/callback/twitter</span>.
          </p>
          <p class="mt-1 text-2xs text-slate-500">
            For TikTok use: <span class="font-medium">/api/social/callback/tiktok</span>.
          </p>
        </div>
        <div class="sm:col-span-2 flex items-center gap-2">
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
    <div v-else class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">Provider</th>
            <th class="table-th">Expires</th>
            <th class="table-th w-32">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="c in creds.list" :key="c.id" class="table-tr">
            <td class="table-td font-medium text-slate-800">{{ c.provider }}</td>
            <td class="table-td">{{ c.expires_at ? formatDate(c.expires_at) : '—' }}</td>
            <td class="table-td">
              <button type="button" class="action-link !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80" @click="disconnect(c.provider)">
                Disconnect
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useCredentialsStore } from '../stores/credentials';
import { useToastStore } from '../stores/toast';
import { useOAuthAppsStore } from '../stores/oauthApps';

const route = useRoute();
const creds = useCredentialsStore();
const toast = useToastStore();
const oauthApps = useOAuthAppsStore();
const provider = ref('youtube');

const providerLabels = {
  youtube: 'YouTube',
  google: 'Google',
  facebook: 'Facebook',
  instagram: 'Instagram',
  twitter: 'Twitter / X',
  tiktok: 'TikTok',
  other: 'Other',
};
const implementedProviders = ['youtube', 'google', 'facebook', 'instagram', 'twitter', 'tiktok'];
const oauthProviderByProvider = {
  youtube: 'google',
  google: 'google',
  facebook: 'facebook',
  instagram: 'facebook',
  twitter: 'twitter',
  tiktok: 'tiktok',
};

const oauthProviderKey = computed(() => oauthProviderByProvider[provider.value] || provider.value);
const supportsOAuthApp = computed(() => Boolean(oauthProviderByProvider[provider.value]));
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

async function disconnect(p) {
  if (window.confirm(`Disconnect ${p}?`)) {
    await creds.disconnect(p);
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

