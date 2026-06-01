<template>
  <div class="space-y-4">
    <AppPageHeader title="Notifications" subtitle="In-app alerts and preferences." icon="bell">
      <template #actions>
        <AppButton variant="secondary" @click="markAll">Mark all read</AppButton>
        <router-link to="/notifications/preferences" class="text-sm text-blue-600 hover:underline ml-2">Preferences</router-link>
      </template>
    </AppPageHeader>

    <AppLoader v-if="store.loading" />
    <AppAlert v-else-if="store.error" variant="danger">{{ store.error }}</AppAlert>

    <AppCard v-else class="p-4 divide-y">
      <AppEmptyState
        v-if="!store.items.length"
        title="No notifications"
        description="Alerts appear when campaigns generate content or scheduled posts publish or fail."
        icon="bell"
      />
      <div v-else v-for="n in store.items" :key="n.id" class="py-2 text-sm" :class="{ 'opacity-60': n.read_at }">
        <div class="font-medium">{{ n.title }}</div>
        <p class="text-slate-600">{{ n.body }}</p>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useNotificationsStore } from '../stores/notifications';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';

const store = useNotificationsStore();
onMounted(() => store.fetchAll());
async function markAll() {
  await store.markAllRead();
}
</script>
