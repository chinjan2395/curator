<template>
  <div class="notifications-center space-y-5">
    <AppPageHeader title="Notifications" subtitle="In-app alerts and preferences." icon="bell">
      <template #actions>
        <AppButton variant="secondary" size="sm" :disabled="store.loading" @click="refresh">
          <AppIcon name="sync" class="h-4 w-4" />
          <span>Refresh</span>
        </AppButton>
        <AppButton size="sm" :disabled="!store.unreadCount || store.loading" @click="markAll">
          <AppIcon name="check" class="h-4 w-4" />
          <span>Mark all read</span>
        </AppButton>
        <router-link to="/notifications/preferences" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">
          Preferences
        </router-link>
      </template>
    </AppPageHeader>

    <AppLoader v-if="store.loading && !store.items.length" />
    <AppAlert v-else-if="store.error" variant="danger">{{ store.error }}</AppAlert>

    <template v-else>
      <AppCard class="notifications-hero overflow-hidden border-slate-200/80 p-0" variant="panel">
        <div class="relative isolate overflow-hidden px-5 py-5 md:px-6 md:py-6">
          <div class="notifications-hero__glow notifications-hero__glow--one" />
          <div class="notifications-hero__glow notifications-hero__glow--two" />
          <div class="relative grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)]">
            <div class="space-y-4">
              <div class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700 backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.16)]" />
                Notification cockpit
              </div>
              <div class="space-y-2">
                <h2 class="max-w-xl text-2xl font-semibold tracking-tight text-slate-950 md:text-3xl">
                  A live signal board for publishing, AI, sync, and inbox events.
                </h2>
                <p class="max-w-2xl text-sm leading-6 text-slate-600 md:text-[15px]">
                  Each alert is styled by intent so you can scan failures, approvals, and wins at a glance.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <AppBadge variant="info">{{ store.unreadCount }} unread</AppBadge>
                <AppBadge variant="success">{{ totalCount }} total</AppBadge>
                <AppBadge variant="warning">{{ priorityCount }} priority</AppBadge>
                <AppBadge variant="purple">{{ activeLabel }}</AppBadge>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Unread</span>
                  <AppIcon name="bell" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ store.unreadCount }}</div>
                <div class="mt-1 text-xs text-slate-500">Waiting for your attention</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Visible</span>
                  <AppIcon name="grid" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ filteredNotifications.length }}</div>
                <div class="mt-1 text-xs text-slate-500">Matches the current filter</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Priority</span>
                  <AppIcon name="alert" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ priorityCount }}</div>
                <div class="mt-1 text-xs text-slate-500">Failures and sync issues</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Freshness</span>
                  <AppIcon name="clock" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-lg font-semibold text-slate-950">{{ newestLabel }}</div>
                <div class="mt-1 text-xs text-slate-500">Latest notification in the stream</div>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <div class="grid gap-4 xl:grid-cols-[minmax(0,240px)_minmax(0,1fr)]">
        <AppCard class="space-y-4 p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-semibold text-slate-950">Filters</div>
              <div class="text-xs text-slate-500">Type-driven scan</div>
            </div>
            <AppBadge variant="default">{{ visibleCategories.length }}</AppBadge>
          </div>

          <div class="space-y-2">
            <AppButton
              v-for="filter in filters"
              :key="filter.key"
              variant="ghost"
              size="sm"
              class="group flex w-full justify-between rounded-2xl border px-3 py-2.5 text-left transition-all duration-200"
              :class="activeFilter === filter.key
                ? 'border-slate-950 bg-slate-950 text-white shadow-lg shadow-slate-950/10'
                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
              @click="activeFilter = filter.key"
            >
              <span class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl" :class="filter.badgeClass">
                  <AppIcon :name="filter.icon" class="h-4 w-4" />
                </span>
                <span>
                  <span class="block text-sm font-semibold">{{ filter.label }}</span>
                  <span class="block text-xs opacity-80">{{ filter.description }}</span>
                </span>
              </span>
              <span class="text-sm font-semibold opacity-80">{{ countsByFilter[filter.key] ?? 0 }}</span>
            </AppButton>
          </div>
        </AppCard>

        <AppCard class="p-0 overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 md:px-6">
            <div>
              <div class="text-sm font-semibold text-slate-950">Activity stream</div>
              <div class="text-xs text-slate-500">
                {{ filteredNotifications.length }} item{{ filteredNotifications.length === 1 ? '' : 's' }} in view
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <AppBadge v-for="cat in visibleCategories" :key="cat.key" :variant="cat.badgeVariant">
                {{ cat.label }}
              </AppBadge>
            </div>
          </div>

          <div v-if="!filteredNotifications.length" class="px-5 py-6 md:px-6">
            <AppEmptyState
              title="No notifications in view"
              description="Try a different filter or clear the unread focus."
              icon="bell"
            >
              <div class="flex flex-wrap justify-center gap-3">
                <AppButton variant="secondary" size="sm" @click="activeFilter = 'all'">Show all</AppButton>
                <router-link to="/notifications/preferences" class="text-sm font-medium text-blue-600 hover:underline">
                  Review preferences
                </router-link>
              </div>
            </AppEmptyState>
          </div>

          <div v-else class="divide-y divide-slate-200/80">
            <article
              v-for="notification in filteredNotifications"
              :key="notification.id"
              class="group relative px-5 py-4 transition-colors md:px-6"
              :class="notification.read_at ? 'bg-white/70' : 'bg-slate-50/80'"
            >
              <div class="absolute left-0 top-4 h-[calc(100%-2rem)] w-1 rounded-r-full" :class="notificationMeta(notification).railClass" />
              <div class="flex flex-col gap-4 pl-3 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-4">
                  <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-sm ring-1 ring-inset"
                    :class="notificationMeta(notification).iconWrapClass"
                  >
                    <AppIcon :name="notificationMeta(notification).icon" class="h-5 w-5" />
                  </div>
                  <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-sm font-semibold text-slate-950">{{ notification.title }}</h3>
                      <AppBadge :variant="notification.read_at ? 'default' : 'success'">
                        {{ notification.read_at ? 'Read' : 'New' }}
                      </AppBadge>
                      <AppBadge :variant="notificationMeta(notification).badgeVariant">
                        {{ notificationMeta(notification).label }}
                      </AppBadge>
                    </div>
                    <p class="max-w-3xl text-sm leading-6 text-slate-600">
                      {{ notification.body }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                      <span class="inline-flex items-center gap-1.5">
                        <AppIcon name="clock" class="h-3.5 w-3.5" />
                        {{ formatRelative(notification.created_at) }}
                      </span>
                      <span v-if="notification.data?.workspace_name" class="inline-flex items-center gap-1.5">
                        <AppIcon name="workspaces" class="h-3.5 w-3.5" />
                        {{ notification.data.workspace_name }}
                      </span>
                      <span v-if="notification.data?.platform" class="inline-flex items-center gap-1.5">
                        <AppIcon name="megaphone" class="h-3.5 w-3.5" />
                        {{ notification.data.platform }}
                      </span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-2 self-start md:self-center">
                  <AppButton
                    v-if="!notification.read_at"
                    variant="ghost"
                    size="sm"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50"
                    @click="markRead(notification.id)"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    Mark read
                  </AppButton>
                </div>
              </div>
            </article>
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useNotificationsStore } from '../stores/notifications';
import { AppAlert, AppBadge, AppButton, AppCard, AppEmptyState, AppIcon, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';

const store = useNotificationsStore();
const activeFilter = ref('all');

const filters = [
  { key: 'all', label: 'All alerts', description: 'Everything in one place', icon: 'bell', badgeClass: 'bg-slate-100 text-slate-700', category: 'all' },
  { key: 'unread', label: 'Unread', description: 'Needs attention', icon: 'sparkles', badgeClass: 'bg-emerald-100 text-emerald-700', category: 'unread' },
  { key: 'success', label: 'Success', description: 'Published or completed', icon: 'check', badgeClass: 'bg-emerald-100 text-emerald-700', category: 'success' },
  { key: 'warning', label: 'Warnings', description: 'Needs follow-up', icon: 'alert', badgeClass: 'bg-amber-100 text-amber-700', category: 'warning' },
  { key: 'sync', label: 'Sync', description: 'Imports and webhooks', icon: 'sync', badgeClass: 'bg-sky-100 text-sky-700', category: 'sync' },
  { key: 'ai', label: 'AI', description: 'Generation and scoring', icon: 'sparkles', badgeClass: 'bg-violet-100 text-violet-700', category: 'ai' },
];

const categoryMap = {
  success: { label: 'Success', badgeVariant: 'success', icon: 'check', iconWrapClass: 'bg-emerald-50 text-emerald-700 ring-emerald-100', railClass: 'bg-emerald-400' },
  warning: { label: 'Warning', badgeVariant: 'warning', icon: 'alert', iconWrapClass: 'bg-amber-50 text-amber-700 ring-amber-100', railClass: 'bg-amber-400' },
  sync: { label: 'Sync', badgeVariant: 'info', icon: 'sync', iconWrapClass: 'bg-sky-50 text-sky-700 ring-sky-100', railClass: 'bg-sky-400' },
  ai: { label: 'AI', badgeVariant: 'purple', icon: 'sparkles', iconWrapClass: 'bg-violet-50 text-violet-700 ring-violet-100', railClass: 'bg-violet-400' },
  default: { label: 'Update', badgeVariant: 'default', icon: 'bell', iconWrapClass: 'bg-slate-100 text-slate-700 ring-slate-200', railClass: 'bg-slate-400' },
};

function inferCategory(notification) {
  const text = `${notification.type || ''} ${notification.title || ''} ${notification.body || ''}`.toLowerCase();
  if (/(fail|error|warning|retry|blocked|sync_failed)/.test(text)) return 'warning';
  if (/(generated|approved|published|complete|success|ready|done)/.test(text)) return 'success';
  if (/(sync|ingest|import|webhook|feed)/.test(text)) return 'sync';
  if (/(ai|llm|caption|variant|score|draft|prompt|image)/.test(text)) return 'ai';
  return 'default';
}

function notificationMeta(notification) {
  const category = inferCategory(notification);
  return categoryMap[category] || categoryMap.default;
}

const groupedCategories = computed(() => {
  const counts = { all: store.items.length, unread: 0, success: 0, warning: 0, sync: 0, ai: 0 };
  for (const notification of store.items) {
    const category = inferCategory(notification);
    if (!notification.read_at) counts.unread += 1;
    if (counts[category] !== undefined) counts[category] += 1;
  }
  return counts;
});

const countsByFilter = computed(() => groupedCategories.value);

const visibleCategories = computed(() => {
  const active = filters.find((filter) => filter.key === activeFilter.value);
  if (!active || active.category === 'all') {
    return filters;
  }
  if (active.category === 'unread') {
    return filters.filter((filter) => ['all', 'unread'].includes(filter.key));
  }
  return filters.filter((filter) => ['all', active.category].includes(filter.category));
});

const filteredNotifications = computed(() => {
  if (activeFilter.value === 'all') return store.items;
  if (activeFilter.value === 'unread') return store.items.filter((notification) => !notification.read_at);
  return store.items.filter((notification) => inferCategory(notification) === activeFilter.value);
});

const totalCount = computed(() => store.items.length);
const priorityCount = computed(() => store.items.filter((notification) => inferCategory(notification) === 'warning').length);
const newestLabel = computed(() => formatRelative(store.items[0]?.created_at));
const activeLabel = computed(() => filters.find((filter) => filter.key === activeFilter.value)?.label || 'All alerts');

async function refresh() {
  await store.fetchAll();
}

async function markAll() {
  await store.markAllRead();
}

function markRead(id) {
  const notification = store.items.find((item) => item.id === id);
  if (!notification) return;
  notification.read_at = new Date().toISOString();
  if (store.unreadCount > 0) {
    store.unreadCount -= 1;
  }
}

function formatRelative(value) {
  if (!value) return 'Just now';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Just now';

  const diffMs = date.getTime() - Date.now();
  const absSeconds = Math.round(Math.abs(diffMs) / 1000);
  const units = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
  ];

  for (const [unit, seconds] of units) {
    if (absSeconds >= seconds) {
      const count = Math.max(1, Math.round(absSeconds / seconds));
      return `${count} ${unit}${count === 1 ? '' : 's'} ${diffMs > 0 ? 'from now' : 'ago'}`;
    }
  }

  return diffMs > 0 ? 'Soon' : 'Just now';
}

onMounted(() => store.fetchAll());
</script>

<style scoped>
.notifications-center {
  position: relative;
}

.notifications-hero {
  background:
    radial-gradient(circle at top left, rgba(14, 165, 233, 0.14), transparent 34%),
    radial-gradient(circle at top right, rgba(168, 85, 247, 0.12), transparent 30%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.92));
}

.notifications-hero__glow {
  position: absolute;
  border-radius: 9999px;
  filter: blur(42px);
  opacity: 0.8;
  pointer-events: none;
}

.notifications-hero__glow--one {
  top: -1.5rem;
  right: 8%;
  width: 11rem;
  height: 11rem;
  background: rgba(56, 189, 248, 0.18);
}

.notifications-hero__glow--two {
  bottom: -2rem;
  left: 28%;
  width: 14rem;
  height: 14rem;
  background: rgba(139, 92, 246, 0.14);
}
</style>
