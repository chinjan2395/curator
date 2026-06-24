<template>
  <div class="space-y-4">
    <AppPageHeader
      title="System overview"
      subtitle="Platform health and queue status."
      icon="settings"
    />

    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <AppCard class="system-overview-hero overflow-hidden border-slate-200/80 p-0" variant="panel">
        <div class="relative isolate overflow-hidden px-5 py-5 md:px-6 md:py-6">
          <div class="system-overview-hero__glow system-overview-hero__glow--one" />
          <div class="system-overview-hero__glow system-overview-hero__glow--two" />

          <div class="relative grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.95fr)]">
            <div class="space-y-4">
              <div class="inline-flex items-center gap-2 rounded-full border border-white/50 bg-white/60 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700 backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-blue-500 shadow-[0_0_0_4px_rgba(59,130,246,0.16)]" />
                Operations cockpit
              </div>

              <div class="space-y-2">
                <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-slate-950 md:text-3xl">
                  Read the platform at a glance.
                </h2>
                <p class="max-w-2xl text-sm leading-6 text-slate-600 md:text-[15px]">
                  Track queue pressure, credential health, and scheduled publishing activity without digging through raw API payloads.
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <AppBadge variant="success">{{ statusSummary.active_credentials }} active credentials</AppBadge>
                <AppBadge variant="warning">{{ statusSummary.broken_credentials }} broken credentials</AppBadge>
                <AppBadge variant="info">{{ statusSummary.queue_size }} queued jobs</AppBadge>
                <AppBadge variant="purple">{{ statusSummary.scheduled_posts }} scheduled posts</AppBadge>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="system-overview-stat-card">
                <div class="system-overview-stat-card__head">
                  <span class="system-overview-stat-card__label">Users</span>
                  <AppIcon name="users" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="system-overview-stat-card__value">{{ statusSummary.users }}</div>
                <div class="system-overview-stat-card__meta">Registered accounts</div>
              </div>
              <div class="system-overview-stat-card">
                <div class="system-overview-stat-card__head">
                  <span class="system-overview-stat-card__label">Campaigns</span>
                  <AppIcon name="sparkles" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="system-overview-stat-card__value">{{ statusSummary.campaigns }}</div>
                <div class="system-overview-stat-card__meta">Live campaign records</div>
              </div>
              <div class="system-overview-stat-card">
                <div class="system-overview-stat-card__head">
                  <span class="system-overview-stat-card__label">Failed</span>
                  <AppIcon name="alert" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="system-overview-stat-card__value">{{ statusSummary.failed_posts }}</div>
                <div class="system-overview-stat-card__meta">Publishing failures</div>
              </div>
              <div class="system-overview-stat-card">
                <div class="system-overview-stat-card__head">
                  <span class="system-overview-stat-card__label">Queue</span>
                  <AppIcon name="sync" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="system-overview-stat-card__value">{{ statusSummary.queue_size }}</div>
                <div class="system-overview-stat-card__meta">Jobs waiting to process</div>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="space-y-4">
          <AppCard class="p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <AppTitle size="sm">Core counters</AppTitle>
                <p class="mt-1 text-xs text-slate-500">The high-signal system numbers driving operations.</p>
              </div>
              <AppBadge variant="default">{{ overviewCards.length }} metrics</AppBadge>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <article
                v-for="item in overviewCards"
                :key="item.key"
                class="system-overview-metric"
                :class="item.emphasis"
              >
                <div class="system-overview-metric__label">{{ item.label }}</div>
                <div class="system-overview-metric__value">{{ item.value }}</div>
                <div class="system-overview-metric__hint">{{ item.hint }}</div>
              </article>
            </div>
          </AppCard>

          <AppCard class="p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <AppTitle size="sm">Queue posture</AppTitle>
                <p class="mt-1 text-xs text-slate-500">How far the system is from a clean publish state.</p>
              </div>
              <AppBadge :variant="queueState.variant">{{ queueState.label }}</AppBadge>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
              <div class="system-overview-pill">
                <span class="system-overview-pill__label">Queue size</span>
                <span class="system-overview-pill__value">{{ statusSummary.queue_size }}</span>
              </div>
              <div class="system-overview-pill">
                <span class="system-overview-pill__label">Scheduled</span>
                <span class="system-overview-pill__value">{{ statusSummary.scheduled_posts }}</span>
              </div>
              <div class="system-overview-pill">
                <span class="system-overview-pill__label">Failed</span>
                <span class="system-overview-pill__value">{{ statusSummary.failed_posts }}</span>
              </div>
            </div>
          </AppCard>
        </div>

        <AppCard class="p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <AppTitle size="sm">Integration health</AppTitle>
              <p class="mt-1 text-xs text-slate-500">Grouped by provider and token health.</p>
            </div>
            <AppBadge variant="info">{{ healthSections.length }} providers</AppBadge>
          </div>

          <div class="mt-4 space-y-3">
            <section
              v-for="section in healthSections"
              :key="section.provider"
              class="system-overview-health"
            >
              <div class="system-overview-health__head">
                <div class="min-w-0">
                  <p class="system-overview-health__provider">{{ section.providerLabel }}</p>
                  <p class="system-overview-health__meta">{{ section.total }} credential{{ section.total !== 1 ? 's' : '' }}</p>
                </div>
                <AppBadge :variant="section.badgeVariant">{{ section.badgeLabel }}</AppBadge>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <div
                  v-for="bucket in section.buckets"
                  :key="bucket.key"
                  class="system-overview-health__bucket"
                >
                  <span class="system-overview-health__bucket-label">{{ bucket.label }}</span>
                  <span class="system-overview-health__bucket-value">{{ bucket.total }}</span>
                </div>
              </div>
            </section>

            <p v-if="!healthSections.length" class="text-xs text-slate-500">
              No integration health data was returned.
            </p>
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { AppAlert, AppBadge, AppCard, AppIcon, AppLoader, AppTitle } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';
import { useAdminSystemOverview } from '../../composables/useAdminSystemOverview';

const {
  overview,
  health,
  loading,
  error,
  fetchOverview,
} = useAdminSystemOverview();

onMounted(fetchOverview);

const statusSummary = computed(() => ({
  users: toNumber(overview.value?.users),
  campaigns: toNumber(overview.value?.campaigns),
  active_credentials: toNumber(overview.value?.active_credentials),
  broken_credentials: toNumber(overview.value?.broken_credentials),
  scheduled_posts: toNumber(overview.value?.scheduled_posts),
  failed_posts: toNumber(overview.value?.failed_posts),
  queue_size: toNumber(overview.value?.queue_size),
}));

const overviewCards = computed(() => ([
  { key: 'users', label: 'Users', value: statusSummary.value.users, hint: 'Accounts with access', emphasis: 'system-overview-metric--blue' },
  { key: 'campaigns', label: 'Campaigns', value: statusSummary.value.campaigns, hint: 'Defined campaign records', emphasis: 'system-overview-metric--indigo' },
  { key: 'active_credentials', label: 'Active credentials', value: statusSummary.value.active_credentials, hint: 'Connected accounts ready to sync', emphasis: 'system-overview-metric--emerald' },
  { key: 'broken_credentials', label: 'Broken credentials', value: statusSummary.value.broken_credentials, hint: 'Need reconnection', emphasis: 'system-overview-metric--rose' },
  { key: 'scheduled_posts', label: 'Scheduled posts', value: statusSummary.value.scheduled_posts, hint: 'Publishing queue entries', emphasis: 'system-overview-metric--amber' },
  { key: 'failed_posts', label: 'Failed posts', value: statusSummary.value.failed_posts, hint: 'Retries or review needed', emphasis: 'system-overview-metric--slate' },
  { key: 'queue_size', label: 'Queue size', value: statusSummary.value.queue_size, hint: 'Jobs waiting in Laravel queue', emphasis: 'system-overview-metric--cyan' },
]));

const queueState = computed(() => {
  if (statusSummary.value.failed_posts > 0) {
    return { label: 'Attention needed', variant: 'warning' };
  }
  if (statusSummary.value.queue_size > 0) {
    return { label: 'Busy', variant: 'info' };
  }
  return { label: 'Clear', variant: 'success' };
});

const healthSections = computed(() => {
  const rows = Array.isArray(health.value) ? health.value : [];
  const grouped = new Map();

  rows.forEach((row) => {
    const provider = String(row.provider || 'unknown');
    const tokenHealth = String(row.token_health || 'unknown');
    if (!grouped.has(provider)) grouped.set(provider, []);
    grouped.get(provider).push({ tokenHealth, total: toNumber(row.total) });
  });

  return [...grouped.entries()].map(([provider, items]) => {
    const total = items.reduce((sum, item) => sum + item.total, 0);
    const broken = items
      .filter((item) => ['expired', 'error', 'needs_reauth', 'disconnected'].includes(item.tokenHealth))
      .reduce((sum, item) => sum + item.total, 0);
    const healthy = items
      .filter((item) => item.tokenHealth === 'valid' || item.tokenHealth === 'healthy')
      .reduce((sum, item) => sum + item.total, 0);

    return {
      provider,
      providerLabel: provider.replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
      total,
      buckets: [
        { key: 'healthy', label: 'Healthy', total: healthy },
        { key: 'broken', label: 'Broken', total: broken },
        ...items
          .filter((item) => !['valid', 'healthy', 'expired', 'error', 'needs_reauth', 'disconnected'].includes(item.tokenHealth))
          .map((item) => ({ key: item.tokenHealth, label: item.tokenHealth, total: item.total })),
      ],
      badgeLabel: broken > 0 ? 'Needs review' : 'Healthy',
      badgeVariant: broken > 0 ? 'warning' : 'success',
    };
  });
});

function toNumber(value) {
  return Number.isFinite(Number(value)) ? Number(value) : 0;
}
</script>

<style scoped>
.system-overview-hero {
  position: relative;
}

.system-overview-hero__glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(20px);
  pointer-events: none;
}

.system-overview-hero__glow--one {
  top: -2rem;
  right: -1rem;
  width: 10rem;
  height: 10rem;
  background: rgba(37, 99, 235, 0.12);
}

.system-overview-hero__glow--two {
  bottom: -2rem;
  left: -1rem;
  width: 12rem;
  height: 12rem;
  background: rgba(16, 185, 129, 0.1);
}

.system-overview-stat-card {
  border: 1px solid rgba(255, 255, 255, 0.55);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.72);
  padding: 0.9rem;
  backdrop-filter: blur(10px);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}

.system-overview-stat-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.system-overview-stat-card__label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #64748b;
}

.system-overview-stat-card__value {
  margin-top: 0.7rem;
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1;
  color: #0f172a;
}

.system-overview-stat-card__meta {
  margin-top: 0.35rem;
  font-size: 0.72rem;
  color: #64748b;
}

.system-overview-metric {
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  padding: 0.9rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.system-overview-metric__label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.system-overview-metric__value {
  margin-top: 0.55rem;
  font-size: 1.7rem;
  font-weight: 800;
  line-height: 1;
  color: #0f172a;
}

.system-overview-metric__hint {
  margin-top: 0.35rem;
  font-size: 0.72rem;
  color: #64748b;
  line-height: 1.45;
}

.system-overview-metric--emerald {
  border-color: rgba(16, 185, 129, 0.18);
  background: linear-gradient(180deg, rgba(236, 253, 245, 0.95) 0%, #ffffff 100%);
}

.system-overview-metric--rose {
  border-color: rgba(244, 63, 94, 0.18);
  background: linear-gradient(180deg, rgba(255, 241, 242, 0.95) 0%, #ffffff 100%);
}

.system-overview-metric--amber {
  border-color: rgba(245, 158, 11, 0.18);
  background: linear-gradient(180deg, rgba(255, 251, 235, 0.95) 0%, #ffffff 100%);
}

.system-overview-metric--cyan {
  border-color: rgba(6, 182, 212, 0.18);
  background: linear-gradient(180deg, rgba(236, 254, 255, 0.95) 0%, #ffffff 100%);
}

.system-overview-pill {
  border: 1px solid #e2e8f0;
  border-radius: 0.9rem;
  padding: 0.8rem;
  background: #f8fafc;
}

.system-overview-pill__label {
  display: block;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.system-overview-pill__value {
  display: block;
  margin-top: 0.4rem;
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
}

.system-overview-health {
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  padding: 0.9rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.system-overview-health__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.system-overview-health__provider {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
}

.system-overview-health__meta {
  margin-top: 0.2rem;
  font-size: 0.72rem;
  color: #64748b;
}

.system-overview-health__bucket {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 999px;
  border: 1px solid #dbe5f0;
  padding: 0.35rem 0.55rem;
  background: white;
}

.system-overview-health__bucket-label {
  font-size: 0.68rem;
  color: #64748b;
}

.system-overview-health__bucket-value {
  font-size: 0.74rem;
  font-weight: 800;
  color: #0f172a;
}
</style>
