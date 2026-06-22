<template>
  <div class="space-y-4">
    <AppPageHeader title="Platform analytics" subtitle="Platform-specific metrics." icon="chart" />
    <SocialPlatformLabel :type="platform" size="md" class="-mt-3 mb-2" />

    <div v-if="loading" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
      <AppCard v-for="n in 5" :key="n" class="p-4 space-y-3">
        <AppSkeleton variant="line" :lines="2" />
      </AppCard>
    </div>
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <div v-else-if="stats" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.post_count ?? 0 }}</div><div class="text-xs text-slate-500">Posts</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.likes ?? 0 }}</div><div class="text-xs text-slate-500">Likes</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.views ?? 0 }}</div><div class="text-xs text-slate-500">Views</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.embed_clicks ?? 0 }}</div><div class="text-xs text-slate-500">Embed clicks</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.engagement_rate ?? 0 }}%</div><div class="text-xs text-slate-500">Engagement</div></AppCard>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { AppAlert, AppCard, AppSkeleton } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';

const route = useRoute();
const platform = computed(() => route.params.platform);
const stats = ref(null);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get(`/api/analytics/platforms/${platform.value}`, { skipErrorToast: true });
    stats.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load platform analytics';
  } finally {
    loading.value = false;
  }
});
</script>
