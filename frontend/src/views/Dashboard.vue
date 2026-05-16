<template>
  <AppSection spacing="md">
    <AppPageHeader
      title="Dashboard"
      subtitle="Operational health, growth, and source analytics across your curation workspace."
      icon="dashboard"
      :breadcrumb="['Curator', 'Dashboard']"
    />

    <AppLoader v-if="analyticsLoading" label="Loading analytics…" />

    <template v-else>
      <AppCard variant="panel" class="p-5 lg:p-6 dashboard-hero-card mb-5 overflow-hidden">
        <div class="dashboard-hero-grid">
          <div>
            <p class="dashboard-eyebrow">Operational snapshot</p>
            <h2 class="dashboard-hero-title">A clearer picture of what is growing, healthy, and at risk.</h2>
            <p class="dashboard-hero-copy">
              The dashboard now surfaces live workspace coverage, source diversity, sync freshness, and follow-up items from your existing data.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
              <div
                v-for="stat in headlineStats"
                :key="stat.label"
                class="dashboard-hero-stat"
              >
                <div class="dashboard-hero-stat-label">{{ stat.label }}</div>
                <div class="dashboard-hero-stat-value">{{ stat.value }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-hero-score">
            <div class="dashboard-hero-score-shell">
              <DonutChart :percent="overallHealthScore" accent="emerald" />
              <div class="dashboard-hero-score-caption">
                <div class="dashboard-hero-score-label">Overall health</div>
                <div class="dashboard-hero-score-meta">
                  {{ publishedCoverage }}% published · {{ healthySyncRate }}% synced this week
                </div>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
        <AppCard variant="metric" class="p-5 dashboard-metric-card">
          <template #icon>
            <AppIcon name="workspaces" class="dashboard-card-icon" />
          </template>
          <template #header>
            <div class="app-card-kicker">Coverage</div>
            <div class="app-card-title">Workspaces</div>
          </template>
          <template #metric>
            <div class="app-card-value">{{ totalWorkspaces }}</div>
            <div class="app-card-subtitle">{{ publishedWorkspaces }} published · {{ recentWorkspaceAdds }} added in 30d</div>
          </template>
          <template #trend>
            <span class="app-card-chip app-card-chip--up dashboard-metric-trend">
              {{ publishedCoverage }}% live
            </span>
          </template>

          <div class="dashboard-metric-bottom">
            <div class="dashboard-metric-visual">
              <DonutChart :percent="publishedCoverage" />
            </div>
            <div class="dashboard-progress">
              <div class="dashboard-progress-bar" :style="{ width: `${publishedCoverage}%` }" />
            </div>
            <div class="dashboard-metric-foot">Publishing coverage across workspaces</div>
          </div>
        </AppCard>

        <AppCard variant="metric" class="p-5 dashboard-metric-card">
          <template #icon>
            <AppIcon name="feeds" class="dashboard-card-icon" />
          </template>
          <template #header>
            <div class="app-card-kicker">Inventory</div>
            <div class="app-card-title">Feeds</div>
          </template>
          <template #metric>
            <div class="app-card-value">{{ totalFeeds }}</div>
            <div class="app-card-subtitle">{{ avgFeedsPerWorkspace }}/workspace · {{ sourceDiversity }} source types</div>
          </template>
          <template #trend>
            <span class="app-card-chip app-card-chip--up dashboard-metric-trend">
              {{ recentFeedAdds }} new / 30d
            </span>
          </template>

          <div class="dashboard-metric-bottom">
            <div class="dashboard-metric-visual">
              <MiniBarChart :data="growthTimeline" series-key="feeds" />
            </div>
            <div class="dashboard-progress">
              <div class="dashboard-progress-bar dashboard-progress-bar--cyan" :style="{ width: `${workspaceUtilization}%` }" />
            </div>
            <div class="dashboard-metric-foot">
              {{ dominantFeedType ? `${feedTypeLabel(dominantFeedType.type)} leads the mix at ${dominantFeedType.share}%` : 'Waiting for feed data' }}
            </div>
          </div>
        </AppCard>

        <AppCard variant="metric" class="p-5 dashboard-metric-card">
          <template #icon>
            <AppIcon name="check" class="dashboard-card-icon" />
          </template>
          <template #header>
            <div class="app-card-kicker">Reliability</div>
            <div class="app-card-title">Healthy Syncs</div>
          </template>
          <template #metric>
            <div class="app-card-value">{{ activeFeeds }}</div>
            <div class="app-card-subtitle">{{ healthySyncRate }}% synced in the last 7 days</div>
          </template>
          <template #trend>
            <span class="app-card-chip app-card-chip--neutral dashboard-metric-trend">
              {{ syncedFeeds }} touched overall
            </span>
          </template>

          <div class="dashboard-metric-bottom">
            <div class="dashboard-segment-bar">
              <div
                v-for="segment in syncStatusBreakdown"
                :key="segment.key"
                class="dashboard-segment"
                :class="`dashboard-segment--${segment.tone}`"
                :style="{ width: `${Math.max(segment.width, segment.count ? 8 : 0)}%` }"
              />
            </div>
            <div class="dashboard-sync-summary">
              <span>{{ agingFeeds }} aging</span>
              <span>{{ staleFeeds + neverSyncedFeeds }} need attention</span>
            </div>
            <div class="dashboard-metric-foot">Sync freshness across all configured feeds</div>
          </div>
        </AppCard>

        <AppCard variant="metric" class="p-5 dashboard-metric-card">
          <template #icon>
            <AppIcon name="bell" class="dashboard-card-icon" />
          </template>
          <template #header>
            <div class="app-card-kicker">Attention</div>
            <div class="app-card-title">Follow-up Items</div>
          </template>
          <template #metric>
            <div class="app-card-value">{{ attentionCount }}</div>
            <div class="app-card-subtitle">{{ brokenCredentialCount }} credential issues · {{ newPostCount }} scheduler sync posts</div>
          </template>
          <template #trend>
            <span class="app-card-chip app-card-chip--down dashboard-metric-trend">
              {{ unpublishedWorkspaces }} unpublished
            </span>
          </template>

          <div class="dashboard-metric-bottom">
            <div class="space-y-2">
              <div
                v-for="item in attentionItems.slice(0, 2)"
                :key="item.title"
                class="dashboard-attention-inline"
              >
                <div class="dashboard-attention-inline-title">{{ item.title }}</div>
                <div class="dashboard-attention-inline-detail">{{ item.detail }}</div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <AppCard variant="panel" class="p-5 xl:col-span-2">
          <template #header>
            <div>
              <div class="app-card-title">Growth Trend</div>
              <div class="dashboard-panel-subtitle">Feeds and workspaces added over the last six months.</div>
            </div>
          </template>

          <TrendChart :data="growthTimeline" />

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            <div class="dashboard-panel-stat">
              <div class="dashboard-panel-stat-label">Feed growth</div>
              <div class="dashboard-panel-stat-value">{{ recentFeedAdds }}</div>
              <div class="dashboard-panel-stat-meta">New feeds in the last 30 days</div>
            </div>
            <div class="dashboard-panel-stat">
              <div class="dashboard-panel-stat-label">Workspace growth</div>
              <div class="dashboard-panel-stat-value">{{ recentWorkspaceAdds }}</div>
              <div class="dashboard-panel-stat-meta">New workspaces in the last 30 days</div>
            </div>
            <div class="dashboard-panel-stat">
              <div class="dashboard-panel-stat-label">Source diversity</div>
              <div class="dashboard-panel-stat-value">{{ sourceDiversity }}</div>
              <div class="dashboard-panel-stat-meta">Distinct feed source types active</div>
            </div>
          </div>
        </AppCard>

        <AppCard variant="panel" class="p-5">
          <template #header>
            <div>
              <div class="app-card-title">Sync Status</div>
              <div class="dashboard-panel-subtitle">Freshness split by feed recency.</div>
            </div>
          </template>

          <div class="dashboard-segment-bar dashboard-segment-bar--lg">
            <div
              v-for="segment in syncStatusBreakdown"
              :key="segment.key"
              class="dashboard-segment"
              :class="`dashboard-segment--${segment.tone}`"
              :style="{ width: `${Math.max(segment.width, segment.count ? 8 : 0)}%` }"
            />
          </div>

          <div class="space-y-3 mt-4">
            <div
              v-for="segment in syncStatusBreakdown"
              :key="`${segment.key}-row`"
              class="dashboard-status-row"
            >
              <div class="dashboard-status-copy">
                <span class="dashboard-status-dot" :class="`dashboard-status-dot--${segment.tone}`" />
                <div>
                  <div class="dashboard-status-title">{{ segment.label }}</div>
                  <div class="dashboard-status-detail">{{ segment.description }}</div>
                </div>
              </div>
              <div class="dashboard-status-count">{{ segment.count }}</div>
            </div>
          </div>
        </AppCard>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <AppCard variant="panel" class="p-5">
          <template #header>
            <div>
              <div class="app-card-title">Source Mix</div>
              <div class="dashboard-panel-subtitle">Distribution by feed type and share of total inventory.</div>
            </div>
          </template>

          <div class="space-y-3 max-h-[280px] overflow-y-auto pr-1">
            <div v-for="type in feedTypeDistribution" :key="type.type" class="space-y-1.5">
              <div class="flex items-center justify-between gap-3 text-xs text-slate-600">
                <span class="inline-flex items-center gap-2 min-w-0">
                  <span class="type-dot" :class="`type-dot--${String(type.type || 'other')}`">
                    <SocialIcon :type="type.type" />
                  </span>
                  <span class="truncate">{{ feedTypeLabel(type.type) }}</span>
                </span>
                <span class="font-semibold text-slate-700 whitespace-nowrap">{{ type.count }} · {{ type.share }}%</span>
              </div>
              <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-cyan-400 to-emerald-400 transition-all" :style="{ width: `${type.share}%` }" />
              </div>
            </div>
            <AppEmptyState v-if="!feedTypeDistribution.length" title="No feed data yet." icon="chart" />
          </div>
        </AppCard>

        <AppCard variant="panel" class="p-5">
          <template #header>
            <div>
              <div class="app-card-title">Workspace Health</div>
              <div class="dashboard-panel-subtitle">Ranked by sync reliability, publishing, and source coverage.</div>
            </div>
          </template>

          <div class="space-y-3 max-h-[320px] overflow-y-auto pr-1">
            <RouterLink
              v-for="workspace in topWorkspaces"
              :key="workspace.id"
              :to="`/workspaces/${workspace.id}/feeds`"
              class="dashboard-workspace-row"
            >
              <div class="dashboard-workspace-top">
                <div class="min-w-0">
                  <div class="dashboard-workspace-name">{{ workspace.name }}</div>
                  <div class="dashboard-workspace-meta">
                    {{ workspace.count }} feeds · {{ workspace.activeCount }} healthy · {{ workspace.staleCount }} stale
                  </div>
                </div>
                <div class="dashboard-workspace-score">
                  <div class="dashboard-workspace-score-value">{{ workspace.score }}</div>
                  <span class="dashboard-health-pill" :class="workspace.score >= 80 ? 'dashboard-health-pill--good' : (workspace.score >= 55 ? 'dashboard-health-pill--mid' : 'dashboard-health-pill--risk')">
                    {{ workspace.status }}
                  </span>
                </div>
              </div>

              <div class="dashboard-workspace-metrics">
                <div class="dashboard-workspace-metric">
                  <span class="dashboard-workspace-metric-label">Healthy</span>
                  <span class="dashboard-workspace-metric-value">{{ workspace.activeCount }}</span>
                </div>
                <div class="dashboard-workspace-metric">
                  <span class="dashboard-workspace-metric-label">Stale</span>
                  <span class="dashboard-workspace-metric-value">{{ workspace.staleCount }}</span>
                </div>
                <div class="dashboard-workspace-metric">
                  <span class="dashboard-workspace-metric-label">State</span>
                  <span class="dashboard-workspace-metric-value">{{ workspace.published ? 'Live' : 'Draft' }}</span>
                </div>
              </div>

              <div class="dashboard-workspace-bar">
                <div class="dashboard-workspace-bar-fill" :style="{ width: `${workspace.scoreWidth}%` }" />
              </div>

              <div class="dashboard-workspace-footer">
                <span>Health score {{ workspace.score }}</span>
                <span class="dashboard-workspace-link">
                  Open feeds
                  <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
                </span>
              </div>
            </RouterLink>
            <AppEmptyState v-if="!topWorkspaces.length" title="No workspace stats yet." icon="workspaces" />
          </div>
        </AppCard>
      </div>
    </template>
  </AppSection>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import SocialIcon from '../components/SocialIcon.vue'
import { AppCard, AppEmptyState, AppIcon, AppLoader } from '../components/ui/index.js'
import { AppSection, AppPageHeader } from '../components/layout/index.js'
import { useDashboardAnalytics } from '../composables/useDashboardAnalytics.js'

defineOptions({
  name: 'DashboardView',
})

const {
  analyticsLoading,
  loadAnalytics,
  totalWorkspaces,
  totalFeeds,
  avgFeedsPerWorkspace,
  publishedWorkspaces,
  syncedFeeds,
  activeFeeds,
  agingFeeds,
  staleFeeds,
  neverSyncedFeeds,
  recentWorkspaceAdds,
  recentFeedAdds,
  workspaceUtilization,
  publishedCoverage,
  healthySyncRate,
  sourceDiversity,
  dominantFeedType,
  newPostCount,
  brokenCredentialCount,
  attentionCount,
  unpublishedWorkspaces,
  syncStatusBreakdown,
  growthTimeline,
  overallHealthScore,
  headlineStats,
  attentionItems,
  topWorkspaces,
  feedTypeDistribution,
} = useDashboardAnalytics()

function feedTypeLabel(type) {
  const labels = {
    youtube: 'YouTube',
    facebook: 'Facebook',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    threads: 'Threads',
    rss: 'RSS / Atom',
    twitter: 'X / Twitter',
  }

  return labels[type] || type || 'Other'
}

onMounted(() => loadAnalytics())

const DonutChart = {
  props: {
    percent: { type: Number, default: 0 },
    accent: { type: String, default: 'blue' },
  },
  setup(props) {
    const safePercent = computed(() => Math.max(0, Math.min(100, Number(props.percent || 0))))
    const radius = 28
    const circumference = computed(() => 2 * Math.PI * radius)
    const offset = computed(() => circumference.value - ((safePercent.value / 100) * circumference.value * 0.75))
    const accentStroke = computed(() => (props.accent === 'emerald' ? '#10b981' : '#3b82f6'))

    return { safePercent, radius, circumference, offset, accentStroke }
  },
  template: `
    <svg width="84" height="84" viewBox="0 0 72 72" class="flex-shrink-0">
      <circle
        cx="36"
        cy="36"
        :r="radius"
        fill="none"
        stroke="#e2e8f0"
        stroke-width="6"
        stroke-dasharray="131.9 44"
        stroke-dashoffset="-11"
        stroke-linecap="round"
        transform="rotate(-210 36 36)"
      />
      <circle
        cx="36"
        cy="36"
        :r="radius"
        fill="none"
        :stroke="accentStroke"
        stroke-width="6"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        stroke-linecap="round"
        transform="rotate(-210 36 36)"
        style="transition: stroke-dashoffset 0.5s ease"
      />
      <text x="36" y="40" text-anchor="middle" font-size="12" font-weight="700" fill="#0f172a">{{ safePercent }}%</text>
    </svg>
  `,
}

const MiniBarChart = {
  props: {
    data: { type: Array, default: () => [] },
    seriesKey: { type: String, default: 'feeds' },
  },
  setup(props) {
    const maxValue = computed(() =>
      Math.max(1, ...props.data.map((item) => Number(item?.[props.seriesKey] || 0))),
    )
    const bars = computed(() =>
      props.data.map((item) => ({
        label: item.label,
        value: Number(item?.[props.seriesKey] || 0),
        height: Math.max(16, Math.round((Number(item?.[props.seriesKey] || 0) / maxValue.value) * 56)),
      })),
    )

    return { bars }
  },
  template: `
    <div class="dashboard-mini-bars">
      <div v-for="bar in bars" :key="bar.label" class="dashboard-mini-bar-col">
        <div class="dashboard-mini-bar" :style="{ height: \`\${bar.height}px\` }" />
        <span class="dashboard-mini-bar-label">{{ bar.label }}</span>
      </div>
    </div>
  `,
}

const TrendChart = {
  props: {
    data: { type: Array, default: () => [] },
  },
  setup(props) {
    const width = 560
    const height = 220
    const paddingX = 24
    const paddingY = 28

    const maxValue = computed(() =>
      Math.max(1, ...props.data.flatMap((item) => [Number(item.feeds || 0), Number(item.workspaces || 0)])),
    )

    const chartWidth = computed(() => width - (paddingX * 2))
    const chartHeight = computed(() => height - (paddingY * 2))
    const stepX = computed(() => (props.data.length > 1 ? chartWidth.value / (props.data.length - 1) : 0))

    function pointY(value) {
      return height - paddingY - ((Number(value || 0) / maxValue.value) * chartHeight.value)
    }

    const feedPoints = computed(() =>
      props.data.map((item, index) => `${paddingX + (index * stepX.value)},${pointY(item.feeds)}`).join(' '),
    )
    const workspacePoints = computed(() =>
      props.data.map((item, index) => `${paddingX + (index * stepX.value)},${pointY(item.workspaces)}`).join(' '),
    )
    const feedArea = computed(() => {
      if (!props.data.length) return ''
      const firstX = paddingX
      const lastX = paddingX + ((props.data.length - 1) * stepX.value)
      return `${firstX},${height - paddingY} ${feedPoints.value} ${lastX},${height - paddingY}`
    })
    const gridLines = computed(() =>
      [0, 0.5, 1].map((ratio) => ({
        y: paddingY + ((1 - ratio) * chartHeight.value),
        label: Math.round(maxValue.value * ratio),
      })),
    )
    const labels = computed(() =>
      props.data.map((item, index) => ({
        x: paddingX + (index * stepX.value),
        label: item.label,
      })),
    )

    return {
      width,
      height,
      paddingY,
      feedPoints,
      workspacePoints,
      feedArea,
      gridLines,
      labels,
      pointY,
    }
  },
  template: `
    <div class="dashboard-trend-chart">
      <svg :viewBox="\`0 0 \${width} \${height}\`" preserveAspectRatio="none" class="w-full h-[240px]">
        <g>
          <line
            v-for="line in gridLines"
            :key="\`line-\${line.y}\`"
            x1="0"
            :x2="width"
            :y1="line.y"
            :y2="line.y"
            stroke="#e2e8f0"
            stroke-dasharray="4 6"
          />
        </g>

        <polygon :points="feedArea" fill="url(#dashboardAreaFill)" />

        <polyline
          :points="feedPoints"
          fill="none"
          stroke="#2563eb"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <polyline
          :points="workspacePoints"
          fill="none"
          stroke="#0f766e"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-dasharray="8 8"
        />

        <circle
          v-for="(item, index) in data"
          :key="\`feed-\${index}\`"
          :cx="labels[index]?.x"
          :cy="pointY(item.feeds)"
          r="4"
          fill="#2563eb"
        />
        <circle
          v-for="(item, index) in data"
          :key="\`workspace-\${index}\`"
          :cx="labels[index]?.x"
          :cy="pointY(item.workspaces)"
          r="4"
          fill="#0f766e"
        />

        <text
          v-for="label in labels"
          :key="\`label-\${label.label}\`"
          :x="label.x"
          :y="height - 6"
          text-anchor="middle"
          font-size="11"
          fill="#64748b"
        >
          {{ label.label }}
        </text>

        <defs>
          <linearGradient id="dashboardAreaFill" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stop-color="#60a5fa" stop-opacity="0.28" />
            <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.02" />
          </linearGradient>
        </defs>
      </svg>

      <div class="dashboard-trend-legend">
        <span><span class="dashboard-trend-legend-dot dashboard-trend-legend-dot--feeds" />Feeds</span>
        <span><span class="dashboard-trend-legend-dot dashboard-trend-legend-dot--workspaces" />Workspaces</span>
      </div>
    </div>
  `,
}
</script>

<style scoped>
.dashboard-eyebrow {
  @apply text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-600;
}

.dashboard-hero-card {
  background:
    radial-gradient(circle at top right, rgba(96, 165, 250, 0.18), transparent 34%),
    linear-gradient(145deg, rgba(248, 250, 252, 0.98), rgba(239, 246, 255, 0.96));
  border: 1px solid rgba(191, 219, 254, 0.75);
}

.dashboard-hero-grid {
  @apply grid grid-cols-1 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,0.75fr)] gap-6 items-center;
}

.dashboard-hero-title {
  @apply mt-2 text-2xl sm:text-[2rem] leading-tight font-semibold text-slate-900;
}

.dashboard-hero-copy {
  @apply mt-3 max-w-2xl text-sm text-slate-600 leading-6;
}

.dashboard-hero-stat {
  @apply rounded-2xl border border-white/80 bg-white/80 backdrop-blur px-4 py-3 shadow-sm;
}

.dashboard-hero-stat-label {
  @apply text-[11px] uppercase tracking-[0.14em] text-slate-400;
}

.dashboard-hero-stat-value {
  @apply mt-1 text-sm font-semibold text-slate-800;
}

.dashboard-hero-score {
  @apply flex justify-start xl:justify-end;
}

.dashboard-hero-score-shell {
  @apply flex items-center gap-4 rounded-[28px] border border-white/70 bg-white/75 px-5 py-4 shadow-sm backdrop-blur;
}

.dashboard-hero-score-caption {
  @apply min-w-0;
}

.dashboard-hero-score-label {
  @apply text-sm font-semibold text-slate-800;
}

.dashboard-hero-score-meta {
  @apply mt-1 text-xs text-slate-500;
}

.dashboard-metric-visual {
  @apply mt-2 flex items-center justify-start;
}

.dashboard-metric-visual :deep(svg) {
  opacity: 0.98;
}

.dashboard-metric-bottom {
  @apply mt-3 pt-3 border-t border-slate-100;
}

.dashboard-metric-foot {
  @apply text-[10px] uppercase tracking-[0.14em] text-slate-400 mt-2;
}

.dashboard-progress {
  @apply mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden;
}

.dashboard-progress-bar {
  @apply h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-500;
}

.dashboard-progress-bar--cyan {
  @apply bg-gradient-to-r from-cyan-400 to-emerald-500;
}

.dashboard-card-icon {
  width: 1.9rem;
  height: 1.9rem;
}

.dashboard-metric-card :deep(.app-card-icon) {
  width: auto;
  height: auto;
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: 0;
  margin-top: 0.1rem;
}

.dashboard-metric-card :deep(.app-card-header-main) {
  gap: 0.75rem;
}

.dashboard-metric-card :deep(.app-card-kicker) {
  @apply text-[10px] font-semibold tracking-[0.16em] text-slate-400;
}

.dashboard-metric-card :deep(.app-card-title) {
  @apply text-[14px] font-semibold text-slate-700;
}

.dashboard-metric-card :deep(.app-card-value) {
  @apply text-[2.05rem] leading-[1.05] font-semibold text-slate-800 mt-2;
}

.dashboard-metric-card :deep(.app-card-subtitle) {
  @apply text-[11px] text-slate-400 mt-1;
}

.dashboard-metric-card {
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.dashboard-metric-card:hover {
  transform: translateY(-2px);
}

.dashboard-metric-trend {
  @apply gap-1.5 h-6;
}

.dashboard-segment-bar {
  @apply flex h-2 overflow-hidden rounded-full bg-slate-100;
}

.dashboard-segment-bar--lg {
  @apply h-3;
}

.dashboard-segment {
  @apply h-full transition-all;
}

.dashboard-segment--emerald {
  @apply bg-emerald-500;
}

.dashboard-segment--amber {
  @apply bg-amber-400;
}

.dashboard-segment--rose {
  @apply bg-rose-400;
}

.dashboard-segment--slate {
  @apply bg-slate-400;
}

.dashboard-sync-summary {
  @apply mt-2 flex items-center justify-between text-[11px] text-slate-500;
}

.dashboard-attention-inline {
  @apply rounded-2xl border border-slate-100 bg-slate-50/80 px-3 py-2;
}

.dashboard-attention-inline-title {
  @apply text-[12px] font-semibold text-slate-700;
}

.dashboard-attention-inline-detail {
  @apply mt-1 text-[11px] leading-5 text-slate-500;
}

.dashboard-panel-subtitle {
  @apply mt-1 text-xs text-slate-500;
}

.dashboard-panel-stat {
  @apply rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3;
}

.dashboard-panel-stat-label {
  @apply text-[11px] uppercase tracking-[0.14em] text-slate-400;
}

.dashboard-panel-stat-value {
  @apply mt-1 text-xl font-semibold text-slate-800;
}

.dashboard-panel-stat-meta {
  @apply mt-1 text-xs text-slate-500;
}

.dashboard-status-row {
  @apply flex items-start justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3;
}

.dashboard-status-copy {
  @apply flex items-start gap-3 min-w-0;
}

.dashboard-status-dot {
  @apply mt-1.5 h-2.5 w-2.5 rounded-full flex-shrink-0;
}

.dashboard-status-dot--emerald {
  @apply bg-emerald-500;
}

.dashboard-status-dot--amber {
  @apply bg-amber-400;
}

.dashboard-status-dot--rose {
  @apply bg-rose-400;
}

.dashboard-status-dot--slate {
  @apply bg-slate-400;
}

.dashboard-status-title {
  @apply text-sm font-semibold text-slate-700;
}

.dashboard-status-detail {
  @apply mt-1 text-xs text-slate-500;
}

.dashboard-status-count {
  @apply text-lg font-semibold text-slate-800;
}

.dashboard-workspace-row {
  @apply block rounded-[22px] border border-slate-200/80 bg-gradient-to-br from-white to-slate-50/80 px-4 py-4 text-left no-underline shadow-sm transition-all;
}

.dashboard-workspace-row:hover {
  transform: translateY(-1px);
  border-color: rgba(96, 165, 250, 0.45);
  box-shadow: 0 16px 30px -26px rgba(15, 23, 42, 0.55);
}

.dashboard-workspace-top {
  @apply flex items-start justify-between gap-4;
}

.dashboard-workspace-name {
  @apply truncate text-sm font-semibold text-slate-800;
}

.dashboard-workspace-meta {
  @apply mt-1 text-xs text-slate-500 leading-5;
}

.dashboard-workspace-score {
  @apply flex flex-col items-end gap-2 flex-shrink-0;
}

.dashboard-workspace-score-value {
  @apply text-2xl font-semibold leading-none text-slate-900;
}

.dashboard-workspace-metrics {
  @apply mt-4 grid grid-cols-3 gap-2;
}

.dashboard-workspace-metric {
  @apply rounded-2xl border border-white/90 bg-white/90 px-3 py-2;
}

.dashboard-workspace-metric-label {
  @apply block text-[10px] uppercase tracking-[0.14em] text-slate-400;
}

.dashboard-workspace-metric-value {
  @apply mt-1 block text-sm font-semibold text-slate-700;
}

.dashboard-workspace-bar {
  @apply mt-4 h-2 overflow-hidden rounded-full bg-slate-200/80;
}

.dashboard-workspace-bar-fill {
  @apply h-full rounded-full bg-gradient-to-r from-blue-500 via-cyan-400 to-emerald-400;
}

.dashboard-workspace-footer {
  @apply mt-3 flex items-center justify-between text-[11px] text-slate-500;
}

.dashboard-workspace-link {
  @apply inline-flex items-center gap-1 text-slate-700 font-medium;
}

.dashboard-workspace-link svg {
  width: 0.85rem;
  height: 0.85rem;
}

.dashboard-health-pill {
  @apply inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap;
}

.dashboard-health-pill--good {
  @apply bg-emerald-50 text-emerald-700;
}

.dashboard-health-pill--mid {
  @apply bg-amber-50 text-amber-700;
}

.dashboard-health-pill--risk {
  @apply bg-rose-50 text-rose-700;
}

.dashboard-mini-bars {
  @apply flex items-end gap-2 h-[74px];
}

.dashboard-mini-bar-col {
  @apply flex flex-col items-center justify-end gap-1;
}

.dashboard-mini-bar {
  width: 0.65rem;
  border-radius: 999px;
  background: linear-gradient(180deg, #38bdf8 0%, #2563eb 100%);
}

.dashboard-mini-bar-label {
  @apply text-[10px] text-slate-400;
}

.dashboard-trend-chart {
  @apply mt-3;
}

.dashboard-trend-legend {
  @apply mt-2 flex items-center gap-4 text-xs text-slate-500;
}

.dashboard-trend-legend span {
  @apply inline-flex items-center gap-2;
}

.dashboard-trend-legend-dot {
  @apply h-2.5 w-2.5 rounded-full;
}

.dashboard-trend-legend-dot--feeds {
  @apply bg-blue-600;
}

.dashboard-trend-legend-dot--workspaces {
  @apply bg-teal-700;
}

.type-dot {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}

.type-dot :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-dot--youtube {
  background: #fee2e2;
  color: #dc2626;
}

.type-dot--facebook {
  background: #dbeafe;
  color: #2563eb;
}

.type-dot--instagram {
  background: #fce7f3;
  color: #be185d;
}

.type-dot--tiktok,
.type-dot--threads,
.type-dot--twitter {
  background: #e2e8f0;
  color: #0f172a;
}

.type-dot--rss {
  background: #ffedd5;
  color: #ea580c;
}

@media (max-width: 767px) {
  .dashboard-hero-score-shell {
    @apply w-full justify-center;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dashboard-metric-card {
    transition: none !important;
  }

  .dashboard-metric-card:hover {
    transform: none !important;
  }
}
</style>
