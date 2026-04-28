<template>
  <div class="space-y-4">
    <div class="dashboard-hero surface-card p-6 flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-one-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 3.75A.75.75 0 0 1 3.75 3h12.5a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75V3.75Zm3 2.5a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0v-7.5Zm4.75 2a.75.75 0 0 0-1.5 0v5.5a.75.75 0 0 0 1.5 0v-5.5Zm4.75-1.5a.75.75 0 0 0-1.5 0v7a.75.75 0 0 0 1.5 0v-7Z" />
          </svg>
          Dashboard analytics
        </h1>
        <p class="page-kicker mt-1">Workspace and feed performance overview.</p>
      </div>
      <div class="inline-flex items-center gap-1.5 rounded-full bg-one-tint px-3 py-1 text-2xs font-semibold text-one-primary">
        <span class="inline-block h-1.5 w-1.5 rounded-full bg-one-accent" />
        Global snapshot
      </div>
    </div>

    <div v-if="analyticsLoading" class="surface-card flex items-center gap-2 text-sm-pro text-one-sub px-5 py-4">
      <span class="inline-block w-4 h-4 border-2 border-one-divider border-t-one-primary rounded-full animate-spin" />
      Loading analytics…
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      <button type="button" class="surface-card p-5 analytics-card text-left transition-colors" @click="goToWorkspaces">
        <div class="text-2xs font-semibold uppercase tracking-wider text-one-sub flex items-center gap-1.5">
          <span class="metric-chip">WS</span> Total workspaces
        </div>
        <div class="mt-3 text-xl-pro text-one-text">{{ totalWorkspaces }}</div>
        <div class="mt-3 h-1.5 rounded-full bg-one-bg overflow-hidden">
          <div class="h-full rounded-full bg-one-primary" :style="{ width: `${workspaceUtilization}%` }" />
        </div>
        <div class="mt-1.5 text-2xs text-one-sub">{{ workspaceUtilization }}% utilization target</div>
      </button>
      <button type="button" class="surface-card p-5 analytics-card text-left transition-colors" @click="goToWorkspaces">
        <div class="text-2xs font-semibold uppercase tracking-wider text-one-sub flex items-center gap-1.5">
          <span class="metric-chip">FD</span> Total feeds
        </div>
        <div class="mt-3 text-xl-pro text-one-text">{{ totalFeeds }}</div>
        <div class="mt-1.5 text-2xs text-one-sub">Avg/workspace: <span class="text-one-text font-semibold">{{ avgFeedsPerWorkspace }}</span></div>
        <div class="mt-3 flex items-end gap-1 h-10">
          <button
            v-for="w in workspaceBars"
            :key="w.id"
            type="button"
            class="flex-1 rounded-sm bg-one-accent/60"
            :style="{ height: `${w.height}%` }"
            :title="`${w.name}: ${w.count} feeds`"
            @click.stop="goToWorkspaceFeeds(w.id)"
          />
        </div>
      </button>
      <button type="button" class="surface-card p-5 analytics-card text-left transition-colors" @click="goToPublish">
        <div class="text-2xs font-semibold uppercase tracking-wider text-one-sub flex items-center gap-1.5">
          <span class="metric-chip">PB</span> Publish coverage
        </div>
        <div class="mt-3 text-xl-pro text-one-text">{{ publishedFeeds }}</div>
        <div class="text-2xs text-one-sub">Workspaces with public key</div>
        <div class="mt-3 h-1.5 rounded-full bg-one-bg overflow-hidden">
          <div class="h-full rounded-full bg-one-accent" :style="{ width: `${publishedCoverage}%` }" />
        </div>
        <div class="mt-1.5 text-2xs text-one-sub">{{ publishedCoverage }}% of all workspaces</div>
      </button>
      <button type="button" class="surface-card p-5 analytics-card text-left transition-colors" @click="goToWorkspaces">
        <div class="text-2xs font-semibold uppercase tracking-wider text-one-sub flex items-center gap-1.5">
          <span class="metric-chip">SY</span> Sync health
        </div>
        <div class="mt-3 text-xl-pro text-one-text">{{ syncedFeeds }}</div>
        <div class="text-2xs text-one-sub">Feeds synced at least once</div>
        <div class="mt-3 h-1.5 rounded-full bg-one-bg overflow-hidden">
          <div class="h-full rounded-full bg-one-primary" :style="{ width: `${syncCoverage}%` }" />
        </div>
        <div class="mt-1.5 text-2xs text-one-sub">{{ syncCoverage }}% sync coverage</div>
      </button>
    </div>

    <div v-if="!analyticsLoading" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="surface-card p-5 analytics-card">
        <div class="text-sm-pro font-semibold text-one-text flex items-center gap-1.5">
          <span class="metric-chip">MX</span> Feed type distribution
        </div>
        <div class="mt-4 space-y-3 max-h-[160px] overflow-y-auto pr-1">
          <div v-for="t in feedTypeDistribution" :key="t.type" class="space-y-1.5">
            <div class="flex items-center justify-between text-2xs text-one-sub">
              <span class="inline-flex items-center gap-1.5">
                <span class="type-dot" :class="`type-dot--${String(t.type || 'other')}`">
                  <SocialIcon :type="t.type" />
                </span>
                {{ feedTypeLabel(t.type) }}
              </span>
              <span class="font-semibold text-one-text">{{ t.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-one-bg overflow-hidden">
              <div class="h-full rounded-full bg-one-primary/70" :style="{ width: `${t.width}%` }" />
            </div>
          </div>
          <div v-if="!feedTypeDistribution.length" class="text-2xs text-one-muted">No feed data yet.</div>
        </div>
      </div>
      <div class="surface-card p-5 analytics-card">
        <div class="text-sm-pro font-semibold text-one-text flex items-center gap-1.5">
          <span class="metric-chip">TOP</span> Top workspaces by feeds
        </div>
        <div class="mt-4 space-y-3 max-h-[160px] overflow-y-auto pr-1">
          <button
            v-for="w in topWorkspaces"
            :key="w.id"
            type="button"
            class="space-y-1.5 w-full text-left rounded-xs-card px-2 py-1.5 hover:bg-one-bg transition-colors"
            @click="goToWorkspaceFeeds(w.id)"
          >
            <div class="flex items-center justify-between text-2xs text-one-sub">
              <span class="truncate">{{ w.name }}</span>
              <span class="font-semibold text-one-text">{{ w.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-one-bg overflow-hidden">
              <div class="h-full rounded-full bg-one-accent/70" :style="{ width: `${w.width}%` }" />
            </div>
          </button>
          <div v-if="!topWorkspaces.length" class="text-2xs text-one-muted">No workspace stats yet.</div>
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
  if (type === 'threads') return 'Threads';
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
  const first = workspaces.list[0];
  if (first) router.push(`/workspaces/${first.id}/publish`);
  else router.push('/workspaces');
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
}
.analytics-card::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 3px;
  background: #1259C3;
  border-radius: 24px 24px 0 0;
}
.analytics-card:nth-child(2)::after { background: #25C5DA; }
.analytics-card:nth-child(3)::after { background: #1259C3; }
.analytics-card:nth-child(4)::after { background: #25C5DA; }

.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 20px;
  padding: 0 7px;
  border-radius: 999px;
  background: #EBF1FB;
  color: #1259C3;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.dashboard-hero {
  background: #ffffff;
}

.type-dot {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.65rem;
  font-weight: 700;
  background: #F4F4F6;
  color: #6E6E73;
}

.type-dot :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-dot--youtube { background: #FEE2E2; color: #DC2626; }
.type-dot--facebook { background: #DBEAFE; color: #2563EB; }
.type-dot--instagram { background: #FCE7F3; color: #BE185D; }
.type-dot--tiktok { background: #F4F4F6; color: #1C1C1E; }
.type-dot--threads { background: #F4F4F6; color: #1C1C1E; }
.type-dot--rss { background: #FFEDD5; color: #EA580C; }
.type-dot--twitter { background: #F4F4F6; color: #1C1C1E; }
</style>
