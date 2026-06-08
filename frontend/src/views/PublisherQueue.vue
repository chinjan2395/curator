<template>
  <div class="space-y-4">
    <AppPageHeader title="Publisher queue" subtitle="Track scheduled and published native posts." icon="send">
      <template #actions>
        <AppButton size="sm" variant="secondary" :loading="loading" @click="load">Refresh</AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="publish" />

    <AppLoader v-if="loading && !queue.length" label="Loading queue…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <AppEmptyState
        v-if="!queue.length"
        title="Queue is empty"
        description="Scheduled posts appear here after you add them from the calendar."
        icon="send"
      >
        <router-link to="/calendar" class="text-sm text-blue-600 hover:underline">Open calendar</router-link>
      </AppEmptyState>

      <template v-else>
        <!-- Summary counts -->
        <div class="grid grid-cols-3 gap-3">
          <AppCard class="p-3 text-center">
            <div class="text-xl font-bold text-sky-700">{{ counts.scheduled }}</div>
            <div class="text-xs text-slate-500 mt-0.5">Scheduled</div>
          </AppCard>
          <AppCard class="p-3 text-center">
            <div class="text-xl font-bold text-red-600">{{ counts.failed }}</div>
            <div class="text-xs text-slate-500 mt-0.5">Failed</div>
          </AppCard>
          <AppCard class="p-3 text-center">
            <div class="text-xl font-bold text-emerald-600">{{ counts.published }}</div>
            <div class="text-xs text-slate-500 mt-0.5">Published</div>
          </AppCard>
        </div>

        <!-- Post rows -->
        <AppCard class="divide-y divide-slate-100">
          <div v-for="post in queue" :key="post.id" class="p-4 space-y-3">

            <!-- Row 1: status badge + account + time -->
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex flex-wrap items-center gap-2">
                <AppBadge :variant="statusBadgeVariant(post)">{{ statusLabel(post) }}</AppBadge>
                <SocialPlatformLabel
                  v-if="post.social_credential?.provider"
                  :type="post.social_credential.provider"
                  size="sm"
                />
                <span v-if="post.social_credential?.account_label" class="text-xs text-slate-500">
                  {{ post.social_credential.account_label }}
                </span>
              </div>
              <span class="text-xs text-slate-400 shrink-0">
                {{ formatScheduledAt(post.scheduled_at) }}
              </span>
            </div>

            <!-- Caption preview -->
            <p v-if="post.content_package?.caption" class="text-sm text-slate-600 line-clamp-2">
              {{ post.content_package.caption }}
            </p>
            <p v-else class="text-xs text-slate-400 italic">No content package attached</p>

            <!-- Retry info (failed or retrying) -->
            <div v-if="post.retry_count > 0" class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 rounded px-3 py-1.5">
              <span class="font-medium">Attempt {{ post.retry_count }} / 3</span>
              <span v-if="post.status === 'scheduled'" class="text-amber-600">
                · Next retry {{ formatScheduledAt(post.scheduled_at) }}
              </span>
            </div>

            <!-- Error message -->
            <div v-if="post.error_message" class="rounded border border-red-200 bg-red-50 px-3 py-2">
              <p class="text-xs font-semibold text-red-700 mb-0.5">Failure reason</p>
              <p class="text-xs text-red-600 break-words">{{ post.error_message }}</p>
            </div>

            <!-- Published: post URL -->
            <div v-if="post.status === 'published'" class="flex flex-wrap items-center gap-3 text-xs">
              <span class="text-slate-500">Published {{ formatScheduledAt(post.published_at) }}</span>
              <a
                v-if="post.platform_post_url"
                :href="post.platform_post_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-blue-600 hover:underline"
              >View post →</a>
            </div>

            <!-- Actions -->
            <div v-if="post.status === 'failed'" class="flex items-center gap-2 pt-1">
              <AppButton
                size="sm"
                variant="primary"
                :loading="retrying[post.id]"
                @click="retry(post)"
              >
                Retry now
              </AppButton>
              <span class="text-xs text-slate-400">Will attempt within 1 minute</span>
            </div>

          </div>
        </AppCard>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppBadge, AppButton, AppCard, AppEmptyState, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { useToastStore } from '../stores/toast';
import { formatScheduledAt } from '../utils/datetime';

const toast = useToastStore();
const queue = ref([]);
const loading = ref(true);
const error = ref(null);
const retrying = reactive({});
let pollTimer = null;

const counts = computed(() => ({
  scheduled: queue.value.filter((p) => p.status === 'scheduled').length,
  failed: queue.value.filter((p) => p.status === 'failed').length,
  published: queue.value.filter((p) => p.status === 'published').length,
}));

function statusLabel(post) {
  if (post.status === 'failed') return 'Failed';
  if (post.status === 'published') return 'Published';
  if (post.status === 'scheduled' && post.retry_count > 0) return 'Retrying';
  return 'Scheduled';
}

function statusBadgeVariant(post) {
  if (post.status === 'failed') return 'danger';
  if (post.status === 'published') return 'success';
  if (post.status === 'scheduled' && post.retry_count > 0) return 'warning';
  return 'info';
}

async function load() {
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
}

async function retry(post) {
  retrying[post.id] = true;
  try {
    const { data } = await axios.post(`/api/schedule/${post.id}/retry`);
    const updated = data.data || data;
    const idx = queue.value.findIndex((p) => p.id === post.id);
    if (idx !== -1) queue.value[idx] = updated;
    toast.success('Post queued for retry. It will publish within a minute.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not retry post');
  } finally {
    retrying[post.id] = false;
  }
}

onMounted(() => {
  load();
  // Auto-refresh every 30 seconds so statuses update without manual reload
  pollTimer = setInterval(load, 30_000);
});

onUnmounted(() => {
  clearInterval(pollTimer);
});
</script>
