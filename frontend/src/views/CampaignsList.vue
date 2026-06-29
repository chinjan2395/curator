<template>
  <div class="cl-campaigns-layout">
    <!-- Main content -->
    <div class="cl-campaigns-main">
      <AppPageHeader
        title="Campaigns"
        subtitle="AI content studio — create and generate multi-platform packages."
        icon="megaphone"
      >
        <template #actions>
          <AppButton variant="primary" @click="$router.push('/campaigns/new')">
            <AppIcon name="add" class="w-3.5 h-3.5 mr-1.5" />
            New campaign
          </AppButton>
        </template>
      </AppPageHeader>

      <CapabilityBanner context="ai" />

      <AppAlert v-if="store.error && !store.loading" variant="danger">{{ store.error }}</AppAlert>

      <AppLoader v-if="store.loading" />

      <template v-else-if="store.list.length">
        <!-- Platform tabs -->
        <div class="cl-tabs">
          <button
            v-for="tab in platformTabs"
            :key="tab.key"
            class="cl-tab"
            :class="{ 'cl-tab--active': activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            <SocialIcon v-if="tab.platform" :type="tab.platform" class="w-3.5 h-3.5" />
            {{ tab.label }}
            <span class="cl-tab__count">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Campaign cards -->
        <div class="cl-campaigns-list">
          <div
            v-for="c in filteredCampaigns"
            :key="c.id"
            class="cl-campaign-card"
            :class="`cl-campaign-card--${statusColor(c.status)}`"
            @click="$router.push(`/campaigns/${c.id}`)"
          >
            <div class="cl-campaign-card__inner">
              <!-- Left: icon -->
              <div class="cl-campaign-icon" :class="`cl-campaign-icon--${statusColor(c.status)}`">
                <AppIcon name="megaphone" class="w-4 h-4" />
              </div>

              <!-- Center: info -->
              <div class="cl-campaign-card__body">
                <div class="cl-campaign-card__meta">
                  <span class="cl-campaign-card__name">{{ c.name }}</span>
                  <AppBadge :variant="statusVariant(c.status)">
                    <AppIcon :name="statusIcon(c.status)" class="w-3 h-3 mr-1" />
                    {{ formatStatus(c.status) }}
                  </AppBadge>
                </div>

                <div v-if="c.platforms?.length" class="cl-campaign-card__platforms">
                  <SocialPlatformLabel
                    v-for="p in c.platforms.slice(0, 4)"
                    :key="p"
                    :type="p"
                    variant="pill"
                    size="sm"
                  />
                  <span v-if="c.platforms.length > 4" class="cl-more-pill">+{{ c.platforms.length - 4 }}</span>
                </div>

                <div class="cl-campaign-card__stats">
                  <span class="cl-stat">
                    <AppIcon name="feeds" class="w-3.5 h-3.5" />
                    {{ c.content_packages_count || 0 }} package{{ (c.content_packages_count || 0) === 1 ? '' : 's' }}
                  </span>
                  <span v-if="c.tone" class="cl-stat">
                    <AppIcon name="edit" class="w-3.5 h-3.5" />
                    {{ c.tone }}
                  </span>
                </div>
              </div>

              <!-- Right: actions -->
              <div class="cl-campaign-card__actions" @click.stop>
                <AppButton variant="ghost" size="sm" @click="$router.push(`/campaigns/${c.id}`)">
                  <AppIcon name="eye" class="w-3.5 h-3.5 mr-1" />
                  View
                </AppButton>
                <AppButton
                  v-if="c.status === 'active' || c.status === 'draft'"
                  variant="primary"
                  size="sm"
                  @click="$router.push(`/campaigns/${c.id}`)"
                >
                  <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1" />
                  Generate
                </AppButton>
              </div>
            </div>
          </div>
        </div>

        <AppEmptyState
          v-if="!filteredCampaigns.length"
          title="No campaigns match this filter"
          description="Try a different platform tab or create a new campaign."
          icon="megaphone"
        />
      </template>

      <AppEmptyState
        v-else-if="!store.loading"
        title="No campaigns yet"
        description="Create a campaign, then generate platform-specific content packages with AI."
        icon="megaphone"
      >
        <AppButton variant="primary" @click="$router.push('/campaigns/new')">
          <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
          New campaign
        </AppButton>
      </AppEmptyState>
    </div>

    <!-- Sidebar -->
    <aside class="cl-campaigns-sidebar">
      <!-- Overview stats -->
      <div class="cl-sidebar-section">
        <p class="cl-sidebar-heading">Campaign overview</p>
        <div class="cl-stats-grid">
          <div class="cl-stat-tile">
            <span class="cl-stat-tile__value cl-stat-tile__value--blue">{{ stats.total }}</span>
            <span class="cl-stat-tile__label">Total</span>
          </div>
          <div class="cl-stat-tile">
            <span class="cl-stat-tile__value cl-stat-tile__value--emerald">{{ stats.active }}</span>
            <span class="cl-stat-tile__label">Active</span>
          </div>
          <div class="cl-stat-tile">
            <span class="cl-stat-tile__value cl-stat-tile__value--amber">{{ stats.draft }}</span>
            <span class="cl-stat-tile__label">Drafts</span>
          </div>
          <div class="cl-stat-tile">
            <span class="cl-stat-tile__value cl-stat-tile__value--violet">{{ stats.completed }}</span>
            <span class="cl-stat-tile__label">Completed</span>
          </div>
        </div>
      </div>

      <!-- Status filter -->
      <div class="cl-sidebar-section">
        <p class="cl-sidebar-heading">Status filter</p>
        <div class="cl-filter-list">
          <button
            v-for="sf in statusFilters"
            :key="sf.key"
            class="cl-filter-item"
            :class="{ 'cl-filter-item--active': activeStatusFilter === sf.key }"
            @click="activeStatusFilter = sf.key"
          >
            <span class="cl-filter-dot" :class="`cl-filter-dot--${sf.color}`" />
            {{ sf.label }}
          </button>
        </div>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCampaignsStore } from '../stores/campaigns';
import { AppAlert, AppBadge, AppButton, AppEmptyState, AppIcon, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import SocialIcon from '../components/SocialIcon.vue';

const store = useCampaignsStore();
onMounted(() => store.fetchAll());

const activeTab = ref('all');
const activeStatusFilter = ref('all');

const allPlatforms = computed(() => {
  const set = new Set();
  store.list.forEach((c) => c.platforms?.forEach((p) => set.add(p)));
  return [...set];
});

const platformTabs = computed(() => {
  const tabs = [{ key: 'all', label: 'All Campaigns', platform: null, count: store.list.length }];
  allPlatforms.value.forEach((p) => {
    tabs.push({
      key: p,
      label: p.charAt(0).toUpperCase() + p.slice(1),
      platform: p,
      count: store.list.filter((c) => c.platforms?.includes(p)).length,
    });
  });
  return tabs;
});

const statusFilters = [
  { key: 'all', label: 'All statuses', color: 'slate' },
  { key: 'active', label: 'Active', color: 'emerald' },
  { key: 'draft', label: 'Draft', color: 'slate' },
  { key: 'completed', label: 'Completed', color: 'blue' },
  { key: 'paused', label: 'Paused', color: 'amber' },
];

const filteredCampaigns = computed(() => {
  let list = store.list;
  if (activeTab.value !== 'all') {
    list = list.filter((c) => c.platforms?.includes(activeTab.value));
  }
  if (activeStatusFilter.value !== 'all') {
    list = list.filter((c) => c.status === activeStatusFilter.value);
  }
  return list;
});

const stats = computed(() => ({
  total: store.list.length,
  active: store.list.filter((c) => c.status === 'active').length,
  draft: store.list.filter((c) => c.status === 'draft').length,
  completed: store.list.filter((c) => c.status === 'completed').length,
}));

function statusColor(status) {
  if (status === 'active') return 'green';
  if (status === 'completed') return 'blue';
  if (status === 'paused') return 'amber';
  return 'violet';
}

function statusVariant(status) {
  if (status === 'active') return 'success';
  if (status === 'completed') return 'info';
  if (status === 'paused') return 'warning';
  return 'default';
}

function statusIcon(status) {
  if (status === 'active') return 'check';
  if (status === 'completed') return 'rocket';
  if (status === 'paused') return 'activity';
  return 'edit';
}

function formatStatus(status) {
  const labels = { draft: 'Draft', active: 'Active', completed: 'Completed', paused: 'Paused', generating: 'Generating' };
  return labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Draft');
}
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.cl-campaigns-layout {
  display: grid;
  grid-template-columns: 1fr 15rem;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 900px) {
  .cl-campaigns-layout { grid-template-columns: 1fr; }
  .cl-campaigns-sidebar { order: -1; }
}

.cl-campaigns-main { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }

/* ── Platform tabs ───────────────────────────────────────── */
.cl-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  border-bottom: 1px solid #e6ebf2;
  padding-bottom: 0;
}

.cl-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem 0.875rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #64748b;
  border: none;
  border-bottom: 2px solid transparent;
  background: none;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

.cl-tab:hover { color: #334155; }

.cl-tab--active {
  color: #6366f1;
  border-bottom-color: #6366f1;
}

.cl-tab__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: #f1f5f9;
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
}

.cl-tab--active .cl-tab__count {
  background: #ede9fe;
  color: #6366f1;
}

/* ── Campaign list ───────────────────────────────────────── */
.cl-campaigns-list { display: flex; flex-direction: column; gap: 0.625rem; }

.cl-campaign-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
  border-left-width: 3px;
}

.cl-campaign-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);
  transform: translateY(-1px);
}

.cl-campaign-card--green  { border-left-color: #10b981; }
.cl-campaign-card--blue   { border-left-color: #3b82f6; }
.cl-campaign-card--amber  { border-left-color: #f59e0b; }
.cl-campaign-card--violet { border-left-color: #8b5cf6; }

.cl-campaign-card__inner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
}

/* ── Campaign icon ───────────────────────────────────────── */
.cl-campaign-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.625rem;
  flex-shrink: 0;
}

.cl-campaign-icon--green  { background: #ecfdf5; color: #10b981; }
.cl-campaign-icon--blue   { background: #eff6ff; color: #3b82f6; }
.cl-campaign-icon--amber  { background: #fffbeb; color: #f59e0b; }
.cl-campaign-icon--violet { background: #f5f3ff; color: #8b5cf6; }

/* ── Card body ───────────────────────────────────────────── */
.cl-campaign-card__body { flex: 1; min-width: 0; }

.cl-campaign-card__meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.375rem;
}

.cl-campaign-card__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cl-campaign-card__platforms {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-bottom: 0.375rem;
}

.cl-campaign-card__stats {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.cl-stat {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #94a3b8;
}

.cl-more-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  font-size: 0.7rem;
  font-weight: 500;
  color: #64748b;
}

/* ── Card actions ────────────────────────────────────────── */
.cl-campaign-card__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.cl-campaigns-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.cl-sidebar-section {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 1rem;
}

.cl-sidebar-heading {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

/* ── Stat tiles ──────────────────────────────────────────── */
.cl-stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.cl-stat-tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 0.625rem 0.75rem;
  border-radius: 0.625rem;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
}

.cl-stat-tile__value {
  font-size: 1.375rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.2rem;
}

.cl-stat-tile__value--blue   { color: #3b82f6; }
.cl-stat-tile__value--emerald { color: #10b981; }
.cl-stat-tile__value--amber  { color: #f59e0b; }
.cl-stat-tile__value--violet { color: #8b5cf6; }

.cl-stat-tile__label {
  font-size: 0.7rem;
  font-weight: 500;
  color: #94a3b8;
}

/* ── Filter list ─────────────────────────────────────────── */
.cl-filter-list { display: flex; flex-direction: column; gap: 0.125rem; }

.cl-filter-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border-radius: 0.5rem;
  border: none;
  background: none;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s, color 0.12s;
}

.cl-filter-item:hover { background: #f8fafc; color: #1e293b; }

.cl-filter-item--active {
  background: #ede9fe;
  color: #6366f1;
}

.cl-filter-dot {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  flex-shrink: 0;
}

.cl-filter-dot--emerald { background: #10b981; }
.cl-filter-dot--blue    { background: #3b82f6; }
.cl-filter-dot--amber   { background: #f59e0b; }
.cl-filter-dot--slate   { background: #94a3b8; }
</style>
