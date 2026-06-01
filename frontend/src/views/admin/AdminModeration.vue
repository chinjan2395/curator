<template>
  <div class="space-y-4">
    <AppPageHeader title="Content moderation" subtitle="Pending posts across workspaces." icon="shield" />
    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>
    <AppCard v-else class="p-4 space-y-2">
      <AppEmptyState v-if="!posts.length" title="No pending posts" icon="shield" />
      <div v-else v-for="p in posts" :key="p.id" class="flex justify-between text-sm border-b py-2">
        <span>{{ p.title || p.content?.slice(0, 60) }}</span>
        <span class="text-slate-500">{{ p.feed?.workspace?.name }}</span>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppEmptyState, AppLoader } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';

const posts = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/posts/pending', { skipErrorToast: true });
    posts.value = data.data?.data || data.data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load pending posts';
  } finally {
    loading.value = false;
  }
});
</script>
