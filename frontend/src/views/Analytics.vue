<template>
  <div class="space-y-4">
    <AppPageHeader title="Analytics" subtitle="Performance across connected platforms." icon="chart" />

    <AppLoader v-if="loading" label="Loading analytics…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else-if="overview">
      <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
        <AppCard class="p-4"><div class="text-2xl font-bold">{{ overview.total_likes ?? 0 }}</div><div class="text-xs text-slate-500">Likes</div></AppCard>
        <AppCard class="p-4"><div class="text-2xl font-bold">{{ overview.total_views ?? 0 }}</div><div class="text-xs text-slate-500">Views</div></AppCard>
        <AppCard class="p-4"><div class="text-2xl font-bold">{{ overview.total_embed_clicks ?? 0 }}</div><div class="text-xs text-slate-500">Embed clicks</div></AppCard>
        <AppCard class="p-4"><div class="text-2xl font-bold">{{ overview.engagement_rate ?? 0 }}%</div><div class="text-xs text-slate-500">Engagement</div></AppCard>
        <AppCard class="p-4"><div class="text-2xl font-bold">{{ overview.follower_total ?? 0 }}</div><div class="text-xs text-slate-500">Followers</div></AppCard>
      </div>

      <AppCard class="p-4" v-if="chartBars.length">
        <AppTitle size="sm">Engagement by platform (30d)</AppTitle>
        <div class="mt-4 space-y-2">
          <div v-for="bar in chartBars" :key="bar.platform" class="flex items-center gap-3 text-sm">
            <router-link :to="`/analytics/platforms/${bar.platform}`" class="min-w-[7.5rem] shrink-0 hover:opacity-80">
              <SocialPlatformLabel :type="bar.platform" size="sm" />
            </router-link>
            <div class="flex-1 h-4 bg-slate-100 rounded overflow-hidden">
              <div class="h-full bg-blue-500 rounded" :style="{ width: bar.pct + '%' }" />
            </div>
            <span class="w-12 text-right text-slate-500">{{ bar.likes }}</span>
          </div>
        </div>
      </AppCard>

      <AppCard v-else class="p-4">
        <p class="text-sm text-slate-500">No platform engagement data yet. Connect feeds and sync posts to see charts.</p>
      </AppCard>

      <AppCard class="p-4" v-if="overview?.top_embed_clicked_posts?.length">
        <AppTitle size="sm">Top clicked embed posts</AppTitle>
        <div class="mt-2 space-y-2 text-sm">
          <div
            v-for="post in overview.top_embed_clicked_posts"
            :key="post.id"
            class="flex items-center justify-between gap-3 border-b py-2 last:border-b-0"
          >
            <span class="truncate">{{ post.title || 'Untitled' }}</span>
            <span class="shrink-0 text-slate-500">{{ post.clicks }} clicks</span>
          </div>
        </div>
      </AppCard>

      <AppCard class="p-4" v-if="overview?.campaigns?.length">
        <AppTitle size="sm">Campaign performance</AppTitle>
        <div class="mt-2 space-y-1 text-sm">
          <div v-for="c in overview.campaigns" :key="c.id" class="flex justify-between border-b py-1">
            <span>{{ c.name }}</span>
            <span class="text-slate-500">{{ c.approved }}/{{ c.packages }} approved</span>
          </div>
        </div>
      </AppCard>

      <AppCard class="p-4" v-if="insights.length">
        <AppTitle size="sm">AI insights</AppTitle>
        <ul class="mt-2 space-y-1 text-sm text-slate-700">
          <li v-for="(line, i) in insights" :key="i">· {{ line }}</li>
        </ul>
      </AppCard>

      <AppCard v-else class="p-4">
        <p class="text-sm text-slate-500">Insights appear once you have synced posts or scheduled content.</p>
      </AppCard>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppLoader, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';

const overview = ref(null);
const insights = ref([]);
const loading = ref(true);
const error = ref(null);

const chartBars = computed(() => {
  const series = overview.value?.time_series || {};
  const totals = Object.entries(series).map(([platform, rows]) => ({
    platform,
    likes: (rows || []).reduce((s, r) => s + Number(r.likes || 0), 0),
  }));
  if (!totals.length) return [];
  const max = Math.max(1, ...totals.map((t) => t.likes));
  return totals.map((t) => ({ ...t, pct: Math.round((t.likes / max) * 100) }));
});

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    const [o, i] = await Promise.all([
      axios.get('/api/analytics/overview', { skipErrorToast: true }),
      axios.get('/api/analytics/insights', { skipErrorToast: true }),
    ]);
    overview.value = o.data.data || o.data;
    insights.value = (i.data.data || i.data).insights || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load analytics';
  } finally {
    loading.value = false;
  }
});
</script>
