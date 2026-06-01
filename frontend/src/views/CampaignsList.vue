<template>
  <div class="space-y-4">
    <AppPageHeader title="Campaigns" subtitle="AI content studio — create and generate multi-platform packages." icon="megaphone">
      <template #actions>
        <AppButton variant="primary" @click="$router.push('/campaigns/new')">New campaign</AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="ai" />

    <AppAlert v-if="store.error && !store.loading" variant="danger">{{ store.error }}</AppAlert>

    <AppLoader v-if="store.loading" />

    <AppEmptyState
      v-else-if="!store.list.length"
      title="No campaigns yet"
      description="Create a campaign, then generate platform-specific content packages."
      icon="megaphone"
    >
      <AppButton variant="primary" @click="$router.push('/campaigns/new')">New campaign</AppButton>
    </AppEmptyState>

    <div v-else class="grid gap-3 md:grid-cols-2">
      <AppCard v-for="c in store.list" :key="c.id" class="p-4 cursor-pointer hover:border-blue-300" @click="$router.push(`/campaigns/${c.id}`)">
        <div class="font-semibold text-slate-800">{{ c.name }}</div>
        <div class="text-xs text-slate-500 mt-1">{{ c.status }} · {{ c.content_packages_count || 0 }} packages</div>
      </AppCard>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useCampaignsStore } from '../stores/campaigns';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';

const store = useCampaignsStore();
onMounted(() => store.fetchAll());
</script>
