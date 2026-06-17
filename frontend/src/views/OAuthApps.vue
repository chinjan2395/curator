<template>
  <div class="space-y-5">
    <AppPageHeader
      title="OAuth app settings"
      subtitle="Manage shared defaults and optional per-user overrides."
      icon="cog"
    />

    <AppCard v-if="oauthApps.isAdmin" class="oauth-admin-toolbar p-4">
      <div class="oauth-admin-toolbar__copy">
        <p class="oauth-section-kicker">Admin tools</p>
        <p class="text-sm-pro font-semibold text-slate-800">Promote existing admin configs</p>
        <p class="text-2xs text-slate-500 mt-1">Copy all your user overrides into shared defaults in one click.</p>
      </div>
      <div class="oauth-admin-toolbar__actions">
        <AppButton variant="secondary" size="sm" :disabled="oauthApps.promoting" @click="promoteToShared(false)">
          {{ oauthApps.promoting ? 'Promoting…' : 'Promote (skip existing)' }}
        </AppButton>
        <AppButton variant="secondary" size="sm" :disabled="oauthApps.promoting" @click="promoteToShared(true)">
          Promote (overwrite shared)
        </AppButton>
      </div>
    </AppCard>

    <AppCard class="credentials-provider-panel p-4 md:p-5">
      <div class="oauth-section-intro">
        <div>
          <p class="oauth-section-kicker">Step 1</p>
          <p class="text-sm-pro font-semibold text-slate-800">Choose the provider you want to configure</p>
          <p class="text-2xs text-slate-500 mt-1">Pick a social platform, then review its shared defaults or your personal override.</p>
        </div>
        <div class="oauth-inline-stat">
          <span class="oauth-inline-stat__value">{{ connectedCountFor(provider) }}</span>
          <span class="oauth-inline-stat__label">{{ providerLabels[provider] || provider }} accounts connected</span>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <AppButton
          v-for="item in socialProviders"
          :key="item.type"
          variant="ghost"
          class="credentials-provider-card"
          :class="provider === item.type ? 'credentials-provider-card--active' : ''"
          @click="provider = item.type"
        >
          <SocialPlatformLabel :type="item.type" variant="badge" size="sm" class="flex-shrink-0" />
          <div class="text-left min-w-0 flex-1">
            <div class="text-2xs text-slate-500 truncate">{{ item.tagline }}</div>
          </div>
          <span
            class="provider-connection-pill"
            :class="connectedCountFor(item.type) > 0 ? 'provider-connection-pill--ok' : ''"
          >
            {{ connectedCountFor(item.type) > 0 ? `${connectedCountFor(item.type)} connected` : 'Not connected' }}
          </span>
        </AppButton>
      </div>
    </AppCard>

    <AppCard class="oauth-settings-shell p-4 md:p-5">
      <div class="oauth-config-layout">
        <aside class="oauth-overview-panel">
          <div class="oauth-provider-hero">
            <div class="min-w-0 flex-1">
              <p class="oauth-section-kicker">Step 2</p>
              <SocialPlatformLabel :type="provider" variant="badge" size="md" class="mt-1" />
              <p class="text-2xs text-slate-500 mt-1">{{ currentProviderMeta.tagline }}</p>
            </div>
          </div>

          <div class="oauth-overview-grid">
            <div class="oauth-overview-stat">
              <span class="oauth-overview-stat__label">Status</span>
              <div class="oauth-status-pill" :class="oauthConfigExists ? 'oauth-status-pill--ok' : ''">
                <span class="oauth-status-pill__dot" />
                {{ oauthConfigExists ? 'Configured' : 'Not configured' }}
              </div>
            </div>
            <div class="oauth-overview-stat">
              <span class="oauth-overview-stat__label">Effective source</span>
              <span class="oauth-overview-stat__value">{{ oauthSourceLabel }}</span>
            </div>
            <div v-if="supportsOAuthApp" class="oauth-overview-stat oauth-overview-stat--wide">
              <span class="oauth-overview-stat__label">Callback path</span>
              <code class="oauth-code-pill">{{ selectedCallbackPath }}</code>
            </div>
          </div>

          <AppAlert v-if="supportsOAuthApp && !oauthApps.isAdmin" variant="info" title="Personal override is optional">
            Shared defaults are managed by admin. Save an override only when this provider needs different credentials for your account.
          </AppAlert>

          <div v-if="supportsOAuthApp && oauthApps.isAdmin" class="oauth-scope-panel">
            <div>
              <p class="text-xs-pro font-medium text-slate-700">Editing scope</p>
              <p class="text-2xs text-slate-500 mt-1">Switch between workspace-wide defaults and your own override.</p>
            </div>
            <div class="oauth-scope-toggle">
              <AppButton
                variant="secondary"
                size="sm"
                :class="oauthScope === 'shared' ? '!bg-blue-50 !border-blue-300 !text-blue-700' : ''"
                @click="oauthScope = 'shared'"
              >
                Edit shared default
              </AppButton>
              <AppButton
                variant="secondary"
                size="sm"
                :class="oauthScope === 'user' ? '!bg-blue-50 !border-blue-300 !text-blue-700' : ''"
                @click="oauthScope = 'user'"
              >
                Edit my override
              </AppButton>
            </div>
          </div>

          <div v-if="!supportsOAuthApp" class="oauth-empty">
            <p class="text-sm-pro font-medium text-slate-700">OAuth app settings are not required</p>
            <p class="mt-1 text-2xs text-slate-500">This provider does not use the shared OAuth app configuration flow.</p>
          </div>
        </aside>

        <form v-if="supportsOAuthApp" class="space-y-4" @submit.prevent="saveOauth">
          <div class="oauth-form-panel">
            <div class="oauth-form-panel__header">
              <div>
                <p class="oauth-section-kicker">Credentials</p>
                <p class="text-sm-pro font-semibold text-slate-800">{{ scopeEditorLabel }}</p>
                <p class="text-2xs text-slate-500 mt-1">These values are stored encrypted and used for OAuth connect and refresh flows.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div class="oauth-field-card">
                <AppFormField
                  label="Client ID"
                  required
                  hint="Use the OAuth client ID from the provider developer console."
                >
                  <AppInput v-model="oauthForm.client_id" type="text" placeholder="OAuth Client ID" />
                </AppFormField>
              </div>

              <div class="oauth-field-card">
                <AppFormField
                  label="Client secret"
                  hint="Saved encrypted. Leave blank to keep the existing secret."
                >
                  <AppInput v-model="oauthForm.client_secret" type="password" placeholder="OAuth Client Secret" />
                </AppFormField>
              </div>
            </div>
          </div>

          <div class="oauth-form-panel">
            <div class="oauth-form-panel__header">
              <div>
                <p class="oauth-section-kicker">Redirect setup</p>
                <p class="text-sm-pro font-semibold text-slate-800">Connect callback</p>
                <p class="text-2xs text-slate-500 mt-1">Use your public API origin and the callback path below when registering the OAuth app.</p>
              </div>
            </div>

            <div class="oauth-redirect-grid">
              <div class="oauth-field-card oauth-field-card--plain">
                <AppFormField label="Redirect URI">
                  <AppInput
                    v-model="oauthForm.redirect_uri"
                    type="url"
                    placeholder="https://your-backend-domain.com/api/social/callback/youtube"
                  />
                </AppFormField>
              </div>

              <div class="oauth-callback-preview">
                <span class="oauth-callback-preview__label">Suggested callback path</span>
                <code class="oauth-callback-preview__path">{{ selectedCallbackPath }}</code>
                <p class="text-2xs text-slate-500">Prepend your public API origin to build the full redirect URI.</p>
              </div>
            </div>

            <AppAlert variant="info" title="Keep app callbacks separate from login callbacks">
              Register this URI for <span class="font-medium text-slate-700">connect / sync</span> only. Social login callbacks belong on the deployment OAuth client under
              <span class="font-mono text-slate-700">/api/auth/social/…/callback</span>, not in this form.
            </AppAlert>
          </div>

          <div class="oauth-action-bar">
            <div class="flex flex-wrap items-center gap-2">
              <AppButton type="submit" variant="secondary" size="sm" :disabled="!canSaveOauth">
                {{ oauthApps.saving ? 'Saving…' : saveButtonLabel }}
              </AppButton>
              <AppButton v-if="activeScopeConfig" variant="secondary" size="sm" @click="removeOauth">
                {{ oauthScope === 'shared' ? 'Remove shared default' : 'Remove my override' }}
              </AppButton>
            </div>
            <div v-if="oauthApps.error" class="text-2xs text-red-600">{{ oauthApps.error }}</div>
          </div>
        </form>
      </div>
    </AppCard>

    <AppCard class="oauth-settings-shell p-4 md:p-5">
      <div class="oauth-config-layout">
        <aside class="oauth-overview-panel">
          <div class="oauth-provider-hero">
            <div class="min-w-0 flex-1">
              <p class="oauth-section-kicker">Asset storage</p>
              <p class="text-sm-pro font-semibold text-slate-800">Google Drive uploads</p>
              <p class="text-2xs text-slate-500 mt-1">
                Connect once to store uploaded assets on Google Drive. Access tokens refresh automatically; click reconnect if Google revokes access.
              </p>
            </div>
          </div>

          <div class="oauth-overview-grid">
            <div class="oauth-overview-stat">
              <span class="oauth-overview-stat__label">Status</span>
              <div class="oauth-status-pill" :class="googleDrive.isConnected && !googleDrive.needsReconnect ? 'oauth-status-pill--ok' : ''">
                <span class="oauth-status-pill__dot" />
                {{ driveStatusLabel }}
              </div>
            </div>
            <div v-if="googleDrive.status?.account_label" class="oauth-overview-stat oauth-overview-stat--wide">
              <span class="oauth-overview-stat__label">Connected account</span>
              <span class="oauth-overview-stat__value">{{ googleDrive.status.account_label }}</span>
            </div>
            <div v-if="googleDrive.status?.source" class="oauth-overview-stat">
              <span class="oauth-overview-stat__label">Source</span>
              <span class="oauth-overview-stat__value">{{ googleDrive.status.source === 'database' ? 'In-app OAuth' : 'Environment' }}</span>
            </div>
          </div>

          <AppAlert v-if="googleDrive.needsReconnect" variant="warning" title="Google Drive needs reconnection">
            {{ googleDrive.status?.last_error || 'The refresh token expired or was revoked. An admin must reconnect Google Drive.' }}
          </AppAlert>

          <AppAlert v-else-if="!canConnectGoogleDrive" variant="info" title="Configure Google OAuth first">
            Save Google client ID and secret in OAuth Apps (Google / YouTube provider) or set
            <code class="font-mono text-slate-700">GOOGLE_CLIENT_ID</code> in the environment, then add
            <code class="oauth-code-pill">/api/social/callback/google-drive</code>
            as an authorized redirect URI in Google Cloud Console.
          </AppAlert>

          <div v-else class="oauth-callback-preview">
            <span class="oauth-callback-preview__label">Drive storage callback path</span>
            <code class="oauth-callback-preview__path">/api/social/callback/google-drive</code>
            <p class="text-2xs text-slate-500">Add this redirect URI to the same Google OAuth client used for YouTube/Google connect.</p>
          </div>
        </aside>

        <div class="space-y-4">
          <div class="oauth-action-bar">
            <div class="flex flex-wrap items-center gap-2">
              <AppButton
                v-if="oauthApps.isAdmin"
                :variant="googleDrive.needsReconnect ? 'primary' : 'secondary'"
                size="sm"
                :disabled="googleDrive.connecting || !canConnectGoogleDrive"
                @click="connectGoogleDrive"
              >
                {{ googleDrive.connecting ? 'Redirecting…' : googleDrive.isConnected ? 'Reconnect Google Drive' : 'Connect Google Drive' }}
              </AppButton>
              <AppButton
                v-if="oauthApps.isAdmin && googleDrive.isConnected && googleDrive.status?.source === 'database'"
                variant="secondary"
                size="sm"
                @click="disconnectGoogleDrive"
              >
                Disconnect
              </AppButton>
            </div>
            <p v-if="!oauthApps.isAdmin" class="text-2xs text-slate-500">
              Only admins can connect or disconnect Google Drive storage.
            </p>
            <div v-if="googleDrive.error" class="text-2xs text-red-600">{{ googleDrive.error }}</div>
          </div>
        </div>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOAuthAppsStore } from '../stores/oauthApps';
import { useCredentialsStore } from '../stores/credentials';
import { useGoogleDriveStore } from '../stores/googleDrive';
import { useToastStore } from '../stores/toast';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { AppPageHeader } from '../components/layout/index.js';
import { AppAlert, AppButton, AppCard, AppFormField, AppInput } from '../components/ui';

const oauthApps = useOAuthAppsStore();
const googleDrive = useGoogleDriveStore();
const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const { confirm } = inject('confirm');
const creds = useCredentialsStore();
const provider = ref('youtube');
const socialProviders = [
  { type: 'youtube', label: 'YouTube', tagline: 'Channel connect', color: '#ef4444', softBg: 'rgba(239,68,68,0.12)' },
  { type: 'google', label: 'Google', tagline: 'Google account', color: '#2563eb', softBg: 'rgba(37,99,235,0.12)' },
  { type: 'facebook', label: 'Facebook', tagline: 'Pages access', color: '#1877f2', softBg: 'rgba(24,119,242,0.14)' },
  { type: 'instagram', label: 'Instagram', tagline: 'Meta business', color: '#db2777', softBg: 'rgba(219,39,119,0.14)' },
  { type: 'twitter', label: 'Twitter / X', tagline: 'API tokens', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'tiktok', label: 'TikTok', tagline: 'Creator access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'threads', label: 'Threads', tagline: 'Meta Threads access', color: '#111827', softBg: 'rgba(17,24,39,0.10)' },
  { type: 'linkedin', label: 'LinkedIn', tagline: 'Share on LinkedIn', color: '#0a66c2', softBg: 'rgba(10,102,194,0.12)' },
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
const oauthProviderKey = computed(() => oauthProviderByProvider[provider.value] || provider.value);
const supportsOAuthApp = computed(() => Boolean(oauthProviderByProvider[provider.value]));
const selectedCallbackPath = computed(() => `/api/social/callback/${oauthProviderKey.value}`);
const currentProviderMeta = computed(() => providerUi[provider.value] || providerUi.other);
const oauthScope = ref('user');
const oauthForm = reactive({ client_id: '', client_secret: '', redirect_uri: '' });
const oauthConfigExists = computed(() => Boolean(oauthApps.effectiveConfigFor(oauthProviderKey.value)));
const oauthSourceLabel = computed(() => {
  const effectiveScope = oauthApps.effectiveScopeFor(oauthProviderKey.value);
  if (effectiveScope === 'user') return 'Your override';
  if (effectiveScope === 'shared') return 'Shared default';
  return 'Not configured';
});
const scopeEditorLabel = computed(() => (oauthScope.value === 'shared' ? 'Shared default' : 'My override'));
const activeScopeConfig = computed(() => (oauthScope.value === 'shared'
  ? oauthApps.sharedConfigFor(oauthProviderKey.value)
  : oauthApps.userConfigFor(oauthProviderKey.value)));
const saveButtonLabel = computed(() => (oauthScope.value === 'shared' ? 'Save shared default' : 'Save my override'));
const canSaveOauth = computed(() => supportsOAuthApp.value && Boolean(oauthForm.client_id?.trim()) && !oauthApps.saving);
const googleOAuthConfigExists = computed(() => Boolean(oauthApps.effectiveConfigFor('google')));
const canConnectGoogleDrive = computed(() => {
  if (googleDrive.status?.oauth_ready != null) {
    return Boolean(googleDrive.status.oauth_ready);
  }

  return googleOAuthConfigExists.value;
});
const driveStatusLabel = computed(() => {
  if (googleDrive.loading && !googleDrive.status) return 'Loading…';
  if (!googleDrive.isConnected) return 'Not connected';
  if (googleDrive.needsReconnect) return 'Needs reconnection';
  return 'Connected';
});

function hydrateOauthForm() {
  if (!supportsOAuthApp.value) {
    oauthForm.client_id = '';
    oauthForm.client_secret = '';
    oauthForm.redirect_uri = '';
    return;
  }
  const existing = activeScopeConfig.value;
  oauthForm.client_id = existing?.client_id || '';
  oauthForm.client_secret = '';
  oauthForm.redirect_uri = existing?.redirect_uri || '';
}

onMounted(async () => {
  await Promise.all([oauthApps.fetchAll(), creds.fetchAll(), googleDrive.fetchStatus().catch(() => {})]);
  oauthScope.value = oauthApps.isAdmin ? 'shared' : 'user';
  hydrateOauthForm();
  handleOAuthReturnQuery();
});

watch(provider, () => {
  oauthScope.value = oauthApps.isAdmin ? 'shared' : 'user';
  hydrateOauthForm();
});

watch(oauthScope, () => {
  hydrateOauthForm();
});

async function saveOauth() {
  if (!supportsOAuthApp.value) return;
  const existing = activeScopeConfig.value;
  await oauthApps.save({
    scope: oauthScope.value,
    provider: oauthProviderKey.value,
    client_id: oauthForm.client_id,
    client_secret: oauthForm.client_secret || (existing ? '__KEEP__' : ''),
    redirect_uri: oauthForm.redirect_uri || null,
  });
  hydrateOauthForm();
}

async function removeOauth() {
  if (!supportsOAuthApp.value) return;
  if (await confirm({ title: 'Remove OAuth settings?', message: 'Remove OAuth app settings?', confirmLabel: 'Remove' })) {
    await oauthApps.remove(oauthProviderKey.value, oauthScope.value);
    hydrateOauthForm();
  }
}

function handleOAuthReturnQuery() {
  const connected = route.query.connected;
  const error = route.query.error;
  const message = route.query.message;
  if (connected === 'google_drive') {
    toast.success('Google Drive connected for asset storage');
    googleDrive.fetchStatus().catch(() => {});
    router.replace({ query: {} });
    return;
  }
  if (error) {
    toast.error(typeof message === 'string' && message ? message : 'Google Drive connection failed');
    router.replace({ query: {} });
  }
}

async function connectGoogleDrive() {
  await googleDrive.connect();
}

async function disconnectGoogleDrive() {
  if (await confirm({
    title: 'Disconnect Google Drive?',
    message: 'New uploads will use local storage until Google Drive is connected again.',
    confirmLabel: 'Disconnect',
  })) {
    await googleDrive.disconnect();
  }
}

async function promoteToShared(overwrite) {
  const message = overwrite
    ? 'Overwrite existing shared configs with your user configs?'
    : 'Promote your user configs to shared defaults? Existing shared configs will be kept.';
  if (!await confirm({ title: 'Promote configs?', message, confirmLabel: 'Continue' })) return;
  await oauthApps.promoteMyUserConfigsToShared({ overwrite });
}

function connectedCountFor(providerType) {
  return creds.byProvider?.[providerType]?.length || 0;
}
</script>

<style scoped>
.credentials-provider-panel {
  background:
    radial-gradient(700px 200px at -8% -60%, rgba(30, 58, 138, 0.06), transparent 62%),
    radial-gradient(560px 200px at 120% -58%, rgba(30, 58, 138, 0.05), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}
.oauth-admin-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background:
    radial-gradient(360px 140px at -8% -50%, rgba(30, 58, 138, 0.05), transparent 62%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
}
.oauth-admin-toolbar__copy {
  min-width: 0;
  flex: 1 1 18rem;
}
.oauth-admin-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.oauth-section-kicker {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}
.oauth-section-intro {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.oauth-inline-stat {
  display: inline-flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
  min-width: 10.5rem;
  padding: 0.8rem 0.9rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 0.95rem;
  background: rgba(255, 255, 255, 0.85);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.oauth-inline-stat__value {
  font-size: 1.25rem;
  line-height: 1.1;
  font-weight: 700;
  color: #0f172a;
}
.oauth-inline-stat__label {
  font-size: 0.72rem;
  color: #64748b;
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
.provider-connection-pill {
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  color: #64748b;
  border-radius: 999px;
  padding: 0.18rem 0.45rem;
  font-size: 0.64rem;
  line-height: 1;
  white-space: nowrap;
}
.provider-connection-pill--ok {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(236, 253, 245, 0.92);
  color: rgb(6 95 70);
}
.credentials-provider-icon {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.oauth-settings-shell {
  background:
    radial-gradient(620px 180px at 105% -45%, rgba(30, 58, 138, 0.05), transparent 60%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}
.oauth-config-layout {
  display: grid;
  gap: 1rem;
}
.oauth-overview-panel {
  display: grid;
  gap: 1rem;
}
.oauth-provider-hero {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 1rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.82);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.oauth-provider-hero__icon {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.oauth-overview-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.oauth-overview-stat {
  display: grid;
  gap: 0.45rem;
  padding: 0.85rem 0.95rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.95rem;
  background: rgba(255, 255, 255, 0.82);
}
.oauth-overview-stat--wide {
  grid-column: 1 / -1;
}
.oauth-overview-stat__label {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}
.oauth-overview-stat__value {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
}
.oauth-code-pill {
  display: inline-flex;
  align-items: center;
  min-height: 2rem;
  padding: 0.4rem 0.6rem;
  border-radius: 0.8rem;
  background: #f8fafc;
  font-size: 0.78rem;
  color: #334155;
  white-space: nowrap;
  overflow-x: auto;
}
.oauth-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.68rem;
  padding: 0.28rem 0.55rem;
  border-radius: 999px;
}
.oauth-status-pill__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: #94a3b8;
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
  border: 1px dashed #d1d9e6;
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.95);
  padding: 1rem;
}
.oauth-field-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 0.85rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.oauth-field-card--plain {
  box-shadow: none;
}
.oauth-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.88);
  padding: 1rem;
  display: grid;
  gap: 1rem;
}
.oauth-form-panel__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.oauth-scope-panel {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.96);
  padding: 0.95rem;
  display: grid;
  gap: 0.85rem;
}
.oauth-scope-toggle {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.oauth-redirect-grid {
  display: grid;
  gap: 0.9rem;
}
.oauth-callback-preview {
  display: grid;
  align-content: start;
  gap: 0.65rem;
  padding: 0.95rem;
  border: 1px dashed #cbd5e1;
  border-radius: 0.95rem;
  background: rgba(248, 250, 252, 0.88);
}
.oauth-callback-preview__label {
  font-size: 0.7rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.oauth-callback-preview__path {
  display: block;
  width: 100%;
  overflow-x: auto;
  padding: 0.65rem 0.75rem;
  border-radius: 0.8rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  font-size: 0.8rem;
  color: #1e293b;
}
.oauth-action-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.1rem 0.1rem 0;
}
@media (min-width: 1024px) {
  .oauth-config-layout {
    grid-template-columns: minmax(18rem, 22rem) minmax(0, 1fr);
    align-items: start;
  }
  .oauth-overview-panel {
    position: sticky;
    top: 1rem;
  }
}
@media (min-width: 768px) {
  .oauth-redirect-grid {
    grid-template-columns: minmax(0, 1.5fr) minmax(16rem, 0.9fr);
  }
}
</style>
