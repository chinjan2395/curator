<script setup>
import { computed } from 'vue';
import { AppBadge, AppButton, AppCard, AppIcon, AppText } from '../ui';

/**
 * Publish-state summary for the top of the Publish page.
 *
 * Replaces the prose sentence + flat button row that made it impossible to tell
 * at a glance whether the live embed was current: how many posts are live, how
 * many are approved but still waiting, when the last publish ran, and the embed
 * key — with the publish action labelled by the work it will actually do.
 */
const props = defineProps({
  stats: { type: Object, default: null },
  publishing: { type: Boolean, default: false },
  embedKey: { type: String, default: '' },
});

defineEmits(['publish', 'get-code', 'test-embed']);

const publishedCount = computed(() => Number(props.stats?.published || 0));

const approvedCount = computed(() => Number(props.stats?.approved || 0));

/** Approved in Curate but without a `published_at` yet — the actual publish backlog. */
const unpublishedCount = computed(() => Math.max(0, approvedCount.value - publishedCount.value));

const isLive = computed(() => publishedCount.value > 0 && !!props.embedKey);

const publishLabel = computed(() => {
  if (props.publishing) return 'Publishing…';
  if (!unpublishedCount.value) return 'Publish changes';
  return `Publish ${unpublishedCount.value} change${unpublishedCount.value === 1 ? '' : 's'}`;
});

const lastPublishedLabel = computed(() => {
  const iso = props.stats?.last_published_at;
  if (!iso) return 'Never';
  const then = new Date(iso).getTime();
  if (!Number.isFinite(then)) return 'Never';
  const mins = Math.floor((Date.now() - then) / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  if (days < 30) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
});

const maskedKey = computed(() => {
  const key = String(props.embedKey || '');
  if (!key) return 'Not published yet';
  if (key.length <= 12) return key;
  return `${key.slice(0, 6)}…${key.slice(-4)}`;
});
</script>

<template>
  <AppCard padding="none" class="px-4 py-3">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
      <div class="pr-5 border-r border-slate-200">
        <AppBadge :variant="isLive ? 'success' : 'default'">
          <span
            class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"
            :class="isLive ? 'publish-status-dot' : ''"
            aria-hidden="true"
          />
          {{ isLive ? 'Live' : 'Not live' }}
        </AppBadge>
      </div>

      <dl class="flex flex-wrap items-start gap-x-7 gap-y-3">
        <div>
          <dt class="publish-status-key">Published</dt>
          <dd class="publish-status-value">{{ publishedCount }}</dd>
        </div>
        <div>
          <dt class="publish-status-key">Approved, not live</dt>
          <dd class="publish-status-value" :class="unpublishedCount ? 'text-amber-700' : ''">
            {{ unpublishedCount }}
          </dd>
        </div>
        <div>
          <dt class="publish-status-key">Last publish</dt>
          <dd class="publish-status-value publish-status-value--text">{{ lastPublishedLabel }}</dd>
        </div>
        <div>
          <dt class="publish-status-key">Embed key</dt>
          <dd class="publish-status-value publish-status-value--code">{{ maskedKey }}</dd>
        </div>
      </dl>

      <div class="ml-auto flex flex-wrap items-center gap-2">
        <AppButton variant="secondary" size="sm" @click="$emit('get-code')">
          <AppIcon name="copy" class="w-3.5 h-3.5" />
          Get code
        </AppButton>
        <AppButton
          variant="secondary"
          size="sm"
          :disabled="!embedKey"
          title="Test embed in iframe"
          @click="$emit('test-embed')"
        >
          <AppIcon name="eye" class="w-3.5 h-3.5" />
          Test embed
        </AppButton>
        <AppButton :disabled="publishing" :loading="publishing" @click="$emit('publish')">
          <AppIcon v-if="!publishing" name="rocket" class="w-4 h-4" />
          {{ publishLabel }}
        </AppButton>
      </div>
    </div>

    <AppText v-if="unpublishedCount" size="xs" muted class="mt-2">
      {{ unpublishedCount }} approved post{{ unpublishedCount === 1 ? '' : 's' }} will go live in your embed
      when you publish.
    </AppText>
  </AppCard>
</template>

<style scoped>
.publish-status-key {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgb(148 163 184);
}

.publish-status-value {
  margin-top: 0.1rem;
  font-size: 1.0625rem;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.01em;
  color: rgb(30 41 59);
  font-variant-numeric: tabular-nums;
}

.publish-status-value--text {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgb(71 85 105);
  padding-top: 0.15rem;
}

.publish-status-value--code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgb(71 85 105);
  padding-top: 0.15rem;
}

.publish-status-dot {
  animation: publish-status-pulse 2.4s ease-in-out infinite;
}

@keyframes publish-status-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.35;
  }
}

@media (prefers-reduced-motion: reduce) {
  .publish-status-dot {
    animation: none;
  }
}
</style>
