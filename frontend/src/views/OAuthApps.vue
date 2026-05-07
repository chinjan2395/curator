<template>
  <div class="space-y-4">
    <AppPageHeader
      title="OAuth app settings"
      subtitle="Manage shared defaults and optional per-user overrides."
      icon="cog"
    />

    <div v-if="oauthApps.isAdmin" class="surface-card p-3.5 flex flex-wrap items-center justify-between gap-2">
      <div>
        <p class="text-xs-pro font-medium text-slate-700">Promote existing admin configs</p>
        <p class="text-2xs text-slate-500">Copy all your user overrides into shared defaults in one click.</p>
      </div>
      <div class="flex items-center gap-1.5">
        <AppButton variant="secondary" size="sm" :disabled="oauthApps.promoting" @click="promoteToShared(false)">
          {{ oauthApps.promoting ? 'Promoting…' : 'Promote (skip existing)' }}
        </AppButton>
        <AppButton variant="secondary" size="sm" :disabled="oauthApps.promoting" @click="promoteToShared(true)">
          Promote (overwrite shared)
        </AppButton>
      </div>
    </div>

    <div class="surface-card credentials-provider-panel p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs-pro font-medium text-slate-700">Select social media</p>
        <p class="text-2xs text-slate-500">Choose a provider to configure app credentials</p>
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
          <div class="credentials-provider-icon" :style="{ background: item.softBg, color: item.color }">
            <SocialIcon :type="item.type" class="w-4 h-4" />
          </div>
          <div class="text-left min-w-0 flex-1">
            <div class="text-sm-pro font-medium text-slate-800 truncate">{{ item.label }}</div>
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
    </div>

    <div class="surface-card oauth-settings-shell p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <div class="text-sm-pro font-semibold text-slate-800 flex items-center gap-1.5">
            Configure credentials for <span class="text-indigo-600">{{ providerLabels[provider] || provider }}</span>
          </div>
          <div class="mt-0.5 text-2xs text-slate-500">Stored encrypted and used for OAuth connect/refresh flow.</div>
        </div>
        <div class="oauth-status-pill" :class="oauthApps.effectiveConfigFor(oauthProviderKey) ? 'oauth-status-pill--ok' : ''">
          <span class="oauth-status-pill__dot" />
          {{ oauthApps.effectiveConfigFor(oauthProviderKey) ? 'Configured' : 'Not configured' }}
        </div>
      </div>

      <div v-if="!supportsOAuthApp" class="oauth-empty text-2xs text-slate-500">
        OAuth app settings are not required for this provider.
      </div>
      <div v-else class="space-y-4">
        <div class="oauth-hints">
          <p class="text-2xs text-slate-500">
            Effective source:
            <span class="font-medium text-slate-700">{{ oauthSourceLabel }}</span>
          </p>
          <p v-if="!oauthApps.isAdmin" class="text-2xs text-slate-500">
            Shared defaults are managed by admin. You can optionally save your own override for this provider.
          </p>
          <div v-if="oauthApps.isAdmin" class="flex items-center gap-1.5">
            <AppButton variant="secondary" size="sm" :class="oauthScope === 'shared' ? '!bg-indigo-50 !border-indigo-300 !text-indigo-700' : ''" @click="oauthScope = 'shared'">
              Edit shared default
            </AppButton>
            <AppButton variant="secondary" size="sm" :class="oauthScope === 'user' ? '!bg-indigo-50 !border-indigo-300 !text-indigo-700' : ''" @click="oauthScope = 'user'">
              Edit my override
            </AppButton>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="oauth-field-card sm:col-span-1">
            <label class="label-pro">Client ID</label>
            <AppInput v-model="oauthForm.client_id" type="text" placeholder="OAuth Client ID" />
          </div>
          <div class="oauth-field-card sm:col-span-1">
            <label class="label-pro">Client secret</label>
            <AppInput v-model="oauthForm.client_secret" type="password" placeholder="OAuth Client Secret" />
            <p class="mt-1 text-2xs text-slate-500">Saved encrypted. Leave blank to keep the existing secret.</p>
          </div>
          <div class="oauth-field-card sm:col-span-2">
            <label class="label-pro">Redirect URI</label>
            <AppInput v-model="oauthForm.redirect_uri" type="url" placeholder="https://your-backend-domain.com/api/social/callback/youtube" />
            <p class="mt-1 text-2xs text-slate-500">
              Register this with the provider for <span class="font-medium text-slate-600">connect / sync</span> only.
              Suggested path: <span class="font-medium text-slate-700">{{ selectedCallbackPath }}</span>
              (prepend your public API origin). Social login callbacks live under
              <span class="font-mono text-slate-600">/api/auth/social/…/callback</span>
              — add those on the OAuth client configured in deployment <span class="font-mono text-slate-600">.env</span>, not in this form.
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <AppButton variant="secondary" size="sm" :disabled="oauthApps.saving || !oauthForm.client_id" @click="saveOauth">
            {{ oauthApps.saving ? 'Saving…' : saveButtonLabel }}
          </AppButton>
          <AppButton v-if="activeScopeConfig" variant="secondary" size="sm" @click="removeOauth">
            {{ oauthScope === 'shared' ? 'Remove shared default' : 'Remove my override' }}
          </AppButton>
          <div v-if="oauthApps.error" class="text-2xs text-red-600">{{ oauthApps.error }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useOAuthAppsStore } from '../stores/oauthApps';
import { useCredentialsStore } from '../stores/credentials';
import SocialIcon from '../components/SocialIcon.vue';
import { AppPageHeader } from '../components/layout/index.js';
import { AppButton, AppInput } from '../components/ui';

const oauthApps = useOAuthAppsStore();
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
const oauthScope = ref('user');
const oauthForm = reactive({ client_id: '', client_secret: '', redirect_uri: '' });
const oauthSourceLabel = computed(() => {
  const effectiveScope = oauthApps.effectiveScopeFor(oauthProviderKey.value);
  if (effectiveScope === 'user') return 'Your override';
  if (effectiveScope === 'shared') return 'Shared default';
  return 'Not configured';
});
const activeScopeConfig = computed(() => (oauthScope.value === 'shared'
  ? oauthApps.sharedConfigFor(oauthProviderKey.value)
  : oauthApps.userConfigFor(oauthProviderKey.value)));
const saveButtonLabel = computed(() => (oauthScope.value === 'shared' ? 'Save shared default' : 'Save my override'));

function hydrateOauthForm() {
  if (!supportsOAuthApp.value) return;
  const existing = activeScopeConfig.value;
  oauthForm.client_id = existing?.client_id || '';
  oauthForm.client_secret = '';
  oauthForm.redirect_uri = existing?.redirect_uri || '';
}

onMounted(async () => {
  await Promise.all([oauthApps.fetchAll(), creds.fetchAll()]);
  oauthScope.value = oauthApps.isAdmin ? 'shared' : 'user';
  hydrateOauthForm();
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
  if (window.confirm('Remove OAuth app settings?')) {
    await oauthApps.remove(oauthProviderKey.value, oauthScope.value);
    hydrateOauthForm();
  }
}

async function promoteToShared(overwrite) {
  const message = overwrite
    ? 'Overwrite existing shared configs with your user configs?'
    : 'Promote your user configs to shared defaults? Existing shared configs will be kept.';
  if (!window.confirm(message)) return;
  await oauthApps.promoteMyUserConfigsToShared({ overwrite });
}

function connectedCountFor(providerType) {
  return creds.byProvider?.[providerType]?.length || 0;
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
.provider-connection-pill {
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(248, 250, 252, 0.95);
  color: rgb(100 116 139);
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
</style>
