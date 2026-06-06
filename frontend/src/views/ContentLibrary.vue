<template>
  <div class="space-y-4">
    <AppPageHeader title="Content library" subtitle="Assets, brand kits, templates, and blocks." icon="library" />
    <div class="flex gap-2 border-b border-slate-200">
      <button v-for="t in tabs" :key="t.id" class="px-3 py-2 text-sm" :class="tab === t.id ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-500'" @click="tab = t.id">
        {{ t.label }}
      </button>
    </div>

    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <AppCard v-else-if="tab === 'assets'" class="p-4">
      <div class="flex gap-2 mb-3">
        <AppInput v-model="assetQuery" placeholder="Search assets…" input-class="flex-1" @keyup.enter="loadAssets" />
        <input type="file" @change="onUpload" />
      </div>
      <AppEmptyState v-if="!assets.length" title="No assets" description="Upload images or videos to attach to content packages." icon="library" />
      <div v-else class="grid gap-3 md:grid-cols-3">
        <AppCard v-for="asset in assets" :key="asset.id" class="p-3 text-sm space-y-1">
          <div class="font-medium truncate">{{ asset.file_name }}</div>
          <div class="text-xs text-slate-500">
            {{ asset.type }} · {{ formatSize(asset.file_size) }}
            <span v-if="asset.storage_disk" class="text-slate-400"> · {{ asset.storage_disk }}</span>
          </div>
          <div v-if="(asset.ai_tags || []).length" class="flex flex-wrap gap-1">
            <span
              v-for="tag in asset.ai_tags"
              :key="tag"
              class="text-2xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-600"
            >{{ tag }}</span>
          </div>
          <a v-if="asset.url" :href="asset.url" target="_blank" rel="noopener" class="text-2xs text-blue-600 truncate block">{{ asset.url }}</a>
        </AppCard>
      </div>
    </AppCard>

    <AppCard v-else-if="tab === 'brand'" class="p-4">
      <BrandKitPanel ref="brandKitPanelRef" :active="tab === 'brand'" />
    </AppCard>

    <AppCard v-else-if="tab === 'templates'" class="p-4 space-y-3">
      <AppButton size="sm" @click="createTemplate">New template</AppButton>
      <AppEmptyState v-if="!templates.length" title="No templates" icon="library" />
      <div v-for="tpl in templates" :key="tpl.id" class="border rounded p-3 text-sm flex flex-wrap items-center gap-2">
        <span class="font-medium">{{ tpl.name }}</span>
        <SocialPlatformLabel v-if="tpl.platform" :type="tpl.platform" size="sm" />
      </div>
    </AppCard>

    <AppCard v-else class="p-4 space-y-3">
      <AppButton size="sm" @click="createBlock">New block</AppButton>
      <AppEmptyState v-if="!blocks.length" title="No content blocks" icon="library" />
      <div v-for="block in blocks" :key="block.id" class="border rounded p-3 text-sm">
        <div class="font-medium">{{ block.name }}</div>
        <p class="text-slate-600 mt-1">{{ block.body }}</p>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppInput, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import BrandKitPanel from '../components/content/BrandKitPanel.vue';

const toast = useToastStore();
const tabs = [
  { id: 'assets', label: 'Assets' },
  { id: 'brand', label: 'Brand kit' },
  { id: 'templates', label: 'Templates' },
  { id: 'blocks', label: 'Blocks' },
];
const tab = ref('assets');
const assets = ref([]);
const brandKitPanelRef = ref(null);
const templates = ref([]);
const blocks = ref([]);
const assetQuery = ref('');
const loading = ref(false);
const error = ref(null);

async function loadAssets() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/content/assets', {
      params: { q: assetQuery.value || undefined },
      skipErrorToast: true,
    });
    assets.value = data.data?.data || data.data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load assets';
  } finally {
    loading.value = false;
  }
}

async function loadTemplates() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/content/templates', { skipErrorToast: true });
    templates.value = data.data || data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load templates';
  } finally {
    loading.value = false;
  }
}

async function loadBlocks() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/content/blocks', { skipErrorToast: true });
    blocks.value = data.data || data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load blocks';
  } finally {
    loading.value = false;
  }
}

watch(tab, (t) => {
  if (t === 'assets') loadAssets();
  if (t === 'brand') brandKitPanelRef.value?.load();
  if (t === 'templates') loadTemplates();
  if (t === 'blocks') loadBlocks();
});

function formatSize(bytes) {
  if (!bytes) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function onUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const form = new FormData();
  form.append('file', file);
  form.append('type', file.type.startsWith('video') ? 'video' : 'image');
  try {
    await axios.post('/api/content/assets', form);
    toast.success('Asset uploaded');
    await loadAssets();
  } catch {
    // interceptor
  }
}

async function createTemplate() {
  const name = prompt('Template name');
  if (!name) return;
  try {
    await axios.post('/api/content/templates', { name, template_data: { caption: '' } });
    toast.success('Template created');
    await loadTemplates();
  } catch {
    // interceptor
  }
}

async function createBlock() {
  const name = prompt('Block name');
  if (!name) return;
  try {
    await axios.post('/api/content/blocks', { type: 'cta', name, body: 'Learn more' });
    toast.success('Block created');
    await loadBlocks();
  } catch {
    // interceptor
  }
}

onMounted(loadAssets);
</script>
