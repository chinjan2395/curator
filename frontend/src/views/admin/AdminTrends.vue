<template>
  <div class="space-y-4">
    <AppPageHeader title="AI trends" subtitle="Hourly trend snapshots." icon="trending" />
    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>
    <AppCard v-else class="p-4 space-y-2">
      <AppEmptyState v-if="!trends.length" title="No trend snapshots" icon="trending" />
      <div v-else v-for="t in trends" :key="t.id" class="border-b border-slate-100 py-2 text-sm">
        <div class="font-medium">{{ t.title }}</div>
        <div class="text-xs text-slate-500">{{ t.source }} · {{ t.captured_at }}</div>
        <p class="text-slate-600 mt-1">{{ t.summary }}</p>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppEmptyState, AppLoader } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';

const trends = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/trends', { skipErrorToast: true });
    trends.value = data.data?.data || data.data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load trends';
  } finally {
    loading.value = false;
  }
});
</script>
