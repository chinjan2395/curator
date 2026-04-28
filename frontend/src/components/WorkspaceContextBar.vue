<template>
  <div v-if="workspace" class="flex items-center gap-3 px-5 py-2.5 bg-one-surface border-b border-one-divider shrink-0">
    <svg class="w-4 h-4 shrink-0 text-one-accent" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path d="M4 5.75A1.75 1.75 0 0 1 5.75 4h8.5A1.75 1.75 0 0 1 16 5.75v8.5A1.75 1.75 0 0 1 14.25 16h-8.5A1.75 1.75 0 0 1 4 14.25v-8.5ZM7 8.75a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Zm0 3a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5H7Z" />
    </svg>
    <span class="text-sm-pro font-bold text-one-text truncate max-w-[180px]">{{ workspace.name }}</span>
    <span class="text-one-divider text-sm select-none mx-1">|</span>
    <nav class="flex items-center gap-0.5">
      <router-link :to="`/workspaces/${workspaceId}/feeds`" class="ctx-tab" :class="{ 'ctx-tab-active': isFeeds }">Feeds</router-link>
      <router-link :to="`/workspaces/${workspaceId}/curate`" class="ctx-tab" :class="{ 'ctx-tab-active': isCurate }">Curate</router-link>
      <router-link :to="`/workspaces/${workspaceId}/publish`" class="ctx-tab" :class="{ 'ctx-tab-active': isPublish }">Publish</router-link>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';

const route = useRoute();
const workspaces = useWorkspacesStore();

const workspaceId = computed(() => route.params.workspaceId);
const workspace = computed(() => workspaces.find(workspaceId.value));

const isFeeds = computed(() => route.name === 'feeds' || route.name === 'feed-new' || route.name === 'feed-edit');
const isCurate = computed(() => route.name === 'curate' || route.name === 'feed-curate');
const isPublish = computed(() => route.name === 'workspace-publish');
</script>

<style scoped>
.ctx-tab {
  @apply px-3 py-1 rounded-xs-card text-sm-pro font-medium text-one-sub hover:text-one-text hover:bg-one-bg transition-colors;
}
.ctx-tab-active {
  @apply bg-one-tint text-one-primary font-semibold;
}
</style>
