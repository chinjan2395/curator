<template>
  <div class="surface-card p-6 text-sm-pro text-slate-600">
    Opening workspace curation…
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';

const route = useRoute();
const router = useRouter();
const feeds = useFeedsStore();

onMounted(async () => {
  const workspaceId = route.params.workspaceId;
  if (!workspaceId) {
    await router.replace('/workspaces');
    return;
  }

  if (!feeds.list.length) {
    await feeds.fetchAll(workspaceId);
  }

  const firstFeed = feeds.list[0];
  if (firstFeed?.id) {
    await router.replace(`/workspaces/${workspaceId}/feeds/${firstFeed.id}/curate`);
    return;
  }

  await router.replace(`/workspaces/${workspaceId}/feeds`);
});
</script>
