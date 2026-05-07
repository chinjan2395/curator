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
      <router-link :to="`/workspaces/${workspaceId}/feeds/new`" title="Add new feed">
        <AppButton variant="secondary" size="sm" class="!w-auto !px-3 !py-1.5 text-sm-pro inline-flex items-center gap-2">
          <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
          Add Feed
        </AppButton>
      </router-link>
      <router-link :to="nextCurateUrl" :aria-disabled="!feeds.list.length" title="Continue to curate posts">
        <AppButton
          size="sm"
          class="!w-auto !px-3 !py-1.5 inline-flex items-center gap-2"
          :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'"
        >
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
          Curate
        </AppButton>
      </router-link>
    </template>

    <div v-if="feeds.loading" class="surface-card-soft px-4 py-3">
      <AppLoader size="sm" label="Loading..." />
    </div>
    <div v-else-if="feeds.error" class="text-sm-pro text-red-600">{{ feeds.error }}</div>
    <AppEmptyState
      v-else-if="!feeds.list.length"
      title="No feeds yet"
      description="Create the first feed to start workspace setup."
      icon="🧩"
      class="surface-card"
    >
      <router-link :to="`/workspaces/${workspaceId}/feeds/new`">
        <AppButton class="!w-auto !py-1.5 !px-3 text-sm-pro">Create feed</AppButton>
      </router-link>
    </AppEmptyState>
    <div v-if="!feeds.loading && feeds.list.length" class="feed-table-shell">
      <AppTable :columns="tableColumns" :rows="feeds.list" row-key="id">
        <template #cell-name="{ row }">
          <div class="flex items-center gap-2 min-w-0">
            <span class="feed-name-dot" :class="`feed-name-dot--${String(row.type || 'other')}`" />
            <span class="truncate font-medium text-slate-800">{{ row.name }}</span>
          </div>
        </template>
        <template #cell-type="{ row }">
          <span class="type-pill" :class="`type-pill--${String(row.type || 'other')}`">
            <span class="type-pill__icon">
              <SocialIcon :type="row.type" />
            </span>
            {{ feedTypeLabel(row.type) }}
          </span>
        </template>
        <template #cell-source_url="{ row }">
          <span v-if="row.source_url" class="source-chip">{{ row.source_url }}</span>
          <span v-else>—</span>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex items-center gap-2">
            <router-link :to="`/workspaces/${workspaceId}/feeds/${row.id}/edit`" @click.prevent="handleEditClick(row)">
              <AppButton
                variant="ghost"
                size="sm"
                class="!w-auto inline-flex items-center gap-1.5 !px-3 !py-1.5 text-sm-pro border border-slate-200 bg-white hover:bg-slate-50"
                :class="canEditOrDelete ? '' : 'text-slate-400 cursor-not-allowed'"
              >
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.885L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" /></svg>
                Edit
              </AppButton>
            </router-link>
            <AppButton
              variant="danger"
              size="sm"
              class="!w-auto inline-flex items-center gap-1.5 !py-1.5 !px-3 text-sm-pro"
              @click="handleDeleteClick(row)"
            >
              <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
              Delete
            </AppButton>
          </div>
        </template>
      </AppTable>
    </div>

    <template #footer>
      <router-link to="/workspaces" title="Go back">
        <AppButton variant="secondary" size="sm" class="!w-auto inline-flex items-center justify-center !py-1.5 !px-3 text-sm-pro">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 0-.75-.75H5.612l4.158-3.96a.75.75 0 1 0-1.04-1.08l-5.5 5.25a.75.75 0 0 0 0 1.08l5.5 5.25a.75.75 0 1 0 1.04-1.08L5.612 10.75H16.25A.75.75 0 0 0 17 10Z" clip-rule="evenodd" /></svg>
          <span class="ml-1">Back</span>
        </AppButton>
      </router-link>
      <router-link :to="nextCurateUrl" :aria-disabled="!feeds.list.length" title="Continue to curate posts">
        <AppButton size="sm" class="!w-auto !px-3 !py-1.5 inline-flex items-center gap-2 text-sm-pro" :class="feeds.list.length ? '' : 'pointer-events-none opacity-50'">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
          Curate
        </AppButton>
      </router-link>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import SocialIcon from '../components/SocialIcon.vue';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import { AppButton, AppEmptyState, AppLoader, AppTable } from '../components/ui';

const route = useRoute();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();

const workspaceId = computed(() => route.params.workspaceId);
const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});
const totalFeeds = computed(() => feeds.list.length);
const feedsWithSource = computed(() => feeds.list.filter((f) => String(f.source_url || '').trim().length > 0).length);
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
const typeVariety = computed(() => Object.keys(feedTypeCounts.value).length);
const feedsTrend = ref(0);
const coverageTrend = ref(0);
const typeVarietyTrend = ref(0);
const tableColumns = [
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'source_url', label: 'Source' },
  { key: 'actions', label: 'Actions', class: 'w-32' },
];
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
  font-size: 12px;
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
  font-size: 0.8rem;
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

.type-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  padding: 0.15rem 0.5rem 0.15rem 0.38rem;
  color: rgb(71 85 105);
  font-size: 0.8rem;
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
.type-pill--threads .type-pill__icon { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
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
.feed-name-dot--threads { background: rgb(15 23 42); box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.14); }
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
