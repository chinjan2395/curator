<template>
  <div class="space-y-4">
    <AppPageHeader title="Unified curator feed" subtitle="All synced posts across workspaces." icon="grid" />
    <div class="flex gap-2">
      <AppSelect v-model="sort" :options="sortOptions" :show-placeholder="false" />
      <AppSelect v-model="platform" :options="platformOptions" :show-placeholder="false" />
    </div>

    <AppLoader v-if="loading" label="Loading feed…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <AppEmptyState
      v-else-if="!posts.length"
      title="No posts to show"
      description="Create a workspace, add feeds, and sync content from Integrations."
      icon="grid"
    >
      <div class="flex gap-2 justify-center">
        <router-link to="/workspaces" class="text-sm text-blue-600 hover:underline">Workspaces</router-link>
        <span class="text-slate-300">·</span>
        <router-link to="/credentials" class="text-sm text-blue-600 hover:underline">Integrations</router-link>
      </div>
    </AppEmptyState>

    <div v-else class="grid gap-3 md:grid-cols-3">
      <AppCard v-for="post in posts" :key="post.id" class="p-3">
        <img v-if="post.thumbnail_url" :src="post.thumbnail_url" class="w-full h-32 object-cover rounded mb-2" alt="" />
        <div class="text-xs text-slate-500">{{ post.feed?.type }} · {{ post.likes }} likes</div>
        <p class="text-sm font-medium line-clamp-2">{{ post.title || post.content }}</p>
      </AppCard>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppEmptyState, AppLoader, AppSelect } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import { getPlatformLabel } from '../constants/socialPlatforms';

const posts = ref([]);
const loading = ref(true);
const error = ref(null);
const sort = ref('latest');
const platform = ref('');
const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'most_liked', label: 'Most liked' },
  { value: 'most_viewed', label: 'Most viewed' },
];
const platformTypes = ['youtube', 'instagram', 'facebook', 'twitter', 'tiktok', 'threads', 'rss'];
const platformOptions = [
  { value: '', label: 'All platforms' },
  ...platformTypes.map((type) => ({ value: type, label: getPlatformLabel(type) })),
];

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/curator/feed', {
      params: { sort: sort.value, platform: platform.value || undefined },
      skipErrorToast: true,
    });
    posts.value = data.data?.data ?? (Array.isArray(data.data) ? data.data : []);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load curator feed';
  } finally {
    loading.value = false;
  }
}

watch([sort, platform], load);
onMounted(load);
</script>
