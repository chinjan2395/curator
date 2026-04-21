<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <router-link to="/workspaces" class="text-sm-pro text-slate-500 hover:text-slate-700">← Workspaces</router-link>
        <div>
          <h1 class="page-title flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M3.5 4.75A1.75 1.75 0 0 1 5.25 3h9.5A1.75 1.75 0 0 1 16.5 4.75v10.5A1.75 1.75 0 0 1 14.75 17h-9.5A1.75 1.75 0 0 1 3.5 15.25V4.75Zm2 1a.75.75 0 0 0 0 1.5h4.75a.75.75 0 0 0 0-1.5H5.5Zm0 3.5a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5h-9Zm0 3.5a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5h-6Z" />
            </svg>
            Feeds · {{ workspaceName }}
          </h1>
          <p class="page-kicker">Source channels configured for this workspace.</p>
        </div>
      </div>
      <router-link :to="`/workspaces/${workspaceId}/feeds/new`" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro">
        New feed
      </router-link>
    </div>
    <div v-if="!feeds.loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">FD</span> Total feeds
        </div>
        <div class="mt-3 flex items-center gap-3">
          <div class="donut-ring" :style="feedRingStyle">
            <div class="donut-inner">{{ totalFeeds }}</div>
          </div>
          <div>
            <div class="text-lg-pro font-semibold text-slate-800">{{ totalFeeds }}</div>
            <div class="text-2xs text-slate-500">Connected sources</div>
            <div class="mt-1 text-2xs" :class="trendClass(feedsTrend)">
              {{ trendLabel(feedsTrend, 'vs last visit') }}
            </div>
          </div>
        </div>
      </div>
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">MX</span> Feed type mix
        </div>
        <div class="mt-1 text-2xs" :class="trendClass(typeVarietyTrend)">
          {{ trendLabel(typeVarietyTrend, 'type variety') }}
        </div>
        <div class="mt-3 space-y-2 max-h-[92px] overflow-y-auto pr-1">
          <div v-for="t in feedTypeDistribution" :key="t.type" class="space-y-1">
            <div class="flex items-center justify-between text-2xs text-slate-600">
              <span class="truncate inline-flex items-center gap-1.5">
                <span class="type-pill__icon">
                  <SocialIcon :type="t.type" />
                </span>
                {{ feedTypeLabel(t.type) }}
              </span>
              <span class="font-medium text-slate-700">{{ t.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full bg-gradient-to-r from-indigo-300/80 to-violet-300/80" :style="{ width: `${t.width}%` }" />
            </div>
          </div>
          <div v-if="!feedTypeDistribution.length" class="text-2xs text-slate-500">No feed data yet.</div>
        </div>
      </div>
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">SRC</span> Source URL coverage
        </div>
        <div class="mt-2 text-lg-pro font-semibold text-slate-800">{{ sourceCoverage }}%</div>
        <div class="mt-1 text-2xs" :class="trendClass(coverageTrend)">
          {{ trendLabel(coverageTrend, 'coverage pts') }}
        </div>
        <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-cyan-300/85 to-indigo-400/85" :style="{ width: `${sourceCoverage}%` }" />
        </div>
        <div class="mt-2 text-2xs text-slate-500">
          {{ feedsWithSource }} with URL · {{ feedsWithoutSource }} without URL
        </div>
      </div>
    </div>
    <div v-if="feeds.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="feeds.error" class="text-sm-pro text-red-600">{{ feeds.error }}</div>
    <div v-else-if="!feeds.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      No feeds yet. Create one to get started.
    </div>
    <div class="table-shell feed-table-shell">
      <table class="w-full text-left">
        <thead class="table-head feed-table-head">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th">Type</th>
            <th class="table-th">Source</th>
            <th class="table-th w-32">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="f in feeds.list" :key="f.id" class="table-tr feed-table-row">
            <td class="table-td font-medium text-slate-800">
              <div class="flex items-center gap-2 min-w-0">
                <span class="feed-name-dot" :class="`feed-name-dot--${String(f.type || 'other')}`" />
                <span class="truncate">{{ f.name }}</span>
              </div>
            </td>
            <td class="table-td">
              <span class="type-pill" :class="`type-pill--${String(f.type || 'other')}`">
                <span class="type-pill__icon">
                  <SocialIcon :type="f.type" />
                </span>
                {{ feedTypeLabel(f.type) }}
              </span>
            </td>
            <td class="px-4 py-2.5 text-2xs text-slate-500 truncate max-w-[240px]">
              <span v-if="f.source_url" class="source-chip">{{ f.source_url }}</span>
              <span v-else>—</span>
            </td>
            <td class="table-td">
              <div class="flex items-center gap-2">
                <router-link
                  :to="`/workspaces/${workspaceId}/feeds/${f.id}/edit`"
                  class="action-link action-link--premium"
                  :class="canEditOrDelete ? '' : 'text-slate-400 cursor-not-allowed'"
                  @click.prevent="handleEditClick(f)"
                >
                  Edit
                </router-link>
                <button
                  type="button"
                  class="action-link action-link--premium"
                  :class="canEditOrDelete ? '!text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80' : 'text-slate-400 cursor-not-allowed'"
                  @click="handleDeleteClick(f)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="surface-card p-4 space-y-3 preview-shell">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <div class="text-sm-pro font-medium text-slate-800 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M5.5 3A2.5 2.5 0 0 0 3 5.5v9A2.5 2.5 0 0 0 5.5 17h9a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 14.5 3h-9Zm1.72 3.47a.75.75 0 0 1 1.06 0L11.81 10l-3.53 3.53a.75.75 0 0 1-1.06-1.06L9.69 10 7.22 7.53a.75.75 0 0 1 0-1.06Z" />
            </svg>
            Live embed preview
          </div>
          <div class="text-2xs text-slate-500">Preview feed output for this workspace before publishing externally.</div>
        </div>
        <div class="text-2xs text-slate-500">Pick a feed card below</div>
      </div>
      <div class="preview-picker-grid">
        <button
          v-for="f in feeds.list"
          :key="f.id"
          type="button"
          class="preview-pick-card"
          :class="String(f.id) === String(previewFeedId) ? 'preview-pick-card--active' : ''"
          @click="previewFeedId = String(f.id)"
        >
          <div class="preview-card-media">
            <img
              v-if="previewMetaByFeedId[f.id]?.thumbnail_url"
              :src="previewMetaByFeedId[f.id].thumbnail_url"
              :alt="`${f.name} thumbnail`"
              class="preview-card-media__img"
            />
            <div v-else class="preview-card-media__placeholder">
              <span class="preview-card-media__icon">
                <SocialIcon :type="f.type" />
              </span>
              <span class="preview-card-media__label">No thumbnail yet</span>
            </div>
          </div>
          <div class="mt-2">
            <div class="type-pill !text-2xs" :class="`type-pill--${String(f.type || 'other')}`">
              <span class="type-pill__icon">
                <SocialIcon :type="f.type" />
              </span>
              {{ feedTypeLabel(f.type) }}
            </div>
          </div>
          <div class="mt-2 text-sm-pro font-medium text-slate-800 truncate text-left">{{ f.name }}</div>
          <div class="mt-1 text-2xs text-slate-500 text-left line-clamp-2">
            {{ previewMetaByFeedId[f.id]?.sample_title || sourceDescriptor(f) }}
          </div>
          <div class="mt-2 flex items-center justify-between text-2xs text-slate-500">
            <span>{{ f.last_synced_at ? formatDate(f.last_synced_at) : 'Not synced' }}</span>
            <span v-if="loadingPreviewMetaByFeedId[f.id]">Loading…</span>
            <span v-else>{{ workspacePublicKey ? 'Preview ready' : 'Publish workspace first' }}</span>
          </div>
        </button>
      </div>
      <div v-if="!previewFeedId" class="text-sm-pro text-slate-600 preview-hint">
        Select a feed to render its live embed preview.
      </div>
      <div v-else-if="!previewLoadedPublicKey" class="text-sm-pro text-slate-600 preview-hint">
        Selected feed has no loaded preview. Click <span class="font-medium text-slate-700">Load preview</span>.
      </div>
      <div v-else>
        <div
          class="curator-embed-box preview-frame"
          :key="previewLoadedPublicKey"
          :data-curator-feed="previewLoadedPublicKey"
        />
        <div v-if="previewLoadError" class="text-2xs text-red-600 mt-3">{{ previewLoadError }}</div>
        <div v-else class="text-2xs text-slate-500 mt-3">
          This matches how the embed is rendered on external sites.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';

const route = useRoute();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const toast = useToastStore();

const workspaceId = computed(() => route.params.workspaceId);
const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});
const workspacePublicKey = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return String(w?.public_key || '').trim();
});
const totalFeeds = computed(() => feeds.list.length);
const feedsWithSource = computed(() => feeds.list.filter((f) => String(f.source_url || '').trim().length > 0).length);
const feedsWithoutSource = computed(() => Math.max(0, totalFeeds.value - feedsWithSource.value));
const sourceCoverage = computed(() =>
  totalFeeds.value ? Math.round((feedsWithSource.value / totalFeeds.value) * 100) : 0,
);
const feedTypeCounts = computed(() =>
  feeds.list.reduce((acc, f) => {
    const type = String(f.type || 'other');
    acc[type] = (acc[type] || 0) + 1;
    return acc;
  }, {}),
);
const maxTypeCount = computed(() => Math.max(1, ...Object.values(feedTypeCounts.value).map((v) => Number(v || 0))));
const feedTypeDistribution = computed(() =>
  Object.entries(feedTypeCounts.value)
    .map(([type, count]) => ({
      type,
      count: Number(count || 0),
      width: Math.max(10, Math.round((Number(count || 0) / maxTypeCount.value) * 100)),
    }))
    .sort((a, b) => b.count - a.count),
);
const typeVariety = computed(() => Object.keys(feedTypeCounts.value).length);
const feedRingStyle = computed(() => {
  const pct = Math.min(100, Math.round((totalFeeds.value / 20) * 100));
  return {
    background: `conic-gradient(rgba(99,102,241,0.75) ${pct}%, rgba(224,231,255,0.9) ${pct}% 100%)`,
  };
});
const feedsTrend = ref(0);
const coverageTrend = ref(0);
const typeVarietyTrend = ref(0);
const previewFeedId = ref('');
const previewLoadedPublicKey = ref('');
const previewLoadError = ref('');
const previewMetaByFeedId = ref({});
const loadingPreviewMetaByFeedId = ref({});
const previewSelectedFeed = computed(() =>
  feeds.list.find((f) => String(f.id) === String(previewFeedId.value)) || null,
);

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (workspaceId.value) await feeds.fetchAll(workspaceId.value);
  if (!previewFeedId.value && feeds.list.length) {
    previewFeedId.value = String(feeds.list[0].id);
  }
  loadTrendSnapshot();
});

watch(workspaceId, async (id) => {
  if (id) {
    feeds.clearList();
    await feeds.fetchAll(id);
    previewFeedId.value = feeds.list.length ? String(feeds.list[0].id) : '';
    previewLoadedPublicKey.value = '';
    previewLoadError.value = '';
    loadTrendSnapshot();
  }
}, { immediate: false });

const canEditOrDelete = computed(() => true); // backend ultimately enforces accepted-post rule

function feedTypeLabel(type) {
  if (type === 'youtube') return 'YouTube';
  if (type === 'facebook') return 'Facebook';
  if (type === 'instagram') return 'Instagram';
  if (type === 'tiktok') return 'TikTok';
  if (type === 'rss') return 'RSS / Atom';
  if (type === 'twitter') return 'X / Twitter';
  return type || '—';
}

function sourceDescriptor(f) {
  if (f.type === 'youtube' && f.youtube_channel_id) return `Channel: ${f.youtube_channel_id}`;
  if (f.type === 'facebook' && f.facebook_page_id) return `Page: ${f.facebook_page_id}`;
  if (f.type === 'instagram' && f.instagram_business_account_id) {
    const page = f.facebook_page_id ? ` · Page ${f.facebook_page_id}` : '';
    return `IG: ${f.instagram_business_account_id}${page}`;
  }
  if (f.type === 'twitter' && f.twitter_username) return `@${f.twitter_username}`;
  if (f.source_url) return f.source_url;
  return 'No source metadata yet';
}

function handleEditClick(f) {
  // Let backend enforce the accepted-post rule; just navigate.
  // If the backend rejects, FeedForm will show the error toast + message.
  window.location.href = `/workspaces/${workspaceId.value}/feeds/${f.id}/edit`;
}

async function handleDeleteClick(f) {
  if (!window.confirm(`Delete feed "${f.name}"?`)) return;
  try {
    await feeds.remove(workspaceId.value, f.id);
  } catch {
    // Error toast already handled in store; nothing else needed here.
  }
}

function resolveApiBaseUrl() {
  const fromEnv = String(
    import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || '',
  ).trim();
  const raw = fromEnv || window.location.origin;
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) return raw.replace(/\/$/, '');
  return `https://${raw}`.replace(/\/$/, '');
}

function ensureEmbedAssets(publicKey) {
  const cssId = `curator-embed-css-${publicKey}`;
  const jsId = `curator-embed-js-${publicKey}`;
  const apiBaseUrl = resolveApiBaseUrl();

  if (!document.getElementById(cssId)) {
    const link = document.createElement('link');
    link.id = cssId;
    link.rel = 'stylesheet';
    link.href = `${apiBaseUrl}/api/embed/${publicKey}.css`;
    document.head.appendChild(link);
  }

  if (!document.getElementById(jsId)) {
    const script = document.createElement('script');
    script.id = jsId;
    script.src = `${apiBaseUrl}/api/embed/${publicKey}.js`;
    script.async = true;
    script.onerror = () => {
      previewLoadError.value = 'Failed to load embed assets for this feed.';
    };
    document.body.appendChild(script);
  }
}

function removeEmbedScript(publicKey) {
  if (!publicKey) return;
  document.getElementById(`curator-embed-js-${publicKey}`)?.remove();
}

async function loadPreviewFeed() {
  previewLoadError.value = '';
  if (!workspacePublicKey.value) return;
  removeEmbedScript(previewLoadedPublicKey.value);
  previewLoadedPublicKey.value = workspacePublicKey.value;
  ensureEmbedAssets(previewLoadedPublicKey.value);
}

async function loadPreviewMetaForFeed(f) {
  if (!f?.id || !workspacePublicKey.value) return;
  if (previewMetaByFeedId.value[f.id] || loadingPreviewMetaByFeedId.value[f.id]) return;
  loadingPreviewMetaByFeedId.value = { ...loadingPreviewMetaByFeedId.value, [f.id]: true };
  try {
    const { data } = await axios.get(`/api/public/feeds/${encodeURIComponent(workspacePublicKey.value)}/posts`, {
      params: { limit: 1 },
    });
    const first = Array.isArray(data?.posts) && data.posts.length ? data.posts[0] : null;
    previewMetaByFeedId.value = {
      ...previewMetaByFeedId.value,
      [f.id]: {
        sample_title: first?.title || '',
        thumbnail_url: first?.thumbnail_url || '',
      },
    };
  } catch {
    // Non-blocking visual enhancement only.
  } finally {
    loadingPreviewMetaByFeedId.value = { ...loadingPreviewMetaByFeedId.value, [f.id]: false };
  }
}

function trendLabel(val, suffix) {
  if (!val) return `No change ${suffix}`;
  const sign = val > 0 ? '+' : '';
  return `${sign}${val} ${suffix}`;
}

function trendClass(val) {
  if (val > 0) return 'text-emerald-600';
  if (val < 0) return 'text-rose-600';
  return 'text-slate-500';
}

function formatDate(v) {
  if (!v) return '—';
  try {
    return new Date(v).toLocaleString();
  } catch {
    return String(v);
  }
}

function snapshotKey() {
  return `feeds_analytics_snapshot_${workspaceId.value || 'global'}`;
}

function loadTrendSnapshot() {
  try {
    const raw = localStorage.getItem(snapshotKey());
    const prev = raw ? JSON.parse(raw) : null;
    if (prev && typeof prev === 'object') {
      feedsTrend.value = totalFeeds.value - Number(prev.totalFeeds || 0);
      coverageTrend.value = sourceCoverage.value - Number(prev.sourceCoverage || 0);
      typeVarietyTrend.value = typeVariety.value - Number(prev.typeVariety || 0);
    } else {
      feedsTrend.value = 0;
      coverageTrend.value = 0;
      typeVarietyTrend.value = 0;
    }
  } catch {
    feedsTrend.value = 0;
    coverageTrend.value = 0;
    typeVarietyTrend.value = 0;
  } finally {
    saveTrendSnapshot();
  }
}

function saveTrendSnapshot() {
  const snapshot = {
    totalFeeds: totalFeeds.value,
    sourceCoverage: sourceCoverage.value,
    typeVariety: typeVariety.value,
  };
  localStorage.setItem(snapshotKey(), JSON.stringify(snapshot));
}

watch([totalFeeds, sourceCoverage, typeVariety], () => {
  saveTrendSnapshot();
});

watch(previewFeedId, async () => {
  const previouslyLoadedKey = previewLoadedPublicKey.value;
  removeEmbedScript(previouslyLoadedKey);
  previewLoadedPublicKey.value = '';
  previewLoadError.value = '';
  if (previewFeedId.value && workspacePublicKey.value) {
    await loadPreviewFeed();
  }
});

watch(
  () => feeds.list,
  async (list) => {
    await Promise.all(list.map((f) => loadPreviewMetaForFeed(f)));
  },
  { immediate: true },
);

onUnmounted(() => {
  removeEmbedScript(previewLoadedPublicKey.value);
});
</script>

<style scoped>
.analytics-card {
  position: relative;
  overflow: hidden;
  background: linear-gradient(150deg, #ffffff 0%, #f8fbff 100%);
  border-color: rgba(199, 210, 254, 0.7);
}
.analytics-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 30px -26px rgba(30, 41, 59, 0.85);
}
.analytics-card::before {
  content: '';
  position: absolute;
  width: 140px;
  height: 140px;
  right: -64px;
  top: -72px;
  border-radius: 9999px;
  background: radial-gradient(circle, rgba(129, 140, 248, 0.16), rgba(129, 140, 248, 0));
  pointer-events: none;
}
.analytics-card::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, rgba(99, 102, 241, 0.7), rgba(34, 211, 238, 0.45));
}
.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  border: 1px solid rgba(165, 180, 252, 0.7);
  background: rgba(238, 242, 255, 0.9);
  color: rgb(79, 70, 229);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
}
.donut-ring {
  width: 48px;
  height: 48px;
  border-radius: 9999px;
  display: grid;
  place-items: center;
}
.donut-inner {
  width: 33px;
  height: 33px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.95);
  color: rgb(51, 65, 85);
  font-size: 0.72rem;
  font-weight: 600;
  display: grid;
  place-items: center;
}
.curator-embed-box :deep(.crt-grid) {
  gap: 18px;
}
.curator-embed-box :deep(.crt-card) {
  border-radius: 14px;
}

.preview-shell {
  background:
    radial-gradient(760px 200px at -6% -42%, rgba(56, 189, 248, 0.08), transparent 65%),
    radial-gradient(640px 180px at 106% -44%, rgba(99, 102, 241, 0.1), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}

.preview-hint {
  border: 1px dashed rgba(203, 213, 225, 0.95);
  background: rgba(248, 250, 252, 0.78);
  border-radius: 0.7rem;
  padding: 0.62rem 0.72rem;
}

.preview-frame {
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 0.9rem;
  padding: 0.5rem;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
}

.preview-picker-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.45rem;
}

@media (min-width: 720px) {
  .preview-picker-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (min-width: 1100px) {
  .preview-picker-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.preview-pick-card {
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 0.8rem;
  padding: 0.42rem 0.48rem 0.48rem;
  background: linear-gradient(165deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.94));
  text-align: left;
  transition: all 0.18s ease;
}

.preview-pick-card:hover {
  transform: translateY(-1px);
  border-color: rgba(165, 180, 252, 0.72);
  box-shadow: 0 14px 24px -22px rgba(79, 70, 229, 0.9);
}

.preview-pick-card--active {
  border-color: rgba(99, 102, 241, 0.58);
  background: linear-gradient(165deg, rgba(238, 242, 255, 0.78), rgba(243, 244, 246, 0.82));
  box-shadow: 0 16px 30px -24px rgba(79, 70, 229, 0.95);
}

.preview-card-media {
  width: 100%;
  aspect-ratio: 16 / 10;
  max-height: 98px;
  border-radius: 0.62rem;
  overflow: hidden;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: linear-gradient(150deg, rgba(241, 245, 249, 0.95), rgba(226, 232, 240, 0.9));
}

.preview-card-media__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.preview-card-media__placeholder {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  gap: 0.2rem;
  background:
    radial-gradient(240px 80px at 18% 12%, rgba(56, 189, 248, 0.2), transparent 62%),
    radial-gradient(220px 70px at 88% 14%, rgba(99, 102, 241, 0.2), transparent 60%),
    linear-gradient(165deg, rgba(241, 245, 249, 0.95), rgba(226, 232, 240, 0.95));
}

.preview-card-media__icon {
  font-size: 0.95rem;
  line-height: 1;
  color: rgb(71 85 105);
}

.preview-card-media__icon :deep(svg) {
  width: 1rem;
  height: 1rem;
  display: block;
}

.preview-card-media__label {
  font-size: 0.62rem;
  color: rgb(100 116 139);
}

.feed-table-shell {
  border-color: rgba(191, 219, 254, 0.58);
}

.feed-table-head {
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.92));
}

.feed-table-row {
  transition: transform 0.16s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.feed-table-row:hover {
  transform: translateY(-1px);
  background: linear-gradient(90deg, rgba(248, 250, 252, 0.85), rgba(241, 245, 249, 0.7));
  box-shadow: inset 3px 0 0 rgba(99, 102, 241, 0.33);
}

.action-link--premium {
  border-color: rgba(203, 213, 225, 0.95);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}
.action-link--premium:active:not(:disabled) {
  transform: translateY(0);
}

.type-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  padding: 0.15rem 0.5rem 0.15rem 0.38rem;
  color: rgb(71 85 105);
  font-size: 0.72rem;
  font-weight: 500;
}

.type-pill__icon {
  width: 1rem;
  height: 1rem;
  display: grid;
  place-items: center;
  border-radius: 999px;
  font-size: 0.67rem;
  font-weight: 700;
  background: rgba(226, 232, 240, 0.82);
  color: rgb(51 65 85);
}

.type-pill__icon :deep(svg) {
  width: 0.78rem;
  height: 0.78rem;
  display: block;
}

.type-pill--youtube .type-pill__icon { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-pill--facebook .type-pill__icon { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-pill--instagram .type-pill__icon { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-pill--tiktok .type-pill__icon { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-pill--rss .type-pill__icon { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }
.type-pill--twitter .type-pill__icon { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }

.feed-name-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  flex-shrink: 0;
  background: rgb(148 163 184);
  box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.14);
}

.feed-name-dot--youtube { background: rgb(220 38 38); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.14); }
.feed-name-dot--facebook { background: rgb(37 99 235); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14); }
.feed-name-dot--instagram { background: rgb(190 24 93); box-shadow: 0 0 0 3px rgba(190, 24, 93, 0.14); }
.feed-name-dot--tiktok { background: rgb(15 23 42); box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.14); }
.feed-name-dot--rss { background: rgb(234 88 12); box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14); }
.feed-name-dot--twitter { background: rgb(15 23 42); box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.14); }

.source-chip {
  display: inline-block;
  max-width: 100%;
  padding: 0.2rem 0.45rem;
  border-radius: 999px;
  border: 1px solid rgba(226, 232, 240, 0.95);
  background: rgba(248, 250, 252, 0.9);
  color: rgb(71 85 105);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .analytics-card:hover {
    transform: none;
    box-shadow: none;
  }
  .feed-table-row:hover {
    transform: none;
    box-shadow: none;
  }
  .preview-pick-card:hover {
    transform: none;
    box-shadow: none;
  }
}
</style>
