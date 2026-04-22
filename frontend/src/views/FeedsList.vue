<template>
  <div class="space-y-4">
    <nav class="page-breadcrumb">
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <span>{{ workspaceName }}</span>
    </nav>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3.5 4.75A1.75 1.75 0 0 1 5.25 3h9.5A1.75 1.75 0 0 1 16.5 4.75v10.5A1.75 1.75 0 0 1 14.75 17h-9.5A1.75 1.75 0 0 1 3.5 15.25V4.75Zm2 1a.75.75 0 0 0 0 1.5h4.75a.75.75 0 0 0 0-1.5H5.5Zm0 3.5a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5h-9Zm0 3.5a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5h-6Z" />
          </svg>
          Feeds
        </h1>
        <p class="page-kicker">Source channels configured for this workspace.</p>
      </div>
      <router-link :to="`/workspaces/${workspaceId}/feeds/new`" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro">
        Add feed
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
    <WorkspaceWizardStepper current="feed" />
    <div class="feed-setup-hero surface-card p-4 md:p-5">
      <div>
        <h2 class="text-sm-pro font-semibold text-slate-800">Feed setup</h2>
        <p class="page-kicker mt-0.5">Add one or more source feeds before reviewing posts and publishing the workspace.</p>
      </div>
      <div class="wizard-actions mt-4">
        <div class="text-2xs text-slate-500">
          {{ feeds.list.length ? 'Feeds are ready. Continue to review posts.' : 'Create at least one feed before continuing.' }}
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <router-link
            :to="nextCurateUrl"
            class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro"
            :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'"
            :aria-disabled="!feeds.list.length"
          >
            Next: Curate
          </router-link>
          <router-link
            :to="`/workspaces/${workspaceId}/publish`"
            class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro"
            :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'"
            :aria-disabled="!feeds.list.length"
          >
            Skip to Publish
          </router-link>
        </div>
      </div>
    </div>
    <div v-if="feeds.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="feeds.error" class="text-sm-pro text-red-600">{{ feeds.error }}</div>
    <div v-else-if="!feeds.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      <div>No feeds yet. Create the first feed to start workspace setup.</div>
      <router-link :to="`/workspaces/${workspaceId}/feeds/new`" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro mt-3 inline-flex">
        Create feed
      </router-link>
    </div>
    <div v-if="!feeds.loading && feeds.list.length" class="table-shell feed-table-shell">
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
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import WorkspaceWizardStepper from '../components/WorkspaceWizardStepper.vue';

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
const nextCurateUrl = computed(() => {
  const selected = feeds.list[0];
  return selected?.id
    ? `/workspaces/${workspaceId.value}/feeds/${selected.id}/curate`
    : `/workspaces/${workspaceId.value}/curate`;
});

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (workspaceId.value) await feeds.fetchAll(workspaceId.value);
  loadTrendSnapshot();
});

watch(workspaceId, async (id) => {
  if (id) {
    feeds.clearList();
    await feeds.fetchAll(id);
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

function loadTrendSnapshot() {
  try {
    const raw = localStorage.getItem(`feeds_analytics_snapshot_${workspaceId.value || 'global'}`);
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
  localStorage.setItem(`feeds_analytics_snapshot_${workspaceId.value || 'global'}`, JSON.stringify(snapshot));
}

watch([totalFeeds, sourceCoverage, typeVariety], () => {
  saveTrendSnapshot();
});
</script>

<style scoped>
.analytics-card {
  position: relative;
  overflow: hidden;
  background: linear-gradient(150deg, #ffffff 0%, #f8fbff 100%);
  border-color: rgba(199, 210, 254, 0.7);
}
.feed-setup-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(56, 189, 248, 0.1), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(99, 102, 241, 0.12), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96));
}
.wizard-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding-top: 0.85rem;
  border-top: 1px solid rgba(226, 232, 240, 0.8);
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
}
</style>
