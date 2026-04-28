<template>
  <WizardPageLayout
    current="feed"
    title="Feeds"
    description="Source channels configured for this workspace."
    :workspaceId="route.params.workspaceId"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span aria-hidden="true">/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <router-link
        :to="`/workspaces/${workspaceId}/feeds/new`"
        class="btn-secondary !w-auto !px-3 !py-2 text-sm-pro inline-flex items-center gap-2"
        title="Add new feed"
      >
        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
        Add Feed
      </router-link>
      <router-link
        :to="nextCurateUrl"
        class="btn-primary !w-auto !px-3 !py-2 inline-flex items-center justify-center"
        :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'"
        :aria-disabled="!feeds.list.length"
        title="Continue to curate posts"
      >
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Continue to curate posts</span>
      </router-link>
    </template>

    <div v-if="feeds.loading" class="surface-card flex items-center gap-2 text-sm-pro text-one-sub px-5 py-4">
      <span class="inline-block w-4 h-4 border-2 border-one-divider border-t-one-primary rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="feeds.error" class="text-sm-pro text-rose-600">{{ feeds.error }}</div>
    <div v-else-if="!feeds.list.length" class="surface-card p-6 text-center text-sm-pro text-one-sub">
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
        <tbody class="divide-y divide-one-divider">
          <tr v-for="f in feeds.list" :key="f.id" class="table-tr">
            <td class="table-td font-semibold text-one-text">
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
            <td class="px-4 py-3 text-2xs text-one-sub truncate max-w-[240px]">
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
                  <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.885L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" /></svg>
                  Edit
                </router-link>
                <button
                  type="button"
                  class="action-link action-link--destructive"
                  :class="canEditOrDelete ? '' : 'opacity-40 cursor-not-allowed'"
                  @click="handleDeleteClick(f)"
                >
                  <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <template #footer>
      <router-link to="/workspaces" class="btn-secondary !w-auto inline-flex items-center justify-center" title="Go back">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 0-.75-.75H5.612l4.158-3.96a.75.75 0 1 0-1.04-1.08l-5.5 5.25a.75.75 0 0 0 0 1.08l5.5 5.25a.75.75 0 1 0 1.04-1.08L5.612 10.75H16.25A.75.75 0 0 0 17 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Go back</span>
      </router-link>
      <router-link
        :to="nextCurateUrl"
        class="btn-primary !w-auto !px-3 !py-2 inline-flex items-center justify-center"
        :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'"
        :aria-disabled="!feeds.list.length"
        title="Continue to curate posts"
      >
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Continue to curate posts</span>
      </router-link>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import WizardPageLayout from '../components/WizardPageLayout.vue';

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
  return `/workspaces/${workspaceId.value}/curate`;
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
  if (type === 'threads') return 'Threads';
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
.type-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  border-radius: 999px;
  background: #F4F4F6;
  padding: 0.2rem 0.55rem 0.2rem 0.3rem;
  color: #6E6E73;
  font-size: 0.8rem;
  font-weight: 600;
}

.type-pill__icon {
  width: 1rem;
  height: 1rem;
  display: grid;
  place-items: center;
  border-radius: 999px;
  background: #E5E5EA;
  color: #1C1C1E;
}

.type-pill__icon :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-pill--youtube .type-pill__icon { background: #FEE2E2; color: #DC2626; }
.type-pill--facebook .type-pill__icon { background: #DBEAFE; color: #2563EB; }
.type-pill--instagram .type-pill__icon { background: #FCE7F3; color: #BE185D; }
.type-pill--tiktok .type-pill__icon { background: #F4F4F6; color: #1C1C1E; }
.type-pill--threads .type-pill__icon { background: #F4F4F6; color: #1C1C1E; }
.type-pill--rss .type-pill__icon { background: #FFEDD5; color: #EA580C; }
.type-pill--twitter .type-pill__icon { background: #F4F4F6; color: #1C1C1E; }

.feed-name-dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  flex-shrink: 0;
  background: #AEAEB2;
}

.feed-name-dot--youtube { background: #DC2626; }
.feed-name-dot--facebook { background: #2563EB; }
.feed-name-dot--instagram { background: #BE185D; }
.feed-name-dot--tiktok { background: #1C1C1E; }
.feed-name-dot--threads { background: #1C1C1E; }
.feed-name-dot--rss { background: #EA580C; }
.feed-name-dot--twitter { background: #1C1C1E; }

.source-chip {
  display: inline-block;
  max-width: 100%;
  padding: 0.18rem 0.5rem;
  border-radius: 999px;
  background: #F4F4F6;
  color: #6E6E73;
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
