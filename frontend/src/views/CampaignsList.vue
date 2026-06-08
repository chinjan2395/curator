<template>
  <div class="space-y-4">
    <AppPageHeader title="Campaigns" subtitle="AI content studio — create and generate multi-platform packages." icon="megaphone">
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

    <AppEmptyState
      v-else-if="!store.list.length"
      title="No campaigns yet"
      description="Create a campaign, then generate platform-specific content packages with AI."
      icon="megaphone"
    >
      <AppButton variant="primary" @click="$router.push('/campaigns/new')">
        <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
        New campaign
      </AppButton>
    </AppEmptyState>

    <div v-else class="grid gap-3 md:grid-cols-2">
      <div
        v-for="c in store.list"
        :key="c.id"
        class="cl-campaign-card"
        @click="$router.push(`/campaigns/${c.id}`)"
      >
        <div class="cl-campaign-card__accent" :class="campaignAccentClass(c.status)" />

        <div class="cl-campaign-card__body">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="cl-campaign-icon">
              <AppIcon name="megaphone" class="w-4 h-4 text-violet-600" />
            </div>
            <AppBadge :variant="campaignStatusVariant(c.status)">
              <AppIcon :name="campaignStatusIcon(c.status)" class="w-3 h-3 mr-1" />
              {{ formatStatus(c.status) }}
            </AppBadge>
          </div>

          <p class="font-semibold text-slate-800 leading-snug mb-1">{{ c.name }}</p>

          <div v-if="c.platforms?.length" class="flex flex-wrap gap-1.5 mb-2">
            <SocialPlatformLabel
              v-for="platform in c.platforms.slice(0, 4)"
              :key="platform"
              :type="platform"
              variant="pill"
              size="sm"
            />
            <span v-if="c.platforms.length > 4" class="cl-platform-more">+{{ c.platforms.length - 4 }}</span>
          </div>

          <div class="flex items-center gap-3 text-xs text-slate-500">
            <span class="flex items-center gap-1">
              <AppIcon name="feeds" class="w-3.5 h-3.5 text-slate-400" />
              {{ c.content_packages_count || 0 }} package{{ (c.content_packages_count || 0) === 1 ? '' : 's' }}
            </span>
            <span v-if="c.tone" class="flex items-center gap-1">
              <AppIcon name="edit" class="w-3.5 h-3.5 text-slate-400" />
              {{ c.tone }}
            </span>
          </div>
        </div>

        <div class="cl-campaign-card__arrow">
          <AppIcon name="chevron-right" class="w-4 h-4 text-slate-400" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useCampaignsStore } from '../stores/campaigns';
import { AppAlert, AppBadge, AppButton, AppEmptyState, AppIcon, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';

const store = useCampaignsStore();
onMounted(() => store.fetchAll());

function campaignStatusVariant(status) {
  if (status === 'active') return 'success';
  if (status === 'completed') return 'info';
  if (status === 'paused') return 'warning';
  if (status === 'draft') return 'default';
  return 'purple';
}

function campaignStatusIcon(status) {
  if (status === 'active') return 'check';
  if (status === 'completed') return 'rocket';
  if (status === 'paused') return 'activity';
  return 'edit';
}

function campaignAccentClass(status) {
  if (status === 'active') return 'cl-campaign-card__accent--green';
  if (status === 'completed') return 'cl-campaign-card__accent--blue';
  if (status === 'paused') return 'cl-campaign-card__accent--amber';
  return 'cl-campaign-card__accent--violet';
}

function formatStatus(status) {
  const labels = {
    draft: 'Draft',
    active: 'Active',
    completed: 'Completed',
    paused: 'Paused',
    generating: 'Generating',
  };
  return labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Draft');
}
</script>

<style scoped>
.cl-campaign-card {
  position: relative;
  display: flex;
  align-items: stretch;
  gap: 0;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.cl-campaign-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);
  transform: translateY(-1px);
}

.cl-campaign-card__accent {
  width: 4px;
  flex-shrink: 0;
}

.cl-campaign-card__accent--green  { background: linear-gradient(180deg, #10b981, #059669); }
.cl-campaign-card__accent--blue   { background: linear-gradient(180deg, #3b82f6, #2563eb); }
.cl-campaign-card__accent--amber  { background: linear-gradient(180deg, #f59e0b, #d97706); }
.cl-campaign-card__accent--violet { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }

.cl-campaign-card__body {
  flex: 1;
  min-width: 0;
  padding: 0.85rem 0.85rem 0.85rem 0.75rem;
}

.cl-campaign-card__arrow {
  display: flex;
  align-items: center;
  padding-right: 0.6rem;
  flex-shrink: 0;
}

.cl-campaign-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  background: #f5f3ff;
  flex-shrink: 0;
}

.cl-platform-more {
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
</style>
