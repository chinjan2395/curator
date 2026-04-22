<template>
  <WizardPageLayout
    current="feed"
    :title="isEdit ? 'Edit feed' : 'Create a feed'"
    description="Connect the content source for this workspace."
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <router-link :to="`/workspaces/${workspaceId}/feeds`">{{ workspaceName }}</router-link>
      <span>/</span>
      <span>Feeds</span>
    </template>

    <div class="feed-form-hero surface-card p-5 md:p-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
        <button
          v-for="t in socialTypes"
          :key="t.type"
          type="button"
          class="feed-type-card"
          :class="form.type === t.type ? 'feed-type-card--active' : ''"
          @click="form.type = t.type"
        >
          <div class="feed-type-icon-wrap" :style="{ background: t.softBg, color: t.color }">
            <SocialIcon :type="t.type" class="w-4 h-4" />
          </div>
          <div class="text-left min-w-0">
            <div class="text-xs-pro font-medium text-slate-700 truncate">{{ t.label }}</div>
            <div class="text-2xs text-slate-500 truncate">{{ t.tagline }}</div>
          </div>
        </button>
      </div>
      <div class="mt-4 flex flex-wrap items-center gap-2.5">
        <div class="setup-step" :class="form.type ? 'setup-step--active' : ''">
          <span class="setup-step__dot">1</span>
          Select source
        </div>
        <div class="setup-step-divider" />
        <div class="setup-step" :class="form.social_credential_id ? 'setup-step--active' : ''">
          <span class="setup-step__dot">2</span>
          Connect account
        </div>
        <div class="setup-step-divider" />
        <div class="setup-step" :class="connectionStatus.kind === 'ok' ? 'setup-step--active' : ''">
          <span class="setup-step__dot">3</span>
          Verify connection
        </div>
      </div>
    </div>
    <form id="feed-form" @submit.prevent="submit" class="surface-card p-5 space-y-4 max-w-3xl">
      <div>
        <label class="label-pro">Name</label>
        <input v-model="form.name" type="text" class="input-pro" placeholder="Feed name" required />
      </div>
      <div>
        <label class="label-pro">Type</label>
        <select v-model="form.type" class="input-pro" required>
          <option value="">Select type</option>
          <option value="instagram">Instagram</option>
          <option value="twitter">Twitter / X</option>
          <option value="youtube">YouTube</option>
          <option value="rss">RSS</option>
          <option value="tiktok">TikTok</option>
          <option value="facebook">Facebook</option>
          <option value="other">Other</option>
        </select>
        <p class="mt-1 text-2xs text-slate-500">{{ selectedTypeMeta.tagline }}</p>
      </div>
      <transition name="fade-slide" mode="out-in">
      <div v-if="form.type === 'youtube'" key="youtube">
        <label class="label-pro">YouTube credential</label>
        <select
          v-model="form.social_credential_id"
          class="input-pro"
          :required="!!youtubeCredentials.length"
        >
          <option value="">Select credential</option>
          <option v-for="c in youtubeCredentials" :key="c.id" :value="c.id">
            {{ c.provider }} ({{ c.expires_at ? formatDate(c.expires_at) : 'no expiry' }})
          </option>
        </select>
        <p v-if="!youtubeCredentials.length" class="mt-1 text-2xs text-red-600">
          No YouTube credentials found. Go to Credentials to connect YouTube first.
        </p>

        <div class="mt-3">
          <label class="label-pro">Channel</label>
          <div class="flex items-center gap-2">
            <select
              v-model="selectedYoutubeChannelId"
              class="input-pro"
              :disabled="loadingYoutubeChannels || !form.social_credential_id"
              :required="!!youtubeCredentials.length"
            >
              <option value="">
                {{
                  !form.social_credential_id
                    ? 'Select credential first'
                    : loadingYoutubeChannels
                      ? 'Loading channels…'
                      : 'Select channel'
                }}
              </option>
              <option v-for="ch in youtubeChannels" :key="ch.id" :value="ch.id">
                {{ youtubeChannelLabel(ch) }}
              </option>
            </select>
            <button
              type="button"
              class="btn-secondary !py-1.5 !px-3 text-xs-pro whitespace-nowrap"
              :disabled="loadingYoutubeChannels || !form.social_credential_id"
              @click="loadYoutubeChannels"
            >
              {{ loadingYoutubeChannels ? 'Loading…' : 'Refresh' }}
            </button>
          </div>
          <p class="mt-1 text-2xs text-slate-500">
            Only channels managed by this Google account are listed (YouTube <span class="font-medium">channels.list?mine=true</span>).
          </p>
        </div>

        <div class="mt-3" v-if="form.youtube_channel_id">
          <label class="label-pro">Selected channel ID</label>
          <input v-model="form.youtube_channel_id" type="text" class="input-pro" readonly />
        </div>

        <div class="mt-3">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="testingConnection || !form.youtube_channel_id || !form.social_credential_id"
            @click="testConnection"
          >
            {{ testingConnection ? 'Testing…' : 'Test connection' }}
          </button>
        </div>
      </div>
      <div v-else-if="form.type === 'facebook'" key="facebook">
        <label class="label-pro">Facebook credential</label>
        <select
          v-model="form.social_credential_id"
          class="input-pro"
          :required="!!facebookCredentials.length"
        >
          <option value="">Select credential</option>
          <option v-for="c in facebookCredentials" :key="c.id" :value="c.id">
            {{ c.provider }} ({{ c.expires_at ? formatDate(c.expires_at) : 'no expiry' }})
          </option>
        </select>
        <p v-if="!facebookCredentials.length" class="mt-1 text-2xs text-red-600">
          No Facebook credentials found. Go to Credentials to connect Facebook first.
        </p>

        <div class="mt-3">
          <label class="label-pro">Facebook Page</label>
          <div class="flex items-center gap-2">
            <select
              v-model="selectedFacebookPageId"
              class="input-pro"
              :disabled="loadingFacebookPages || !form.social_credential_id"
              required
            >
              <option value="">
                {{ !form.social_credential_id ? 'Select credential first' : (loadingFacebookPages ? 'Loading pages…' : 'Select page') }}
              </option>
              <option v-for="p in facebookPages" :key="p.id" :value="p.id">
                {{ p.name || p.id }} ({{ p.id }})
              </option>
            </select>
            <button
              type="button"
              class="btn-secondary !py-1.5 !px-3 text-xs-pro whitespace-nowrap"
              :disabled="loadingFacebookPages || !form.social_credential_id"
              @click="loadFacebookPages"
            >
              {{ loadingFacebookPages ? 'Loading…' : 'Refresh' }}
            </button>
          </div>
          <p class="mt-1 text-2xs text-slate-500">
            We’ll list Pages from your Facebook account via <span class="font-medium">/me/accounts</span>. If your Page doesn’t appear, disconnect/reconnect Facebook and ensure Page permissions are granted.
          </p>
        </div>

        <div class="mt-3" v-if="form.facebook_page_id">
          <label class="label-pro">Selected Page ID</label>
          <input v-model="form.facebook_page_id" type="text" class="input-pro" readonly />
        </div>

        <div class="mt-3">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="testingFacebook || !form.facebook_page_id || !form.social_credential_id"
            @click="testFacebookConnection"
          >
            {{ testingFacebook ? 'Testing…' : 'Test connection' }}
          </button>
        </div>
      </div>
      <div v-else-if="form.type === 'instagram'" key="instagram">
        <label class="label-pro">Instagram credential</label>
        <select
          v-model="form.social_credential_id"
          class="input-pro"
          :required="!!instagramCredentials.length"
        >
          <option value="">Select credential</option>
          <option v-for="c in instagramCredentials" :key="c.id" :value="c.id">
            {{ c.provider }} ({{ c.expires_at ? formatDate(c.expires_at) : 'no expiry' }})
          </option>
        </select>
        <p v-if="!instagramCredentials.length" class="mt-1 text-2xs text-red-600">
          No Instagram credentials found. Go to Credentials to connect Instagram first.
        </p>

        <div class="mt-3">
          <label class="label-pro">Linked Page &amp; Instagram account</label>
          <div class="flex items-center gap-2">
            <select
              v-model="selectedInstagramCombo"
              class="input-pro"
              :disabled="loadingInstagramAccounts || !form.social_credential_id"
              required
            >
              <option value="">
                {{
                  !form.social_credential_id
                    ? 'Select credential first'
                    : loadingInstagramAccounts
                      ? 'Loading accounts…'
                      : 'Select account'
                }}
              </option>
              <option v-for="a in instagramAccounts" :key="instagramAccountValue(a)" :value="instagramAccountValue(a)">
                @{{ a.instagram_username || '…' }} · {{ a.facebook_page_name || 'Page' }} (IG {{ a.instagram_business_account_id }})
              </option>
            </select>
            <button
              type="button"
              class="btn-secondary !py-1.5 !px-3 text-xs-pro whitespace-nowrap"
              :disabled="loadingInstagramAccounts || !form.social_credential_id"
              @click="loadInstagramAccounts"
            >
              {{ loadingInstagramAccounts ? 'Loading…' : 'Refresh' }}
            </button>
          </div>
          <p class="mt-1 text-2xs text-slate-500">
            Only Facebook Pages with a linked Instagram Professional account appear here (<span class="font-medium">/me/accounts</span> + <span class="font-medium">instagram_business_account</span>).
          </p>
          <p
            v-if="form.social_credential_id && !loadingInstagramAccounts && !instagramAccounts.length"
            class="mt-1 text-2xs text-red-600"
          >
            No linked Instagram accounts found. Link IG to a Page in Meta, then disconnect/reconnect Instagram with permissions granted.
          </p>
        </div>

        <div class="mt-3" v-if="form.facebook_page_id && form.instagram_business_account_id">
          <label class="label-pro">Backing Page ID</label>
          <input v-model="form.facebook_page_id" type="text" class="input-pro mb-2" readonly />
          <label class="label-pro">Instagram Business account ID</label>
          <input v-model="form.instagram_business_account_id" type="text" class="input-pro" readonly />
        </div>

        <div class="mt-3">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="
              testingInstagram || !form.facebook_page_id || !form.instagram_business_account_id || !form.social_credential_id
            "
            @click="testInstagramConnection"
          >
            {{ testingInstagram ? 'Testing…' : 'Test connection' }}
          </button>
        </div>
      </div>
      <div v-else-if="form.type === 'twitter'" key="twitter">
        <label class="label-pro">X / Twitter credential</label>
        <select
          v-model="form.social_credential_id"
          class="input-pro"
          :required="!!twitterCredentials.length"
        >
          <option value="">Select credential</option>
          <option v-for="c in twitterCredentials" :key="c.id" :value="c.id">
            {{ c.provider }} ({{ c.expires_at ? formatDate(c.expires_at) : 'no expiry' }})
          </option>
        </select>
        <p v-if="!twitterCredentials.length" class="mt-1 text-2xs text-red-600">
          No Twitter / X credentials found. Go to Credentials to connect first.
        </p>

        <div class="mt-3">
          <label class="label-pro">X account</label>
          <div class="flex items-center gap-2">
            <select
              v-model="selectedTwitterUserId"
              class="input-pro"
              :disabled="loadingTwitterAccount || !form.social_credential_id"
              :required="!!twitterCredentials.length"
            >
              <option value="">
                {{
                  !form.social_credential_id
                    ? 'Select credential first'
                    : loadingTwitterAccount
                      ? 'Loading account…'
                      : 'Select account'
                }}
              </option>
              <option v-for="a in twitterAccounts" :key="a.id" :value="a.id">
                {{ twitterAccountLabel(a) }}
              </option>
            </select>
            <button
              type="button"
              class="btn-secondary !py-1.5 !px-3 text-xs-pro whitespace-nowrap"
              :disabled="loadingTwitterAccount || !form.social_credential_id"
              @click="loadTwitterAccount"
            >
              {{ loadingTwitterAccount ? 'Loading…' : 'Refresh' }}
            </button>
          </div>
          <p class="mt-1 text-2xs text-slate-500">
            From X <span class="font-medium">users/me</span> for this credential—only that account’s posts are synced. Reconnect X if this fails; offline access is required for token refresh.
          </p>
        </div>

        <div class="mt-3" v-if="selectedTwitterUserId">
          <label class="label-pro">User ID</label>
          <input v-model="selectedTwitterUserId" type="text" class="input-pro" readonly />
        </div>

        <div class="mt-3">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="testingTwitter || !form.social_credential_id || !selectedTwitterUserId"
            @click="testTwitterConnection"
          >
            {{ testingTwitter ? 'Testing…' : 'Test connection' }}
          </button>
        </div>
      </div>
      <div v-else-if="form.type === 'tiktok'" key="tiktok">
        <label class="label-pro">TikTok credential</label>
        <select
          v-model="form.social_credential_id"
          class="input-pro"
          :required="!!tiktokCredentials.length"
        >
          <option value="">Select credential</option>
          <option v-for="c in tiktokCredentials" :key="c.id" :value="c.id">
            {{ c.provider }} ({{ c.expires_at ? formatDate(c.expires_at) : 'no expiry' }})
          </option>
        </select>
        <p v-if="!tiktokCredentials.length" class="mt-1 text-2xs text-red-600">
          No TikTok credentials found. Go to Credentials to connect first.
        </p>

        <div class="mt-3">
          <label class="label-pro">TikTok account</label>
          <div class="flex items-center gap-2">
            <select
              v-model="selectedTikTokOpenId"
              class="input-pro"
              :disabled="loadingTikTokAccount || !form.social_credential_id"
              :required="!!tiktokCredentials.length"
            >
              <option value="">
                {{
                  !form.social_credential_id
                    ? 'Select credential first'
                    : loadingTikTokAccount
                      ? 'Loading account…'
                      : 'Select account'
                }}
              </option>
              <option v-for="a in tiktokAccounts" :key="a.open_id" :value="a.open_id">
                {{ tikTokAccountLabel(a) }}
              </option>
            </select>
            <button
              type="button"
              class="btn-secondary !py-1.5 !px-3 text-xs-pro whitespace-nowrap"
              :disabled="loadingTikTokAccount || !form.social_credential_id"
              @click="loadTikTokAccount"
            >
              {{ loadingTikTokAccount ? 'Loading…' : 'Refresh' }}
            </button>
          </div>
          <p class="mt-1 text-2xs text-slate-500">
            From TikTok account info for this credential. Only that connected account’s videos are synced.
          </p>
          <p
            v-if="form.social_credential_id && !loadingTikTokAccount && !tiktokAccounts.length"
            class="mt-1 text-2xs text-red-600"
          >
            Could not load a TikTok account for this credential. Reconnect TikTok and retry.
          </p>
        </div>

        <div class="mt-3" v-if="selectedTikTokOpenId">
          <label class="label-pro">Open ID</label>
          <input v-model="selectedTikTokOpenId" type="text" class="input-pro" readonly />
        </div>

        <div class="mt-3">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="testingTikTok || !form.social_credential_id || !selectedTikTokOpenId"
            @click="testTikTokConnection"
          >
            {{ testingTikTok ? 'Testing…' : 'Test connection' }}
          </button>
        </div>
      </div>
      <div v-else-if="form.type === 'rss'" key="rss">
        <label class="label-pro">RSS or Atom feed URL</label>
        <input
          v-model="form.source_url"
          type="url"
          class="input-pro"
          placeholder="https://example.com/feed.xml"
          required
        />
        <p class="mt-1 text-2xs text-slate-500">
          Supports RSS 2.0 and Atom. The server fetches this URL on sync (no OAuth). Use a stable public URL.
        </p>
        <div class="mt-3 flex items-center gap-2 flex-wrap">
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-xs-pro"
            :disabled="testingRss || !form.source_url?.trim()"
            @click="testRss"
          >
            {{ testingRss ? 'Testing…' : 'Test feed' }}
          </button>
        </div>
        <div
          v-if="rssTestSummary"
          class="mt-3 rounded-md border border-slate-200/90 bg-slate-50/80 px-3 py-2 text-2xs text-slate-700 space-y-1"
        >
          <div v-if="rssTestSummary.feed_title">
            <span class="font-medium text-slate-600">Feed:</span>
            {{ rssTestSummary.feed_title }}
          </div>
          <div v-if="rssTestSummary.item_count != null">
            <span class="font-medium text-slate-600">Entries found:</span>
            {{ rssTestSummary.item_count }}
          </div>
          <div v-if="rssTestSummary.sample_title">
            <span class="font-medium text-slate-600">Latest title:</span>
            {{ rssTestSummary.sample_title }}
          </div>
        </div>
      </div>
      <div v-else key="other">
        <label class="label-pro">Source URL <span class="text-slate-400 font-normal">(optional)</span></label>
        <input v-model="form.source_url" type="url" class="input-pro" placeholder="https://…" />
        <p class="mt-1 text-2xs text-slate-500">
          Optional reference URL for this feed type.
        </p>
      </div>
      </transition>
      <div
        v-if="connectionStatus.message"
        class="rounded-md border px-3 py-2 text-2xs"
        :class="connectionStatus.kind === 'ok'
          ? 'border-emerald-200 bg-emerald-50/70 text-emerald-700'
          : 'border-rose-200 bg-rose-50/70 text-rose-700'"
      >
        {{ connectionStatus.message }}
      </div>
      <div v-if="feeds.lastActionError" class="text-2xs text-red-600">
        {{ feeds.lastActionError }}
      </div>
    </form>

    <template #footer>
      <router-link :to="`/workspaces/${workspaceId}/feeds`" class="btn-secondary !w-auto">Cancel</router-link>
      <button
        type="submit"
        form="feed-form"
        class="btn-primary !w-auto !px-4"
        :disabled="
          saving ||
          (form.type === 'twitter' && (!form.social_credential_id || !selectedTwitterUserId)) ||
          (form.type === 'tiktok' && (!form.social_credential_id || !selectedTikTokOpenId)) ||
          (form.type === 'instagram' &&
            (!form.social_credential_id || !form.facebook_page_id || !form.instagram_business_account_id)) ||
          (form.type === 'rss' && !String(form.source_url || '').trim())
        "
      >
        {{ saving ? 'Saving…' : (isEdit ? 'Save and continue' : 'Create and continue') }} →
      </button>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useCredentialsStore } from '../stores/credentials';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import WizardPageLayout from '../components/WizardPageLayout.vue';

const route = useRoute();
const router = useRouter();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const credentials = useCredentialsStore();
const toast = useToastStore();

const workspaceId = computed(() => route.params.workspaceId);
const feedId = computed(() => route.params.feedId);
const isEdit = computed(() => !!feedId.value);
const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

const form = reactive({
  name: '',
  type: '',
  source_url: '',
  youtube_channel_id: '',
  facebook_page_id: '',
  instagram_business_account_id: '',
  social_credential_id: '',
});
const saving = ref(false);
const testingConnection = ref(false);
const testingFacebook = ref(false);
const testingInstagram = ref(false);
const testingTwitter = ref(false);
const testingTikTok = ref(false);
const testingRss = ref(false);
const loadingFacebookPages = ref(false);
const facebookPages = ref([]);
const selectedFacebookPageId = ref('');
const loadingInstagramAccounts = ref(false);
const instagramAccounts = ref([]);
const selectedInstagramCombo = ref('');
/** When true, Instagram credential watch only loads accounts; does not clear selection (edit hydrate). */
const skipNextInstagramCredReset = ref(false);
const loadingYoutubeChannels = ref(false);
const youtubeChannels = ref([]);
const selectedYoutubeChannelId = ref('');
/** When true, YouTube credential watch only loads channels; does not clear selection (edit hydrate). */
const skipNextYoutubeCredReset = ref(false);
const loadingTwitterAccount = ref(false);
const twitterAccounts = ref([]);
const selectedTwitterUserId = ref('');
/** When true, Twitter credential watch only loads account; does not clear selection (edit hydrate). */
const skipNextTwitterCredReset = ref(false);
const loadingTikTokAccount = ref(false);
const tiktokAccounts = ref([]);
const selectedTikTokOpenId = ref('');
/** When true, TikTok credential watch only loads account; does not clear selection (edit hydrate). */
const skipNextTikTokCredReset = ref(false);
/** Last successful RSS test payload for inline summary. */
const rssTestSummary = ref(null);
const connectionStatus = reactive({
  kind: '',
  message: '',
});

const youtubeCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'youtube'),
);

const facebookCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'facebook'),
);

const instagramCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'instagram'),
);

const twitterCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'twitter'),
);
const tiktokCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'tiktok'),
);

const socialTypes = [
  { type: 'instagram', label: 'Instagram', tagline: 'Stories, reels, and posts', color: '#e1306c', softBg: 'rgba(225,48,108,0.13)' },
  { type: 'twitter', label: 'X / Twitter', tagline: 'Real-time conversations', color: '#111827', softBg: 'rgba(15,23,42,0.12)' },
  { type: 'youtube', label: 'YouTube', tagline: 'Channels and videos', color: '#ff0000', softBg: 'rgba(255,0,0,0.12)' },
  { type: 'rss', label: 'RSS / Atom', tagline: 'Blogs and publications', color: '#f97316', softBg: 'rgba(249,115,22,0.15)' },
  { type: 'tiktok', label: 'TikTok', tagline: 'Short-form video stream', color: '#111827', softBg: 'rgba(244,63,94,0.12)' },
  { type: 'facebook', label: 'Facebook', tagline: 'Pages and updates', color: '#1877f2', softBg: 'rgba(24,119,242,0.12)' },
  { type: 'other', label: 'Other', tagline: 'Custom source setup', color: '#475569', softBg: 'rgba(71,85,105,0.12)' },
];

const selectedTypeMeta = computed(() =>
  socialTypes.find((item) => item.type === form.type) || { tagline: 'Choose a source type to configure credentials and sync settings.' },
);

function youtubeChannelLabel(ch) {
  const handle = ch.custom_url ? ` · ${ch.custom_url}` : '';
  return `${ch.title || 'Channel'}${handle} (${ch.id})`;
}

function twitterAccountLabel(a) {
  const u = (a.username && String(a.username)) || 'account';
  const n = a.name && String(a.name).trim() ? ` — ${a.name}` : '';
  return `@${u}${n} (${a.id})`;
}

function tikTokAccountLabel(a) {
  const u = (a.username && String(a.username).trim()) || 'tiktok_user';
  const n = a.display_name && String(a.display_name).trim() ? ` — ${a.display_name}` : '';
  return `@${u}${n} (${a.open_id})`;
}

function instagramAccountValue(a) {
  return `${a.facebook_page_id}|${a.instagram_business_account_id}`;
}

async function loadInstagramAccounts() {
  if (form.type !== 'instagram' || !form.social_credential_id) return;
  loadingInstagramAccounts.value = true;
  try {
    const { data } = await axios.get(
      `/api/workspaces/${workspaceId.value}/feeds/instagram/accounts`,
      { params: { social_credential_id: Number(form.social_credential_id) } },
    );
    instagramAccounts.value = data.accounts || [];
    if (form.facebook_page_id && form.instagram_business_account_id && !selectedInstagramCombo.value) {
      selectedInstagramCombo.value = instagramAccountValue({
        facebook_page_id: String(form.facebook_page_id),
        instagram_business_account_id: String(form.instagram_business_account_id),
      });
    }
    if (
      !selectedInstagramCombo.value &&
      instagramAccounts.value.length === 1
    ) {
      selectedInstagramCombo.value = instagramAccountValue(instagramAccounts.value[0]);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load Instagram accounts';
    toast.error(msg);
    instagramAccounts.value = [];
  } finally {
    loadingInstagramAccounts.value = false;
  }
}

async function loadTikTokAccount() {
  if (form.type !== 'tiktok' || !form.social_credential_id) return;
  loadingTikTokAccount.value = true;
  try {
    const { data } = await axios.get(`/api/workspaces/${workspaceId.value}/feeds/tiktok/account`, {
      params: { social_credential_id: Number(form.social_credential_id) },
    });
    tiktokAccounts.value = data.accounts || [];
    if (!selectedTikTokOpenId.value && tiktokAccounts.value.length === 1) {
      selectedTikTokOpenId.value = String(tiktokAccounts.value[0].open_id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load TikTok account';
    toast.error(msg);
    tiktokAccounts.value = [];
    selectedTikTokOpenId.value = '';
  } finally {
    loadingTikTokAccount.value = false;
  }
}

async function loadTwitterAccount() {
  if (form.type !== 'twitter' || !form.social_credential_id) return;
  loadingTwitterAccount.value = true;
  try {
    const { data } = await axios.get(`/api/workspaces/${workspaceId.value}/feeds/twitter/account`, {
      params: { social_credential_id: Number(form.social_credential_id) },
    });
    twitterAccounts.value = data.accounts || [];
    if (!selectedTwitterUserId.value && twitterAccounts.value.length === 1) {
      selectedTwitterUserId.value = String(twitterAccounts.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load X account';
    toast.error(msg);
    twitterAccounts.value = [];
    selectedTwitterUserId.value = '';
  } finally {
    loadingTwitterAccount.value = false;
  }
}

async function loadYoutubeChannels() {
  if (form.type !== 'youtube' || !form.social_credential_id) return;
  loadingYoutubeChannels.value = true;
  try {
    const { data } = await axios.get(`/api/workspaces/${workspaceId.value}/feeds/youtube/channels`, {
      params: { social_credential_id: Number(form.social_credential_id) },
    });
    youtubeChannels.value = data.channels || [];
    if (form.youtube_channel_id && !selectedYoutubeChannelId.value) {
      selectedYoutubeChannelId.value = String(form.youtube_channel_id);
    }
    if (!selectedYoutubeChannelId.value && youtubeChannels.value.length === 1) {
      selectedYoutubeChannelId.value = String(youtubeChannels.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load YouTube channels';
    toast.error(msg);
    youtubeChannels.value = [];
  } finally {
    loadingYoutubeChannels.value = false;
  }
}

async function loadFacebookPages() {
  if (form.type !== 'facebook' || !form.social_credential_id) return;
  loadingFacebookPages.value = true;
  try {
    const { data } = await axios.get(
      `/api/workspaces/${workspaceId.value}/feeds/facebook/pages`,
      { params: { social_credential_id: Number(form.social_credential_id) } },
    );
    facebookPages.value = data.pages || [];
    // If editing an existing feed, auto-select its saved page.
    if (form.facebook_page_id && !selectedFacebookPageId.value) {
      selectedFacebookPageId.value = String(form.facebook_page_id);
    }
    // If only one page is available, preselect it.
    if (!selectedFacebookPageId.value && facebookPages.value.length === 1) {
      selectedFacebookPageId.value = String(facebookPages.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load Facebook Pages';
    toast.error(msg);
    facebookPages.value = [];
  } finally {
    loadingFacebookPages.value = false;
  }
}

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'facebook') return;
    selectedFacebookPageId.value = '';
    form.facebook_page_id = '';
    facebookPages.value = [];
    if (cred) await loadFacebookPages();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'instagram') return;
    if (skipNextInstagramCredReset.value) {
      if (cred) await loadInstagramAccounts();
      return;
    }
    selectedInstagramCombo.value = '';
    form.facebook_page_id = '';
    form.instagram_business_account_id = '';
    instagramAccounts.value = [];
    if (cred) await loadInstagramAccounts();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'tiktok') return;
    if (skipNextTikTokCredReset.value) {
      if (cred) await loadTikTokAccount();
      return;
    }
    selectedTikTokOpenId.value = '';
    tiktokAccounts.value = [];
    if (cred) await loadTikTokAccount();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'youtube') return;
    if (skipNextYoutubeCredReset.value) {
      if (cred) await loadYoutubeChannels();
      return;
    }
    selectedYoutubeChannelId.value = '';
    form.youtube_channel_id = '';
    youtubeChannels.value = [];
    if (cred) await loadYoutubeChannels();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'twitter') return;
    if (skipNextTwitterCredReset.value) {
      if (cred) await loadTwitterAccount();
      return;
    }
    selectedTwitterUserId.value = '';
    twitterAccounts.value = [];
    if (cred) await loadTwitterAccount();
  },
);

watch(
  () => selectedFacebookPageId.value,
  (v) => {
    if (form.type !== 'facebook') return;
    form.facebook_page_id = v ? String(v) : '';
  },
);

watch(
  () => selectedInstagramCombo.value,
  (v) => {
    if (form.type !== 'instagram') return;
    if (!v || typeof v !== 'string') {
      form.facebook_page_id = '';
      form.instagram_business_account_id = '';
      return;
    }
    const i = v.indexOf('|');
    if (i <= 0) {
      form.facebook_page_id = '';
      form.instagram_business_account_id = '';
      return;
    }
    form.facebook_page_id = String(v.slice(0, i));
    form.instagram_business_account_id = String(v.slice(i + 1));
  },
);

watch(
  () => selectedYoutubeChannelId.value,
  (v) => {
    if (form.type !== 'youtube') return;
    form.youtube_channel_id = v ? String(v) : '';
  },
);

function formatDate(v) {
  try {
    return new Date(v).toLocaleDateString();
  } catch {
    return String(v);
  }
}

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (!credentials.list.length) await credentials.fetchAll();
  if (isEdit.value && workspaceId.value) {
    await feeds.fetchAll(workspaceId.value);
    const f = feeds.list.find((x) => x.id === Number(feedId.value));
    if (f) {
      if (f.type === 'youtube' && f.social_credential_id) {
        skipNextYoutubeCredReset.value = true;
      }
      if (f.type === 'twitter' && f.social_credential_id) {
        skipNextTwitterCredReset.value = true;
      }
      if (f.type === 'tiktok' && f.social_credential_id) {
        skipNextTikTokCredReset.value = true;
      }
      if (f.type === 'instagram' && f.social_credential_id) {
        skipNextInstagramCredReset.value = true;
      }
      form.name = f.name;
      form.type = f.type;
      form.source_url = f.source_url || '';
      form.youtube_channel_id = f.youtube_channel_id || '';
      form.facebook_page_id = f.facebook_page_id || '';
      form.instagram_business_account_id = f.instagram_business_account_id || '';
      form.social_credential_id = f.social_credential_id || '';
      if (f.type === 'facebook' && f.social_credential_id) {
        selectedFacebookPageId.value = String(f.facebook_page_id || '');
        await loadFacebookPages();
      }
      if (f.type === 'instagram' && f.social_credential_id) {
        await loadInstagramAccounts();
        if (form.facebook_page_id && form.instagram_business_account_id) {
          selectedInstagramCombo.value = instagramAccountValue({
            facebook_page_id: String(form.facebook_page_id),
            instagram_business_account_id: String(form.instagram_business_account_id),
          });
        }
        await nextTick();
        skipNextInstagramCredReset.value = false;
      }
      if (f.type === 'youtube' && f.social_credential_id) {
        await nextTick();
        skipNextYoutubeCredReset.value = false;
      }
      if (f.type === 'twitter' && f.social_credential_id) {
        await nextTick();
        skipNextTwitterCredReset.value = false;
      }
      if (f.type === 'tiktok' && f.social_credential_id) {
        await nextTick();
        skipNextTikTokCredReset.value = false;
      }
    }
  }
});

async function submit() {
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      type: form.type,
      source_url:
        form.type === 'youtube' ||
        form.type === 'facebook' ||
        form.type === 'instagram' ||
        form.type === 'twitter' ||
        form.type === 'tiktok'
          ? ''
          : (form.source_url || ''),
      youtube_channel_id: form.type === 'youtube' ? form.youtube_channel_id : null,
      facebook_page_id:
        form.type === 'facebook' || form.type === 'instagram' ? form.facebook_page_id : null,
      instagram_business_account_id: form.type === 'instagram' ? form.instagram_business_account_id : null,
      social_credential_id:
        (form.type === 'youtube' ||
          form.type === 'facebook' ||
          form.type === 'instagram' ||
          form.type === 'twitter' ||
          form.type === 'tiktok') &&
        form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    };
    let savedFeedId = feedId.value;
    if (isEdit.value) {
      const updated = await feeds.update(workspaceId.value, Number(feedId.value), payload);
      savedFeedId = updated.id;
    } else {
      const created = await feeds.create(workspaceId.value, payload);
      savedFeedId = created.id;
    }
    router.push(`/workspaces/${workspaceId.value}/feeds/${savedFeedId}/curate`);
  } catch {
    // error in store
  } finally {
    saving.value = false;
  }
}

async function testConnection() {
  testingConnection.value = true;
  try {
    await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-youtube`, {
      social_credential_id:
        form.type === 'youtube' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      youtube_channel_id: form.type === 'youtube' ? form.youtube_channel_id : null,
    });
    toast.success('YouTube connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'YouTube connection is healthy and ready for sync.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test YouTube connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingConnection.value = false;
  }
}

async function testFacebookConnection() {
  testingFacebook.value = true;
  try {
    await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-facebook`, {
      social_credential_id:
        form.type === 'facebook' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      facebook_page_id: form.type === 'facebook' ? form.facebook_page_id : null,
    });
    toast.success('Facebook connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'Facebook page connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Facebook connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingFacebook.value = false;
  }
}

async function testInstagramConnection() {
  testingInstagram.value = true;
  try {
    await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-instagram`, {
      social_credential_id:
        form.type === 'instagram' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      facebook_page_id: form.type === 'instagram' ? form.facebook_page_id : null,
      instagram_business_account_id:
        form.type === 'instagram' ? form.instagram_business_account_id : null,
    });
    toast.success('Instagram connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'Instagram media endpoint verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Instagram connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingInstagram.value = false;
  }
}

async function testTwitterConnection() {
  testingTwitter.value = true;
  try {
    await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-twitter`, {
      social_credential_id:
        form.type === 'twitter' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    });
    toast.success('X connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'X account connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test X connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingTwitter.value = false;
  }
}

async function testTikTokConnection() {
  testingTikTok.value = true;
  try {
    await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-tiktok`, {
      social_credential_id:
        form.type === 'tiktok' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    });
    toast.success('TikTok connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'TikTok connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test TikTok connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingTikTok.value = false;
  }
}

async function testRss() {
  testingRss.value = true;
  rssTestSummary.value = null;
  try {
    const { data } = await axios.post(`/api/workspaces/${workspaceId.value}/feeds/test-rss`, {
      source_url: form.source_url,
    });
    rssTestSummary.value = {
      feed_title: data.feed_title || null,
      item_count: data.item_count ?? null,
      sample_title: data.sample_title || null,
    };
    toast.success(data.message || 'RSS feed looks good.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'RSS feed URL resolved and parsed successfully.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test RSS';
    toast.error(msg);
    rssTestSummary.value = null;
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingRss.value = false;
  }
}

watch(
  () => form.type,
  (t) => {
    if (t !== 'rss') rssTestSummary.value = null;
    connectionStatus.kind = '';
    connectionStatus.message = '';
  },
);

watch(
  () => form.source_url,
  () => {
    if (form.type === 'rss') rssTestSummary.value = null;
    if (form.type === 'rss') {
      connectionStatus.kind = '';
      connectionStatus.message = '';
    }
  },
);
</script>

<style scoped>
.feed-form-shell {
  position: relative;
}

.feed-form-hero {
  background:
    radial-gradient(880px 260px at -8% -48%, rgba(56, 189, 248, 0.15), transparent 65%),
    radial-gradient(760px 240px at 110% -40%, rgba(99, 102, 241, 0.18), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.94));
}

.feed-type-card {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 0.7rem;
  background: rgba(255, 255, 255, 0.88);
  padding: 0.55rem 0.65rem;
  transition: all 0.18s ease;
}

.feed-type-card:hover {
  border-color: rgba(165, 180, 252, 0.72);
  background: rgba(238, 242, 255, 0.6);
}

.feed-type-card--active {
  border-color: rgba(99, 102, 241, 0.52);
  background: rgba(238, 242, 255, 0.88);
  box-shadow: 0 10px 24px -18px rgba(79, 70, 229, 0.9);
}

.feed-type-icon-wrap {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.setup-step {
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

.setup-step__dot {
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

.setup-step--active {
  border-color: rgba(129, 140, 248, 0.55);
  color: rgb(67 56 202);
  background: rgba(238, 242, 255, 0.84);
}

.setup-step--active .setup-step__dot {
  background: rgba(99, 102, 241, 0.95);
  color: white;
}

.setup-step-divider {
  width: 1rem;
  height: 1px;
  background: rgba(203, 213, 225, 0.9);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.18s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
