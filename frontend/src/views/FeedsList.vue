<template>
  <WizardPageLayout
    current="feed"
    title="Feeds"
    description="Source channels configured for this workspace."
    :workspaceId="route.params.workspaceId"
    :breadcrumb="['Workspaces', workspaceName || 'Workspace', 'Feeds']"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span aria-hidden="true">/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <AppButton :to="`/workspaces/${workspaceId}/feeds/new`" variant="secondary" size="sm" title="Add new feed">
        <AppIcon name="add" class="w-3.5 h-3.5 shrink-0" />
        Add Feed
      </AppButton>
      <AppButton :to="nextCurateUrl" size="sm" :disabled="!feeds.list.length" title="Continue to curate posts">
        <AppIcon name="forward" class="w-4 h-4" />
        Curate
      </AppButton>
    </template>

    <div v-if="feeds.loading" class="surface-card-soft px-4 py-3">
      <AppLoader size="sm" label="Loading..." />
    </div>
    <div v-else-if="feeds.error" class="text-sm-pro text-red-600">{{ feeds.error }}</div>
    <AppCard v-else-if="!feeds.list.length" class="p-6">
      <AppEmptyState
        title="No feeds yet"
        description="Create the first feed to start workspace setup."
        icon="feeds"
      >
        <AppButton :to="`/workspaces/${workspaceId}/feeds/new`" size="sm">
          <AppIcon name="add" class="w-3.5 h-3.5 shrink-0" />
          Create feed
        </AppButton>
      </AppEmptyState>
    </AppCard>
    <div v-if="!feeds.loading && feeds.list.length" class="feed-table-shell">
      <AppTable :columns="tableColumns" :rows="feeds.list" row-key="id">
        <template #cell-name="{ row }">
          <div class="flex items-center gap-2 min-w-0">
            <span class="feed-name-dot" :class="`feed-name-dot--${String(row.type || 'other')}`" />
            <span class="truncate font-medium text-slate-800">{{ row.name }}</span>
          </div>
        </template>
        <template #cell-type="{ row }">
          <SocialPlatformLabel :type="row.type" variant="pill" size="sm" />
        </template>
        <template #cell-source_url="{ row }">
          <span v-if="row.source_url" class="source-chip">{{ row.source_url }}</span>
          <span v-else>—</span>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex items-center gap-2">
            <AppButton variant="ghost" size="sm" class="gap-1.5" @click="handleEditClick(row)">
              <AppIcon name="edit" class="w-3.5 h-3.5 shrink-0" />
              Edit
            </AppButton>
            <AppButton variant="ghost" tone="destructive" size="sm" class="gap-1.5" @click="handleDeleteClick(row)">
              <AppIcon name="delete" class="w-3.5 h-3.5 shrink-0" />
              Delete
            </AppButton>
          </div>
        </template>
      </AppTable>
    </div>

    <template #footer>
      <AppButton to="/workspaces" variant="secondary" size="sm" title="Go back">
        <AppIcon name="back" class="w-4 h-4" />
        Back
      </AppButton>
      <AppButton :to="nextCurateUrl" size="sm" :disabled="!feeds.list.length" title="Continue to curate posts">
        <AppIcon name="forward" class="w-4 h-4" />
        Curate
      </AppButton>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { getPlatformLabel } from '../constants/socialPlatforms';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import { AppButton, AppCard, AppEmptyState, AppIcon, AppLoader, AppTable } from '../components/ui';

const route = useRoute();
const feeds = useFeedsStore();
const { confirm } = inject('confirm');
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

function feedTypeLabel(type) {
  return getPlatformLabel(type) || '—';
}

function handleEditClick(f) {
  // Let backend enforce the accepted-post rule; just navigate.
  // If the backend rejects, FeedForm will show the error toast + message.
  window.location.href = `/workspaces/${workspaceId.value}/feeds/${f.id}/edit`;
}

async function handleDeleteClick(f) {
  if (!await confirm({ title: 'Delete feed?', message: `Delete feed "${f.name}"?`, confirmLabel: 'Delete' })) return;

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
  background: linear-gradient(150deg, #ffffff 0%, #f8fafc 100%);
  border-color: rgba(30, 58, 138, 0.12);
}
.feed-setup-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(30, 58, 138, 0.05), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(30, 58, 138, 0.04), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
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
  background: radial-gradient(circle, rgba(30, 58, 138, 0.08), rgba(30, 58, 138, 0));
  pointer-events: none;
}
.analytics-card::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, rgba(30, 58, 138, 0.7), rgba(37, 99, 235, 0.45));
}
.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  border: 1px solid rgba(30, 58, 138, 0.2);
  background: rgba(239, 246, 255, 0.9);
  color: #1e3a8a;
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
  border-color: #e6ebf2;
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
  box-shadow: inset 3px 0 0 rgba(30, 58, 138, 0.3);
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
