<template>
  <div class="space-y-4">
    <nav class="page-breadcrumb">
      <span>Workspaces</span>
    </nav>
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
          </svg>
          Workspaces
        </h1>
        <p class="page-kicker">Manage workspace setup from Feed to Curate to Publish.</p>
      </div>
      <router-link to="/workspaces/new" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro">
        New workspace
      </router-link>
    </div>
    <div v-if="!workspaces.loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">WS</span> Total workspaces
        </div>
        <div class="mt-3 flex items-center gap-3">
          <div class="donut-ring" :style="workspaceRingStyle">
            <div class="donut-inner">{{ totalWorkspaces }}</div>
          </div>
          <div>
            <div class="text-lg-pro font-semibold text-slate-800">{{ totalWorkspaces }}</div>
            <div class="text-2xs text-slate-500">Active containers</div>
            <div class="mt-1 text-2xs" :class="trendClass(workspacesTrend)">
              {{ trendLabel(workspacesTrend, 'vs last visit') }}
            </div>
          </div>
        </div>
      </div>
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">FD</span> Total feeds
        </div>
        <div class="mt-2 text-lg-pro font-semibold text-slate-800">{{ totalFeeds }}</div>
        <div class="mt-1 text-2xs" :class="trendClass(feedsTrend)">
          {{ trendLabel(feedsTrend, 'vs last visit') }}
        </div>
        <div class="mt-3 flex items-end gap-1.5 h-12">
          <div
            v-for="(w, idx) in workspaceBars"
            :key="w.id"
            class="flex-1 rounded-sm bg-gradient-to-t from-indigo-300/70 to-cyan-200/70"
            :style="{ height: `${w.height}%`, opacity: idx % 2 ? 0.82 : 1 }"
            :title="`${w.name}: ${w.count} feeds`"
          />
        </div>
      </div>
      <div class="surface-card p-4 analytics-card">
        <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
          <span class="metric-chip">LD</span> Workspace feed load
        </div>
        <div class="mt-1 text-2xs" :class="trendClass(avgFeedsTrend)">
          {{ trendLabel(avgFeedsTrend, 'avg/workspace') }}
        </div>
        <div class="mt-3 space-y-2 max-h-[92px] overflow-y-auto pr-1">
          <div v-for="w in workspaceDistribution" :key="w.id" class="space-y-1">
            <div class="flex items-center justify-between text-2xs text-slate-600">
              <span class="truncate">{{ w.name }}</span>
              <span class="font-medium text-slate-700">{{ w.count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full bg-gradient-to-r from-violet-300/80 to-indigo-400/80" :style="{ width: `${w.width}%` }" />
            </div>
          </div>
          <div v-if="!workspaceDistribution.length" class="text-2xs text-slate-500">No feed data yet.</div>
        </div>
      </div>
    </div>
    <div v-if="workspaces.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="workspaces.error" class="text-sm-pro text-red-600">{{ workspaces.error }}</div>
    <div v-else-if="!workspaces.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      No workspaces yet. Create one to get started.
    </div>
    <div v-else class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th w-40">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="w in workspaces.list" :key="w.id" class="table-tr">
            <td class="table-td font-medium text-slate-800">{{ w.name }}</td>
            <td class="table-td">
              <div class="flex items-center gap-2">
                <router-link :to="`/workspaces/${w.id}/feeds`" class="action-link">Setup</router-link>
                <router-link :to="`/workspaces/${w.id}/curate`" class="action-link">Curate</router-link>
                <router-link :to="`/workspaces/${w.id}/publish`" class="action-link">Publish</router-link>
                <router-link :to="`/workspaces/${w.id}/edit`" class="action-link">Edit</router-link>
                <button type="button" class="action-link !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80" @click="confirmDelete(w)">Delete</button>
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
import axios from 'axios';
import { useWorkspacesStore } from '../stores/workspaces';

const workspaces = useWorkspacesStore();
const feedCountsByWorkspace = ref({});

const totalWorkspaces = computed(() => workspaces.list.length);
const totalFeeds = computed(() =>
  Object.values(feedCountsByWorkspace.value).reduce((sum, val) => sum + Number(val || 0), 0),
);
const avgFeedsPerWorkspace = computed(() =>
  totalWorkspaces.value ? Number((totalFeeds.value / totalWorkspaces.value).toFixed(1)) : 0,
);
const maxFeeds = computed(() => Math.max(1, ...Object.values(feedCountsByWorkspace.value).map((v) => Number(v || 0))));
const workspaceRingStyle = computed(() => {
  const pct = Math.min(100, Math.round((totalWorkspaces.value / 12) * 100));
  return {
    background: `conic-gradient(rgba(99,102,241,0.75) ${pct}%, rgba(224,231,255,0.9) ${pct}% 100%)`,
  };
});
const workspaceBars = computed(() =>
  workspaces.list.map((w) => {
    const count = Number(feedCountsByWorkspace.value[w.id] || 0);
    return {
      id: w.id,
      name: w.name,
      count,
      height: Math.max(14, Math.round((count / maxFeeds.value) * 100)),
    };
  }),
);
const workspaceDistribution = computed(() =>
  workspaces.list
    .map((w) => {
      const count = Number(feedCountsByWorkspace.value[w.id] || 0);
      return {
        id: w.id,
        name: w.name,
        count,
        width: Math.max(8, Math.round((count / maxFeeds.value) * 100)),
      };
    })
    .sort((a, b) => b.count - a.count),
);
const workspacesTrend = ref(0);
const feedsTrend = ref(0);
const avgFeedsTrend = ref(0);

onMounted(async () => {
  await workspaces.fetchAll();
  await loadFeedCounts();
  loadTrendSnapshot();
});

async function loadFeedCounts() {
  if (!workspaces.list.length) {
    feedCountsByWorkspace.value = {};
    return;
  }
  const counts = await Promise.all(
    workspaces.list.map(async (w) => {
      try {
        const { data } = await axios.get(`/api/workspaces/${w.id}/feeds`);
        return [w.id, Array.isArray(data) ? data.length : 0];
      } catch {
        return [w.id, 0];
      }
    }),
  );
  feedCountsByWorkspace.value = Object.fromEntries(counts);
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

function loadTrendSnapshot() {
  try {
    const raw = localStorage.getItem('workspace_analytics_snapshot');
    const prev = raw ? JSON.parse(raw) : null;
    if (prev && typeof prev === 'object') {
      workspacesTrend.value = totalWorkspaces.value - Number(prev.totalWorkspaces || 0);
      feedsTrend.value = totalFeeds.value - Number(prev.totalFeeds || 0);
      avgFeedsTrend.value = Number((avgFeedsPerWorkspace.value - Number(prev.avgFeedsPerWorkspace || 0)).toFixed(1));
    }
  } catch {
    // Ignore malformed snapshots.
  } finally {
    saveTrendSnapshot();
  }
}

function saveTrendSnapshot() {
  const snapshot = {
    totalWorkspaces: totalWorkspaces.value,
    totalFeeds: totalFeeds.value,
    avgFeedsPerWorkspace: avgFeedsPerWorkspace.value,
  };
  localStorage.setItem('workspace_analytics_snapshot', JSON.stringify(snapshot));
}

async function confirmDelete(w) {
  if (window.confirm(`Delete workspace "${w.name}"?`)) {
    await workspaces.remove(w.id);
    await loadFeedCounts();
    saveTrendSnapshot();
  }
}

watch([totalWorkspaces, totalFeeds, avgFeedsPerWorkspace], () => {
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
</style>
