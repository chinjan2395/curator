<template>
  <AppSection spacing="md">
    <AppPageHeader
      title="Dashboard analytics"
      subtitle="Workspace and feed performance overview."
      icon="dashboard"
      badge="Global snapshot"
    />

    <AppLoader v-if="analyticsLoading" label="Loading analytics…" />

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <AppButton variant="ghost" class="analytics-card surface-card p-4 !rounded-xl text-left h-auto" @click="router.push('/workspaces')">
          <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <span class="metric-chip">WS</span> Total workspaces
          </div>
          <div class="mt-2 text-xl font-semibold text-slate-900">{{ totalWorkspaces }}</div>
          <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-indigo-300/85 to-violet-300/85" :style="{ width: `${workspaceUtilization}%` }" />
          </div>
          <AppText muted size="xs" class="mt-1">{{ workspaceUtilization }}% utilization target</AppText>
        </AppButton>

        <AppButton variant="ghost" class="analytics-card surface-card p-4 !rounded-xl text-left h-auto" @click="router.push('/workspaces')">
          <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <span class="metric-chip">FD</span> Total feeds
          </div>
          <div class="mt-2 text-xl font-semibold text-slate-900">{{ totalFeeds }}</div>
          <AppText muted size="xs">Avg/workspace: <span class="text-slate-700 font-medium">{{ avgFeedsPerWorkspace }}</span></AppText>
        </AppButton>

        <AppButton variant="ghost" class="analytics-card surface-card p-4 !rounded-xl text-left h-auto" @click="router.push('/publish')">
          <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <span class="metric-chip">PB</span> Publish coverage
          </div>
          <div class="mt-2 text-xl font-semibold text-slate-900">{{ publishedFeeds }}</div>
          <AppText muted size="xs">Workspaces with public key</AppText>
          <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-300/80 to-cyan-300/80" :style="{ width: `${publishedCoverage}%` }" />
          </div>
          <AppText muted size="xs" class="mt-1">{{ publishedCoverage }}% of all workspaces</AppText>
        </AppButton>

        <AppButton variant="ghost" class="analytics-card surface-card p-4 !rounded-xl text-left h-auto" @click="router.push('/workspaces')">
          <div class="text-2xs uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <span class="metric-chip">SY</span> Sync health
          </div>
          <div class="mt-2 text-xl font-semibold text-slate-900">{{ syncedFeeds }}</div>
          <AppText muted size="xs">Feeds synced at least once</AppText>
          <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-amber-300/85 to-indigo-300/85" :style="{ width: `${syncCoverage}%` }" />
          </div>
          <AppText muted size="xs" class="mt-1">{{ syncCoverage }}% sync coverage</AppText>
        </AppButton>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <AppCard padding="md" class="analytics-card">
          <div class="text-sm font-medium text-slate-800 flex items-center gap-1.5 mb-3">
            <span class="metric-chip">MX</span> Feed type distribution
          </div>
          <div class="space-y-2 max-h-[154px] overflow-y-auto pr-1">
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
            <AppEmptyState v-if="!feedTypeDistribution.length" title="No feed data yet." icon="📊" />
          </div>
        </AppCard>

        <AppCard padding="md" class="analytics-card">
          <div class="text-sm font-medium text-slate-800 flex items-center gap-1.5 mb-3">
            <span class="metric-chip">TOP</span> Top workspaces by feeds
          </div>
          <div class="space-y-2 max-h-[154px] overflow-y-auto pr-1">
            <AppButton
              v-for="w in topWorkspaces"
              :key="w.id"
              variant="ghost"
              class="space-y-1 w-full text-left !rounded-md !px-1.5 !py-1 h-auto"
              @click="router.push(`/workspaces/${w.id}/feeds`)"
            >
              <div class="flex items-center justify-between text-2xs text-slate-600">
                <span class="truncate">{{ w.name }}</span>
                <span class="font-medium text-slate-700">{{ w.count }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-cyan-300/80 to-blue-300/80" :style="{ width: `${w.width}%` }" />
              </div>
            </AppButton>
            <AppEmptyState v-if="!topWorkspaces.length" title="No workspace stats yet." icon="🗂️" />
          </div>
        </AppCard>
      </div>
    </template>
  </AppSection>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import SocialIcon from '../components/SocialIcon.vue'
import { AppButton, AppCard, AppEmptyState, AppLoader, AppText } from '../components/ui/index.js'
import { AppSection, AppPageHeader } from '../components/layout/index.js'
import { useDashboardAnalytics } from '../composables/useDashboardAnalytics.js'

const router = useRouter()

const {
  analyticsLoading,
  loadAnalytics,
  totalWorkspaces,
  totalFeeds,
  avgFeedsPerWorkspace,
  publishedFeeds,
  syncedFeeds,
  workspaceUtilization,
  publishedCoverage,
  syncCoverage,
  topWorkspaces,
  feedTypeDistribution,
} = useDashboardAnalytics()

function feedTypeLabel(type) {
  const labels = {
    youtube: 'YouTube', facebook: 'Facebook', instagram: 'Instagram',
    tiktok: 'TikTok', threads: 'Threads', rss: 'RSS / Atom', twitter: 'X / Twitter',
  }
  return labels[type] || type || '—'
}

onMounted(() => loadAnalytics())
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
.analytics-card::after {
  content: '';
  position: absolute;
  left: 0; right: 0; top: 0;
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
}
.type-dot {
  width: 1rem; height: 1rem;
  border-radius: 999px;
  display: grid; place-items: center;
  font-size: 0.65rem; font-weight: 700;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}
.type-dot :deep(svg) { width: 0.72rem; height: 0.72rem; display: block; }
.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok, .type-dot--threads, .type-dot--twitter { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--rss { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }

@media (prefers-reduced-motion: reduce) {
  .analytics-card:hover { transform: none; box-shadow: none; }
}
</style>
