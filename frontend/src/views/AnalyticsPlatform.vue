<template>
  <div class="space-y-4">
    <AppPageHeader :title="platform + ' analytics'" subtitle="Platform-specific metrics." icon="chart" />

    <AppLoader v-if="loading" label="Loading…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <div v-else-if="stats" class="grid gap-3 md:grid-cols-4">
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.post_count ?? 0 }}</div><div class="text-xs text-slate-500">Posts</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.likes ?? 0 }}</div><div class="text-xs text-slate-500">Likes</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.views ?? 0 }}</div><div class="text-xs text-slate-500">Views</div></AppCard>
      <AppCard class="p-4"><div class="text-2xl font-bold">{{ stats.engagement_rate ?? 0 }}%</div><div class="text-xs text-slate-500">Engagement</div></AppCard>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { AppAlert, AppCard, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';

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
