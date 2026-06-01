<template>
  <AppCard v-if="visible" variant="panel" class="p-5 mb-5">
    <div class="flex items-start justify-between gap-3 mb-4">
      <div>
        <h2 class="text-sm font-semibold text-slate-800">Getting started</h2>
        <p class="text-xs text-slate-500 mt-0.5">Complete these steps to use campaigns, analytics, scheduling, and more.</p>
      </div>
      <button
        v-if="allComplete"
        type="button"
        class="text-xs text-slate-400 hover:text-slate-600"
        @click="dismiss"
      >
        Dismiss
      </button>
    </div>

    <AppLoader v-if="loading" label="Loading setup status…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <ul v-else class="space-y-2">
      <li
        v-for="step in steps"
        :key="step.id"
        class="flex items-center gap-3 text-sm"
      >
        <span
          class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
          :class="step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
        >
          <AppIcon v-if="step.done" name="check" class="w-3.5 h-3.5" />
          <span v-else>{{ step.num }}</span>
        </span>
        <router-link
          v-if="!step.done"
          :to="step.to"
          class="text-blue-600 hover:underline font-medium"
        >
          {{ step.label }}
        </router-link>
        <span v-else class="text-slate-600">{{ step.label }}</span>
      </li>
    </ul>
  </AppCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppIcon, AppLoader } from './ui';

const DISMISS_KEY = 'curator_getting_started_dismissed';

const status = ref(null);
const loading = ref(true);
const error = ref(null);
const dismissed = ref(localStorage.getItem(DISMISS_KEY) === '1');

const steps = computed(() => {
  const s = status.value || {};
  return [
    { id: 'onboard', num: 1, label: 'Complete your profile', to: '/onboarding', done: s.is_onboarded },
    { id: 'credentials', num: 2, label: 'Connect a social account', to: '/credentials', done: s.has_social_credentials },
    { id: 'workspace', num: 3, label: 'Create a workspace and feed', to: '/workspaces', done: s.has_feeds },
    { id: 'sync', num: 4, label: 'Sync posts from your feeds', to: '/workspaces', done: s.has_synced_posts },
    { id: 'campaign', num: 5, label: 'Create a campaign and generate content', to: '/campaigns/new', done: s.has_campaigns },
    { id: 'approve', num: 6, label: 'Approve a content package', to: '/campaigns', done: s.has_approved_packages },
    { id: 'schedule', num: 7, label: 'Schedule a native post', to: '/calendar', done: s.has_scheduled_posts },
  ];
});

const allComplete = computed(() => steps.value.length > 0 && steps.value.every((st) => st.done));

const visible = computed(() => !dismissed.value && (!allComplete.value || !dismissed.value));

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/setup/status', { skipErrorToast: true });
    status.value = data.data || data;
    if (allComplete.value && localStorage.getItem(DISMISS_KEY) === '1') {
      dismissed.value = true;
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load setup status';
  } finally {
    loading.value = false;
  }
});

function dismiss() {
  dismissed.value = true;
  localStorage.setItem(DISMISS_KEY, '1');
}
</script>
