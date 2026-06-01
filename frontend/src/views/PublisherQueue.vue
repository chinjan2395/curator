<template>
  <div class="space-y-4">
    <AppPageHeader title="Publisher queue" subtitle="Track scheduled and published native posts." icon="send" />

    <CapabilityBanner context="publish" />

    <AppLoader v-if="loading" label="Loading queue…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <AppCard v-else class="p-4 space-y-2">
      <AppEmptyState
        v-if="!queue.length"
        title="Queue is empty"
        description="Scheduled posts appear here after you add them from the calendar."
        icon="send"
      >
        <router-link to="/calendar" class="text-sm text-blue-600 hover:underline">Open calendar</router-link>
      </AppEmptyState>
      <div v-else>
        <div v-for="post in queue" :key="post.id" class="py-3 border-b text-sm">
          <div class="flex justify-between">
            <span>#{{ post.id }} · {{ post.social_credential?.provider }} · <span :class="statusClass(post.status)">{{ post.status }}</span></span>
            <span class="text-slate-500">{{ post.scheduled_at }}</span>
          </div>
          <p v-if="post.error_message" class="text-xs text-red-600 mt-1">{{ post.error_message }}</p>
          <p v-if="post.retry_count" class="text-xs text-slate-500">Retries: {{ post.retry_count }}</p>
        </div>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppEmptyState, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';

const queue = ref([]);
const loading = ref(true);
const error = ref(null);

function statusClass(status) {
  if (status === 'failed') return 'text-red-600 font-medium';
  if (status === 'published') return 'text-emerald-600';
  return 'text-slate-600';
}

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/publisher/queue', { skipErrorToast: true });
    queue.value = data.data || data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load publisher queue';
  } finally {
    loading.value = false;
  }
});
</script>
