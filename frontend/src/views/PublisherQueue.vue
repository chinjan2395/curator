<template>
  <div class="space-y-4">
    <AppPageHeader title="Publisher queue" subtitle="Track scheduled and published native posts." icon="send">
      <template #actions>
        <AppButton size="sm" variant="secondary" :loading="loading && !!queue.length" @click="load">
          Refresh
        </AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="publish" />

    <AppCard v-if="queuePlatformList.length" class="p-4">
      <PlatformPublishGuide
        :platforms="queuePlatformList"
        variant="compact"
        title="Platform content types in your queue"
        subtitle="Aa = text · IMG = image · VID = video · CAR = carousel · LINK = article"
      />
    </AppCard>

    <div v-if="loading && !queue.length" class="space-y-3">
      <AppCard class="p-4"><AppSkeleton variant="line" :lines="2" /></AppCard>
      <AppCard v-for="n in 4" :key="n" class="p-4 space-y-3">
        <AppSkeleton variant="line" :lines="2" />
        <AppSkeleton variant="block" />
      </AppCard>
    </div>
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <!-- Empty state -->
      <AppCard v-if="!queue.length" class="p-6">
        <AppEmptyState
          title="Queue is empty"
          description="Scheduled posts appear here once added from the calendar."
          icon="send"
        >
          <router-link to="/calendar">
            <AppButton size="sm" variant="secondary">Go to calendar</AppButton>
          </router-link>
        </AppEmptyState>
      </AppCard>

      <template v-else>
        <!-- Summary bar -->
        <div class="pq-stats">
          <div class="pq-stat">
            <span class="pq-stat__count pq-stat__count--info">{{ counts.scheduled }}</span>
            <span class="pq-stat__label">Scheduled</span>
          </div>
          <div class="pq-stat-divider" />
          <div class="pq-stat">
            <span class="pq-stat__count pq-stat__count--warning">{{ counts.retrying }}</span>
            <span class="pq-stat__label">Retrying</span>
          </div>
          <div class="pq-stat-divider" />
          <div class="pq-stat">
            <span class="pq-stat__count pq-stat__count--danger">{{ counts.failed }}</span>
            <span class="pq-stat__label">Failed</span>
          </div>
          <div class="pq-stat-divider" />
          <div class="pq-stat">
            <span class="pq-stat__count pq-stat__count--success">{{ counts.published }}</span>
            <span class="pq-stat__label">Published</span>
          </div>
        </div>

        <!-- Post cards -->
        <div class="space-y-2">
          <div
            v-for="post in queue"
            :key="post.id"
            class="pq-card"
            :class="`pq-card--${statusKey(post)}`"
          >
            <!-- Main grid: content | actions -->
            <div class="pq-card__body">
              <!-- Left: content -->
              <div class="pq-card__main">
                <!-- Row 1: platform + account + status -->
                <div class="pq-card__header">
                  <div class="flex flex-wrap items-center gap-2">
                    <SocialPlatformLabel
                      v-if="post.social_credential?.provider"
                      :type="post.social_credential.provider"
                      variant="pill"
                      size="sm"
                      :show-label="true"
                      :suffix="post.social_credential.account_label ? ` · ${post.social_credential.account_label}` : ''"
                    />
                    <span v-else class="pq-card__no-account">No account linked</span>
                  </div>
                  <span class="pq-status-badge" :class="`pq-status-badge--${statusKey(post)}`">
                    {{ statusLabel(post) }}
                  </span>
                </div>

                <!-- Caption preview -->
                <p v-if="post.content_package?.caption" class="pq-card__caption">
                  {{ post.content_package.caption }}
                </p>
                <p v-else class="pq-card__no-caption">No content package attached</p>

                <!-- Meta row: time info -->
                <div class="pq-card__meta">
                  <span v-if="post.status === 'published' && post.published_at">
                    <span class="pq-meta-icon">✓</span>
                    Published {{ formatScheduledAt(post.published_at) }}
                  </span>
                  <span v-else-if="post.status === 'scheduled' && post.retry_count > 0">
                    <span class="pq-meta-icon">↻</span>
                    Next attempt {{ formatScheduledAt(post.scheduled_at) }}
                  </span>
                  <span v-else>
                    <span class="pq-meta-icon">⏰</span>
                    Scheduled for {{ formatScheduledAt(post.scheduled_at) }}
                  </span>
                  <span class="pq-meta-id">#{{ post.id }}</span>
                </div>

                <!-- Attempt counter -->
                <div v-if="post.retry_count > 0" class="pq-attempt">
                  <span class="pq-attempt__label">Attempt {{ post.retry_count }} of 3</span>
                  <span v-if="post.status === 'failed'" class="pq-attempt__exhausted">— no more automatic retries</span>
                  <span v-else class="pq-attempt__next">— retrying automatically</span>
                </div>

                <!-- Error reason -->
                <div v-if="post.error_message" class="pq-error">
                  <span class="pq-error__label">Failure reason</span>
                  <span class="pq-error__message">{{ post.error_message }}</span>
                </div>
              </div>

              <!-- Right: actions -->
              <div class="pq-card__actions">
                <!-- Published: view link -->
                <a
                  v-if="post.status === 'published' && post.platform_post_url"
                  :href="post.platform_post_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="pq-action-link"
                >
                  View post
                  <svg class="pq-action-link__icon" viewBox="0 0 16 16" fill="none">
                    <path d="M6 3H3a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-3M9 2h5m0 0v5m0-5L7 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </a>

                <!-- Failed: retry -->
                <AppButton
                  v-if="post.status === 'failed'"
                  size="sm"
                  variant="primary"
                  :loading="retrying[post.id]"
                  @click="retry(post)"
                >
                  Retry now
                </AppButton>

                <!-- Scheduled: cancel -->
                <button
                  v-if="post.status === 'scheduled'"
                  type="button"
                  class="pq-cancel-btn"
                  :disabled="cancelling[post.id]"
                  @click="cancel(post)"
                >
                  {{ cancelling[post.id] ? 'Cancelling…' : 'Cancel' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppSkeleton } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import PlatformPublishGuide from '../components/PlatformPublishGuide.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { useToastStore } from '../stores/toast';
import { useRealtimeWithFallback } from '../composables/useRealtimeWithFallback';
import { formatScheduledAt } from '../utils/datetime';

const toast = useToastStore();
const queue = ref([]);
const loading = ref(true);
const error = ref(null);
const retrying = reactive({});
const cancelling = reactive({});

/** 'retrying' = scheduled but has already failed at least once */
function statusKey(post) {
  if (post.status === 'failed') return 'failed';
  if (post.status === 'published') return 'published';
  if (post.status === 'scheduled' && post.retry_count > 0) return 'retrying';
  return 'scheduled';
}

function statusLabel(post) {
  const map = { failed: 'Failed', published: 'Published', retrying: 'Retrying', scheduled: 'Scheduled' };
  return map[statusKey(post)];
}

const counts = computed(() => ({
  scheduled: queue.value.filter((p) => statusKey(p) === 'scheduled').length,
  retrying: queue.value.filter((p) => statusKey(p) === 'retrying').length,
  failed: queue.value.filter((p) => p.status === 'failed').length,
  published: queue.value.filter((p) => p.status === 'published').length,
}));

const queuePlatformList = computed(() => [
  ...new Set(
    queue.value
      .map((p) => p.social_credential?.provider)
      .filter(Boolean),
  ),
]);

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

async function cancel(post) {
  cancelling[post.id] = true;
  try {
    await axios.delete(`/api/schedule/${post.id}`);
    queue.value = queue.value.filter((p) => p.id !== post.id);
    toast.success('Schedule cancelled.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not cancel post');
  } finally {
    cancelling[post.id] = false;
  }
}

function applyQueuePost(post) {
  if (!post?.id) return;
  if (post.status === 'cancelled') {
    queue.value = queue.value.filter((p) => p.id !== post.id);
    return;
  }
  const idx = queue.value.findIndex((p) => p.id === post.id);
  if (idx !== -1) {
    queue.value[idx] = post;
  } else if (['scheduled', 'failed', 'published'].includes(post.status)) {
    queue.value = [post, ...queue.value];
  }
}

useRealtimeWithFallback({
  event: 'scheduledPost',
  onEvent: ({ post }) => applyQueuePost(post),
  poll: () => load(),
});

onMounted(load);
</script>

<style scoped>
/* ── Stats bar ── */
.pq-stats {
  display: flex;
  align-items: center;
  gap: 0;
  padding: 0.65rem 1rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
}

.pq-stat {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  padding: 0 1rem;
  flex: 1;
  justify-content: center;
}

.pq-stat-divider {
  width: 1px;
  height: 1.5rem;
  background: #e6ebf2;
  flex-shrink: 0;
}

.pq-stat__count {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1;
}

.pq-stat__count--info    { color: #0369a1; }
.pq-stat__count--warning { color: #b45309; }
.pq-stat__count--danger  { color: #dc2626; }
.pq-stat__count--success { color: #059669; }

.pq-stat__label {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

/* ── Post card ── */
.pq-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  overflow: hidden;
  border-left-width: 3px;
  transition: box-shadow 0.15s ease;
}

.pq-card:hover {
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.07);
}

.pq-card--scheduled { border-left-color: #38bdf8; }
.pq-card--retrying  { border-left-color: #f59e0b; }
.pq-card--failed    { border-left-color: #ef4444; }
.pq-card--published { border-left-color: #10b981; }

.pq-card__body {
  display: grid;
  gap: 0.75rem;
  padding: 0.85rem 0.85rem 0.85rem 1rem;
}

@media (min-width: 640px) {
  .pq-card__body {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}

/* ── Main content column ── */
.pq-card__main {
  display: grid;
  gap: 0.5rem;
  min-width: 0;
}

.pq-card__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.pq-card__no-account {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}

.pq-card__caption {
  font-size: 0.82rem;
  line-height: 1.5;
  color: #475569;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pq-card__no-caption {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}

.pq-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: #64748b;
}

.pq-meta-icon {
  margin-right: 0.25rem;
}

.pq-meta-id {
  color: #94a3b8;
  font-size: 0.7rem;
  font-variant-numeric: tabular-nums;
}

/* ── Attempt counter ── */
.pq-attempt {
  font-size: 0.72rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem 0.6rem;
  border-radius: 0.45rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  width: fit-content;
}

.pq-attempt__label {
  font-weight: 600;
  color: #92400e;
}

.pq-attempt__exhausted {
  color: #b45309;
}

.pq-attempt__next {
  color: #78350f;
}

/* ── Error box ── */
.pq-error {
  display: grid;
  gap: 0.2rem;
  padding: 0.55rem 0.75rem;
  border-radius: 0.55rem;
  border: 1px solid #fecaca;
  background: #fff5f5;
}

.pq-error__label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #b91c1c;
}

.pq-error__message {
  font-size: 0.78rem;
  color: #dc2626;
  word-break: break-word;
  line-height: 1.4;
}

/* ── Status badge ── */
.pq-status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  white-space: nowrap;
  flex-shrink: 0;
}

.pq-status-badge--scheduled {
  background: #e0f2fe;
  color: #0369a1;
}

.pq-status-badge--retrying {
  background: #fef3c7;
  color: #92400e;
}

.pq-status-badge--failed {
  background: #fee2e2;
  color: #b91c1c;
}

.pq-status-badge--published {
  background: #d1fae5;
  color: #065f46;
}

/* ── Actions column ── */
.pq-card__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
  padding-top: 0.1rem;
  flex-shrink: 0;
}

.pq-action-link {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.4rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid #d1fae5;
  background: #ecfdf5;
  font-size: 0.75rem;
  font-weight: 600;
  color: #065f46;
  text-decoration: none;
  transition: background 0.15s ease, border-color 0.15s ease;
  white-space: nowrap;
}

.pq-action-link:hover {
  background: #d1fae5;
  border-color: #6ee7b7;
}

.pq-action-link__icon {
  width: 0.85rem;
  height: 0.85rem;
  flex-shrink: 0;
}

.pq-cancel-btn {
  padding: 0.4rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  white-space: nowrap;
}

.pq-cancel-btn:hover:not(:disabled) {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #b91c1c;
}

.pq-cancel-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
