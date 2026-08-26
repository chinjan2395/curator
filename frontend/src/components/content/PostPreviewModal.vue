<template>
  <AppModal :open="open" title="Post preview" size="xl" @close="$emit('close')">
    <div class="space-y-4">
      <p class="text-xs text-slate-500 -mt-2">
        A visual approximation of how this draft will render on each platform. Engagement counts are illustrative.
      </p>

      <AppTabs v-model="activePlatform" :tabs="tabs" aria-label="Preview platform" />

      <div class="pp-stage">
        <PostPreviewCard
          :platform="activePlatform"
          :caption="contentPackage?.caption || ''"
          :hashtags="contentPackage?.hashtags || []"
          :media-url="contentPackage?.media_urls?.[0] || null"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full gap-3">
        <p class="text-2xs text-slate-400">Preview only — nothing is published from this screen.</p>
        <AppButton variant="secondary" @click="$emit('close')">Close</AppButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { normalizePlatformType } from '../../constants/socialPlatforms';
import { AppButton, AppModal, AppTabs } from '../ui';
import PostPreviewCard from './PostPreviewCard.vue';

const props = defineProps({
  open: { type: Boolean, required: true },
  contentPackage: { type: Object, default: null },
});

defineEmits(['close']);

const tabs = [
  { key: 'facebook', label: 'Facebook' },
  { key: 'instagram', label: 'Instagram' },
  { key: 'twitter', label: 'Twitter / X' },
];

const activePlatform = ref('facebook');

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    const platform = normalizePlatformType(props.contentPackage?.platform);
    activePlatform.value = tabs.some((tab) => tab.key === platform) ? platform : 'facebook';
  },
);
</script>

<style scoped>
.pp-stage {
  display: flex;
  justify-content: center;
  padding: 1.25rem;
  background: #f1f5f9;
  border-radius: 1rem;
  border: 1px solid #e2e8f0;
}
</style>
