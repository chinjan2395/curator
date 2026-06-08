<template>
  <div class="space-y-4">
    <AppPageHeader title="Content library" subtitle="Assets, brand kits, templates, and blocks." icon="library" />
    <div class="flex gap-2 border-b border-slate-200">
      <button v-for="t in tabs" :key="t.id" class="px-3 py-2 text-sm" :class="tab === t.id ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-500'" @click="tab = t.id">
        {{ t.label }}
      </button>
    </div>

    <AssetPanel v-if="tab === 'assets'" ref="assetPanelRef" :active="tab === 'assets'" />

    <AppCard v-else-if="tab === 'brand'" padding="none" class="p-4 md:p-5">
      <BrandKitPanel ref="brandKitPanelRef" :active="tab === 'brand'" />
    </AppCard>

    <AppCard v-else-if="tab === 'templates'" padding="none" class="p-4 md:p-5">
      <TemplatePanel ref="templatePanelRef" :active="tab === 'templates'" />
    </AppCard>

    <AppCard v-else-if="tab === 'blocks'" padding="none" class="p-4 md:p-5">
      <BlockPanel ref="blockPanelRef" :active="tab === 'blocks'" />
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { AppCard } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import AssetPanel from '../components/content/AssetPanel.vue';
import BrandKitPanel from '../components/content/BrandKitPanel.vue';
import TemplatePanel from '../components/content/TemplatePanel.vue';
import BlockPanel from '../components/content/BlockPanel.vue';

const tabs = [
  { id: 'assets', label: 'Assets' },
  { id: 'brand', label: 'Brand kit' },
  { id: 'templates', label: 'Templates' },
  { id: 'blocks', label: 'Blocks' },
];
const tab = ref('assets');
const assetPanelRef = ref(null);
const brandKitPanelRef = ref(null);
const templatePanelRef = ref(null);
const blockPanelRef = ref(null);

watch(tab, (t) => {
  if (t === 'assets') assetPanelRef.value?.load();
  if (t === 'brand') brandKitPanelRef.value?.load();
  if (t === 'templates') templatePanelRef.value?.load();
  if (t === 'blocks') blockPanelRef.value?.load();
});

onMounted(() => assetPanelRef.value?.load());
</script>
