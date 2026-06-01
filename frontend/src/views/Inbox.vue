<template>
  <div class="space-y-4">
    <AppPageHeader title="Inbox" subtitle="Unified comments and messages (where APIs allow)." icon="inbox">
      <template #actions>
        <AppButton size="sm" :disabled="syncing" @click="syncNow">{{ syncing ? 'Syncing…' : 'Sync now' }}</AppButton>
      </template>
    </AppPageHeader>

    <AppAlert variant="info">
      Inbox sync is in preview mode. With a healthy X / Twitter credential, Sync now ingests sample mentions until live API ingest is enabled.
    </AppAlert>

    <AppLoader v-if="loading" label="Loading inbox…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <AppCard v-else class="p-4 space-y-2">
      <AppEmptyState
        v-if="!messages.length"
        title="No messages yet"
        description="Connect X / Twitter in Integrations with a healthy token, then click Sync now."
        icon="inbox"
      >
        <div class="flex gap-3 justify-center">
          <router-link to="/credentials" class="text-sm text-blue-600 hover:underline">Integrations</router-link>
          <AppButton size="sm" :disabled="syncing" @click="syncNow">Sync now</AppButton>
        </div>
      </AppEmptyState>
      <div v-else>
        <div v-for="msg in messages" :key="msg.id" class="border-b py-2 text-sm">
          <div class="font-medium">{{ msg.author_name || 'Unknown' }} · {{ msg.platform }}</div>
          <p class="text-slate-600">{{ msg.body }}</p>
        </div>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';

const AUTO_SYNC_KEY = 'curator_inbox_auto_synced';

const messages = ref([]);
const loading = ref(true);
const error = ref(null);
const syncing = ref(false);
const toast = useToastStore();

async function loadMessages() {
  const { data } = await axios.get('/api/inbox', { skipErrorToast: true });
  messages.value = data.data || data || [];
}

async function syncNow() {
  syncing.value = true;
  try {
    const { data } = await axios.post('/api/inbox/sync');
    const payload = data.data || data;
    const count = payload.ingested ?? 0;
    toast.success(count ? `Synced ${count} new message(s)` : 'Sync complete — no new messages');
    await loadMessages();
  } finally {
    syncing.value = false;
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    await loadMessages();
    if (!messages.value.length && !sessionStorage.getItem(AUTO_SYNC_KEY)) {
      sessionStorage.setItem(AUTO_SYNC_KEY, '1');
      try {
        await syncNow();
      } catch {
        // user may lack twitter credential
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load inbox';
  } finally {
    loading.value = false;
  }
});
</script>
