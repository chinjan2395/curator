<template>
  <div class="analytics-page space-y-5">
    <AppPageHeader title="Analytics" subtitle="Performance across connected platforms." icon="chart" />

    <AppLoader v-if="loading" label="Loading analytics…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else-if="overview">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <AppCard
          v-for="metric in metricCards"
          :key="metric.id"
          variant="metric"
          class="p-5 analytics-metric-card"
        >
          <template #icon>
            <div class="analytics-metric-icon" :class="metric.iconClass">
              <AppIcon :name="metric.icon" class="analytics-metric-icon__glyph" />
            </div>
          </template>
          <template #header>
            <div class="app-card-kicker">{{ metric.kicker }}</div>
            <div class="app-card-title">{{ metric.label }}</div>
          </template>
          <template #metric>
            <div class="app-card-value">{{ formatMetric(metric.value, metric.format) }}</div>
            <div v-if="metric.hint" class="app-card-subtitle">{{ metric.hint }}</div>
          </template>
        </AppCard>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <AppCard class="p-5 lg:col-span-2">
          <div class="analytics-section-head">
            <div class="analytics-section-icon analytics-section-icon--blue">
              <AppIcon name="chart" class="w-4 h-4" />
            </div>
            <div>
              <AppTitle size="sm">Engagement by platform</AppTitle>
              <p class="analytics-section-copy">Likes across connected channels · last 30 days</p>
            </div>
          </div>

          <div v-if="chartBars.length" class="mt-5 space-y-4">
            <div v-for="bar in chartBars" :key="bar.platform" class="analytics-platform-row">
              <router-link
                :to="`/analytics/platforms/${bar.platform}`"
                class="analytics-platform-link"
              >
                <SocialPlatformLabel :type="bar.platform" size="sm" />
                <AppIcon name="chevron-right" class="analytics-platform-link__arrow" />
              </router-link>
              <div class="analytics-platform-track">
                <div
                  class="analytics-platform-fill"
                  :style="{
                    width: `${bar.pct}%`,
                    background: `linear-gradient(90deg, ${bar.color}dd, ${bar.color})`,
                  }"
                />
              </div>
              <span class="analytics-platform-value">{{ formatMetric(bar.likes) }}</span>
            </div>
          </div>

          <div v-else class="analytics-empty mt-5">
            <div class="analytics-empty__icon">
              <AppIcon name="chart" class="w-5 h-5" />
            </div>
            <p class="analytics-empty__title">No engagement data yet</p>
            <p class="analytics-empty__copy">Connect feeds and sync posts to see platform breakdowns.</p>
          </div>
        </AppCard>

        <AppCard class="p-5">
          <div class="analytics-section-head">
            <div class="analytics-section-icon analytics-section-icon--violet">
              <AppIcon name="target" class="w-4 h-4" />
            </div>
            <div>
              <AppTitle size="sm">Embed clicks</AppTitle>
              <p class="analytics-section-copy">Widget opens by platform · last 30 days</p>
            </div>
          </div>

          <template v-if="hasEmbedClickData">
            <div v-if="clickChartBars.length" class="mt-4 space-y-3">
              <div v-for="bar in clickChartBars" :key="bar.platform" class="analytics-platform-row">
                <router-link
                  :to="`/analytics/platforms/${bar.platform}`"
                  class="analytics-platform-link"
                >
                  <SocialPlatformLabel :type="bar.platform" size="sm" />
                  <AppIcon name="chevron-right" class="analytics-platform-link__arrow" />
                </router-link>
                <div class="analytics-platform-track">
                  <div
                    class="analytics-platform-fill"
                    :style="{
                      width: `${bar.pct}%`,
                      background: `linear-gradient(90deg, ${bar.color}dd, ${bar.color})`,
                    }"
                  />
                </div>
                <span class="analytics-platform-value">{{ formatMetric(bar.clicks) }}</span>
              </div>
            </div>

            <div
              v-if="overview?.top_embed_clicked_posts?.length"
              class="analytics-top-posts-block"
              :class="{ 'analytics-top-posts-block--with-chart': clickChartBars.length }"
            >
              <p class="analytics-top-posts-label">Top clicked posts</p>
              <div class="space-y-2">
                <div
                  v-for="(post, index) in overview.top_embed_clicked_posts"
                  :key="post.id"
                  class="analytics-top-post"
                >
                  <span class="analytics-top-post__rank">{{ index + 1 }}</span>
                  <SocialPlatformLabel
                    v-if="post.platform"
                    :type="post.platform"
                    variant="badge"
                    size="sm"
                    :show-label="false"
                    class="analytics-top-post__platform"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="analytics-top-post__title">{{ post.title || 'Untitled post' }}</p>
                    <p class="analytics-top-post__meta">
                      <span v-if="post.platform">{{ getPlatformMeta(post.platform).label }} · </span>
                      {{ post.clicks }} clicks
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div v-else class="analytics-empty mt-4">
            <div class="analytics-empty__icon">
              <AppIcon name="target" class="w-5 h-5" />
            </div>
            <p class="analytics-empty__title">No click data yet</p>
            <p class="analytics-empty__copy">Embed traffic will appear once visitors interact with your feed.</p>
          </div>
        </AppCard>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <AppCard v-if="overview?.campaigns?.length" class="p-5">
          <div class="analytics-section-head">
            <div class="analytics-section-icon analytics-section-icon--amber">
              <AppIcon name="megaphone" class="w-4 h-4" />
            </div>
            <div>
              <AppTitle size="sm">Campaign performance</AppTitle>
              <p class="analytics-section-copy">Approved packages per active campaign</p>
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <div v-for="campaign in overview.campaigns" :key="campaign.id" class="analytics-campaign">
              <div class="analytics-campaign__head">
                <span class="analytics-campaign__name">{{ campaign.name }}</span>
                <span class="analytics-campaign__ratio">
                  {{ campaign.approved }}/{{ campaign.packages }}
                </span>
              </div>
              <div class="analytics-campaign__track">
                <div
                  class="analytics-campaign__fill"
                  :style="{ width: `${campaignProgress(campaign)}%` }"
                />
              </div>
            </div>
          </div>
        </AppCard>

        <AppCard class="p-5" :class="{ 'lg:col-span-2': !overview?.campaigns?.length }">
          <div class="analytics-section-head">
            <div class="analytics-section-icon analytics-section-icon--emerald">
              <AppIcon name="sparkles" class="w-4 h-4" />
            </div>
            <div class="flex-1">
              <AppTitle size="sm">AI insights</AppTitle>
              <p class="analytics-section-copy">Quick takeaways from your content activity</p>
            </div>
            <AppButton
              size="sm"
              variant="secondary"
              :loading="insightsLoading"
              :disabled="insightsLoading"
              @click="requestInsights"
            >
              {{ insightsLoading ? 'Generating…' : 'Refresh insights' }}
            </AppButton>
          </div>

          <AppLoader v-if="insightsLoading && !insights.length" class="mt-4" size="sm" label="Generating AI insights…" />

          <ul v-else-if="insights.length" class="mt-4 space-y-2">
            <li v-for="(line, index) in insights" :key="index" class="analytics-insight">
              <span class="analytics-insight__bullet">
                <AppIcon name="sparkles" class="w-3.5 h-3.5" />
              </span>
              <span>{{ line }}</span>
            </li>
          </ul>

          <div v-else class="analytics-empty mt-4">
            <div class="analytics-empty__icon">
              <AppIcon name="sparkles" class="w-5 h-5" />
            </div>
            <p class="analytics-empty__title">Insights on the way</p>
            <p class="analytics-empty__copy">Click refresh to generate AI insights from your analytics data.</p>
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppButton, AppCard, AppIcon, AppLoader, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { getPlatformMeta } from '../constants/socialPlatforms';
import { useRealtimeStore } from '../stores/realtime';
import { useToastStore } from '../stores/toast';

const overview = ref(null);
const insights = ref([]);
const loading = ref(true);
const insightsLoading = ref(false);
const error = ref(null);
const realtime = useRealtimeStore();
const toast = useToastStore();
let unsubscribeInsights = null;

const metricCards = computed(() => [
  {
    id: 'likes',
    kicker: 'Engagement',
    label: 'Likes',
    value: overview.value?.total_likes ?? 0,
    icon: 'heart',
    iconClass: 'analytics-metric-icon--rose',
    format: 'number',
    hint: 'Across synced posts',
  },
  {
    id: 'views',
    kicker: 'Reach',
    label: 'Views',
    value: overview.value?.total_views ?? 0,
    icon: 'view',
    iconClass: 'analytics-metric-icon--blue',
    format: 'number',
    hint: 'Total impressions',
  },
  {
    id: 'clicks',
    kicker: 'Embed',
    label: 'Clicks',
    value: overview.value?.total_embed_clicks ?? 0,
    icon: 'link',
    iconClass: 'analytics-metric-icon--violet',
    format: 'number',
    hint: 'Widget interactions',
  },
  {
    id: 'engagement',
    kicker: 'Rate',
    label: 'Engagement',
    value: overview.value?.engagement_rate ?? 0,
    icon: 'trending',
    iconClass: 'analytics-metric-icon--emerald',
    format: 'percent',
    hint: 'Likes vs views',
  },
  {
    id: 'followers',
    kicker: 'Audience',
    label: 'Followers',
    value: overview.value?.follower_total ?? 0,
    icon: 'users',
    iconClass: 'analytics-metric-icon--amber',
    format: 'number',
    hint: 'Connected accounts',
  },
]);

function buildPlatformBars(items, valueKey) {
  if (!items.length) return [];
  const max = Math.max(1, ...items.map((item) => Number(item[valueKey] || 0)));
  return items
    .map((item) => ({
      ...item,
      color: getPlatformMeta(item.platform).color,
      pct: Math.round((Number(item[valueKey] || 0) / max) * 100),
    }))
    .sort((a, b) => Number(b[valueKey] || 0) - Number(a[valueKey] || 0));
}

const chartBars = computed(() => {
  const series = overview.value?.time_series || {};
  const totals = Object.entries(series).map(([platform, rows]) => ({
    platform,
    likes: (rows || []).reduce((sum, row) => sum + Number(row.likes || 0), 0),
  }));
  return buildPlatformBars(totals, 'likes');
});

const clickChartBars = computed(() =>
  buildPlatformBars(overview.value?.embed_clicks_by_platform || [], 'clicks'),
);

const hasEmbedClickData = computed(
  () =>
    clickChartBars.value.length > 0 ||
    (overview.value?.top_embed_clicked_posts?.length ?? 0) > 0,
);

function formatMetric(value, format = 'number') {
  const numeric = Number(value || 0);
  if (format === 'percent') {
    return `${Number.isFinite(numeric) ? numeric : 0}%`;
  }
  if (numeric >= 1_000_000) return `${(numeric / 1_000_000).toFixed(1)}M`;
  if (numeric >= 1_000) return `${(numeric / 1_000).toFixed(1)}K`;
  return new Intl.NumberFormat().format(numeric);
}

function campaignProgress(campaign) {
  const total = Number(campaign.packages || 0);
  const approved = Number(campaign.approved || 0);
  if (!total) return 0;
  return Math.min(100, Math.round((approved / total) * 100));
}

function handleInsightsEvent(event) {
  if (event.status === 'started') {
    insightsLoading.value = true;
    return;
  }
  insightsLoading.value = false;
  if (event.status === 'completed' && Array.isArray(event.insights)) {
    insights.value = event.insights;
    toast.success(event.message || 'AI insights ready');
  } else if (event.status === 'failed') {
    toast.error(event.message || 'Failed to generate insights');
  }
}

async function requestInsights() {
  insightsLoading.value = true;
  try {
    await axios.get('/api/analytics/insights', { skipErrorToast: true });
  } catch (e) {
    insightsLoading.value = false;
    toast.error(e.response?.data?.message || 'Failed to start insights generation');
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = null;
  unsubscribeInsights = realtime.on('analyticsInsights', handleInsightsEvent);
  try {
    const overviewResponse = await axios.get('/api/analytics/overview', { skipErrorToast: true });
    overview.value = overviewResponse.data.data || overviewResponse.data;
    await requestInsights();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load analytics';
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (unsubscribeInsights) unsubscribeInsights();
});
</script>

<style scoped>
.analytics-metric-card :deep(.app-card-icon) {
  width: auto;
  height: auto;
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: 0;
}

.analytics-metric-card :deep(.app-card-header-main) {
  gap: 0.85rem;
}

.analytics-metric-card :deep(.app-card-kicker) {
  @apply text-[10px] font-semibold tracking-[0.16em] text-slate-400;
}

.analytics-metric-card :deep(.app-card-title) {
  @apply text-[14px] font-semibold text-slate-700;
}

.analytics-metric-card :deep(.app-card-value) {
  @apply text-[2rem] leading-none font-semibold text-slate-800 mt-2;
}

.analytics-metric-card :deep(.app-card-subtitle) {
  @apply text-[11px] text-slate-400 mt-1;
}

.analytics-metric-card {
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.analytics-metric-card:hover {
  transform: translateY(-2px);
}

.analytics-metric-icon {
  @apply flex h-11 w-11 items-center justify-center rounded-2xl border;
}

.analytics-metric-icon__glyph {
  @apply h-5 w-5;
}

.analytics-metric-icon--rose {
  @apply border-rose-200 bg-rose-50 text-rose-600;
}

.analytics-metric-icon--blue {
  @apply border-blue-200 bg-blue-50 text-blue-600;
}

.analytics-metric-icon--violet {
  @apply border-violet-200 bg-violet-50 text-violet-600;
}

.analytics-metric-icon--emerald {
  @apply border-emerald-200 bg-emerald-50 text-emerald-600;
}

.analytics-metric-icon--amber {
  @apply border-amber-200 bg-amber-50 text-amber-600;
}

.analytics-section-head {
  @apply flex items-start gap-3;
}

.analytics-section-icon {
  @apply mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border;
}

.analytics-section-icon--blue {
  @apply border-blue-200 bg-blue-50 text-blue-600;
}

.analytics-section-icon--violet {
  @apply border-violet-200 bg-violet-50 text-violet-600;
}

.analytics-section-icon--amber {
  @apply border-amber-200 bg-amber-50 text-amber-600;
}

.analytics-section-icon--emerald {
  @apply border-emerald-200 bg-emerald-50 text-emerald-600;
}

.analytics-section-copy {
  @apply mt-0.5 text-xs text-slate-500;
}

.analytics-platform-row {
  @apply grid items-center gap-3;
  grid-template-columns: minmax(7.5rem, 9rem) minmax(0, 1fr) 3.5rem;
}

.analytics-platform-link {
  @apply inline-flex min-w-0 items-center gap-1 rounded-lg transition-colors hover:bg-slate-50;
}

.analytics-platform-link__arrow {
  @apply h-3.5 w-3.5 text-slate-300 transition-colors;
}

.analytics-platform-link:hover .analytics-platform-link__arrow {
  @apply text-slate-500;
}

.analytics-platform-track {
  @apply h-3 overflow-hidden rounded-full bg-slate-100;
}

.analytics-platform-fill {
  @apply h-full rounded-full transition-all duration-500;
  min-width: 0.35rem;
}

.analytics-platform-value {
  @apply text-right text-sm font-semibold tabular-nums text-slate-600;
}

.analytics-top-post {
  @apply flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5;
}

.analytics-top-post__rank {
  @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-600 shadow-sm ring-1 ring-slate-200;
}

.analytics-top-post__title {
  @apply truncate text-sm font-medium text-slate-800;
}

.analytics-top-post__meta {
  @apply text-xs text-slate-500;
}

.analytics-top-posts-block--with-chart {
  @apply mt-5 border-t border-slate-100 pt-4;
}

.analytics-top-posts-label {
  @apply mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400;
}

.analytics-top-post__platform {
  @apply shrink-0;
}

.analytics-campaign__head {
  @apply mb-1.5 flex items-center justify-between gap-3 text-sm;
}

.analytics-campaign__name {
  @apply truncate font-medium text-slate-800;
}

.analytics-campaign__ratio {
  @apply shrink-0 text-xs font-semibold tabular-nums text-slate-500;
}

.analytics-campaign__track {
  @apply h-2 overflow-hidden rounded-full bg-slate-100;
}

.analytics-campaign__fill {
  @apply h-full rounded-full bg-gradient-to-r from-amber-300 to-amber-500 transition-all duration-500;
  min-width: 0.25rem;
}

.analytics-insight {
  @apply flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2.5 text-sm text-slate-700;
}

.analytics-insight__bullet {
  @apply mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-emerald-500 ring-1 ring-emerald-100;
}

.analytics-empty {
  @apply rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-center;
}

.analytics-empty__icon {
  @apply mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-400 ring-1 ring-slate-200;
}

.analytics-empty__title {
  @apply text-sm font-semibold text-slate-700;
}

.analytics-empty__copy {
  @apply mt-1 text-xs text-slate-500;
}

@media (max-width: 767px) {
  .analytics-platform-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .analytics-platform-value {
    @apply text-left;
  }
}
</style>
