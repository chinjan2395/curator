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
              <span class="truncate">{{ feedTypeLabel(t.type) }}</span>
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
    <div v-else class="surface-card p-4 space-y-3">
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
        <div class="flex items-center gap-2 flex-wrap">
          <select v-model="previewFeedId" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto min-w-[220px]">
            <option value="">Select feed</option>
            <option v-for="f in feeds.list" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
          </select>
          <button
            type="button"
            class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro"
            :disabled="!previewSelectedFeed?.public_key"
            @click="loadPreviewFeed"
          >
            Load preview
          </button>
        </div>
      </div>
      <div v-if="!previewFeedId" class="text-sm-pro text-slate-600">
        Select a feed to render its live embed preview.
      </div>
      <div v-else-if="!previewLoadedPublicKey" class="text-sm-pro text-slate-600">
        Selected feed has no loaded preview. Click <span class="font-medium text-slate-700">Load preview</span>.
      </div>
      <div v-else>
        <div
          class="curator-embed-box"
          :key="previewLoadedPublicKey"
          :data-curator-feed="previewLoadedPublicKey"
        />
        <div v-if="previewLoadError" class="text-2xs text-red-600 mt-3">{{ previewLoadError }}</div>
        <div v-else class="text-2xs text-slate-500 mt-3">
          This matches how the embed is rendered on external sites.
        </div>
      </div>
    </div>
    <div v-if="feeds.list.length" class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th">Type</th>
            <th class="table-th">Source</th>
            <th class="table-th w-32">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="f in feeds.list" :key="f.id" class="table-tr">
            <td class="table-td font-medium text-slate-800">{{ f.name }}</td>
            <td class="table-td">{{ feedTypeLabel(f.type) }}</td>
            <td class="px-4 py-2.5 text-2xs text-slate-500 truncate max-w-[200px]">{{ f.source_url || '—' }}</td>
            <td class="table-td">
              <div class="flex items-center gap-2">
                <router-link :to="`/workspaces/${workspaceId}/feeds/${f.id}/curate`" class="action-link">Curate</router-link>
                <router-link
                  :to="`/workspaces/${workspaceId}/feeds/${f.id}/publish`"
                  class="action-link"
                >
                  Publish
                </router-link>
                <router-link
                  :to="`/workspaces/${workspaceId}/feeds/${f.id}/edit`"
                  class="action-link"
                  :class="canEditOrDelete ? '' : 'text-slate-400 cursor-not-allowed'"
                  @click.prevent="handleEditClick(f)"
                >
                  Edit
                </router-link>
                <button
                  type="button"
                  class="action-link"
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
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const toast = useToastStore();

const workspaceId = computed(() => route.params.workspaceId);
const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
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
  if (!previewSelectedFeed.value?.public_key) return;
  removeEmbedScript(previewLoadedPublicKey.value);
  previewLoadedPublicKey.value = previewSelectedFeed.value.public_key;
  ensureEmbedAssets(previewLoadedPublicKey.value);
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
  previewLoadedPublicKey.value = '';
  previewLoadError.value = '';
  if (previewFeedId.value && previewSelectedFeed.value?.public_key) {
    await loadPreviewFeed();
  }
});

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
</style>
