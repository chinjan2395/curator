<template>
  <div class="space-y-4">
    <AppPageHeader title="Brand kit" subtitle="Logo, colors, fonts, and watermark settings used across curate and publish." icon="sparkles" />
    <AppCard padding="none" class="p-4 md:p-5">
      <BrandKitPanel ref="brandKitPanelRef" :active="true" />
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { AppCard } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import BrandKitPanel from '../components/content/BrandKitPanel.vue';

const brandKitPanelRef = ref(null);

onMounted(() => brandKitPanelRef.value?.load());
</script>
