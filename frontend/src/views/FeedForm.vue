<template>
  <div class="space-y-4 max-w-3xl">
    <div class="mb-4">
      <router-link :to="`/workspaces/${workspaceId}/feeds`" class="text-sm-pro text-slate-500 hover:text-slate-700">← Feeds</router-link>
    </div>
    <div>
      <h1 class="page-title">{{ isEdit ? 'Edit feed' : 'New feed' }}</h1>
      <p class="page-kicker">Configure source type, provider connection, and sync defaults.</p>
    </div>
    <form @submit.prevent="submit" class="surface-card p-5 space-y-4 max-w-3xl">
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
      </div>
      <div v-if="form.type === 'youtube'">
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
      <div v-else-if="form.type === 'facebook'">
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
      <div v-else-if="form.type === 'twitter'">
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
      <div v-else-if="form.type === 'tiktok'">
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
      <div v-else-if="form.type === 'rss'">
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
      <div v-else>
        <label class="label-pro">Source URL <span class="text-slate-400 font-normal">(optional)</span></label>
        <input v-model="form.source_url" type="url" class="input-pro" placeholder="https://…" />
        <p class="mt-1 text-2xs text-slate-500">
          Optional reference URL for this feed type.
        </p>
      </div>
      <div v-if="feeds.lastActionError" class="text-2xs text-red-600">
        {{ feeds.lastActionError }}
      </div>
      <div class="flex items-center gap-2">
        <button
          type="submit"
          class="btn-primary !w-auto !px-4"
          :disabled="
            saving ||
            (form.type === 'twitter' && (!form.social_credential_id || !selectedTwitterUserId)) ||
            (form.type === 'tiktok' && (!form.social_credential_id || !selectedTikTokOpenId)) ||
            (form.type === 'rss' && !String(form.source_url || '').trim())
          "
        >
          {{ saving ? 'Saving…' : (isEdit ? 'Save' : 'Create') }}
        </button>
        <router-link :to="`/workspaces/${workspaceId}/feeds`" class="btn-secondary !w-auto">Cancel</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useCredentialsStore } from '../stores/credentials';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const credentials = useCredentialsStore();
const toast = useToastStore();

const workspaceId = computed(() => route.params.workspaceId);
const feedId = computed(() => route.params.feedId);
const isEdit = computed(() => !!feedId.value);

const form = reactive({
  name: '',
  type: '',
  source_url: '',
  youtube_channel_id: '',
  facebook_page_id: '',
  social_credential_id: '',
});
const saving = ref(false);
const testingConnection = ref(false);
const testingFacebook = ref(false);
const testingTwitter = ref(false);
const testingTikTok = ref(false);
const testingRss = ref(false);
const loadingFacebookPages = ref(false);
const facebookPages = ref([]);
const selectedFacebookPageId = ref('');
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

const youtubeCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'youtube'),
);

const facebookCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'facebook'),
);

const twitterCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'twitter'),
);
const tiktokCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'tiktok'),
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
      form.name = f.name;
      form.type = f.type;
      form.source_url = f.source_url || '';
      form.youtube_channel_id = f.youtube_channel_id || '';
      form.facebook_page_id = f.facebook_page_id || '';
      form.social_credential_id = f.social_credential_id || '';
      if (f.type === 'facebook' && f.social_credential_id) {
        selectedFacebookPageId.value = String(f.facebook_page_id || '');
        await loadFacebookPages();
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
        form.type === 'youtube' || form.type === 'facebook' || form.type === 'twitter' || form.type === 'tiktok'
          ? ''
          : (form.source_url || ''),
      youtube_channel_id: form.type === 'youtube' ? form.youtube_channel_id : null,
      facebook_page_id: form.type === 'facebook' ? form.facebook_page_id : null,
      social_credential_id:
        (form.type === 'youtube' || form.type === 'facebook' || form.type === 'twitter' || form.type === 'tiktok') &&
        form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    };
    if (isEdit.value) {
      await feeds.update(workspaceId.value, Number(feedId.value), payload);
    } else {
      await feeds.create(workspaceId.value, payload);
    }
    router.push(`/workspaces/${workspaceId.value}/feeds`);
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
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test YouTube connection';
    toast.error(msg);
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
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Facebook connection';
    toast.error(msg);
  } finally {
    testingFacebook.value = false;
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
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test X connection';
    toast.error(msg);
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
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test TikTok connection';
    toast.error(msg);
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
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test RSS';
    toast.error(msg);
    rssTestSummary.value = null;
  } finally {
    testingRss.value = false;
  }
}

watch(
  () => form.type,
  (t) => {
    if (t !== 'rss') rssTestSummary.value = null;
  },
);

watch(
  () => form.source_url,
  () => {
    if (form.type === 'rss') rssTestSummary.value = null;
  },
);
</script>
