<template>
  <div class="space-y-4">
    <div class="dashboard-hero surface-card p-5 md:p-6 flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 3.75A.75.75 0 0 1 3.75 3h12.5a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75V3.75Zm3 2.5a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0v-7.5Zm4.75 2a.75.75 0 0 0-1.5 0v5.5a.75.75 0 0 0 1.5 0v-5.5Zm4.75-1.5a.75.75 0 0 0-1.5 0v7a.75.75 0 0 0 1.5 0v-7Z" />
          </svg>
          Dashboard analytics
        </h1>
        <p class="page-kicker mt-1">Workspace and feed performance overview.</p>
      </div>
      <div class="inline-flex items-center gap-1.5 rounded-full border border-white/75 bg-white/80 px-2.5 py-1 text-2xs text-slate-600">
        <span class="inline-block h-1.5 w-1.5 rounded-full bg-cyan-500" />
        Global snapshot
      </div>
    </div>

    <div v-if="analyticsLoading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading analytics…
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      <button type="button" class="surface-card p-4 analytics-card text-left hover:border-slate-300 transition-colors" @click="goToWorkspaces">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">WS</span> Total workspaces
        </div>
        <div class="mt-2 text-xl-pro font-semibold text-slate-900">{{ totalWorkspaces }}</div>
        <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-indigo-300/85 to-violet-300/85" :style="{ width: `${workspaceUtilization}%` }" />
        </div>
        <div class="mt-1 text-2xs text-slate-500">{{ workspaceUtilization }}% utilization target</div>
      </button>
      <button type="button" class="surface-card p-4 analytics-card text-left hover:border-slate-300 transition-colors" @click="goToWorkspaces">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">FD</span> Total feeds
        </div>
        <div class="mt-2 text-xl-pro font-semibold text-slate-900">{{ totalFeeds }}</div>
        <div class="mt-2 text-2xs text-slate-500">Avg/workspace: <span class="text-slate-700 font-medium">{{ avgFeedsPerWorkspace }}</span></div>
        <div class="mt-3 flex items-end gap-1.5 h-10">
          <button
            v-for="w in workspaceBars"
            :key="w.id"
            type="button"
            class="flex-1 rounded-sm bg-gradient-to-t from-cyan-300/75 to-indigo-300/75"
            :style="{ height: `${w.height}%` }"
            :title="`${w.name}: ${w.count} feeds`"
            @click.stop="goToWorkspaceFeeds(w.id)"
          />
        </div>
      </button>
      <button type="button" class="surface-card p-4 analytics-card text-left hover:border-slate-300 transition-colors" @click="goToPublish">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">PB</span> Publish coverage
        </div>
        <div class="mt-2 text-xl-pro font-semibold text-slate-900">{{ publishedFeeds }}</div>
        <div class="text-2xs text-slate-500">Workspaces with public key</div>
        <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-emerald-300/80 to-cyan-300/80" :style="{ width: `${publishedCoverage}%` }" />
        </div>
        <div class="mt-1 text-2xs text-slate-500">{{ publishedCoverage }}% of all workspaces</div>
      </button>
      <button type="button" class="surface-card p-4 analytics-card text-left hover:border-slate-300 transition-colors" @click="goToWorkspaces">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">SY</span> Sync health
        </div>
        <div class="mt-2 text-xl-pro font-semibold text-slate-900">{{ syncedFeeds }}</div>
        <div class="text-2xs text-slate-500">Feeds synced at least once</div>
        <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-amber-300/85 to-indigo-300/85" :style="{ width: `${syncCoverage}%` }" />
        </div>
        <div class="mt-1 text-2xs text-slate-500">{{ syncCoverage }}% sync coverage</div>
      </button>
    </div>

    <div v-if="!analyticsLoading" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="surface-card p-4 analytics-card">
        <div class="text-sm-pro font-medium text-slate-800 flex items-center gap-1.5">
          <span class="metric-chip">MX</span> Feed type distribution
        </div>
        <div class="mt-3 space-y-2 max-h-[154px] overflow-y-auto pr-1">
          <div v-for="t in feedTypeDistribution" :key="t.type" class="space-y-1">
            <div class="flex items-center justify-between text-2xs text-slate-600">
              <span class="inline-flex items-center gap-1.5">
                <span class="type-dot" :class="`type-dot--${String(t.type || 'other')}`">
                  <SocialIcon :type="t.type" />
                </span>
                {{ feedTypeLabel(t.type) }}
              </span>
              <span class="font-medium text-slate-700">{{ t.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full bg-gradient-to-r from-violet-300/80 to-indigo-400/80" :style="{ width: `${t.width}%` }" />
            </div>
          </div>
          <div v-if="!feedTypeDistribution.length" class="text-2xs text-slate-500">No feed data yet.</div>
        </div>
      </div>
      <div class="surface-card p-4 analytics-card">
        <div class="text-sm-pro font-medium text-slate-800 flex items-center gap-1.5">
          <span class="metric-chip">TOP</span> Top workspaces by feeds
        </div>
        <div class="mt-3 space-y-2 max-h-[154px] overflow-y-auto pr-1">
          <button
            v-for="w in topWorkspaces"
            :key="w.id"
            type="button"
            class="space-y-1 w-full text-left rounded-md px-1.5 py-1 hover:bg-indigo-50/55 transition-colors"
            @click="goToWorkspaceFeeds(w.id)"
          >
            <div class="flex items-center justify-between text-2xs text-slate-600">
              <span class="truncate">{{ w.name }}</span>
              <span class="font-medium text-slate-700">{{ w.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full bg-gradient-to-r from-cyan-300/80 to-blue-300/80" :style="{ width: `${w.width}%` }" />
            </div>
          </button>
          <div v-if="!topWorkspaces.length" class="text-2xs text-slate-500">No workspace stats yet.</div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import SocialIcon from '../components/SocialIcon.vue';

const workspaces = useWorkspacesStore();
const router = useRouter();

const analyticsLoading = ref(false);
const allFeeds = ref([]);
const feedCountsByWorkspace = ref({});
const totalWorkspaces = computed(() => workspaces.list.length);
const totalFeeds = computed(() => allFeeds.value.length);
const avgFeedsPerWorkspace = computed(() =>
  totalWorkspaces.value ? Number((totalFeeds.value / totalWorkspaces.value).toFixed(1)) : 0,
);
const publishedFeeds = computed(() =>
  workspaces.list.filter((w) => String(w.public_key || '').trim()).length,
);
const syncedFeeds = computed(() => allFeeds.value.filter((f) => f.last_synced_at).length);
const maxFeedCount = computed(() => Math.max(1, ...Object.values(feedCountsByWorkspace.value).map((v) => Number(v || 0))));
const workspaceUtilization = computed(() => Math.min(100, Math.round((totalWorkspaces.value / 12) * 100)));
const publishedCoverage = computed(() =>
  totalWorkspaces.value ? Math.round((publishedFeeds.value / totalWorkspaces.value) * 100) : 0,
);
const syncCoverage = computed(() => totalFeeds.value ? Math.round((syncedFeeds.value / totalFeeds.value) * 100) : 0);
const workspaceBars = computed(() =>
  workspaces.list.map((w) => {
    const count = Number(feedCountsByWorkspace.value[w.id] || 0);
    return {
      id: w.id,
      name: w.name,
      count,
      height: Math.max(14, Math.round((count / maxFeedCount.value) * 100)),
    };
  }),
);
const topWorkspaces = computed(() =>
  workspaces.list
    .map((w) => {
      const count = Number(feedCountsByWorkspace.value[w.id] || 0);
      return {
        id: w.id,
        name: w.name,
        count,
        width: Math.max(10, Math.round((count / maxFeedCount.value) * 100)),
      };
    })
    .sort((a, b) => b.count - a.count)
    .slice(0, 6),
);
const feedTypeCounts = computed(() =>
  allFeeds.value.reduce((acc, f) => {
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

function feedTypeLabel(type) {
  if (type === 'youtube') return 'YouTube';
  if (type === 'facebook') return 'Facebook';
  if (type === 'instagram') return 'Instagram';
  if (type === 'tiktok') return 'TikTok';
  if (type === 'rss') return 'RSS / Atom';
  if (type === 'twitter') return 'X / Twitter';
  return type || '—';
}

function goToWorkspaces() {
  router.push('/workspaces');
}

function goToWorkspaceFeeds(id) {
  if (!id) return;
  router.push(`/workspaces/${id}/feeds`);
}

function goToPublish() {
  router.push('/publish');
}

onMounted(async () => {
  await workspaces.fetchAll();
  await loadGlobalAnalytics();
});

async function loadGlobalAnalytics() {
  analyticsLoading.value = true;
  try {
    const counts = {};
    const all = [];
    const responses = await Promise.all(
      workspaces.list.map(async (w) => {
        try {
          const { data } = await axios.get(`/api/workspaces/${w.id}/feeds`);
          return { id: w.id, feeds: Array.isArray(data) ? data : [] };
        } catch {
          return { id: w.id, feeds: [] };
        }
      }),
    );
    for (const r of responses) {
      counts[r.id] = r.feeds.length;
      all.push(...r.feeds);
    }
    feedCountsByWorkspace.value = counts;
    allFeeds.value = all;
  } finally {
    analyticsLoading.value = false;
  }
}
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
  box-shadow: 0 16px 30px -24px rgba(30, 41, 59, 0.88);
}
.analytics-card::before {
  content: '';
  position: absolute;
  width: 150px;
  height: 150px;
  right: -70px;
  top: -75px;
  border-radius: 9999px;
  background: radial-gradient(circle, rgba(129, 140, 248, 0.18), rgba(129, 140, 248, 0));
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

.dashboard-hero {
  background:
    radial-gradient(880px 260px at -8% -48%, rgba(56, 189, 248, 0.14), transparent 65%),
    radial-gradient(760px 240px at 110% -40%, rgba(99, 102, 241, 0.16), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
}

.type-dot {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.65rem;
  font-weight: 700;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}

.type-dot :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--rss { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }
.type-dot--twitter { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }

@media (prefers-reduced-motion: reduce) {
  .analytics-card:hover {
    transform: none;
    box-shadow: none;
  }
}
</style>
