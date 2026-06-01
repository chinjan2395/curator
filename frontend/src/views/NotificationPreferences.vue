<template>
  <div class="space-y-4 max-w-lg">
    <AppPageHeader title="Notification preferences" subtitle="Choose which events send email." icon="bell" />

    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <AppCard v-else class="p-4 space-y-3">
      <div v-for="event in events" :key="event" class="flex items-center justify-between text-sm border-b py-2">
        <span>{{ event }}</span>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" v-model="prefsMap[event].email" />
          Email
        </label>
      </div>
      <AppButton @click="save" :disabled="saving">{{ saving ? 'Saving…' : 'Save preferences' }}</AppButton>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { AppAlert, AppButton, AppCard, AppLoader } from '../components/ui';
import { AppPageHeader } from '../components/layout';

const toast = useToastStore();
const events = ['post_published', 'post_failed', 'campaign_generated', 'sync_failed'];
const prefsMap = reactive(Object.fromEntries(events.map((e) => [e, { event_type: e, in_app: true, email: false, push: false }])));
const saving = ref(false);
const loading = ref(true);
const error = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await axios.get('/api/notifications/preferences', { skipErrorToast: true });
    const list = data.data || data || [];
    for (const p of list) {
      if (prefsMap[p.event_type]) {
        prefsMap[p.event_type].email = !!p.email;
        prefsMap[p.event_type].in_app = !!p.in_app;
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load preferences';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    await axios.put('/api/notifications/preferences', {
      preferences: events.map((e) => prefsMap[e]),
    });
    toast.success('Preferences saved');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
