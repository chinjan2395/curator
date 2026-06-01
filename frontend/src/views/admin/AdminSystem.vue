<template>
  <div class="space-y-4">
    <AppPageHeader title="System overview" subtitle="Platform health and queue status." icon="settings" />
    <AppLoader v-if="loading" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>
    <template v-else>
      <div class="grid gap-3 md:grid-cols-3" v-if="overview">
        <AppCard class="p-4" v-for="(val, key) in overview" :key="key">
          <div class="text-2xl font-bold">{{ val }}</div>
          <div class="text-xs text-slate-500">{{ key }}</div>
        </AppCard>
      </div>
      <AppCard class="p-4">
        <AppTitle size="sm">Integration health</AppTitle>
        <pre class="text-xs mt-2 overflow-auto">{{ health }}</pre>
      </AppCard>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppLoader, AppTitle } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';

const overview = ref(null);
const health = ref(null);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    const [o, h] = await Promise.all([
      axios.get('/api/admin/system/overview', { skipErrorToast: true }),
      axios.get('/api/admin/integrations/health', { skipErrorToast: true }),
    ]);
    overview.value = o.data.data || o.data;
    health.value = h.data.data || h.data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load system overview';
  } finally {
    loading.value = false;
  }
});
</script>
